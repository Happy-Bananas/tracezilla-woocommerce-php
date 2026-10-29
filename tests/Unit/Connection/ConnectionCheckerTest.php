<?php

declare(strict_types=1);

namespace Tests\Unit\Connection;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tracezilla\WooCommerce\Connection\ConnectionChecker;

final class ConnectionCheckerTest extends TestCase
{
    public function test_it_reports_both_connections(): void
    {
        $result = $this->checker(
            static fn (): array => ['environment' => ['site_url' => 'http://localhost:8080', 'version' => '11.2.1']],
            static fn (): array => ['data' => []],
        )->check();

        self::assertSame([
            'status' => 'ok',
            'woocommerce' => ['connected' => true, 'store_url' => 'http://localhost:8080', 'version' => '11.2.1'],
            'tracezilla' => ['team' => 'dev-team', 'connected' => true],
        ], $result);
    }

    public function test_a_woocommerce_failure_does_not_hide_the_tracezilla_result(): void
    {
        $result = $this->checker(
            static fn (): array => throw new RuntimeException('Missing required environment variable: WOOCOMMERCE_CONSUMER_KEY'),
            static fn (): array => ['data' => []],
        )->check();

        self::assertSame('failed', $result['status']);
        self::assertSame(['connected' => false, 'error' => 'Missing required environment variable: WOOCOMMERCE_CONSUMER_KEY'], $result['woocommerce']);
        self::assertTrue($result['tracezilla']['connected']);
    }

    public function test_a_tracezilla_failure_does_not_hide_the_woocommerce_result(): void
    {
        $result = $this->checker(
            static fn (): array => ['environment' => ['site_url' => 'http://localhost:8080', 'version' => '11.2.1']],
            static fn (): array => throw new RuntimeException('No tracezilla token is stored. Run bin/tracezilla-login first.'),
        )->check();

        self::assertSame('failed', $result['status']);
        self::assertTrue($result['woocommerce']['connected']);
        self::assertStringContainsString('bin/tracezilla-login', $result['tracezilla']['error']);
    }

    public function test_unexpected_responses_count_as_failures(): void
    {
        $result = $this->checker(static fn (): array => [], static fn (): array => ['message' => 'nope'])->check();

        self::assertSame('WooCommerce returned no system status.', $result['woocommerce']['error']);
        self::assertSame('tracezilla returned no data collection.', $result['tracezilla']['error']);
    }

    private function checker(\Closure $wooCommerce, \Closure $tracezilla): ConnectionChecker
    {
        return new ConnectionChecker($wooCommerce, $tracezilla, 'dev-team');
    }
}
