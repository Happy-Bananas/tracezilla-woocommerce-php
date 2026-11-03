<?php

declare(strict_types=1);

namespace Tracezilla\WooCommerce\Scenarios;

use InvalidArgumentException;
use RuntimeException;

/**
 * Creates a new scenario by copying a template from templates/scenarios/ into
 * custom/Scenarios/WooCommerce/. The copied files belong to the developer.
 */
final readonly class ScenarioGenerator
{
    public const DEFAULT_TEMPLATE = 'confirm-credentials';

    /** The namespace used in the template files; replaced by the scenario's own. */
    private const TEMPLATE_NAMESPACE = 'Consultant\\Scenarios\\WooCommerce\\ScenarioTemplate';

    public function __construct(private string $projectDirectory)
    {
    }

    /** @return list<string> */
    public function templates(): array
    {
        $directories = glob($this->templateDirectory().'/*', GLOB_ONLYDIR);
        if ($directories === false) {
            return [];
        }

        $templates = [];
        foreach ($directories as $directory) {
            $templates[] = basename($directory);
        }
        sort($templates);

        return $templates;
    }

    /** @return list<string> The created files */
    public function generate(string $slug, string $template = self::DEFAULT_TEMPLATE): array
    {
        $scenario = new Scenario($this->projectDirectory, $slug);
        if (! in_array($template, $this->templates(), true)) {
            throw new InvalidArgumentException("Unknown template: {$template}. Available templates: ".implode(', ', $this->templates()).'.');
        }
        if (file_exists($scenario->directory)) {
            throw new RuntimeException("Scenario already exists: {$slug}");
        }
        if (! mkdir($scenario->directory, 0750, true) && ! is_dir($scenario->directory)) {
            throw new RuntimeException("Could not create scenario directory: {$scenario->directory}");
        }

        $files = [];
        foreach (['DataReader.php', 'BusinessRules.php', 'BusinessRulesTest.php'] as $name) {
            $source = $this->templateDirectory().'/'.$template.'/'.$name;
            $contents = file_get_contents($source);
            if ($contents === false) {
                throw new RuntimeException("Template {$template} is missing {$name}.");
            }
            $path = $scenario->directory.'/'.$name;
            if (file_put_contents($path, str_replace(self::TEMPLATE_NAMESPACE, $scenario->namespace, $contents), LOCK_EX) === false) {
                throw new RuntimeException("Could not write generated file: {$path}");
            }
            $files[] = $path;
        }

        return $files;
    }

    private function templateDirectory(): string
    {
        return dirname(__DIR__, 2).'/templates/scenarios';
    }
}
