<?php

declare(strict_types=1);

namespace Tracezilla\WooCommerce;

/**
 * Where the integration keeps its runtime files (locks, retries, tokens):
 * TRACEZILLA_RUNTIME_DIR, or var/ in the project folder.
 */
final class RuntimeDirectory
{
    public static function resolve(string $projectDirectory): string
    {
        $configured = trim((string) getenv('TRACEZILLA_RUNTIME_DIR'));
        if ($configured === '') {
            return rtrim($projectDirectory, '/').'/var';
        }
        if (str_starts_with($configured, '/')) {
            return rtrim($configured, '/');
        }

        return rtrim($projectDirectory, '/').'/'.trim($configured, '/');
    }
}
