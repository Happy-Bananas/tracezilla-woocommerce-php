<?php

declare(strict_types=1);

namespace Tracezilla\WooCommerce\Connection;

use Throwable;

/**
 * Checks that the WooCommerce and tracezilla credentials work, with one small
 * read-only request each. Both are always checked, so a failure in one does
 * not hide the state of the other.
 */
final class ConnectionChecker
{
    public function __construct(
        private WooCommerceConnection $wooCommerce,
        private TracezillaConnection $tracezilla,
        private string $teamSlug,
    ) {
    }

    /** @return array<string, mixed> */
    public function check(): array
    {
        $wooCommerce = $this->checkWooCommerce();
        $tracezilla = $this->checkTracezilla();

        return $this->summary($wooCommerce, $tracezilla);
    }

    /** @return array<string, mixed> */
    private function checkWooCommerce(): array
    {
        try {
            $status = $this->wooCommerce->systemStatus();
        } catch (Throwable $exception) {
            return $this->failure($exception->getMessage());
        }

        $environment = $this->environmentFrom($status);
        if ($environment === null) {
            return $this->failure('WooCommerce returned no system status.');
        }

        return [
            'connected' => true,
            'store_url' => $this->textOrNull($environment, 'site_url'),
            'version' => $this->textOrNull($environment, 'version'),
        ];
    }

    /** @return array<string, mixed> */
    private function checkTracezilla(): array
    {
        $result = ['team' => $this->teamSlug];

        try {
            $page = $this->tracezilla->firstSku();
        } catch (Throwable $exception) {
            $result['connected'] = false;
            $result['error'] = $exception->getMessage();

            return $result;
        }

        if (! $this->hasSkuList($page)) {
            $result['connected'] = false;
            $result['error'] = 'tracezilla returned no data collection.';

            return $result;
        }

        $result['connected'] = true;

        return $result;
    }

    /**
     * @param array<string, mixed> $wooCommerce
     * @param array<string, mixed> $tracezilla
     * @return array<string, mixed>
     */
    private function summary(array $wooCommerce, array $tracezilla): array
    {
        $status = 'failed';
        if ($wooCommerce['connected'] && $tracezilla['connected']) {
            $status = 'ok';
        }

        return [
            'status' => $status,
            'woocommerce' => $wooCommerce,
            'tracezilla' => $tracezilla,
        ];
    }

    /**
     * The "environment" part of WooCommerce's status, or null if it is missing.
     *
     * @param array<string, mixed> $status
     * @return array<string, mixed>|null
     */
    private function environmentFrom(array $status): ?array
    {
        if (! isset($status['environment'])) {
            return null;
        }
        if (! is_array($status['environment'])) {
            return null;
        }

        return $status['environment'];
    }

    /**
     * Whether tracezilla answered with a list of SKUs, even an empty one.
     *
     * @param array<string, mixed> $page
     */
    private function hasSkuList(array $page): bool
    {
        if (! isset($page['data'])) {
            return false;
        }

        return is_array($page['data']);
    }

    /** @return array{connected: false, error: string} */
    private function failure(string $message): array
    {
        return ['connected' => false, 'error' => $message];
    }

    /** @param array<string, mixed> $values */
    private function textOrNull(array $values, string $key): ?string
    {
        if (isset($values[$key]) && is_string($values[$key])) {
            return $values[$key];
        }

        return null;
    }
}
