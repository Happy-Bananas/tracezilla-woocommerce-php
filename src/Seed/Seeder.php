<?php

declare(strict_types=1);

namespace Tracezilla\WooCommerce\Seed;

use DateTimeImmutable;
use RuntimeException;
use Throwable;

/**
 * Creates the records of a seed definition that do not exist in tracezilla.
 *
 * Existing records are reported and left untouched. Without $execute nothing
 * is written, and records that depend on not-yet-created ones are reported as
 * would_create.
 */
final readonly class Seeder
{
    public function __construct(
        private SeedGateway $gateway,
        private ?string $ownCompanyName = null,
        private DateTimeImmutable $today = new DateTimeImmutable('today'),
    ) {
    }

    public function run(SeedDefinition $definition, bool $execute): SeedResult
    {
        $result = new SeedResult();

        // ref => id, or null when the record is only planned in a dry run.
        /** @var array<string, array{id: ?string, locations: array<string, ?string>}> $partners */
        $partners = $this->gateway->partnersByRef();

        if ($definition->usesOwnCompany()) {
            if ($this->ownCompanyName === null || trim($this->ownCompanyName) === '') {
                throw new RuntimeException('The seed file uses own_company. Set TRACEZILLA_OWN_COMPANY to the name of your company in tracezilla.');
            }
            $partners[SeedDefinition::OWN_COMPANY] = $this->gateway->ownCompany($this->ownCompanyName);
            $this->seedLocations(SeedDefinition::OWN_COMPANY, $definition->ownCompanyLocations, $partners, $execute, $result);
        }

        foreach ($definition->partners as $partner) {
            $this->seedPartner($partner, $partners, $execute, $result);
        }

        $skuCodes = $this->gateway->skuCodes();
        foreach ($definition->skus as $sku) {
            $this->seedSku($sku, $skuCodes, $execute, $result);
        }

        foreach ($definition->purchaseOrders as $order) {
            $this->seedPurchaseOrder($order, $partners, $skuCodes, $execute, $result);
        }

        return $result;
    }

    private function seedPartner(array $partner, array &$partners, bool $execute, SeedResult $result): void
    {
        $ref = (string) $partner['ref'];
        $locations = $partner['locations'] ?? [];
        unset($partner['locations']);

        if (isset($partners[$ref])) {
            $result->add('partner', $ref, SeedResult::EXISTS);
        } elseif (! $execute) {
            $partners[$ref] = ['id' => null, 'locations' => []];
            $result->add('partner', $ref, SeedResult::WOULD_CREATE, (string) $partner['name']);
        } else {
            try {
                $partners[$ref] = ['id' => $this->gateway->createPartner($partner), 'locations' => []];
                $result->add('partner', $ref, SeedResult::CREATED, (string) $partner['name']);
            } catch (Throwable $exception) {
                $result->add('partner', $ref, SeedResult::FAILED, $exception->getMessage());
                foreach ($locations as $location) {
                    $result->add('location', "{$ref}/{$location['ref']}", SeedResult::FAILED, 'Partner was not created.');
                }

                return;
            }
        }

        $this->seedLocations($ref, $locations, $partners, $execute, $result);
    }

    private function seedLocations(string $ref, array $locations, array &$partners, bool $execute, SeedResult $result): void
    {
        foreach ($locations as $location) {
            $key = "{$ref}/{$location['ref']}";
            $partnerId = $partners[$ref]['id'];

            if (array_key_exists($location['ref'], $partners[$ref]['locations'])) {
                $result->add('location', $key, SeedResult::EXISTS);
            } elseif (! $execute || $partnerId === null) {
                $partners[$ref]['locations'][$location['ref']] = null;
                $result->add('location', $key, SeedResult::WOULD_CREATE, (string) $location['name']);
            } else {
                try {
                    $partners[$ref]['locations'][$location['ref']] = $this->gateway->createLocation($partnerId, $location);
                    $result->add('location', $key, SeedResult::CREATED, (string) $location['name']);
                } catch (Throwable $exception) {
                    $result->add('location', $key, SeedResult::FAILED, $exception->getMessage());
                }
            }
        }
    }

    private function seedSku(array $sku, array &$skuCodes, bool $execute, SeedResult $result): void
    {
        $code = (string) $sku['sku_code'];

        if (isset($skuCodes[$code])) {
            $result->add('sku', $code, SeedResult::EXISTS);

            return;
        }
        if (! $execute) {
            $skuCodes[$code] = true;
            $result->add('sku', $code, SeedResult::WOULD_CREATE, (string) ($sku['global_name'] ?? ''));

            return;
        }

        try {
            $this->gateway->createSku($sku);
            $skuCodes[$code] = true;
            $result->add('sku', $code, SeedResult::CREATED, (string) ($sku['global_name'] ?? ''));
        } catch (Throwable $exception) {
            $result->add('sku', $code, SeedResult::FAILED, $exception->getMessage());
        }
    }

    private function seedPurchaseOrder(array $order, array $partners, array $skuCodes, bool $execute, SeedResult $result): void
    {
        $extRef = (string) $order['ext_ref'];

        $problems = [];
        foreach (['supplier', 'deliver_to'] as $role) {
            ['partner' => $partnerRef, 'location' => $locationRef] = $order[$role];
            if (! isset($partners[$partnerRef])) {
                $problems[] = "{$role} partner [{$partnerRef}] is not in tracezilla or the seed file";
            } elseif (! array_key_exists($locationRef, $partners[$partnerRef]['locations'])) {
                $problems[] = "{$role} location [{$partnerRef}/{$locationRef}] is not in tracezilla or the seed file";
            }
        }
        foreach ($order['lines'] as $line) {
            if (! isset($skuCodes[(string) $line['sku_code']])) {
                $problems[] = "SKU [{$line['sku_code']}] is not in tracezilla or the seed file";
            }
        }
        if ($problems !== []) {
            $result->add('purchase_order', $extRef, SeedResult::INVALID, implode('; ', $problems).'.');

            return;
        }

        $supplier = $order['supplier'];
        $deliverTo = $order['deliver_to'];
        $ids = [
            'supplier' => $partners[$supplier['partner']]['id'],
            'supplier_location' => $partners[$supplier['partner']]['locations'][$supplier['location']],
            'deliver_to' => $partners[$deliverTo['partner']]['id'],
            'deliver_to_location' => $partners[$deliverTo['partner']]['locations'][$deliverTo['location']],
        ];
        $detail = count($order['lines']).' lines'.(($order['receive'] ?? false) ? ', received' : '');

        if (! in_array(null, $ids, true) && $this->gateway->purchaseOrderExists($extRef)) {
            $result->add('purchase_order', $extRef, SeedResult::EXISTS);

            return;
        }
        if (! $execute) {
            $result->add('purchase_order', $extRef, SeedResult::WOULD_CREATE, $detail);

            return;
        }
        if (in_array(null, $ids, true)) {
            $result->add('purchase_order', $extRef, SeedResult::FAILED, 'A partner or location it uses was not created.');

            return;
        }

        try {
            $this->gateway->createPurchaseOrder($this->purchaseOrderPayload($order, $ids));
            $result->add('purchase_order', $extRef, SeedResult::CREATED, $detail);
        } catch (Throwable $exception) {
            $result->add('purchase_order', $extRef, SeedResult::FAILED, $exception->getMessage());
        }
    }

    /** @param array<string, string> $ids */
    private function purchaseOrderPayload(array $order, array $ids): array
    {
        $date = $this->today->format('Y-m-d');
        $supplier = ['partner_id' => $ids['supplier'], 'location_id' => $ids['supplier_location']];

        return [
            'order_header' => [
                'ext_ref' => $order['ext_ref'],
                'currency' => $order['currency'],
                'exchange_rate' => $order['exchange_rate'],
                'order_date' => $date,
                'pickup_date' => $date,
                'delivery_date' => $date,
                'status' => 'draft',
                'partners' => [
                    'supplier' => $supplier,
                    'pickup_from' => $supplier,
                    'deliver_to' => ['partner_id' => $ids['deliver_to'], 'location_id' => $ids['deliver_to_location']],
                ],
            ],
            'inbound_lots' => $order['lines'],
            'price_logic' => 'none',
            'post_save_action' => ($order['receive'] ?? false) ? 'receive' : 'none',
            'ignore_missing_skus' => false,
        ];
    }
}
