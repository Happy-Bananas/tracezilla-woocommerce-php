<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tracezilla\WooCommerce\Configuration;

final class ConfigurationTest extends TestCase
{
    private const VARIABLES = [
        'WOOCOMMERCE_URL',
        'WOOCOMMERCE_CONSUMER_KEY',
        'WOOCOMMERCE_CONSUMER_SECRET',
        'HTTP_TIMEOUT',
        'HTTP_CONNECT_TIMEOUT',
    ];

    protected function tearDown(): void
    {
        foreach (self::VARIABLES as $name) {
            putenv($name);
        }
    }

    public function test_it_loads_and_normalizes_environment_configuration(): void
    {
        putenv('WOOCOMMERCE_URL=https://shop.example/');
        putenv('WOOCOMMERCE_CONSUMER_KEY=ck_test');
        putenv('WOOCOMMERCE_CONSUMER_SECRET=cs_test');
        putenv('HTTP_TIMEOUT=12');
        putenv('HTTP_CONNECT_TIMEOUT=4');

        $configuration = Configuration::fromEnvironment();

        self::assertSame('https://shop.example', $configuration->wooCommerceUrl);
        self::assertSame('ck_test', $configuration->consumerKey);
        self::assertSame('cs_test', $configuration->consumerSecret);
        self::assertSame(12.0, $configuration->timeout);
        self::assertSame(4.0, $configuration->connectTimeout);
    }

    public function test_it_rejects_missing_credentials(): void
    {
        putenv('WOOCOMMERCE_URL=https://shop.example');
        putenv('WOOCOMMERCE_CONSUMER_KEY');
        putenv('WOOCOMMERCE_CONSUMER_SECRET=cs_test');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('WOOCOMMERCE_CONSUMER_KEY');

        Configuration::fromEnvironment();
    }
}
