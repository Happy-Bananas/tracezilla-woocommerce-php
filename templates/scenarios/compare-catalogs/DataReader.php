<?php

declare(strict_types=1);

namespace Consultant\Scenarios\WooCommerce\ScenarioTemplate;

use Tracezilla\WooCommerce\Scenarios\TracezillaReader;
use Tracezilla\WooCommerce\Scenarios\WooCommerceReader;

/**
 * Reads both complete catalogs. Both readers are read-only.
 */
final class DataReader
{
    /** @return array{woocommerce: list<array<string, mixed>>, tracezilla: list<array<string, mixed>>} */
    public function read(WooCommerceReader $wooCommerce, TracezillaReader $tracezilla): array
    {
        return [
            // Simple products, and the variations of variable products.
            'woocommerce' => $wooCommerce->catalog(),
            // All SKUs, active and inactive.
            'tracezilla' => $tracezilla->skus(),
        ];
    }
}
