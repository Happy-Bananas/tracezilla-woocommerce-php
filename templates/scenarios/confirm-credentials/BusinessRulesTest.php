<?php

declare(strict_types=1);

namespace Consultant\Scenarios\WooCommerce\ScenarioTemplate;

use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Examples of the customer's rules that run without contacting either API.
 */
final class BusinessRulesTest extends TestCase
{
    public function test_it_confirms_both_connections(): void
    {
        $result = (new BusinessRules())->apply([
            'woocommerce_status' => ['environment' => ['site_url' => 'https://shop.example.test', 'version' => '11.2.1']],
            'tracezilla_skus' => ['data' => []],
        ]);

        self::assertSame('ok', $result['status']);
        self::assertSame('https://shop.example.test', $result['woocommerce']['store_url']);
        self::assertTrue($result['tracezilla']['credentials_valid']);
    }

    public function test_it_rejects_an_unexpected_woocommerce_response(): void
    {
        $this->expectException(RuntimeException::class);

        (new BusinessRules())->apply(['woocommerce_status' => [], 'tracezilla_skus' => ['data' => []]]);
    }
}
