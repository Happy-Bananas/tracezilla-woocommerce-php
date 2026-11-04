<?php

declare(strict_types=1);

namespace Tracezilla\WooCommerce\Scenarios;

use RuntimeException;
use Throwable;

/**
 * Runs a scenario: its DataReader reads what the rules need, its BusinessRules
 * decide the result, and the changes the rules planned are carried out.
 *
 * Changes are only written when the run is executed. Otherwise the run is a
 * dry run that reports each change as "planned".
 */
final readonly class ScenarioRunner
{
    public function __construct(
        private string $projectDirectory,
        private WooCommerceReader $wooCommerce,
        private TracezillaReader $tracezilla,
        private ChangeWriter $writer,
    ) {
    }

    /**
     * @param bool $execute Write the planned changes; otherwise only report them
     * @param int|null $limit Write at most this many changes; null means all
     * @return array<string, mixed>
     */
    public function run(string $slug, bool $execute = false, ?int $limit = null): array
    {
        $scenario = new Scenario($this->projectDirectory, $slug);
        $this->loadScenarioFiles($scenario);

        $data = $this->readData($scenario);
        $result = $this->applyRules($scenario, $data);

        return $this->carryOutChanges($result, $execute, $limit);
    }

    private function loadScenarioFiles(Scenario $scenario): void
    {
        foreach (['DataReader.php', 'BusinessRules.php'] as $file) {
            $path = $scenario->directory.'/'.$file;
            if (! is_file($path)) {
                throw new RuntimeException("Scenario {$scenario->slug} is missing {$file}. Create it with scenario:create {$scenario->slug}.");
            }
            require_once $path;
        }
    }

    /** @return array<string, mixed> */
    private function readData(Scenario $scenario): array
    {
        $readerClass = $scenario->namespace.'\\DataReader';
        $reader = new $readerClass();

        return $reader->read($this->wooCommerce, $this->tracezilla);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function applyRules(Scenario $scenario, array $data): array
    {
        $rulesClass = $scenario->namespace.'\\BusinessRules';
        $rules = new $rulesClass();

        return $rules->apply($data);
    }

    /**
     * Replace the planned changes in the result with what happened to each one.
     *
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    private function carryOutChanges(array $result, bool $execute, ?int $limit): array
    {
        if (! isset($result['changes'])) {
            return $result;
        }

        $outcomes = [];
        foreach ($result['changes'] as $index => $change) {
            $outcomes[] = $this->carryOutChange($change, $execute, $this->isWithinLimit($index, $limit));
        }

        $result['mode'] = 'dry_run';
        if ($execute) {
            $result['mode'] = 'execute';
        }
        $result['changes'] = $outcomes;

        return $result;
    }

    private function isWithinLimit(int $index, ?int $limit): bool
    {
        if ($limit === null) {
            return true;
        }

        return $index < $limit;
    }

    /**
     * @param array{action: string, key: string, data: array<string, mixed>} $change
     * @return array<string, mixed>
     */
    private function carryOutChange(array $change, bool $execute, bool $withinLimit): array
    {
        $outcome = [
            'action' => $change['action'],
            'key' => $change['key'],
            'data' => $change['data'],
        ];

        if (! $withinLimit) {
            $outcome['outcome'] = 'skipped_by_limit';

            return $outcome;
        }
        if (! $execute) {
            $outcome['outcome'] = 'planned';

            return $outcome;
        }

        try {
            $this->writer->write($change['action'], $change['data']);
        } catch (Throwable $exception) {
            // One failed change must not stop the others; it is reported instead.
            $outcome['outcome'] = 'failed';
            $outcome['error'] = $exception->getMessage();

            return $outcome;
        }
        $outcome['outcome'] = 'done';

        return $outcome;
    }
}
