<?php

declare(strict_types=1);

namespace Tests\Unit\Connection;

use PHPUnit\Framework\TestCase;
use Tracezilla\WooCommerce\Connection\ConnectionChecker;

final class ConnectionCheckerTest extends TestCase
{
    public function test_it_reports_both_connections(): void
    {
        $checker = new ConnectionChecker(
            FakeWooCommerceConnection::answering(['environment' => ['site_url' => 'http://localhost:8080', 'version' => '11.2.1']]),
            FakeTracezillaConnection::answering(['data' => []]),
            'dev-team',
        );

        $result = $checker->check();

        self::assertSame([
            'status' => 'ok',
            'woocommerce' => ['connected' => true, 'store_url' => 'http://localhost:8080', 'version' => '11.2.1'],
            'tracezilla' => ['team' => 'dev-team', 'connected' => true],
        ], $result);
    }

    public function test_a_woocommerce_failure_does_not_hide_the_tracezilla_result(): void
    {
        $checker = new ConnectionChecker(
            FakeWooCommerceConnection::failingWith('Missing required environment variable: WOOCOMMERCE_CONSUMER_KEY'),
            FakeTracezillaConnection::answering(['data' => []]),
            'dev-team',
        );

        $result = $checker->check();

        self::assertSame('failed', $result['status']);
        self::assertSame(['connected' => false, 'error' => 'Missing required environment variable: WOOCOMMERCE_CONSUMER_KEY'], $result['woocommerce']);
        self::assertTrue($result['tracezilla']['connected']);
    }

    public function test_a_tracezilla_failure_does_not_hide_the_woocommerce_result(): void
    {
        $checker = new ConnectionChecker(
            FakeWooCommerceConnection::answering(['environment' => ['site_url' => 'http://localhost:8080', 'version' => '11.2.1']]),
            FakeTracezillaConnection::failingWith('tracezilla token request failed with HTTP 400: invalid_grant'),
            'dev-team',
        );

        $result = $checker->check();

        self::assertSame('failed', $result['status']);
        self::assertTrue($result['woocommerce']['connected']);
        self::assertStringContainsString('invalid_grant', $result['tracezilla']['error']);
    }

    public function test_unexpected_responses_count_as_failures(): void
    {
        $checker = new ConnectionChecker(
            FakeWooCommerceConnection::answering([]),
            FakeTracezillaConnection::answering(['message' => 'nope']),
            'dev-team',
        );

        $result = $checker->check();

        self::assertSame('WooCommerce returned no system status.', $result['woocommerce']['error']);
        self::assertSame('tracezilla returned no data collection.', $result['tracezilla']['error']);
    }
}
