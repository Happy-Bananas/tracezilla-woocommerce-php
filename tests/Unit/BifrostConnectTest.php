<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tracezilla\WooCommerce\Lock\FileIntegrationLock;

final class BifrostConnectTest extends TestCase
{
    private string $runtimeDirectory;

    protected function setUp(): void
    {
        $this->runtimeDirectory = sys_get_temp_dir().'/tracezilla-entry-'.bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->runtimeDirectory.'/locks/*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->runtimeDirectory.'/locks')) {
            rmdir($this->runtimeDirectory.'/locks');
        }
        if (is_dir($this->runtimeDirectory)) {
            rmdir($this->runtimeDirectory);
        }
    }

    public function test_help_lists_the_commands(): void
    {
        [$exitCode, $output] = $this->bifrostConnect('help');

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('connection:check', $output);
        self::assertStringContainsString('tracezilla:seed', $output);
    }

    public function test_an_unknown_command_is_rejected(): void
    {
        [$exitCode, , $errors] = $this->bifrostConnect('orders:everything');

        self::assertSame(2, $exitCode);
        self::assertStringContainsString('Unknown command: orders:everything', $errors);
    }

    public function test_a_locked_command_is_skipped_while_another_run_holds_the_lock(): void
    {
        $lock = (new FileIntegrationLock($this->runtimeDirectory))->acquire('integration', ['command' => 'tracezilla:seed']);
        self::assertNotNull($lock);

        [$exitCode, $output, $errors] = $this->bifrostConnect('tracezilla:seed');
        $lock->release();

        self::assertSame(75, $exitCode);
        self::assertSame('', $output);
        self::assertStringContainsString('Another integration command is already running.', $errors);
        self::assertStringContainsString('Command: tracezilla:seed', $errors);
    }

    /** @return array{int, string, string} */
    private function bifrostConnect(string ...$arguments): array
    {
        $command = array_merge([PHP_BINARY, dirname(__DIR__, 2).'/bin/bifrost-connect'], $arguments);
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
