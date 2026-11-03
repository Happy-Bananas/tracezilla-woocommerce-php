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
    /** @var array<string, true> The SKUs the shop sells */
    private array $soldInShop = [];

    /** @var array<string, array<string, mixed>> Decisions for the customer, by SKU */
    private array $decisions = [];

    /** @var array<string, array<string, mixed>> SKUs that never belong in the shop, by SKU */
    private array $notForTheShop = [];

    private int $inactiveSkipped = 0;

    /**
     * @param array{woocommerce: list<array<string, mixed>>, tracezilla: list<array<string, mixed>>} $data
     * @return array<string, mixed>
     */
    public function apply(array $data): array
    {
        $this->rememberShopSkus($data['woocommerce']);
        $this->sortTracezillaSkus($data['tracezilla']);

        return $this->decisionList();
    }

    /** @param list<array<string, mixed>> $items */
    private function rememberShopSkus(array $items): void
    {
        foreach ($items as $item) {
            $this->soldInShop[$this->comparable((string) $item['sku'])] = true;
        }
    }

    /**
     * Put each tracezilla SKU the shop does not sell on the decision list or
     * on the "not for the shop" list.
     *
     * @param list<array<string, mixed>> $skus
     */
    private function sortTracezillaSkus(array $skus): void
    {
        foreach ($skus as $sku) {
            $code = $this->comparable((string) $sku['sku_code']);
            if ($code === '') {
                continue;
            }
            if (isset($this->soldInShop[$code])) {
                continue;
            }
            // RULE: inactive tracezilla SKUs are ignored.
            if ($sku['active'] !== true) {
                $this->inactiveSkipped++;
                continue;
            }

            $record = [
                'sku' => $code,
                'tracezilla_id' => $sku['id'],
                'name' => $sku['name'],
                'category' => $sku['category'],
            ];
            $reason = $this->whyNotForTheShop($sku);
            if ($reason === null) {
                $this->decisions[$code] = $record;
            } else {
                $record['reason'] = $reason;
                $this->notForTheShop[$code] = $record;
            }
        }
    }

    /** @return array<string, mixed> */
    private function decisionList(): array
    {
        ksort($this->decisions, SORT_STRING);
        ksort($this->notForTheShop, SORT_STRING);

        return [
            'status' => 'ok',
            'summary' => [
                'decide_with_customer' => count($this->decisions),
                'not_for_the_shop' => count($this->notForTheShop),
                'inactive_tracezilla_skus_skipped' => $this->inactiveSkipped,
            ],
            'decide_with_customer' => array_values($this->decisions),
            'not_for_the_shop' => array_values($this->notForTheShop),
        ];
    }

    /**
     * RULE: which tracezilla SKUs can never be sold in the shop. Here: SKUs
     * that tracezilla does not allow to be sold, and everything that is not a
     * finished good, such as raw materials.
     *
     * @param array<string, mixed> $sku
     */
    private function whyNotForTheShop(array $sku): ?string
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
    private function comparable(string $sku): string
    {
        return trim($sku);
    }
}
