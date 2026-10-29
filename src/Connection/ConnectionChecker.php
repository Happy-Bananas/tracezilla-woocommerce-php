<?php

declare(strict_types=1);

namespace Tracezilla\WooCommerce\Connection;

use Closure;
use RuntimeException;
use Throwable;

/**
 * Checks the WooCommerce and tracezilla credentials with one read-only request
 * each. Both systems are always checked, so a failure in one does not hide the
 * state of the other.
 */
final readonly class ConnectionChecker
{
    /**
     * @param Closure(): array<string, mixed> $wooCommerceStatus Returns WooCommerce's system status.
     * @param Closure(): array<string, mixed> $tracezillaSkus Returns one page of tracezilla SKUs.
     */
    public function __construct(
        private Closure $wooCommerceStatus,
        private Closure $tracezillaSkus,
        private string $teamSlug,
    ) {
    }

    /**
     * @return array{
     *     status: 'ok'|'failed',
     *     woocommerce: array{connected: bool, store_url?: ?string, version?: ?string, error?: string},
     *     tracezilla: array{connected: bool, team: string, error?: string}
     * }
     */
    public function check(): array
    {
        $woocommerce = $this->attempt(function (): array {
            $environment = ($this->wooCommerceStatus)()['environment'] ?? null;
            if (! is_array($environment)) {
                throw new RuntimeException('WooCommerce returned no system status.');
            }

            return [
                'store_url' => is_string($environment['site_url'] ?? null) ? $environment['site_url'] : null,
                'version' => is_string($environment['version'] ?? null) ? $environment['version'] : null,
            ];
        });

        $tracezilla = ['team' => $this->teamSlug] + $this->attempt(function (): array {
            if (! is_array(($this->tracezillaSkus)()['data'] ?? null)) {
                throw new RuntimeException('tracezilla returned no data collection.');
            }

            return [];
        });

        return [
            'status' => $woocommerce['connected'] && $tracezilla['connected'] ? 'ok' : 'failed',
            'woocommerce' => $woocommerce,
            'tracezilla' => $tracezilla,
        ];
    }

    /**
     * @param Closure(): array<string, mixed> $check
     * @return array<string, mixed>
     */
    private function attempt(Closure $check): array
    {
        try {
            return ['connected' => true] + $check();
        } catch (Throwable $exception) {
            return ['connected' => false, 'error' => $exception->getMessage()];
        }
    }
}
