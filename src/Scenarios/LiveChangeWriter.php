<?php

declare(strict_types=1);

namespace Tracezilla\WooCommerce\Scenarios;

use Bifrost\Core\Tracezilla\SkuCreator;
use InvalidArgumentException;

/**
 * Carries out planned changes against the real APIs. Each action a scenario
 * may plan is listed here; any other action is refused.
 */
final readonly class LiveChangeWriter implements ChangeWriter
{
    public const CREATE_TRACEZILLA_SKU = 'create_tracezilla_sku';

    public function __construct(private SkuCreator $skuCreator)
    {
    }

    public function write(string $action, array $data): void
    {
        if ($action === self::CREATE_TRACEZILLA_SKU) {
            $this->skuCreator->create($data);

            return;
        }

        throw new InvalidArgumentException("Unknown change action: {$action}. Known actions: ".self::CREATE_TRACEZILLA_SKU.'.');
    }
}
