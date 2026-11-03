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

    public function test_it_runs_the_generated_hello_world_with_both_responses(): void
    {
        // A name no other test uses, because PHP cannot load a class twice.
        (new ScenarioGenerator($this->project))->generate('runner-hello-world');
        $requests = [];

        $result = (new ScenarioRunner(
            $this->project,
            static function (string $path, array $query) use (&$requests): array {
                $requests[] = ['woocommerce', $path, $query];

                return ['environment' => ['site_url' => 'https://shop.example.test', 'version' => '11.2.1']];
            },
            static function (string $path, array $query) use (&$requests): array {
                $requests[] = ['tracezilla', $path, $query];

                return ['data' => []];
            },
        ))->run('runner-hello-world');

        self::assertSame([
            ['woocommerce', 'system_status', []],
            ['tracezilla', '/skus', ['perPage' => 1]],
        ], $requests);
        self::assertSame('ok', $result['status']);
        self::assertSame('https://shop.example.test', $result['woocommerce']['store_url']);
    }

    public function test_it_explains_how_to_create_a_missing_scenario(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Create it with scenario:create missing-scenario');

        (new ScenarioRunner(
            $this->project,
            static fn (): array => [],
            static fn (): array => [],
        ))->run('missing-scenario');
    }
}
