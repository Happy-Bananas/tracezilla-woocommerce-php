<?php

declare(strict_types=1);

namespace Tests\Unit;

use Bifrost\Core\Lock\FileIntegrationLock;
use PHPUnit\Framework\TestCase;

final class BifrostConnectTest extends TestCase
{
    private string $runtimeDirectory;

    protected function setUp(): void
    {
        $this->runtimeDirectory = sys_get_temp_dir().'/tracezilla-entry-'.bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        // Removes the vendor link in the test project, not the real vendor folder.
        exec('rm -rf '.escapeshellarg($this->runtimeDirectory));
    }

    public function test_help_lists_the_commands(): void
    {
        [$exitCode, $output] = $this->bifrostConnect('help');

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('connection:check', $output);
        self::assertStringContainsString('scenario:run', $output);
    }

    public function test_a_command_left_out_of_the_project_is_not_offered(): void
    {
        $project = $this->runtimeDirectory.'/project-without-seed';
        mkdir($project.'/bin', 0777, true);
        copy(dirname(__DIR__, 2).'/bin/bifrost-connect', $project.'/bin/bifrost-connect');
        copy(dirname(__DIR__, 2).'/bin/check-connection', $project.'/bin/check-connection');
        symlink(dirname(__DIR__, 2).'/vendor', $project.'/vendor');

        [, $help] = $this->bifrostConnectIn($project, 'help');
        [$exitCode, , $errors] = $this->bifrostConnectIn($project, 'tracezilla:seed', '--dry-run');

        self::assertStringContainsString('connection:check', $help);
        self::assertStringNotContainsString('tracezilla:seed', $help);
        self::assertSame(2, $exitCode);
        self::assertStringContainsString('Unknown command: tracezilla:seed', $errors);
    }

    public function test_an_unknown_command_is_rejected(): void
    {
        [$exitCode, , $errors] = $this->bifrostConnect('orders:everything');

        self::assertSame(2, $exitCode);
        self::assertStringContainsString('Unknown command: orders:everything', $errors);
    }

    public function test_a_locked_command_is_skipped_while_another_run_holds_the_lock(): void
    {
        $lock = (new FileIntegrationLock($this->runtimeDirectory))->acquire('integration', ['command' => 'scenario:run']);
        self::assertNotNull($lock);

        [$exitCode, $output, $errors] = $this->bifrostConnect('scenario:run', 'compare');
        $lock->release();

        self::assertSame(75, $exitCode);
        self::assertSame('', $output);
        self::assertStringContainsString('Another integration command is already running.', $errors);
        self::assertStringContainsString('Command: scenario:run', $errors);
    }

    /** @return array{int, string, string} */
    private function bifrostConnect(string ...$arguments): array
    {
        return $this->bifrostConnectIn(dirname(__DIR__, 2), ...$arguments);
    }

    /** @return array{int, string, string} */
    private function bifrostConnectIn(string $project, string ...$arguments): array
    {
        $command = array_merge([PHP_BINARY, $project.'/bin/bifrost-connect'], $arguments);
        $environment = getenv();
        $environment['TRACEZILLA_RUNTIME_DIR'] = $this->runtimeDirectory;
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $environment);
        self::assertIsResource($process);
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), (string) $output, (string) $errors];
    }
}
