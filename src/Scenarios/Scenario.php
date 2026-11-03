<?php

declare(strict_types=1);

namespace Tracezilla\WooCommerce\Scenarios;

use InvalidArgumentException;

/**
 * Where a consultant scenario lives: confirm-credentials becomes the class
 * namespace Consultant\Scenarios\WooCommerce\ConfirmCredentials in
 * custom/Scenarios/WooCommerce/ConfirmCredentials/.
 */
final readonly class Scenario
{
    public string $className;
    public string $namespace;
    public string $directory;

    public function __construct(string $projectDirectory, public string $slug)
    {
        if (preg_match('/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/', $slug) !== 1) {
            throw new InvalidArgumentException('Scenario name must use lowercase letters, numbers, and single hyphens, for example confirm-credentials.');
        }

        $this->className = str_replace(' ', '', ucwords(str_replace('-', ' ', $slug)));
        $this->namespace = 'Consultant\\Scenarios\\WooCommerce\\'.$this->className;
        $this->directory = rtrim($projectDirectory, '/').'/custom/Scenarios/WooCommerce/'.$this->className;
    }
}
