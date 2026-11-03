<?php

declare(strict_types=1);

namespace Tracezilla\WooCommerce\Scenarios;

use RuntimeException;
use Tracezilla\WooCommerce\WooCommerce\Client;

/**
 * Read-only access to the WooCommerce REST API for a scenario's DataReader.
 * Paths are relative to /wp-json/wc/v3/, for example "products".
 * https://woocommerce.github.io/woocommerce-rest-api-docs/
 */
final readonly class WooCommerceReader
{
    private const PER_PAGE = 100;
    private const MAX_PAGES = 1000;

    public function __construct(private Client $client)
    {
    }

    /**
     * One request.
     *
     * @param array<string, scalar> $query
     * @return array<mixed>
     */
    public function get(string $path, array $query = []): array
    {
        return $this->client->get($path, $query);
    }

    /**
     * Every page of a list, 100 records at a time.
     *
     * @param array<string, scalar> $query
     * @return list<array<string, mixed>>
     */
    public function getAll(string $path, array $query = []): array
    {
        $records = [];
        for ($page = 1; $page <= self::MAX_PAGES; $page++) {
            $batch = $this->client->get($path, ['per_page' => self::PER_PAGE, 'page' => $page] + $query);
            if (! array_is_list($batch)) {
                throw new RuntimeException("WooCommerce response for {$path} is not a list.");
            }
            $records = array_merge($records, $batch);
            if (count($batch) < self::PER_PAGE) {
                return $records;
            }
        }

        throw new RuntimeException("WooCommerce returned more than ".self::MAX_PAGES." pages for {$path}.");
    }

    /**
     * Everything a customer can buy: simple products, and the variations of
     * variable products instead of the variable product itself.
     *
     * @return list<array{sku: string, name: string, product_id: int, variation_id: ?int, status: string, manage_stock: bool, stock_quantity: ?int}>
     */
    public function catalog(): array
    {
        $items = [];
        foreach ($this->getAll('products') as $product) {
            if (($product['type'] ?? null) !== 'variable') {
                $items[] = self::item($product, (string) ($product['name'] ?? ''), (int) $product['id'], null);
                continue;
            }
            foreach ($this->getAll("products/{$product['id']}/variations") as $variation) {
                $name = trim(($product['name'] ?? '').' - '.($variation['name'] ?? ''), ' -');
                $items[] = self::item($variation, $name, (int) $product['id'], (int) $variation['id']);
            }
        }

        return $items;
    }

    /** @return array{sku: string, name: string, product_id: int, variation_id: ?int, status: string, manage_stock: bool, stock_quantity: ?int} */
    private static function item(array $record, string $name, int $productId, ?int $variationId): array
    {
        return [
            'sku' => trim((string) ($record['sku'] ?? '')),
            'name' => $name,
            'product_id' => $productId,
            'variation_id' => $variationId,
            'status' => (string) ($record['status'] ?? ''),
            'manage_stock' => ($record['manage_stock'] ?? false) === true,
            'stock_quantity' => is_int($record['stock_quantity'] ?? null) ? $record['stock_quantity'] : null,
        ];
    }
}
