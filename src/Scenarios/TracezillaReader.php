<?php

declare(strict_types=1);

namespace Tracezilla\WooCommerce\Scenarios;

use Tracezilla\WooCommerce\Tracezilla\Client;

/**
 * Read-only access to the tracezilla API for a scenario's DataReader. Paths are
 * relative to /api/v1/{team-slug}/, for example "/skus".
 * https://tech.tracezilla.com/docs/rest-api
 */
final readonly class TracezillaReader
{
    public function __construct(private Client $client)
    {
    }

    /**
     * One request.
     *
     * @param array<string, scalar> $query
     * @return array<string, mixed>
     */
    public function get(string $path, array $query = []): array
    {
        return $this->client->get($path, $query);
    }

    /**
     * Every page of a list.
     *
     * @param array<string, scalar> $query
     * @return list<array<string, mixed>>
     */
    public function getAll(string $path, array $query = []): array
    {
        return $this->client->getAll($path, $query);
    }

    /**
     * All SKUs, active and inactive, sorted by SKU code.
     *
     * @return list<array{id: string, sku_code: string, name: string, active: bool, category: string, allow_sales: bool}>
     */
    public function skus(): array
    {
        return array_map(static fn (array $sku): array => [
            'id' => (string) ($sku['id'] ?? ''),
            'sku_code' => trim((string) ($sku['sku_code'] ?? '')),
            'name' => (string) ($sku['global_name'] ?? ''),
            'active' => ($sku['active'] ?? false) === true,
            // For example finished_good or raw_material.
            'category' => (string) ($sku['category'] ?? ''),
            'allow_sales' => ($sku['allow_sales'] ?? false) === true,
        ], $this->getAll('/skus', ['sortBy' => 'sku_code', 'sortDirection' => 'asc', 'perPage' => 250]));
    }
}
