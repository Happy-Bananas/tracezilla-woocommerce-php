<?php

declare(strict_types=1);

namespace Consultant\Scenarios\WooCommerce\ScenarioTemplate;

/**
 * Plans a tracezilla SKU for every product the shop sells that tracezilla does
 * not know yet. It only plans: the changes are written by scenario:run, and only
 * with --execute --confirm.
 *
 * The rules to agree with the customer are marked RULE. Change them here and
 * in BusinessRulesTest.php.
 */
final class BusinessRules
{
    /** tracezilla rejects longer SKU codes. */
    private const MAX_SKU_CODE_LENGTH = 17;

    /** RULE: the weight used when WooCommerce has none, in kg per piece. */
    private const ASSUMED_WEIGHT_KG = 1.0;

    /** How many kg one unit of each WooCommerce weight unit is. */
    private const KG_PER_UNIT = ['kg' => 1.0, 'g' => 0.001, 'lbs' => 0.45359237, 'oz' => 0.028349523125];

    /** @var array<string, true> SKU codes that exist in tracezilla, active or not */
    private array $inTracezilla = [];

    /** @var array<string, array<string, mixed>> Planned changes, by SKU */
    private array $changes = [];

    /** @var array<string, array<string, mixed>> Products that cannot become an SKU, by SKU */
    private array $skipped = [];

    /** @var list<string> SKUs whose weight was assumed */
    private array $weightAssumed = [];

    private int $alreadyInTracezilla = 0;
    private string $weightUnit = 'kg';

    /**
     * @param array{woocommerce: list<array<string, mixed>>, weight_unit: string, tracezilla: list<array<string, mixed>>} $data
     * @return array<string, mixed>
     */
    public function apply(array $data): array
    {
        $this->weightUnit = $data['weight_unit'];
        $this->rememberTracezillaSkus($data['tracezilla']);
        $this->planMissingSkus($data['woocommerce']);

        return $this->plan();
    }

    /** @param list<array<string, mixed>> $skus */
    private function rememberTracezillaSkus(array $skus): void
    {
        foreach ($skus as $sku) {
            // Inactive SKUs count too: tracezilla does not allow the same code twice.
            $this->inTracezilla[$this->comparable((string) $sku['sku_code'])] = true;
        }
    }

    /** @param list<array<string, mixed>> $items */
    private function planMissingSkus(array $items): void
    {
        foreach ($items as $item) {
            $code = $this->comparable((string) $item['sku']);
            if ($code === '') {
                continue;
            }
            if (isset($this->inTracezilla[$code])) {
                $this->alreadyInTracezilla++;
                continue;
            }

            $reason = $this->whyItCannotBeCreated($code);
            if ($reason !== null) {
                $this->skipped[$code] = ['sku' => $code, 'name' => $item['name'], 'reason' => $reason];
                continue;
            }

            $this->changes[$code] = [
                'action' => 'create_tracezilla_sku',
                'key' => $code,
                'data' => $this->tracezillaSku($code, $item),
            ];
        }
    }

    private function whyItCannotBeCreated(string $code): ?string
    {
        if (strlen($code) > self::MAX_SKU_CODE_LENGTH) {
            return 'the SKU is longer than '.self::MAX_SKU_CODE_LENGTH.' characters';
        }

        return null;
    }

    /**
     * RULE: how a shop product becomes a tracezilla SKU. Here: a finished good
     * sold and stored per piece, named like the product, with its weight.
     *
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function tracezillaSku(string $code, array $item): array
    {
        $weight = $this->weightInKg($code, (string) $item['weight']);

        return [
            'sku_code' => $code,
            'global_name' => $item['name'],
            'category' => 'finished_good',
            'unit_of_measure' => 'pcs',
            'lot_unit' => 'pcs',
            'default_uom_conversion' => 1,
            'weight_factor_net' => $weight,
            // RULE: the gross weight (with packaging) equals the net weight.
            'weight_factor_gross' => $weight,
        ];
    }

    /** The product's weight in kg, or the assumed weight if WooCommerce has none. */
    private function weightInKg(string $code, string $weight): float
    {
        if (! $this->isUsableWeight($weight)) {
            $this->weightAssumed[] = $code;

            return self::ASSUMED_WEIGHT_KG;
        }

        return round((float) $weight * self::KG_PER_UNIT[$this->weightUnit], 4);
    }

    /** Whether the weight is a number in a unit we can convert to kg. */
    private function isUsableWeight(string $weight): bool
    {
        if (! is_numeric($weight)) {
            return false;
        }

        return isset(self::KG_PER_UNIT[$this->weightUnit]);
    }

    /** @return array<string, mixed> */
    private function plan(): array
    {
        ksort($this->changes, SORT_STRING);
        ksort($this->skipped, SORT_STRING);
        sort($this->weightAssumed, SORT_STRING);

        return [
            'status' => 'ok',
            'summary' => [
                'to_create' => count($this->changes),
                'skipped' => count($this->skipped),
                'already_in_tracezilla' => $this->alreadyInTracezilla,
                'weight_assumed' => count($this->weightAssumed),
            ],
            'skipped' => array_values($this->skipped),
            'weight_assumed' => $this->weightAssumed,
            'changes' => array_values($this->changes),
        ];
    }

    /**
     * RULE: how a SKU is compared. Here: surrounding spaces are ignored and
     * upper and lower case differ.
     */
    private function comparable(string $sku): string
    {
        return trim($sku);
    }
}
