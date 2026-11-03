<?php

declare(strict_types=1);

namespace Tracezilla\WooCommerce\Seed;

use RuntimeException;
use Tracezilla\WooCommerce\Tracezilla\Client;

/**
 * The seed's access to tracezilla: it looks up existing records and creates
 * missing ones through the tracezilla API.
 */
final readonly class TracezillaSeedGateway implements SeedGateway
{
    /** Unit fields that tracezilla may replace when it creates an SKU. */
    private const UNIT_FIELDS = ['unit_of_measure', 'lot_unit', 'default_uom_conversion'];

    public function __construct(private Client $client)
    {
    }

    public function partnersByRef(): array
    {
        $partners = [];
        foreach ($this->client->getAll('/partners', ['include' => 'locations', 'perPage' => 250]) as $partner) {
            $ref = $this->trimmedText($partner, 'ref');
            if ($ref === '') {
                continue;
            }
            // Never silently pick one of several matching records.
            if (isset($partners[$ref])) {
                throw new RuntimeException("Several tracezilla partners have ref [{$ref}].");
            }
            $partners[$ref] = [
                'id' => (string) $partner['id'],
                'locations' => $this->locationsByRef($partner, $ref),
            ];
        }

        return $partners;
    }

    public function ownCompany(string $name): array
    {
        $matches = $this->partnersNamed($name);
        if (count($matches) === 0) {
            throw new RuntimeException("No tracezilla partner is named [{$name}]. Check TRACEZILLA_OWN_COMPANY.");
        }
        if (count($matches) > 1) {
            throw new RuntimeException("Several tracezilla partners are named [{$name}]. Rename one of them.");
        }

        $partner = $matches[0];

        return [
            'id' => (string) $partner['id'],
            'locations' => $this->locationsByRef($partner, $name),
        ];
    }

    public function skuCodes(): array
    {
        $codes = [];
        foreach ($this->client->getAll('/skus', ['perPage' => 250]) as $sku) {
            $code = $this->trimmedText($sku, 'sku_code');
            if ($code !== '') {
                $codes[$code] = true;
            }
        }

        return $codes;
    }

    public function purchaseOrderExists(string $extRef): bool
    {
        $page = $this->client->get('/orders/purchase', ['ext_ref' => ['eq' => $extRef]]);
        if (! isset($page['data'])) {
            return false;
        }

        foreach ($page['data'] as $order) {
            if (isset($order['ext_ref']) && $order['ext_ref'] === $extRef) {
                return true;
            }
        }

        return false;
    }

    public function createPartner(array $payload): string
    {
        $response = $this->client->post('/partners', $payload);

        return $this->createdId($response, 'partner');
    }

    public function createLocation(string $partnerId, array $payload): string
    {
        $response = $this->client->post("/partners/{$partnerId}/locations", $payload);

        return $this->createdId($response, 'location');
    }

    public function createSku(array $payload): void
    {
        $response = $this->client->post('/skus', $payload);
        $created = $this->dataOf($response);
        if ($this->unitsMatch($payload, $created)) {
            return;
        }

        $this->correctUnits($payload, $this->createdId($response, 'SKU'));
    }

    public function createPurchaseOrder(array $payload): void
    {
        $this->client->put('/orders/purchase', $payload);
    }

    /**
     * On create, tracezilla locks the unit conversion and derives the lot unit
     * from the unit of measure (pcs -> pcs, kg -> g). Updating the SKU with the
     * lock removed stores the requested units.
     *
     * @param array<string, mixed> $payload
     */
    private function correctUnits(array $payload, string $skuId): void
    {
        $update = $payload;
        $update['lock_uom_conversion'] = false;

        $response = $this->client->put("/skus/{$skuId}", $update);
        $updated = $this->dataOf($response);
        if ($this->unitsMatch($payload, $updated)) {
            return;
        }

        $stored = json_encode($this->unitsOf($updated));
        $requested = json_encode($this->unitsOf($payload));
        throw new RuntimeException("tracezilla stored units {$stored} instead of {$requested} for SKU [{$payload['sku_code']}].");
    }

    /**
     * The partners whose name equals $name, ignoring case and surrounding spaces.
     *
     * @return list<array<string, mixed>>
     */
    private function partnersNamed(string $name): array
    {
        $candidates = $this->client->getAll('/partners', ['keyword' => ['ct' => $name], 'include' => 'locations', 'perPage' => 250]);

        $matches = [];
        foreach ($candidates as $partner) {
            if (strcasecmp($this->trimmedText($partner, 'name'), trim($name)) === 0) {
                $matches[] = $partner;
            }
        }

        return $matches;
    }

    /**
     * @param array<string, mixed> $partner
     * @return array<string, string>
     */
    private function locationsByRef(array $partner, string $partnerLabel): array
    {
        if (! isset($partner['locations'])) {
            return [];
        }

        $locations = [];
        foreach ($partner['locations'] as $location) {
            $ref = $this->trimmedText($location, 'ref');
            if ($ref === '') {
                continue;
            }
            if (isset($locations[$ref])) {
                throw new RuntimeException("tracezilla partner [{$partnerLabel}] has several locations with ref [{$ref}].");
            }
            $locations[$ref] = (string) $location['id'];
        }

        return $locations;
    }

    /**
     * @param array<string, mixed> $requested
     * @param array<string, mixed> $stored
     */
    private function unitsMatch(array $requested, array $stored): bool
    {
        foreach (self::UNIT_FIELDS as $field) {
            if (! array_key_exists($field, $requested)) {
                continue;
            }
            $storedValue = '';
            if (isset($stored[$field])) {
                $storedValue = (string) $stored[$field];
            }
            if ((string) $requested[$field] !== $storedValue) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $sku
     * @return array<string, mixed>
     */
    private function unitsOf(array $sku): array
    {
        $units = [];
        foreach (self::UNIT_FIELDS as $field) {
            if (array_key_exists($field, $sku)) {
                $units[$field] = $sku[$field];
            }
        }

        return $units;
    }

    /**
     * @param array<string, mixed> $response
     * @return array<string, mixed>
     */
    private function dataOf(array $response): array
    {
        if (! isset($response['data']) || ! is_array($response['data'])) {
            return [];
        }

        return $response['data'];
    }

    /** @param array<string, mixed> $response */
    private function createdId(array $response, string $type): string
    {
        $data = $this->dataOf($response);
        if (! isset($data['id']) || ! is_scalar($data['id'])) {
            throw new RuntimeException("tracezilla did not return an ID for the created {$type}.");
        }
        $id = (string) $data['id'];
        if ($id === '') {
            throw new RuntimeException("tracezilla did not return an ID for the created {$type}.");
        }

        return $id;
    }

    /**
     * The value under $key as trimmed text, or '' if it is missing.
     *
     * @param array<string, mixed> $record
     */
    private function trimmedText(array $record, string $key): string
    {
        if (! isset($record[$key])) {
            return '';
        }

        return trim((string) $record[$key]);
    }
}
