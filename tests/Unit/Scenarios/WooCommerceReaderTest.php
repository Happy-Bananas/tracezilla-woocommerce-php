<?php

declare(strict_types=1);

namespace Tests\Unit\Scenarios;

use PHPUnit\Framework\TestCase;

final class WooCommerceReaderTest extends TestCase
{
    public function test_it_reads_every_page(): void
    {
        $fullPage = array_fill(0, 100, ['id' => 1]);
        $reader = FakeApis::wooCommerce([$fullPage, [['id' => 2]]]);

        self::assertCount(101, $reader->getAll('products'));
    }

    public function test_the_catalog_lists_variations_instead_of_their_variable_product(): void
    {
        $reader = FakeApis::wooCommerce([
            [
                ['id' => 1, 'type' => 'simple', 'sku' => 'TZ-FRUIT-001', 'name' => 'Bananas', 'status' => 'publish', 'manage_stock' => true, 'stock_quantity' => 100],
                ['id' => 2, 'type' => 'variable', 'sku' => 'TZ-COFFEE-001', 'name' => 'House Blend', 'status' => 'publish'],
            ],
            [
                ['id' => 3, 'sku' => 'TZ-COFFEE-001-01', 'name' => '250 g', 'status' => 'publish', 'manage_stock' => true, 'stock_quantity' => 5],
            ],
        ]);

        $catalog = $reader->catalog();

        self::assertSame(['TZ-FRUIT-001', 'TZ-COFFEE-001-01'], array_column($catalog, 'sku'));
        self::assertSame('House Blend - 250 g', $catalog[1]['name']);
        self::assertSame(3, $catalog[1]['variation_id']);
    }
}
