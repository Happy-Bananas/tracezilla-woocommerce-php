<?php

declare(strict_types=1);

namespace Tracezilla\WooCommerce\Connection;

/**
 * What the connection check needs from WooCommerce. The real store and the
 * fake store in the tests both provide it.
 */
interface WooCommerceConnection
{
    /** @return array<string, mixed> WooCommerce's system status */
    public function systemStatus(): array;
}
