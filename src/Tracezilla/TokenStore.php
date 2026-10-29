<?php

declare(strict_types=1);

namespace Tracezilla\WooCommerce\Tracezilla;

use RuntimeException;

/**
 * Keeps the OAuth tokens in a JSON file that only the owner can read.
 */
final readonly class TokenStore
{
    public function __construct(private string $path)
    {
    }

    public function path(): string
    {
        return $this->path;
    }

    public function load(): ?OAuthTokens
    {
        if (! is_file($this->path)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($this->path), true);
        if (! is_array($data)) {
            throw new RuntimeException("The tracezilla token file {$this->path} is not valid JSON. Run bin/bifrost-connect tracezilla:login.");
        }

        return OAuthTokens::fromArray($data);
    }

    public function save(OAuthTokens $tokens): void
    {
        $this->ensureDirectory();
        $temporary = $this->path.'.tmp';

        if (file_put_contents($temporary, json_encode($tokens->toArray(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)) === false) {
            throw new RuntimeException("Unable to write the tracezilla token file {$this->path}.");
        }
        chmod($temporary, 0600);
        // Rename is atomic, so a crash never leaves a half-written token file.
        rename($temporary, $this->path);
    }

    /**
     * Run $callback while holding an exclusive lock, so concurrent runs never
     * use the same refresh token twice.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public function locked(callable $callback): mixed
    {
        $this->ensureDirectory();
        $handle = fopen($this->path.'.lock', 'c');
        if ($handle === false || ! flock($handle, LOCK_EX)) {
            throw new RuntimeException("Unable to lock the tracezilla token file {$this->path}.");
        }

        try {
            return $callback();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function ensureDirectory(): void
    {
        $directory = dirname($this->path);
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException("Unable to create the directory {$directory}.");
        }
    }
}
