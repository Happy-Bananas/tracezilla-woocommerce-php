<?php

declare(strict_types=1);

namespace Consultant\Scenarios\WooCommerce\ScenarioTemplate;

/**
 * Lists the tracezilla SKUs that the shop does not sell, so you can decide
 * with the customer which of them belong in WooCommerce.
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
        $soldInShop = [];
        foreach ($data['woocommerce'] as $item) {
            $soldInShop[$this->key((string) $item['sku'])] = true;
        }

        $inactive = 0;
        $candidates = [];
        $notForTheShop = [];
        foreach ($data['tracezilla'] as $sku) {
            $code = $this->key((string) $sku['sku_code']);
            if ($code === '' || isset($soldInShop[$code])) {
                continue;
            }
            // RULE: inactive tracezilla SKUs are ignored.
            if ($sku['active'] !== true) {
                $inactive++;
                continue;
            }
            $reason = $this->notForTheShop($sku);
            $record = ['sku' => $code, 'tracezilla_id' => $sku['id'], 'name' => $sku['name'], 'category' => $sku['category']];
            if ($reason === null) {
                $candidates[] = $record;
            } else {
                $notForTheShop[] = $record + ['reason' => $reason];
            }
        }

        usort($candidates, static fn (array $a, array $b): int => strcmp($a['sku'], $b['sku']));
        usort($notForTheShop, static fn (array $a, array $b): int => strcmp($a['sku'], $b['sku']));

        return [
            'status' => 'ok',
            'summary' => [
                'decide_with_customer' => count($candidates),
                'not_for_the_shop' => count($notForTheShop),
                'inactive_tracezilla_skus_skipped' => $inactive,
            ],
            'decide_with_customer' => $candidates,
            'not_for_the_shop' => $notForTheShop,
        ];
    }

    /**
     * RULE: which tracezilla SKUs can never be sold in the shop. Here: SKUs
     * that tracezilla does not allow to be sold, and everything that is not a
     * finished good, such as raw materials.
     *
     * @param array<string, mixed> $sku
     */
    private function notForTheShop(array $sku): ?string
    {
        if ($sku['allow_sales'] !== true) {
            return 'sales are not allowed in tracezilla';
        }
        if ($sku['category'] !== 'finished_good') {
            return "category is {$sku['category']}";
        }

        return null;
    }

    /**
     * RULE: how a SKU is compared. Here: surrounding spaces are ignored and
     * upper and lower case differ.
     */
    private function key(string $sku): string
    {
        return trim($sku);
    }
}
