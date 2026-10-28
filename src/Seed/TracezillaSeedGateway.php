<?php

declare(strict_types=1);

namespace Tracezilla\WooCommerce\Seed;

use RuntimeException;
use Tracezilla\WooCommerce\Tracezilla\Client;

final readonly class TracezillaSeedGateway implements SeedGateway
{
    public function __construct(private Client $client)
    {
    }

    public function partnersByRef(): array
    {
        $partners = [];

        foreach ($this->client->getAll('/partners', ['include' => 'locations', 'perPage' => 250]) as $partner) {
            $ref = trim((string) ($partner['ref'] ?? ''));
            if ($ref === '') {
                continue;
            }
            // Never silently pick one of several matching records.
            if (isset($partners[$ref])) {
                throw new RuntimeException("Several tracezilla partners have ref [{$ref}].");
            }

            $partners[$ref] = ['id' => (string) $partner['id'], 'locations' => $this->locationsByRef($partner, $ref)];
        }

        return $partners;
    }

    public function ownCompany(string $name): array
    {
        $matches = array_values(array_filter(
            $this->client->getAll('/partners', ['keyword' => ['ct' => $name], 'include' => 'locations', 'perPage' => 250]),
            static fn (array $partner): bool => strcasecmp(trim((string) ($partner['name'] ?? '')), trim($name)) === 0,
        ));

        if (count($matches) !== 1) {
            throw new RuntimeException(count($matches) === 0
                ? "No tracezilla partner is named [{$name}]. Check TRACEZILLA_OWN_COMPANY."
                : "Several tracezilla partners are named [{$name}]. Rename one of them.");
        }

        return ['id' => (string) $matches[0]['id'], 'locations' => $this->locationsByRef($matches[0], $name)];
    }

    public function skuCodes(): array
    {
        $codes = [];
        foreach ($this->client->getAll('/skus', ['perPage' => 250]) as $sku) {
            $codes[trim((string) ($sku['sku_code'] ?? ''))] = true;
        }
        unset($codes['']);

        return $codes;
    }

    public function purchaseOrderExists(string $extRef): bool
    {
        $orders = $this->client->get('/orders/purchase', ['ext_ref' => ['eq' => $extRef]])['data'] ?? [];

        foreach ($orders as $order) {
            if (($order['ext_ref'] ?? null) === $extRef) {
                return true;
            }
        }

        return false;
    }

    public function createPartner(array $payload): string
    {
        return $this->createdId($this->client->post('/partners', $payload), 'partner');
    }

    public function createLocation(string $partnerId, array $payload): string
    {
        return $this->createdId($this->client->post("/partners/{$partnerId}/locations", $payload), 'location');
    }

    public function createSku(array $payload): void
    {
        $this->client->post('/skus', $payload);
    }

    public function createPurchaseOrder(array $payload): void
    {
        $this->client->put('/orders/purchase', $payload);
    }

    /** @return array<string, string> */
    private function locationsByRef(array $partner, string $partnerLabel): array
    {
        $locations = [];
        foreach ($partner['locations'] ?? [] as $location) {
            $ref = trim((string) ($location['ref'] ?? ''));
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

    private function createdId(array $response, string $type): string
    {
        $id = $response['data']['id'] ?? null;
        if (! is_scalar($id) || (string) $id === '') {
            throw new RuntimeException("tracezilla did not return an ID for the created {$type}.");
        }

        return (string) $id;
    }
}
