<?php

declare(strict_types=1);

namespace Tracezilla\WooCommerce\Lock;

use InvalidArgumentException;
use RuntimeException;

final class FileIntegrationLock
{
    private string $lockDirectory;

    public function __construct(string $runtimeDirectory)
    {
        $this->lockDirectory = $runtimeDirectory.'/locks';
        if (! is_dir($this->lockDirectory) && ! mkdir($this->lockDirectory, 0700, true) && ! is_dir($this->lockDirectory)) {
            throw new RuntimeException("Could not create lock directory [{$this->lockDirectory}].");
        }
        chmod($this->lockDirectory, 0700);
    }

    public function acquire(string $name, array $metadata = []): ?AcquiredIntegrationLock
    {
        $path = $this->path($name);
        $handle = fopen($path, 'c+');
        if ($handle === false) {
            throw new RuntimeException("Could not open integration lock [{$path}].");
        }
        chmod($path, 0600);

        if (! flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            return null;
        }

        $payload = [
            ...$metadata,
            'pid' => getmypid(),
            'acquired_at' => gmdate(DATE_ATOM),
        ];
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
        ftruncate($handle, 0);
        rewind($handle);
        if (fwrite($handle, $json) !== strlen($json) || ! fflush($handle)) {
            flock($handle, LOCK_UN);
            fclose($handle);
            throw new RuntimeException("Could not write integration-lock metadata [{$path}].");
        }

        return new AcquiredIntegrationLock($handle);
    }

    public function metadata(string $name): array
    {
        $path = $this->path($name);
        if (! is_file($path)) {
            return [];
        }
        $contents = file_get_contents($path);
        if ($contents === false) {
            return [];
        }
        $data = json_decode($contents, true);

        return is_array($data) ? $data : [];
    }

    private function path(string $name): string
    {
        if (! preg_match('/^[a-z0-9][a-z0-9._-]*$/', $name)) {
            throw new InvalidArgumentException('Lock name contains unsafe characters.');
        }

        return $this->lockDirectory.'/'.$name.'.lock';
    }
}

