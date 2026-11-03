<?php

declare(strict_types=1);

namespace Consultant\Scenarios\WooCommerce\ScenarioTemplate;

use PHPUnit\Framework\TestCase;

/**
 * Examples of the comparison rules that run without contacting either API.
 */
final class BusinessRulesTest extends TestCase
{
    public function test_it_sorts_skus_into_matched_and_only_on_one_side(): void
    {
        $result = (new BusinessRules())->apply([
            'woocommerce' => [$this->shopItem('TZ-FRUIT-001', 'Fairtrade Bananas'), $this->shopItem('TZ-FRUIT-004', 'Kiwi')],
            'tracezilla' => [$this->sku('TZ-FRUIT-001', 'Fairtrade Bananas 1 kg'), $this->sku('TZ-RAW-001', 'Raw Cocoa Beans')],
        ]);

        self::assertSame([['sku' => 'TZ-FRUIT-001', 'woocommerce_name' => 'Fairtrade Bananas', 'tracezilla_name' => 'Fairtrade Bananas 1 kg']], $result['matched']);
        self::assertSame([['sku' => 'TZ-FRUIT-004', 'name' => 'Kiwi']], $result['only_in_woocommerce']);
        self::assertSame([['sku' => 'TZ-RAW-001', 'name' => 'Raw Cocoa Beans']], $result['only_in_tracezilla']);
    }

    public function test_it_ignores_surrounding_spaces(): void
    {
        $result = (new BusinessRules())->apply([
            'woocommerce' => [$this->shopItem(' TZ-FRUIT-001 ', 'Bananas')],
            'tracezilla' => [$this->sku('TZ-FRUIT-001', 'Bananas')],
        ]);

        self::assertSame(1, $result['summary']['matched']);
    }

    public function test_it_skips_inactive_tracezilla_skus(): void
    {
        $result = (new BusinessRules())->apply([
            'woocommerce' => [],
            'tracezilla' => [$this->sku('TZ-OLD-RAW-001', 'Old cocoa', active: false)],
        ]);

        self::assertSame([], $result['only_in_tracezilla']);
        self::assertSame(1, $result['summary']['inactive_tracezilla_skus_skipped']);
    }

    public function test_it_reports_products_without_a_sku_and_duplicates(): void
    {
        $result = (new BusinessRules())->apply([
            'woocommerce' => [$this->shopItem('', 'Gift card'), $this->shopItem('TZ-TEA-001', 'Tea'), $this->shopItem('TZ-TEA-001', 'Tea again')],
            'tracezilla' => [],
        ]);

        self::assertSame('Gift card', $result['woocommerce_without_sku'][0]['name']);
        self::assertSame(['TZ-TEA-001'], $result['duplicate_skus']['woocommerce']);
    }

    /** @return array<string, mixed> */
    private function shopItem(string $sku, string $name): array
    {
        return ['sku' => $sku, 'name' => $name, 'product_id' => 1, 'variation_id' => null, 'status' => 'publish', 'manage_stock' => true, 'stock_quantity' => 1];
    }

    /** @return array<string, mixed> */
    private function sku(string $code, string $name, bool $active = true): array
    {
        return ['id' => 'id-'.$code, 'sku_code' => $code, 'name' => $name, 'active' => $active];
    }
}
