<?php

declare(strict_types=1);

namespace Consultant\Scenarios\WooCommerce\ScenarioTemplate;

use Tracezilla\WooCommerce\Scenarios\TracezillaReader;
use Tracezilla\WooCommerce\Scenarios\WooCommerceReader;

/**
 * Reads both complete catalogs and the store's weight unit. Both readers are
 * read-only.
 */
final class DataReader
{
    /** @return array{woocommerce: list<array<string, mixed>>, weight_unit: string, tracezilla: list<array<string, mixed>>} */
    public function read(WooCommerceReader $wooCommerce, TracezillaReader $tracezilla): array
    {
        return [
            // Simple products, and the variations of variable products.
            'woocommerce' => $wooCommerce->catalog(),
            // The unit of the products' weight: kg, g, lbs, or oz.
            'weight_unit' => $wooCommerce->weightUnit(),
            // All SKUs, active and inactive.
            'tracezilla' => $tracezilla->skus(),
        ];
    }
}
