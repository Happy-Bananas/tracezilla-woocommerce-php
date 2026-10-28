<?php

declare(strict_types=1);

namespace Tracezilla\WooCommerce\Tracezilla;

final readonly class TracezillaConfiguration
{
    public function __construct(
        public string $baseUrl,
        public string $teamSlug,
        public string $apiKey,
        public float $timeout,
        public float $connectTimeout,
    ) {
    }

    public static function fromEnvironment(): self
    {
        $baseUrl = rtrim((string) getenv('TRACEZILLA_BASE_URL'), '/');
        $teamSlug = (string) getenv('TRACEZILLA_TEAM_SLUG');
        $apiKey = (string) getenv('TRACEZILLA_API_KEY');

        foreach (['TRACEZILLA_BASE_URL' => $baseUrl, 'TRACEZILLA_TEAM_SLUG' => $teamSlug, 'TRACEZILLA_API_KEY' => $apiKey] as $name => $value) {
            if ($value === '') {
                throw new \RuntimeException("Missing required environment variable: {$name}");
            }
        }

        return new self(
            $baseUrl,
            $teamSlug,
            $apiKey,
            (float) (getenv('HTTP_TIMEOUT') ?: 30),
            (float) (getenv('HTTP_CONNECT_TIMEOUT') ?: 10),
        );
    }
}
