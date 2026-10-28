<?php

declare(strict_types=1);

namespace Tracezilla\WooCommerce\Seed;

final class SeedResult
{
    public const WOULD_CREATE = 'would_create';
    public const CREATED = 'created';
    public const EXISTS = 'exists';
    public const INVALID = 'invalid';
    public const FAILED = 'failed';

    /** @var list<array{type: string, key: string, action: string, detail: string}> */
    private array $items = [];

    public function add(string $type, string $key, string $action, string $detail = ''): void
    {
        $this->items[] = ['type' => $type, 'key' => $key, 'action' => $action, 'detail' => $detail];
    }

    /** @return list<array{type: string, key: string, action: string, detail: string}> */
    public function items(): array
    {
        return $this->items;
    }

    /** @return array<string, int> */
    public function counts(): array
    {
        return array_count_values(array_column($this->items, 'action'));
    }

    public function isSuccessful(): bool
    {
        $counts = $this->counts();

        return ($counts[self::FAILED] ?? 0) === 0 && ($counts[self::INVALID] ?? 0) === 0;
    }
}
