<?php

declare(strict_types=1);

namespace Tests\Unit\Scenarios;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tracezilla\WooCommerce\Scenarios\ScenarioGenerator;
use Tracezilla\WooCommerce\Scenarios\ScenarioRunner;
use Tracezilla\WooCommerce\WriteMode;

final class ScenarioRunnerTest extends TestCase
{
    private string $project;

    protected function setUp(): void
    {
        $this->project = sys_get_temp_dir().'/tracezilla-scenarios-'.bin2hex(random_bytes(6));
        mkdir($this->project);
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->project));
    }

    // Each test uses a scenario name no other test uses, because PHP cannot
    // load a class twice.

    public function test_it_runs_the_hello_world_template(): void
    {
        (new ScenarioGenerator($this->project))->generate('runner-hello-world');

        $result = $this->runner(
            [['environment' => ['site_url' => 'https://shop.test', 'version' => '11.2.1']]],
            [['data' => []]],
        )->run('runner-hello-world');

        self::assertSame('ok', $result['status']);
        self::assertSame('https://shop.test', $result['woocommerce']['store_url']);
    }

    public function test_it_runs_the_compare_catalogs_template(): void
    {
        (new ScenarioGenerator($this->project))->generate('runner-compare', 'compare-catalogs');

        $result = $this->runner(
            [[['id' => 1, 'type' => 'simple', 'sku' => 'TZ-FRUIT-001', 'name' => 'Bananas'], ['id' => 2, 'type' => 'simple', 'sku' => 'TZ-FRUIT-004', 'name' => 'Kiwi']]],
            [['data' => [
                ['id' => 'a', 'sku_code' => 'TZ-FRUIT-001', 'global_name' => 'Bananas 1 kg', 'active' => true],
                ['id' => 'b', 'sku_code' => 'TZ-RAW-001', 'global_name' => 'Cocoa', 'active' => true],
            ], 'links' => ['next_page' => null]]],
        )->run('runner-compare');

        self::assertSame(['matched' => 1, 'only_in_woocommerce' => 1, 'only_in_tracezilla' => 1, 'woocommerce_without_sku' => 0, 'duplicate_skus' => 0, 'inactive_tracezilla_skus_skipped' => 0], $result['summary']);
    }

    public function test_it_runs_the_missing_in_woocommerce_template(): void
    {
        (new ScenarioGenerator($this->project))->generate('runner-missing', 'missing-in-woocommerce');

        $result = $this->runner(
            [[['id' => 1, 'type' => 'simple', 'sku' => 'TZ-FRUIT-001', 'name' => 'Bananas']]],
            [['data' => [
                ['id' => 'a', 'sku_code' => 'TZ-FRUIT-001', 'global_name' => 'Bananas', 'active' => true, 'category' => 'finished_good', 'allow_sales' => true],
                ['id' => 'b', 'sku_code' => 'TZ-JAM-001', 'global_name' => 'Jam', 'active' => true, 'category' => 'finished_good', 'allow_sales' => true],
                ['id' => 'c', 'sku_code' => 'TZ-RAW-001', 'global_name' => 'Cocoa', 'active' => true, 'category' => 'raw_material', 'allow_sales' => true],
            ], 'links' => ['next_page' => null]]],
        )->run('runner-missing');

        self::assertSame(['decide_with_customer' => 1, 'not_for_the_shop' => 1, 'inactive_tracezilla_skus_skipped' => 0], $result['summary']);
        self::assertSame('TZ-JAM-001', $result['decide_with_customer'][0]['sku']);
    }

    public function test_it_explains_how_to_create_a_missing_scenario(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Create it with scenario:create missing-scenario');

        $this->runner([], [])->run('missing-scenario');
    }

    public function test_a_dry_run_only_reports_the_planned_changes(): void
    {
        $writer = new FakeChangeWriter();
        (new ScenarioGenerator($this->project))->generate('runner-dry-run', 'create-tracezilla-skus');

        $result = $this->runner($this->shopWithTwoNewProducts(), $this->emptyTracezilla(), $writer)->run('runner-dry-run', WriteMode::dryRun());

        self::assertSame('dry_run', $result['mode']);
        self::assertSame(['planned', 'planned'], array_column($result['changes'], 'outcome'));
        self::assertSame([], $writer->written);
    }

    public function test_an_executed_run_writes_up_to_the_limit(): void
    {
        $writer = new FakeChangeWriter();
        (new ScenarioGenerator($this->project))->generate('runner-execute', 'create-tracezilla-skus');

        $result = $this->runner($this->shopWithTwoNewProducts(), $this->emptyTracezilla(), $writer)->run('runner-execute', WriteMode::execute(), limit: 1);

        self::assertSame('execute', $result['mode']);
        self::assertSame(['done', 'skipped_by_limit'], array_column($result['changes'], 'outcome'));
        self::assertSame('TZ-FRUIT-004', $writer->written[0]['data']['sku_code']);
    }

    public function test_one_failed_change_does_not_stop_the_others(): void
    {
        $writer = new FakeChangeWriter(failForKey: 'TZ-FRUIT-004');
        (new ScenarioGenerator($this->project))->generate('runner-failure', 'create-tracezilla-skus');

        $result = $this->runner($this->shopWithTwoNewProducts(), $this->emptyTracezilla(), $writer)->run('runner-failure', WriteMode::execute());

        self::assertSame(['failed', 'done'], array_column($result['changes'], 'outcome'));
        self::assertSame('tracezilla rejected TZ-FRUIT-004.', $result['changes'][0]['error']);
    }

    public function test_a_scenario_that_plans_changes_needs_a_mode(): void
    {
        $writer = new FakeChangeWriter();
        (new ScenarioGenerator($this->project))->generate('runner-no-mode', 'create-tracezilla-skus');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('This scenario plans changes. Choose a mode');

        try {
            $this->runner($this->shopWithTwoNewProducts(), $this->emptyTracezilla(), $writer)->run('runner-no-mode');
        } finally {
            self::assertSame([], $writer->written);
        }
    }

    /** @return list<mixed> The fake WooCommerce answers: the catalog, then the weight unit */
    private function shopWithTwoNewProducts(): array
    {
        return [
            [
                ['id' => 1, 'type' => 'simple', 'sku' => 'TZ-FRUIT-004', 'name' => 'Kiwi', 'weight' => '0.1'],
                ['id' => 2, 'type' => 'simple', 'sku' => 'TZ-FRUIT-005', 'name' => 'Lime', 'weight' => '0.1'],
            ],
            ['id' => 'woocommerce_weight_unit', 'value' => 'kg'],
        ];
    }

    /** @return list<mixed> */
    private function emptyTracezilla(): array
    {
        return [['data' => [], 'links' => ['next_page' => null]]];
    }

    /**
     * @param list<mixed> $wooCommerce
     * @param list<mixed> $tracezilla
     */
    private function runner(array $wooCommerce, array $tracezilla, ?FakeChangeWriter $writer = null): ScenarioRunner
    {
        if ($writer === null) {
            $writer = new FakeChangeWriter();
        }

        return new ScenarioRunner(
            $this->project,
            FakeApis::wooCommerce($wooCommerce),
            FakeApis::tracezilla($tracezilla, $this->project.'/var'),
            $writer,
        );
    }
}
