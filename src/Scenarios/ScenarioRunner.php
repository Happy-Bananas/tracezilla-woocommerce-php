<?php

declare(strict_types=1);

namespace Tracezilla\WooCommerce\Scenarios;

use RuntimeException;
use Throwable;
use Tracezilla\WooCommerce\WriteMode;

/**
 * Runs a scenario: its DataReader reads what the rules need, its BusinessRules
 * decide the result, and the changes the rules planned are carried out.
 *
 * A scenario that plans changes needs a mode: a dry run reports each change as
 * "planned", an executed run writes them. A read-only scenario needs no mode.
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
     * @param WriteMode|null $mode Dry run or execute; null when no mode was chosen
     * @param int|null $limit Write at most this many changes; null means all
     * @return array<string, mixed>
     */
    public function run(string $slug, ?WriteMode $mode = null, ?int $limit = null): array
    {
        if ($mode === null) {
            $mode = WriteMode::notChosen();
        }

        $scenario = new Scenario($this->projectDirectory, $slug);
        $this->loadScenarioFiles($scenario);

        $data = $this->readData($scenario);
        $result = $this->applyRules($scenario, $data);

        return $this->carryOutChanges($result, $mode, $limit);
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
    private function carryOutChanges(array $result, WriteMode $mode, ?int $limit): array
    {
        if (! isset($result['changes'])) {
            return $result;
        }
        if (! $mode->isChosen()) {
            throw new RuntimeException('This scenario plans changes. '.WriteMode::CHOOSE_A_MODE.' Nothing was written.');
        }
        $execute = $mode->writes();

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
