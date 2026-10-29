<?php

declare(strict_types=1);

namespace Tests\Unit\Lock;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tracezilla\WooCommerce\Lock\FileIntegrationLock;

final class FileIntegrationLockTest extends TestCase
{
    private string $runtimeDirectory;

    protected function setUp(): void
    {
        $this->runtimeDirectory = sys_get_temp_dir().'/tracezilla-lock-'.bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        if (! is_dir($this->runtimeDirectory)) {
            return;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->runtimeDirectory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->runtimeDirectory);
    }

    public function test_only_one_process_can_hold_the_global_lock(): void
    {
        $locks = new FileIntegrationLock($this->runtimeDirectory);
        $first = $locks->acquire('integration', ['command' => 'tracezilla:seed']);

        self::assertNotNull($first);
        self::assertNull((new FileIntegrationLock($this->runtimeDirectory))->acquire(
            'integration',
            ['command' => 'orders:import'],
        ));
        self::assertSame('tracezilla:seed', $locks->metadata('integration')['command'] ?? null);

        $first->release();
        $second = $locks->acquire('integration', ['command' => 'orders:import']);
        self::assertNotNull($second);
        $second?->release();
    }
}

