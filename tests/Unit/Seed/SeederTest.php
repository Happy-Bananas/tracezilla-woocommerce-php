<?php

declare(strict_types=1);

namespace Tests\Unit\Seed;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
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
        self::assertSame([SeedResult::WOULD_CREATE => 7], $result->counts());
        self::assertTrue($result->isSuccessful());
    }

    public function test_execute_creates_records_in_dependency_order(): void
    {
        $result = $this->seeder()->run($this->definition(), execute: true);

        self::assertSame([
            'partner TZ-SUPPLIER',
            'location TZ-SUPPLIER-HQ',
            'partner TZ-WAREHOUSE',
            'location TZ-WAREHOUSE-MAIN',
            'sku TZ-A',
            'sku TZ-B',
            'purchase_order TZ-STOCK-001',
        ], $this->gateway->writes);
        self::assertSame([SeedResult::CREATED => 7], $result->counts());
    }

    public function test_a_second_run_skips_everything(): void
    {
        $this->seeder()->run($this->definition(), execute: true);
        $this->gateway->writes = [];

        $result = $this->seeder()->run($this->definition(), execute: true);

        self::assertSame([], $this->gateway->writes);
        self::assertSame([SeedResult::EXISTS => 7], $result->counts());
    }

    public function test_it_builds_the_purchase_order_payload_from_resolved_ids(): void
    {
        $this->seeder()->run($this->definition(), execute: true);

        $payload = $this->gateway->purchaseOrders['TZ-STOCK-001'];
        $supplier = $this->gateway->partners['TZ-SUPPLIER'];
        $warehouse = $this->gateway->partners['TZ-WAREHOUSE'];

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
        self::assertSame(['sku', 'TZ-A', SeedResult::FAILED, 'HTTP 422: lot_unit is invalid'], array_values($result->items()[4]));
    }

    public function test_a_failed_partner_fails_its_locations_and_orders(): void
    {
        $this->gateway->failures['TZ-SUPPLIER'] = 'HTTP 500';

        $result = $this->seeder()->run($this->definition(), execute: true);

        self::assertSame(SeedResult::FAILED, $result->items()[1]['action']);
        self::assertSame(SeedResult::INVALID, $result->items()[6]['action']);
        self::assertNotContains('purchase_order TZ-STOCK-001', $this->gateway->writes);
    }

    public function test_an_unknown_reference_is_invalid(): void
    {
        $data = $this->data();
        $data['purchase_orders'][0]['lines'][] = ['sku_code' => 'TZ-UNKNOWN', 'quantity' => 1];

        $result = $this->seeder()->run(SeedDefinition::fromArray($data), execute: false);

        $order = $result->items()[6];
        self::assertSame(SeedResult::INVALID, $order['action']);
        self::assertStringContainsString('TZ-UNKNOWN', $order['detail']);
    }

    private function seeder(): Seeder
    {
        return new Seeder($this->gateway, new DateTimeImmutable('2026-10-28'));
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
            'partners' => [
                ['ref' => 'TZ-SUPPLIER', 'name' => 'Supplier', 'country' => 'DK', 'locations' => [$location('TZ-SUPPLIER-HQ')]],
                ['ref' => 'TZ-WAREHOUSE', 'name' => 'Warehouse', 'country' => 'DK', 'locations' => [$location('TZ-WAREHOUSE-MAIN')]],
            ],
            'skus' => [$sku('TZ-A'), $sku('TZ-B')],
            'purchase_orders' => [[
                'ext_ref' => 'TZ-STOCK-001',
                'supplier' => ['partner' => 'TZ-SUPPLIER', 'location' => 'TZ-SUPPLIER-HQ'],
                'deliver_to' => ['partner' => 'TZ-WAREHOUSE', 'location' => 'TZ-WAREHOUSE-MAIN'],
                'currency' => 'DKK',
                'exchange_rate' => 100,
                'receive' => true,
                'lines' => [['sku_code' => 'TZ-A', 'quantity' => 10, 'unit_price' => 5]],
            ]],
        ];
    }
}
