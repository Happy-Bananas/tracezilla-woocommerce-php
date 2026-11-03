<?php

declare(strict_types=1);

namespace Consultant\Scenarios\WooCommerce\ScenarioTemplate;

/**
 * Compares the WooCommerce catalog with the tracezilla SKUs.
 *
 * The rules to agree with the customer are marked RULE. Change them here and
 * in BusinessRulesTest.php.
 */
final class BusinessRules
{
    /** @var array<string, list<array<string, mixed>>> Shop items grouped by SKU */
    private array $shop = [];

    /** @var list<array<string, mixed>> Shop items without a SKU */
    private array $withoutSku = [];

    /** @var array<string, list<array<string, mixed>>> Active tracezilla SKUs grouped by SKU code */
    private array $tracezilla = [];

    private int $inactiveSkipped = 0;

    /**
     * @param array{woocommerce: list<array<string, mixed>>, tracezilla: list<array<string, mixed>>} $data
     * @return array<string, mixed>
     */
    public function apply(array $data): array
    {
        $this->groupShopItems($data['woocommerce']);
        $this->groupTracezillaSkus($data['tracezilla']);

        return $this->comparison();
    }

    /** @param list<array<string, mixed>> $items */
    private function groupShopItems(array $items): void
    {
        foreach ($items as $item) {
            $sku = $this->comparable((string) $item['sku']);
            if ($sku === '') {
                $this->withoutSku[] = [
                    'name' => $item['name'],
                    'product_id' => $item['product_id'],
                    'variation_id' => $item['variation_id'],
                ];
                continue;
            }
            $this->shop[$sku][] = $item;
        }
        ksort($this->shop, SORT_STRING);
    }

    /** @param list<array<string, mixed>> $skus */
    private function groupTracezillaSkus(array $skus): void
    {
        foreach ($skus as $sku) {
            // RULE: inactive tracezilla SKUs are not part of the catalog.
            if ($sku['active'] !== true) {
                $this->inactiveSkipped++;
                continue;
            }
            $code = $this->comparable((string) $sku['sku_code']);
            $this->tracezilla[$code][] = $sku;
        }
        ksort($this->tracezilla, SORT_STRING);
    }

    /** @return array<string, mixed> */
    private function comparison(): array
    {
        $matched = $this->matched();
        $onlyInWooCommerce = $this->onlyInWooCommerce();
        $onlyInTracezilla = $this->onlyInTracezilla();
        $duplicates = [
            'woocommerce' => $this->repeatedSkus($this->shop),
            'tracezilla' => $this->repeatedSkus($this->tracezilla),
        ];

        return [
            'status' => 'ok',
            'summary' => [
                'matched' => count($matched),
                'only_in_woocommerce' => count($onlyInWooCommerce),
                'only_in_tracezilla' => count($onlyInTracezilla),
                'woocommerce_without_sku' => count($this->withoutSku),
                'duplicate_skus' => count($duplicates['woocommerce']) + count($duplicates['tracezilla']),
                'inactive_tracezilla_skus_skipped' => $this->inactiveSkipped,
            ],
            'matched' => $matched,
            'only_in_woocommerce' => $onlyInWooCommerce,
            'only_in_tracezilla' => $onlyInTracezilla,
            'woocommerce_without_sku' => $this->withoutSku,
            'duplicate_skus' => $duplicates,
        ];
    }

    /** @return list<array<string, mixed>> SKUs in both systems */
    private function matched(): array
    {
        $matched = [];
        foreach ($this->shop as $sku => $items) {
            if (isset($this->tracezilla[$sku])) {
                $matched[] = [
                    'sku' => $sku,
                    'woocommerce_name' => $items[0]['name'],
                    'tracezilla_name' => $this->tracezilla[$sku][0]['name'],
                ];
            }
        }

        return $matched;
    }

    /** @return list<array<string, mixed>> */
    private function onlyInWooCommerce(): array
    {
        $onlyInWooCommerce = [];
        foreach ($this->shop as $sku => $items) {
            if (! isset($this->tracezilla[$sku])) {
                $onlyInWooCommerce[] = ['sku' => $sku, 'name' => $items[0]['name']];
            }
        }

        return $onlyInWooCommerce;
    }

    /** @return list<array<string, mixed>> */
    private function onlyInTracezilla(): array
    {
        $onlyInTracezilla = [];
        foreach ($this->tracezilla as $sku => $records) {
            if (! isset($this->shop[$sku])) {
                $onlyInTracezilla[] = ['sku' => $sku, 'name' => $records[0]['name']];
            }
        }

        return $onlyInTracezilla;
    }

    /**
     * SKUs used by more than one record. The comparison never picks one of
     * them; they are reported so the data can be fixed.
     *
     * @param array<string, list<array<string, mixed>>> $groups
     * @return list<string>
     */
    private function repeatedSkus(array $groups): array
    {
        $repeated = [];
        foreach ($groups as $sku => $records) {
            if (count($records) > 1) {
                $repeated[] = $sku;
            }
        }

        return $repeated;
    }

    /**
     * RULE: how a SKU is compared. Here: surrounding spaces are ignored and
     * upper and lower case differ. Use strtoupper(trim($sku)) to ignore case.
     */
    private function comparable(string $sku): string
    {
        return trim($sku);
    }
}
