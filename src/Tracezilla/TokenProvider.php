<?php

declare(strict_types=1);

namespace Tracezilla\WooCommerce\Tracezilla;

use Closure;
use DateTimeImmutable;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use RuntimeException;

/**
 * Supplies a valid tracezilla access token using the OAuth Resource Owner
 * Password Credentials grant.
 *
 * The username and password are used once, by bin/tracezilla-login. After
 * that the stored refresh token renews the access token well before it
 * expires, so a regularly running integration never needs the password again.
 */
final readonly class TokenProvider
{
    public const RENEW_WITHIN_DAYS = 30;

    /** @var Closure(): DateTimeImmutable */
    private Closure $clock;

    public function __construct(
        private HttpClient $http,
        private TokenStore $store,
        private string $clientId,
        private string $clientSecret,
        ?Closure $clock = null,
    ) {
        $this->clock = $clock ?? static fn (): DateTimeImmutable => new DateTimeImmutable();
    }

    public static function fromConfiguration(TracezillaConfiguration $configuration): self
    {
        return new self(
            new HttpClient([
                'base_uri' => "{$configuration->baseUrl}/",
                'timeout' => $configuration->timeout,
                'connect_timeout' => $configuration->connectTimeout,
                'headers' => ['Accept' => 'application/json'],
            ]),
            new TokenStore($configuration->tokenFile),
            $configuration->clientId,
            $configuration->clientSecret,
        );
    }

    /** Exchange a username and password for tokens and store them. */
    public function login(string $username, string $password): OAuthTokens
    {
        $tokens = $this->requestTokens([
            'grant_type' => 'password',
            'username' => $username,
            'password' => $password,
        ]);
        $this->store->save($tokens);

        return $tokens;
    }

    /** An access token that is valid for at least RENEW_WITHIN_DAYS more days. */
    public function accessToken(): string
    {
        $tokens = $this->stored();
        if (! $tokens->expiresWithin(self::RENEW_WITHIN_DAYS, ($this->clock)())) {
            return $tokens->accessToken;
        }

        return $this->store->locked(function (): string {
            // Another run may have renewed the token while we waited for the lock.
            $tokens = $this->stored();
            if ($tokens->expiresWithin(self::RENEW_WITHIN_DAYS, ($this->clock)())) {
                $tokens = $this->renew($tokens);
            }

            return $tokens->accessToken;
        });
    }

    /**
     * Renew the token after tracezilla rejected it, unless another run already
     * replaced the rejected token.
     */
    public function renewAfterRejection(string $rejectedAccessToken): string
    {
        return $this->store->locked(function () use ($rejectedAccessToken): string {
            $tokens = $this->stored();

            return $tokens->accessToken !== $rejectedAccessToken
                ? $tokens->accessToken
                : $this->renew($tokens)->accessToken;
        });
    }

    public function expiresAt(): DateTimeImmutable
    {
        return $this->stored()->expiresAt;
    }

    private function renew(OAuthTokens $tokens): OAuthTokens
    {
        $renewed = $this->requestTokens([
            'grant_type' => 'refresh_token',
            'refresh_token' => $tokens->refreshToken,
        ]);
        // The refresh token can only be used once, so store the new one at once.
        $this->store->save($renewed);

        return $renewed;
    }

    private function stored(): OAuthTokens
    {
        return $this->store->load()
            ?? throw new RuntimeException('No tracezilla token is stored. Run bin/tracezilla-login first.');
    }

    private function requestTokens(array $grant): OAuthTokens
    {
        try {
            $response = $this->http->post('oauth/token', ['form_params' => $grant + [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ]]);
        } catch (RequestException $exception) {
            $status = $exception->getResponse()?->getStatusCode() ?? 0;
            $body = $exception->getResponse()?->getBody()->__toString() ?? '';
            $hint = $grant['grant_type'] === 'refresh_token'
                ? ' The refresh token was rejected; run bin/tracezilla-login again.'
                : ' Check TRACEZILLA_CLIENT_ID, TRACEZILLA_CLIENT_SECRET, and the username and password.';

            throw new RuntimeException("tracezilla token request failed with HTTP {$status}: ".mb_substr($body, 0, 500).$hint, previous: $exception);
        } catch (GuzzleException $exception) {
            throw new RuntimeException('tracezilla token request failed: '.$exception->getMessage(), previous: $exception);
        }

        return OAuthTokens::fromTokenResponse(
            json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR),
            ($this->clock)(),
        );
    }
}
