<?php

declare(strict_types=1);

namespace Tracezilla\WooCommerce\Scenarios;

use Closure;
use RuntimeException;

/**
 * Runs a read-only scenario: one WooCommerce request, one tracezilla request,
 * then the scenario's business rules on both responses.
 */
final readonly class ScenarioRunner
{
    /**
     * @param Closure(string, array<string, scalar>): array<mixed> $wooCommerceGet
     * @param Closure(string, array<string, scalar>): array<mixed> $tracezillaGet
     */
    public function __construct(
        private string $projectDirectory,
        private Closure $wooCommerceGet,
        private Closure $tracezillaGet,
    ) {
    }

    /** @return array<mixed> */
    public function run(string $slug): array
    {
        $scenario = new Scenario($this->projectDirectory, $slug);
        foreach (['WooCommerceRequest.php', 'TracezillaRequest.php', 'BusinessRules.php'] as $file) {
            if (! is_file($scenario->directory.'/'.$file)) {
                throw new RuntimeException("Scenario {$slug} is missing {$file}. Create it with scenario:create {$slug}.");
            }
            require_once $scenario->directory.'/'.$file;
        }

        $wooCommerceRequestClass = $scenario->namespace.'\\WooCommerceRequest';
        $tracezillaRequestClass = $scenario->namespace.'\\TracezillaRequest';
        $rulesClass = $scenario->namespace.'\\BusinessRules';
        $wooCommerceRequest = new $wooCommerceRequestClass();
        $tracezillaRequest = new $tracezillaRequestClass();

        $wooCommerce = ($this->wooCommerceGet)($wooCommerceRequest->path(), $wooCommerceRequest->query());
        $tracezilla = ($this->tracezillaGet)($tracezillaRequest->path(), $tracezillaRequest->query());

        return (new $rulesClass())->apply($wooCommerce, $tracezilla);
    }
}
