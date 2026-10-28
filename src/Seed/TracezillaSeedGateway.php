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

            $locations = [];
            foreach ($partner['locations'] ?? [] as $location) {
                $locationRef = trim((string) ($location['ref'] ?? ''));
                if ($locationRef === '') {
                    continue;
                }
                if (isset($locations[$locationRef])) {
                    throw new RuntimeException("tracezilla partner [{$ref}] has several locations with ref [{$locationRef}].");
                }
                $locations[$locationRef] = (string) $location['id'];
            }

            $partners[$ref] = ['id' => (string) $partner['id'], 'locations' => $locations];
        }

        return $partners;
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

    private function createdId(array $response, string $type): string
    {
        $id = $response['data']['id'] ?? null;
        if (! is_scalar($id) || (string) $id === '') {
            throw new RuntimeException("tracezilla did not return an ID for the created {$type}.");
        }

        return (string) $id;
    }
}
