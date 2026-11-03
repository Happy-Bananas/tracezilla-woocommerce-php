<?php

declare(strict_types=1);

namespace Tests\Unit\Connection;

use RuntimeException;
use Tracezilla\WooCommerce\Connection\TracezillaConnection;

/**
 * A tracezilla team for tests: it gives a prepared answer, or fails with a
 * prepared message, and never contacts tracezilla.
 */
final class FakeTracezillaConnection implements TracezillaConnection
{
    /** @param array<string, mixed> $answer */
    private function __construct(private array $answer, private ?string $error)
    {
    }

    /** @param array<string, mixed> $answer */
    public static function answering(array $answer): self
    {
        return new self($answer, null);
    }

    public static function failingWith(string $error): self
    {
        return new self([], $error);
    }

    public function firstSku(): array
    {
        if ($this->error !== null) {
            throw new RuntimeException($this->error);
        }

        return $this->answer;
    }
}
