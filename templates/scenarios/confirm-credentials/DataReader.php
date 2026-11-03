<?php

declare(strict_types=1);

namespace Consultant\Scenarios\WooCommerce\ScenarioTemplate;

use Tracezilla\WooCommerce\Scenarios\TracezillaReader;
use Tracezilla\WooCommerce\Scenarios\WooCommerceReader;

/**
 * Reads what the business rules need. Both readers are read-only.
 */
final class DataReader
{
    /** @return array<string, mixed> */
    public function read(WooCommerceReader $wooCommerce, TracezillaReader $tracezilla): array
    {
        return [
            'woocommerce_status' => $wooCommerce->get('system_status'),
            'tracezilla_skus' => $tracezilla->get('/skus', ['perPage' => 1]),
        ];
    }
}
