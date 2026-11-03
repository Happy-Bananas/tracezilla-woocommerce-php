<?php

declare(strict_types=1);

namespace Consultant\Scenarios\WooCommerce\ScenarioTemplate;

use PHPUnit\Framework\TestCase;

/**
 * Examples of the rules that run without contacting either API.
 */
final class BusinessRulesTest extends TestCase
{
    public function test_it_plans_an_sku_for_a_product_tracezilla_does_not_know(): void
    {
        $result = (new BusinessRules())->apply([
            'woocommerce' => [$this->shopItem('TZ-FRUIT-001', 'Bananas'), $this->shopItem('TZ-FRUIT-004', 'Kiwi', weight: '0.5')],
            'weight_unit' => 'kg',
            'tracezilla' => [$this->sku('TZ-FRUIT-001')],
        ]);

        self::assertSame([[
            'action' => 'create_tracezilla_sku',
            'key' => 'TZ-FRUIT-004',
            'data' => [
                'sku_code' => 'TZ-FRUIT-004',
                'global_name' => 'Kiwi',
                'category' => 'finished_good',
                'unit_of_measure' => 'pcs',
                'lot_unit' => 'pcs',
                'default_uom_conversion' => 1,
                'weight_factor_net' => 0.5,
                'weight_factor_gross' => 0.5,
            ],
        ]], $result['changes']);
        self::assertSame(1, $result['summary']['already_in_tracezilla']);
    }

    public function test_it_converts_the_weight_to_kg(): void
    {
        $result = (new BusinessRules())->apply([
            'woocommerce' => [$this->shopItem('TZ-TEA-001', 'Tea', weight: '250')],
            'weight_unit' => 'g',
            'tracezilla' => [],
        ]);

        self::assertSame(0.25, $result['changes'][0]['data']['weight_factor_net']);
    }

    public function test_it_assumes_a_weight_when_the_shop_has_none_and_says_so(): void
    {
        $result = (new BusinessRules())->apply([
            'woocommerce' => [$this->shopItem('TZ-TEA-001', 'Tea', weight: '')],
            'weight_unit' => 'kg',
            'tracezilla' => [],
        ]);

        self::assertSame(1.0, $result['changes'][0]['data']['weight_factor_net']);
        self::assertSame(['TZ-TEA-001'], $result['weight_assumed']);
    }

    public function test_an_inactive_tracezilla_sku_is_not_created_again(): void
    {
        $result = (new BusinessRules())->apply([
            'woocommerce' => [$this->shopItem('TZ-OLD-001', 'Old')],
            'weight_unit' => 'kg',
            'tracezilla' => [$this->sku('TZ-OLD-001', active: false)],
        ]);

        self::assertSame([], $result['changes']);
    }

    public function test_a_code_tracezilla_would_reject_is_skipped_with_the_reason(): void
    {
        $result = (new BusinessRules())->apply([
            'woocommerce' => [$this->shopItem('TZ-VERY-LONG-CODE-1', 'Too long')],
            'weight_unit' => 'kg',
            'tracezilla' => [],
        ]);

        self::assertSame([], $result['changes']);
        self::assertSame('the SKU is longer than 17 characters', $result['skipped'][0]['reason']);
    }

    /** @return array<string, mixed> */
    private function shopItem(string $sku, string $name, string $weight = '1'): array
    {
        return ['sku' => $sku, 'name' => $name, 'product_id' => 1, 'variation_id' => null, 'status' => 'publish', 'manage_stock' => true, 'stock_quantity' => 1, 'weight' => $weight];
    }

    /** @return array<string, mixed> */
    private function sku(string $code, bool $active = true): array
    {
        return ['id' => 'id-'.$code, 'sku_code' => $code, 'name' => $code, 'active' => $active, 'category' => 'finished_good', 'allow_sales' => true];
    }
}
