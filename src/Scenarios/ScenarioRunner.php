<?php

declare(strict_types=1);

namespace Tracezilla\WooCommerce\Scenarios;

use RuntimeException;

/**
 * Runs a read-only scenario: its DataReader reads what the rules need, then its
 * BusinessRules decide the result.
 */
final readonly class ScenarioRunner
{
    public function __construct(
        private string $projectDirectory,
        private WooCommerceReader $wooCommerce,
        private TracezillaReader $tracezilla,
    ) {
    }

    /** @return array<mixed> */
    public function run(string $slug): array
    {
        $scenario = new Scenario($this->projectDirectory, $slug);
        foreach (['DataReader.php', 'BusinessRules.php'] as $file) {
            if (! is_file($scenario->directory.'/'.$file)) {
                throw new RuntimeException("Scenario {$slug} is missing {$file}. Create it with scenario:create {$slug}.");
            }
            require_once $scenario->directory.'/'.$file;
        }

        $readerClass = $scenario->namespace.'\\DataReader';
        $rulesClass = $scenario->namespace.'\\BusinessRules';
        $data = (new $readerClass())->read($this->wooCommerce, $this->tracezilla);

        return (new $rulesClass())->apply($data);
    }
}
