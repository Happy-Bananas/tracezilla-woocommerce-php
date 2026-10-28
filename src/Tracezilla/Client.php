<?php

declare(strict_types=1);

namespace Tracezilla\WooCommerce\Tracezilla;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\RequestException;
use RuntimeException;

final class Client
{
    private HttpClient $http;

    public function __construct(TracezillaConfiguration $configuration)
    {
        $this->http = new HttpClient([
            'base_uri' => "{$configuration->baseUrl}/api/v1/{$configuration->teamSlug}/",
            'timeout' => $configuration->timeout,
            'connect_timeout' => $configuration->connectTimeout,
            'headers' => ['Accept' => 'application/json', 'Authorization' => "Bearer {$configuration->apiKey}"],
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
        try {
            $response = $this->http->request($method, ltrim($path, '/'), $options);
        } catch (RequestException $exception) {
            // Validation errors are explained in the body; Guzzle's message truncates it.
            $body = $exception->getResponse()?->getBody()->__toString() ?? '';
            $status = $exception->getResponse()?->getStatusCode() ?? 0;
            throw new RuntimeException(
                "{$method} {$path} failed with HTTP {$status}: ".($body !== '' ? mb_substr($body, 0, 1000) : $exception->getMessage()),
                previous: $exception,
            );
        }

        return json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
    }
}
