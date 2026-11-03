<?php

declare(strict_types=1);

namespace Tracezilla\WooCommerce\Connection;

use Tracezilla\WooCommerce\Configuration;
use Tracezilla\WooCommerce\WooCommerce\Client;

/**
 * Asks the real WooCommerce store for its system status.
 */
final class LiveWooCommerceConnection implements WooCommerceConnection
{
    public function systemStatus(): array
    {
        // The settings are read here, not earlier, so a missing setting is
        // reported as a WooCommerce failure without stopping the tracezilla check.
        $configuration = Configuration::fromEnvironment();
        $client = new Client($configuration);

        return $client->systemStatus();
    }
}
