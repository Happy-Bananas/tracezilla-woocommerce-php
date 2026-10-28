<?php

declare(strict_types=1);

namespace Tracezilla\WooCommerce\Tracezilla;

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
        $baseUrl = rtrim((string) getenv('TRACEZILLA_BASE_URL'), '/');
        $teamSlug = (string) getenv('TRACEZILLA_TEAM_SLUG');
        $clientId = (string) getenv('TRACEZILLA_CLIENT_ID');
        $clientSecret = (string) getenv('TRACEZILLA_CLIENT_SECRET');

        foreach ([
            'TRACEZILLA_BASE_URL' => $baseUrl,
            'TRACEZILLA_TEAM_SLUG' => $teamSlug,
            'TRACEZILLA_CLIENT_ID' => $clientId,
            'TRACEZILLA_CLIENT_SECRET' => $clientSecret,
        ] as $name => $value) {
            if ($value === '') {
                throw new \RuntimeException("Missing required environment variable: {$name}");
            }
        }

        $tokenFile = (string) (getenv('TRACEZILLA_TOKEN_FILE') ?: 'var/tracezilla-token.json');
        if (! str_starts_with($tokenFile, '/')) {
            $tokenFile = rtrim($projectDirectory, '/').'/'.$tokenFile;
        }

        return new self(
            $baseUrl,
            $teamSlug,
            $clientId,
            $clientSecret,
            $tokenFile,
            (float) (getenv('HTTP_TIMEOUT') ?: 30),
            (float) (getenv('HTTP_CONNECT_TIMEOUT') ?: 10),
        );
    }
}
