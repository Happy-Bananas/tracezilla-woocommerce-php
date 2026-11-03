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
        $records = $this->getAll('/skus', ['sortBy' => 'sku_code', 'sortDirection' => 'asc', 'perPage' => 250]);

        $skus = [];
        foreach ($records as $record) {
            $skus[] = $this->skuFrom($record);
        }

        return $skus;
    }

    /**
     * The fields a scenario usually needs from a tracezilla SKU.
     *
     * @param array<string, mixed> $record
     * @return array{id: string, sku_code: string, name: string, active: bool, category: string, allow_sales: bool}
     */
    private function skuFrom(array $record): array
    {
        return [
            'id' => $this->text($record, 'id'),
            'sku_code' => trim($this->text($record, 'sku_code')),
            'name' => $this->text($record, 'global_name'),
            'active' => $this->isTrue($record, 'active'),
            // For example finished_good or raw_material.
            'category' => $this->text($record, 'category'),
            'allow_sales' => $this->isTrue($record, 'allow_sales'),
        ];
    }

    /**
     * The value under $key as text, or '' if it is missing.
     *
     * @param array<string, mixed> $record
     */
    private function text(array $record, string $key): string
    {
        if (! isset($record[$key])) {
            return '';
        }

        return (string) $record[$key];
    }

    /** @param array<string, mixed> $record */
    private function isTrue(array $record, string $key): bool
    {
        if (! isset($record[$key])) {
            return false;
        }

        return $record[$key] === true;
    }
}
