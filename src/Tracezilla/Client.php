<?php

declare(strict_types=1);

namespace Tracezilla\WooCommerce\Tracezilla;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

final class Client
{
    private HttpClient $http;

    public function __construct(
        TracezillaConfiguration $configuration,
        private TokenProvider $tokens,
        ?HttpClient $http = null,
    ) {
        $this->http = $http ?? new HttpClient([
            'base_uri' => "{$configuration->baseUrl}/api/v1/{$configuration->teamSlug}/",
            'timeout' => $configuration->timeout,
            'connect_timeout' => $configuration->connectTimeout,
            'headers' => ['Accept' => 'application/json'],
        ]);
    }

    /** @return array<string, mixed> */
    public function get(string $path, array $query = []): array
    {
        return $this->send('GET', $path, ['query' => $query]);
    }

    /** @return array<string, mixed> */
    public function post(string $path, array $payload): array
    {
        return $this->send('POST', $path, ['json' => $payload]);
    }

    /** @return array<string, mixed> */
    public function put(string $path, array $payload): array
    {
        return $this->send('PUT', $path, ['json' => $payload]);
    }

    /** @return array<string, mixed> */
    public function delete(string $path): array
    {
        return $this->send('DELETE', $path, []);
    }

    /**
     * Follow links.next_page until the last page.
     *
     * @return list<array<string, mixed>>
     */
    public function getAll(string $path, array $query = []): array
    {
        $records = [];
        $visited = [];

        do {
            $payload = $this->get($path, $query);
            if (! is_array($payload['data'] ?? null)) {
                throw new RuntimeException("tracezilla response for {$path} is missing data.");
            }
            $records = array_merge($records, array_values($payload['data']));

            $next = $payload['links']['next_page'] ?? null;
            if (! is_string($next) || $next === '') {
                return $records;
            }
            parse_str((string) parse_url($next, PHP_URL_QUERY), $page);
            if ($page === []) {
                throw new RuntimeException("tracezilla returned an invalid next-page URL for {$path}.");
            }
            $query = array_merge($query, $page);
            $fingerprint = http_build_query($query);
            if (isset($visited[$fingerprint])) {
                throw new RuntimeException("tracezilla returned the same page of {$path} repeatedly.");
            }
            $visited[$fingerprint] = true;
        } while (true);
    }

    /** @return array<string, mixed> */
    private function send(string $method, string $path, array $options): array
    {
        $token = $this->tokens->accessToken();

        try {
            try {
                $response = $this->request($method, $path, $options, $token);
            } catch (RequestException $exception) {
                if ($exception->getResponse()?->getStatusCode() !== 401) {
                    throw $exception;
                }
                // A revoked or otherwise rejected token: renew it once and retry.
                $response = $this->request($method, $path, $options, $this->tokens->renewAfterRejection($token));
            }
        } catch (RequestException $exception) {
            // Validation errors are explained in the body; Guzzle's message truncates it.
            $body = $exception->getResponse()?->getBody()->__toString() ?? '';
            $status = $exception->getResponse()?->getStatusCode() ?? 0;
            throw new RuntimeException(
                "{$method} {$path} failed with HTTP {$status}: ".($body !== '' ? mb_substr($body, 0, 1000) : $exception->getMessage()),
                previous: $exception,
            );
        }

        $body = (string) $response->getBody();

        $decoded = $body === '' ? [] : json_decode($body, true, flags: JSON_THROW_ON_ERROR);

        // Some endpoints, such as DELETE, answer with a bare value.
        return is_array($decoded) ? $decoded : ['data' => $decoded];
    }

    private function request(string $method, string $path, array $options, string $token): ResponseInterface
    {
        $options['headers'] = ['Authorization' => "Bearer {$token}"];

        return $this->http->request($method, ltrim($path, '/'), $options);
    }
}
