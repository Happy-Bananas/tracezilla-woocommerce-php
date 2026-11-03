<?php

declare(strict_types=1);

namespace Tracezilla\WooCommerce;

use RuntimeException;

/**
 * The WooCommerce settings from .env.
 */
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
        return new self(
            rtrim(self::required('WOOCOMMERCE_URL'), '/'),
            self::required('WOOCOMMERCE_CONSUMER_KEY'),
            self::required('WOOCOMMERCE_CONSUMER_SECRET'),
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
}
