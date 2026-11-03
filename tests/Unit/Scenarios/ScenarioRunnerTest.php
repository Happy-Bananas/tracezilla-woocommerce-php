<?php

declare(strict_types=1);

namespace Tests\Unit\Scenarios;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tracezilla\WooCommerce\Scenarios\ScenarioGenerator;
use Tracezilla\WooCommerce\Scenarios\ScenarioRunner;

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

    /**
     * @param list<mixed> $wooCommerce
     * @param list<mixed> $tracezilla
     */
    private function runner(array $wooCommerce, array $tracezilla): ScenarioRunner
    {
        return new ScenarioRunner(
            $this->project,
            FakeApis::wooCommerce($wooCommerce),
            FakeApis::tracezilla($tracezilla, $this->project.'/var'),
        );
    }
}
