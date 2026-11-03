<?php

declare(strict_types=1);

namespace Consultant\Scenarios\WooCommerce\ScenarioTemplate;

use RuntimeException;

/**
 * The customer's decisions, in plain PHP. It receives what DataReader read and
 * returns the result. It never calls an API itself.
 */
final class BusinessRules
{
    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function apply(array $data): array
    {
        $environment = $data['woocommerce_status']['environment'] ?? null;
        if (! is_array($environment) || ! is_string($environment['site_url'] ?? null)) {
            throw new RuntimeException('WooCommerce credential check returned no store.');
        }
        if (! is_array($data['tracezilla_skus']['data'] ?? null)) {
            throw new RuntimeException('tracezilla credential check returned no data collection.');
        }

        return [
            'status' => 'ok',
            'woocommerce' => [
                'credentials_valid' => true,
                'store_url' => $environment['site_url'],
                'version' => $environment['version'] ?? null,
            ],
            'tracezilla' => ['credentials_valid' => true],
            'message' => 'Hello! Both API credentials are working.',
        ];
    }
}
