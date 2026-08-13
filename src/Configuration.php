<?php

declare(strict_types=1);

namespace Tracezilla\WooCommerce;

final readonly class Configuration
{
    public function __construct(
        public string $wooCommerceUrl,
        public string $consumerKey,
        public string $consumerSecret,
        public float $timeout,
        public float $connectTimeout,
    ) {
    }

    public static function fromEnvironment(): self
    {
        $url = rtrim((string) getenv('WOOCOMMERCE_URL'), '/');
        $key = (string) getenv('WOOCOMMERCE_CONSUMER_KEY');
        $secret = (string) getenv('WOOCOMMERCE_CONSUMER_SECRET');

        foreach (['WOOCOMMERCE_URL' => $url, 'WOOCOMMERCE_CONSUMER_KEY' => $key, 'WOOCOMMERCE_CONSUMER_SECRET' => $secret] as $name => $value) {
            if ($value === '') {
                throw new \RuntimeException("Missing required environment variable: {$name}");
            }
        }

        return new self(
            $url,
            $key,
            $secret,
            (float) (getenv('HTTP_TIMEOUT') ?: 30),
            (float) (getenv('HTTP_CONNECT_TIMEOUT') ?: 10),
        );
    }
}

