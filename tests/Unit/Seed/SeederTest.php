<?php

declare(strict_types=1);

namespace Tests\Unit\Seed;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tracezilla\WooCommerce\Seed\SeedDefinition;
use Tracezilla\WooCommerce\Seed\Seeder;
use Tracezilla\WooCommerce\Seed\SeedResult;

final class SeederTest extends TestCase
{
    private InMemorySeedGateway $gateway;

    protected function setUp(): void
    {
        $this->gateway = new InMemorySeedGateway();
    }

    public function test_a_dry_run_plans_every_record_and_writes_nothing(): void
    {
        $result = $this->seeder()->run($this->definition(), execute: false);

        self::assertSame([], $this->gateway->writes);
        self::assertSame([SeedResult::WOULD_CREATE => 6], $result->counts());
        self::assertTrue($result->isSuccessful());
    }

    public function test_execute_creates_records_in_dependency_order(): void
    {
        $result = $this->seeder()->run($this->definition(), execute: true);

        self::assertSame([
            'location TZ-WAREHOUSE-MAIN',
            'partner TZ-SUPPLIER',
            'location TZ-SUPPLIER-HQ',
            'sku TZ-A',
            'sku TZ-B',
            'purchase_order TZ-STOCK-001',
        ], $this->gateway->writes);
        self::assertSame([SeedResult::CREATED => 6], $result->counts());
    }

    public function test_a_second_run_skips_everything(): void
    {
        $this->seeder()->run($this->definition(), execute: true);
        $this->gateway->writes = [];

        $result = $this->seeder()->run($this->definition(), execute: true);

        self::assertSame([], $this->gateway->writes);
        self::assertSame([SeedResult::EXISTS => 6], $result->counts());
    }

    public function test_it_builds_the_purchase_order_payload_from_resolved_ids(): void
    {
        $this->seeder()->run($this->definition(), execute: true);

        $payload = $this->gateway->purchaseOrders['TZ-STOCK-001'];
        $supplier = $this->gateway->partners['TZ-SUPPLIER'];
        $warehouse = $this->gateway->companies['Own Company'];

        self::assertSame('2026-10-28', $payload['order_header']['delivery_date']);
        self::assertSame(
            ['partner_id' => $supplier['id'], 'location_id' => $supplier['locations']['TZ-SUPPLIER-HQ']],
            $payload['order_header']['partners']['supplier'],
        );
        self::assertSame(
            ['partner_id' => $warehouse['id'], 'location_id' => $warehouse['locations']['TZ-WAREHOUSE-MAIN']],
            $payload['order_header']['partners']['deliver_to'],
        );
        self::assertSame('receive', $payload['post_save_action']);
        self::assertSame([['sku_code' => 'TZ-A', 'quantity' => 10, 'unit_price' => 5]], $payload['inbound_lots']);
    }

    public function test_it_adds_a_missing_location_to_an_existing_partner(): void
    {
        $this->gateway->partners['TZ-SUPPLIER'] = ['id' => 'existing', 'locations' => []];

        $this->seeder()->run($this->definition(), execute: true);

        self::assertContains('location TZ-SUPPLIER-HQ', $this->gateway->writes);
        self::assertNotContains('partner TZ-SUPPLIER', $this->gateway->writes);
    }

    public function test_a_failed_sku_does_not_stop_the_other_records(): void
    {
        $this->gateway->failures['TZ-A'] = 'HTTP 422: lot_unit is invalid';

        $result = $this->seeder()->run($this->definition(), execute: true);

        self::assertContains('sku TZ-B', $this->gateway->writes);
        self::assertNotContains('purchase_order TZ-STOCK-001', $this->gateway->writes);
        self::assertFalse($result->isSuccessful());
        self::assertSame(['sku', 'TZ-A', SeedResult::FAILED, 'HTTP 422: lot_unit is invalid'], array_values($result->items()[3]));
    }

    public function test_a_failed_partner_fails_its_locations_and_orders(): void
    {
        $this->gateway->failures['TZ-SUPPLIER'] = 'HTTP 500';

        $result = $this->seeder()->run($this->definition(), execute: true);

        self::assertSame(SeedResult::FAILED, $result->items()[1]['action']);
        self::assertSame(SeedResult::FAILED, $result->items()[2]['action']);
        self::assertSame(SeedResult::INVALID, $result->items()[5]['action']);
        self::assertNotContains('purchase_order TZ-STOCK-001', $this->gateway->writes);
    }

    public function test_an_unknown_reference_is_invalid(): void
    {
        $data = $this->data();
        $data['purchase_orders'][0]['lines'][] = ['sku_code' => 'TZ-UNKNOWN', 'quantity' => 1];

        $result = $this->seeder()->run(SeedDefinition::fromArray($data), execute: false);

        $order = $result->items()[5];
        self::assertSame(SeedResult::INVALID, $order['action']);
        self::assertStringContainsString('TZ-UNKNOWN', $order['detail']);
    }

    public function test_own_company_requires_a_configured_name(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('TRACEZILLA_OWN_COMPANY');

        (new Seeder($this->gateway))->run($this->definition(), execute: false);
    }

    public function test_an_existing_own_company_location_is_reused(): void
    {
        $this->gateway->companies['Own Company']['locations']['TZ-WAREHOUSE-MAIN'] = 'existing-warehouse';

        $this->seeder()->run($this->definition(), execute: true);

        self::assertNotContains('location TZ-WAREHOUSE-MAIN', $this->gateway->writes);
        self::assertSame(
            'existing-warehouse',
            $this->gateway->purchaseOrders['TZ-STOCK-001']['order_header']['partners']['deliver_to']['location_id'],
        );
    }

    private function seeder(): Seeder
    {
        return new Seeder($this->gateway, 'Own Company', new DateTimeImmutable('2026-10-28'));
    }

    private function definition(): SeedDefinition
    {
        return SeedDefinition::fromArray($this->data());
    }

    private function data(): array
    {
        $location = static fn (string $ref): array => ['ref' => $ref, 'name' => $ref, 'country' => 'DK'];
        $sku = static fn (string $code): array => [
            'sku_code' => $code,
            'unit_of_measure' => 'pcs',
            'lot_unit' => 'pcs',
            'weight_factor_net' => 1,
            'weight_factor_gross' => 1,
        ];

        return [
            'version' => 1,
            'own_company' => ['locations' => [$location('TZ-WAREHOUSE-MAIN')]],
            'partners' => [
                ['ref' => 'TZ-SUPPLIER', 'name' => 'Supplier', 'country' => 'DK', 'locations' => [$location('TZ-SUPPLIER-HQ')]],
            ],
            'skus' => [$sku('TZ-A'), $sku('TZ-B')],
            'purchase_orders' => [[
                'ext_ref' => 'TZ-STOCK-001',
                'supplier' => ['partner' => 'TZ-SUPPLIER', 'location' => 'TZ-SUPPLIER-HQ'],
                'deliver_to' => ['partner' => 'own_company', 'location' => 'TZ-WAREHOUSE-MAIN'],
                'currency' => 'DKK',
                'exchange_rate' => 100,
                'receive' => true,
                'lines' => [['sku_code' => 'TZ-A', 'quantity' => 10, 'unit_price' => 5]],
            ]],
        ];
    }
}
