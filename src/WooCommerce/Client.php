<?php

declare(strict_types=1);

namespace Tracezilla\WooCommerce\WooCommerce;

use GuzzleHttp\Client as HttpClient;
use Tracezilla\WooCommerce\Configuration;

final class Client
{
    private HttpClient $http;

    public function __construct(Configuration $configuration)
    {
        $this->http = new HttpClient([
            'base_uri' => $configuration->wooCommerceUrl.'/wp-json/wc/v3/',
            'auth' => [$configuration->consumerKey, $configuration->consumerSecret],
            'timeout' => $configuration->timeout,
            'connect_timeout' => $configuration->connectTimeout,
            'headers' => ['Accept' => 'application/json'],
        ]);
    }

    /** @return array<string, mixed> */
    public function systemStatus(): array
    {
        return $this->get('system_status');
    }

    /**
     * @param array<string, scalar> $query
     * @return array<mixed>
     */
    public function get(string $path, array $query = []): array
    {
        $response = $this->http->get(ltrim($path, '/'), ['query' => $query]);

        return json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
    }
}

