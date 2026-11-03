<?php

declare(strict_types=1);

namespace Tests\Unit\Scenarios;

use RuntimeException;
use Tracezilla\WooCommerce\Scenarios\ChangeWriter;

/**
 * A change writer for tests: it remembers every change it was asked to write
 * and never contacts an API. It can be told to fail for one key.
 */
final class FakeChangeWriter implements ChangeWriter
{
    /** @var list<array{action: string, data: array<string, mixed>}> */
    public array $written = [];

    public function __construct(private ?string $failForKey = null)
    {
    }

    public function write(string $action, array $data): void
    {
        if ($this->failForKey !== null && $data['sku_code'] === $this->failForKey) {
            throw new RuntimeException("tracezilla rejected {$this->failForKey}.");
        }
        $this->written[] = ['action' => $action, 'data' => $data];
    }
}
