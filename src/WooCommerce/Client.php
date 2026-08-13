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
        $response = $this->http->get('system_status');

        return json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
    }
}

