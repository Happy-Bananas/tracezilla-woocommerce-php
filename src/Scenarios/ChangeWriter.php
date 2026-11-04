<?php

declare(strict_types=1);

namespace Tracezilla\WooCommerce\Scenarios;

/**
 * Carries out one change that a scenario's BusinessRules planned. The live
 * writer calls the APIs; the tests use a fake that only records the changes.
 */
interface ChangeWriter
{
    /**
     * @param string $action For example "create_tracezilla_sku"
     * @param array<string, mixed> $data What the action needs, with API field names
     */
    public function write(string $action, array $data): void;
}
