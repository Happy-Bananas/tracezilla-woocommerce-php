<?php

declare(strict_types=1);

namespace Consultant\Scenarios\WooCommerce\ScenarioTemplate;

use PHPUnit\Framework\TestCase;

/**
 * Examples of the rules that run without contacting either API.
 */
final class BusinessRulesTest extends TestCase
{
    public function test_a_finished_good_the_shop_does_not_sell_is_a_decision(): void
    {
        $result = (new BusinessRules())->apply([
            'woocommerce' => [$this->shopItem('TZ-FRUIT-001')],
            'tracezilla' => [$this->sku('TZ-FRUIT-001'), $this->sku('TZ-JAM-001', 'Strawberry Jam')],
        ]);

        self::assertSame(
            [['sku' => 'TZ-JAM-001', 'tracezilla_id' => 'id-TZ-JAM-001', 'name' => 'Strawberry Jam', 'category' => 'finished_good']],
            $result['decide_with_customer'],
        );
    }

    public function test_raw_materials_and_skus_not_for_sale_are_not_for_the_shop(): void
    {
        $result = (new BusinessRules())->apply([
            'woocommerce' => [],
            'tracezilla' => [
                $this->sku('TZ-RAW-001', category: 'raw_material'),
                $this->sku('TZ-SAMPLE-001', allowSales: false),
            ],
        ]);

        self::assertSame([], $result['decide_with_customer']);
        self::assertSame(['category is raw_material', 'sales are not allowed in tracezilla'], array_column($result['not_for_the_shop'], 'reason'));
    }

    public function test_inactive_skus_are_ignored(): void
    {
        $result = (new BusinessRules())->apply([
            'woocommerce' => [],
            'tracezilla' => [$this->sku('TZ-OLD-001', active: false)],
        ]);

        self::assertSame(0, $result['summary']['decide_with_customer']);
        self::assertSame(1, $result['summary']['inactive_tracezilla_skus_skipped']);
    }

    /** @return array<string, mixed> */
    private function shopItem(string $sku): array
    {
        return ['sku' => $sku, 'name' => $sku, 'product_id' => 1, 'variation_id' => null, 'status' => 'publish', 'manage_stock' => true, 'stock_quantity' => 1];
    }

    /** @return array<string, mixed> */
    private function sku(string $code, string $name = 'Product', bool $active = true, string $category = 'finished_good', bool $allowSales = true): array
    {
        return ['id' => 'id-'.$code, 'sku_code' => $code, 'name' => $name, 'active' => $active, 'category' => $category, 'allow_sales' => $allowSales];
    }
}
