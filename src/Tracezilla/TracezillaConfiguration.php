<?php

declare(strict_types=1);

namespace Tracezilla\WooCommerce\Tracezilla;

use RuntimeException;

/**
 * The tracezilla settings from .env.
 */
final readonly class TracezillaConfiguration
{
    public function __construct(
        public string $baseUrl,
        public string $teamSlug,
        public string $clientId,
        public string $clientSecret,
        public string $tokenFile,
        public float $timeout,
        public float $connectTimeout,
    ) {
    }

    public static function fromEnvironment(string $projectDirectory): self
    {
        return new self(
            rtrim(self::required('TRACEZILLA_BASE_URL'), '/'),
            self::required('TRACEZILLA_TEAM_SLUG'),
            self::required('TRACEZILLA_CLIENT_ID'),
            self::required('TRACEZILLA_CLIENT_SECRET'),
            self::tokenFile($projectDirectory),
            (float) self::optional('HTTP_TIMEOUT', '30'),
            (float) self::optional('HTTP_CONNECT_TIMEOUT', '10'),
        );
    }

    private static function required(string $name): string
    {
        $value = (string) getenv($name);
        if ($value === '') {
            throw new RuntimeException("Missing required environment variable: {$name}");
        }

        return $value;
    }

    private static function optional(string $name, string $default): string
    {
        $value = (string) getenv($name);
        if ($value === '') {
            return $default;
        }

        return $value;
    }

    /** The token file; a relative path starts at the project folder. */
    private static function tokenFile(string $projectDirectory): string
    {
        $tokenFile = self::optional('TRACEZILLA_TOKEN_FILE', 'var/tracezilla-token.json');
        if (str_starts_with($tokenFile, '/')) {
            return $tokenFile;
        }

        return rtrim($projectDirectory, '/').'/'.$tokenFile;
    }
}
