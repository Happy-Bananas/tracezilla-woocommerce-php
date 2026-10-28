<?php

declare(strict_types=1);

namespace Tests\Unit\Seed;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tracezilla\WooCommerce\Seed\SeedDefinition;

final class SeedDefinitionTest extends TestCase
{
    public function test_the_woocommerce_sandbox_seed_is_valid(): void
    {
        $definition = SeedDefinition::fromFile(dirname(__DIR__, 3).'/seeds/woocommerce-sandbox.yaml');

        self::assertCount(3, $definition->partners);
        self::assertContains('TZ-SIMPLE-001', array_column($definition->skus, 'sku_code'));
        self::assertSame('TZ-STOCK-001', $definition->purchaseOrders[0]['ext_ref']);
    }

    public function test_it_reports_every_problem_at_once(): void
    {
        try {
            SeedDefinition::fromArray([
                'version' => 2,
                'partner' => [],
                'partners' => [['ref' => 'TZ-A', 'country' => 'DK'], ['ref' => 'TZ-A', 'name' => 'A', 'country' => 'DK']],
                'skus' => [['sku_code' => 'TZ-1']],
                'purchase_orders' => [[
                    'ext_ref' => 'TZ-PO',
                    'supplier' => 'TZ-A',
                    'deliver_to' => ['partner' => 'TZ-A', 'location' => 'TZ-A-HQ'],
                    'currency' => 'DKK',
                    'exchange_rate' => 100,
                    'receive' => 'yes',
                    'lines' => [['sku_code' => 'TZ-1']],
                ]],
            ]);
            self::fail('Expected the definition to be rejected.');
        } catch (InvalidArgumentException $exception) {
            $message = $exception->getMessage();
        }

        foreach ([
            'version must be 1.',
            'Unknown top-level key [partner].',
            'partners[0].name is required.',
            'partners[1] repeats [TZ-A].',
            'skus[0].unit_of_measure is required.',
            'purchase_orders[0].supplier must be { partner: <ref>, location: <ref> }.',
            'purchase_orders[0].receive must be true or false.',
            'purchase_orders[0].lines[0].quantity is required.',
        ] as $expected) {
            self::assertStringContainsString($expected, $message);
        }
    }

    public function test_it_rejects_a_missing_file(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Seed file not found');

        SeedDefinition::fromFile('/nonexistent/seed.yaml');
    }
}
