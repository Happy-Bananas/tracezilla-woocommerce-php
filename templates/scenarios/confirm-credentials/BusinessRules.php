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
        $environment = $this->environmentFrom($data['woocommerce_status']);
        if ($environment === null) {
            throw new RuntimeException('WooCommerce credential check returned no store.');
        }
        if (! $this->hasSkuList($data['tracezilla_skus'])) {
            throw new RuntimeException('tracezilla credential check returned no data collection.');
        }

        $version = null;
        if (isset($environment['version'])) {
            $version = $environment['version'];
        }

        return [
            'status' => 'ok',
            'woocommerce' => [
                'credentials_valid' => true,
                'store_url' => $environment['site_url'],
                'version' => $version,
            ],
            'tracezilla' => ['credentials_valid' => true],
            'message' => 'Hello! Both API credentials are working.',
        ];
    }

    /**
     * The "environment" part of WooCommerce's status, if it names the store.
     *
     * @param array<string, mixed> $status
     * @return array<string, mixed>|null
     */
    private function environmentFrom(array $status): ?array
    {
        if (! isset($status['environment'])) {
            return null;
        }
        $environment = $status['environment'];
        if (! is_array($environment)) {
            return null;
        }
        if (! isset($environment['site_url']) || ! is_string($environment['site_url'])) {
            return null;
        }

        return $environment;
    }

    /** @param array<string, mixed> $page */
    private function hasSkuList(array $page): bool
    {
        if (! isset($page['data'])) {
            return false;
        }

        return is_array($page['data']);
    }
}
