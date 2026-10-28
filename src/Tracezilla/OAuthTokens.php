<?php

declare(strict_types=1);

namespace Tracezilla\WooCommerce\Tracezilla;

use DateTimeImmutable;
use RuntimeException;

final readonly class OAuthTokens
{
    public function __construct(
        public string $accessToken,
        public string $refreshToken,
        public DateTimeImmutable $expiresAt,
    ) {
    }

    /** Build tokens from a /oauth/token response. */
    public static function fromTokenResponse(array $response, DateTimeImmutable $now): self
    {
        $access = $response['access_token'] ?? null;
        $refresh = $response['refresh_token'] ?? null;
        $expiresIn = $response['expires_in'] ?? null;

        if (! is_string($access) || $access === '' || ! is_string($refresh) || $refresh === '' || ! is_int($expiresIn)) {
            throw new RuntimeException('tracezilla token response is missing access_token, refresh_token, or expires_in.');
        }

        return new self($access, $refresh, $now->modify("+{$expiresIn} seconds"));
    }

    public static function fromArray(array $data): self
    {
        if (! is_string($data['access_token'] ?? null) || ! is_string($data['refresh_token'] ?? null) || ! is_string($data['expires_at'] ?? null)) {
            throw new RuntimeException('The stored tracezilla token is incomplete. Run bin/tracezilla-login.');
        }

        return new self($data['access_token'], $data['refresh_token'], new DateTimeImmutable($data['expires_at']));
    }

    /** @return array{access_token: string, refresh_token: string, expires_at: string} */
    public function toArray(): array
    {
        return [
            'access_token' => $this->accessToken,
            'refresh_token' => $this->refreshToken,
            'expires_at' => $this->expiresAt->format(DATE_ATOM),
        ];
    }

    public function expiresWithin(int $days, DateTimeImmutable $now): bool
    {
        return $this->expiresAt <= $now->modify("+{$days} days");
    }
}
