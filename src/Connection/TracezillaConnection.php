<?php

declare(strict_types=1);

namespace Tracezilla\WooCommerce\Connection;

/**
 * What the connection check needs from tracezilla. The real team and the fake
 * team in the tests both provide it.
 */
interface TracezillaConnection
{
    /** @return array<string, mixed> One page with at most one SKU */
    public function firstSku(): array;
}
