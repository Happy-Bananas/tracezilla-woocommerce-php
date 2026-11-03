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
            $pageQuery = $query;
            $pageQuery['per_page'] = self::PER_PAGE;
            $pageQuery['page'] = $page;
            $batch = $this->client->get($path, $pageQuery);
            if (! array_is_list($batch)) {
                throw new RuntimeException("WooCommerce response for {$path} is not a list.");
            }
            $records = array_merge($records, $batch);
            if (count($batch) < self::PER_PAGE) {
                return $records;
            }
        }

        throw new RuntimeException('WooCommerce returned more than '.self::MAX_PAGES." pages for {$path}.");
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
            if ($this->text($product, 'type') === 'variable') {
                $items = array_merge($items, $this->variationsOf($product));
            } else {
                $items[] = $this->catalogItem($product, $this->text($product, 'name'), (int) $product['id'], null);
            }
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $product
     * @return list<array<string, mixed>>
     */
    private function variationsOf(array $product): array
    {
        $productId = (int) $product['id'];
        $items = [];
        foreach ($this->getAll("products/{$productId}/variations") as $variation) {
            $name = $this->variationName($product, $variation);
            $items[] = $this->catalogItem($variation, $name, $productId, (int) $variation['id']);
        }

        return $items;
    }

    /**
     * For example "House Blend Coffee Beans - 250 g".
     *
     * @param array<string, mixed> $product
     * @param array<string, mixed> $variation
     */
    private function variationName(array $product, array $variation): string
    {
        $productName = $this->text($product, 'name');
        $variationName = $this->text($variation, 'name');
        if ($variationName === '') {
            return $productName;
        }

        return "{$productName} - {$variationName}";
    }

    /**
     * @param array<string, mixed> $record A product or a variation
     * @return array{sku: string, name: string, product_id: int, variation_id: ?int, status: string, manage_stock: bool, stock_quantity: ?int}
     */
    private function catalogItem(array $record, string $name, int $productId, ?int $variationId): array
    {
        return [
            'sku' => trim($this->text($record, 'sku')),
            'name' => $name,
            'product_id' => $productId,
            'variation_id' => $variationId,
            'status' => $this->text($record, 'status'),
            'manage_stock' => $this->isTrue($record, 'manage_stock'),
            'stock_quantity' => $this->wholeNumberOrNull($record, 'stock_quantity'),
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

    /** @param array<string, mixed> $record */
    private function wholeNumberOrNull(array $record, string $key): ?int
    {
        if (! isset($record[$key])) {
            return null;
        }
        if (! is_int($record[$key])) {
            return null;
        }

        return $record[$key];
    }
}
