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
    /**
     * @param array{woocommerce: list<array<string, mixed>>, tracezilla: list<array<string, mixed>>} $data
     * @return array<string, mixed>
     */
    public function apply(array $data): array
    {
        $withoutSku = [];
        $shop = [];
        foreach ($data['woocommerce'] as $item) {
            $code = $this->key((string) $item['sku']);
            if ($code === '') {
                $withoutSku[] = ['name' => $item['name'], 'product_id' => $item['product_id'], 'variation_id' => $item['variation_id']];
                continue;
            }
            $shop[$code][] = $item;
        }

        $inactive = 0;
        $tracezilla = [];
        foreach ($data['tracezilla'] as $sku) {
            // RULE: inactive tracezilla SKUs are not part of the catalog.
            if ($sku['active'] !== true) {
                $inactive++;
                continue;
            }
            $tracezilla[$this->key((string) $sku['sku_code'])][] = $sku;
        }

        ksort($shop, SORT_STRING);
        ksort($tracezilla, SORT_STRING);

        $matched = [];
        $onlyInWooCommerce = [];
        $onlyInTracezilla = [];
        foreach ($shop as $code => $items) {
            if (isset($tracezilla[$code])) {
                $matched[] = ['sku' => $code, 'woocommerce_name' => $items[0]['name'], 'tracezilla_name' => $tracezilla[$code][0]['name']];
            } else {
                $onlyInWooCommerce[] = ['sku' => $code, 'name' => $items[0]['name']];
            }
        }
        foreach ($tracezilla as $code => $skus) {
            if (! isset($shop[$code])) {
                $onlyInTracezilla[] = ['sku' => $code, 'name' => $skus[0]['name']];
            }
        }

        // Never pick one of several records with the same SKU; report them instead.
        $duplicates = [
            'woocommerce' => array_keys(array_filter($shop, static fn (array $items): bool => count($items) > 1)),
            'tracezilla' => array_keys(array_filter($tracezilla, static fn (array $skus): bool => count($skus) > 1)),
        ];

        return [
            'status' => 'ok',
            'summary' => [
                'matched' => count($matched),
                'only_in_woocommerce' => count($onlyInWooCommerce),
                'only_in_tracezilla' => count($onlyInTracezilla),
                'woocommerce_without_sku' => count($withoutSku),
                'duplicate_skus' => count($duplicates['woocommerce']) + count($duplicates['tracezilla']),
                'inactive_tracezilla_skus_skipped' => $inactive,
            ],
            'matched' => $matched,
            'only_in_woocommerce' => $onlyInWooCommerce,
            'only_in_tracezilla' => $onlyInTracezilla,
            'woocommerce_without_sku' => $withoutSku,
            'duplicate_skus' => $duplicates,
        ];
    }

    /**
     * RULE: how a SKU is compared. Here: surrounding spaces are ignored and
     * upper and lower case differ. Use strtoupper(trim($sku)) to ignore case.
     */
    private function key(string $sku): string
    {
        return trim($sku);
    }
}
