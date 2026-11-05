<?php

declare(strict_types=1);

namespace Tracezilla\WooCommerce\Connection;

use Bifrost\Core\Tracezilla\Client;
use Bifrost\Core\Tracezilla\TokenProvider;
use Bifrost\Core\Tracezilla\TracezillaConfiguration;

/**
 * Asks the real tracezilla team for one SKU.
 */
final class LiveTracezillaConnection implements TracezillaConnection
{
    public function __construct(private string $projectDirectory)
    {
    }

    public function firstSku(): array
    {
        // The settings are read here, not earlier, so a missing setting is
        // reported as a tracezilla failure without stopping the WooCommerce check.
        $configuration = TracezillaConfiguration::fromEnvironment($this->projectDirectory);
        $tokens = TokenProvider::fromConfiguration($configuration);
        $client = new Client($configuration, $tokens);

        return $client->get('/skus', ['perPage' => 1]);
    }
}
