<?php

declare(strict_types=1);

namespace Tracezilla\WooCommerce;

use RuntimeException;

/**
 * The WooCommerce settings from .env.
 */
final readonly class Configuration
{
    /** Hosts of a test store on this computer, which may use plain http://. */
    private const LOCAL_HOSTS = ['localhost', '127.0.0.1', 'host.docker.internal'];

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
            self::storeUrl(),
            self::required('WOOCOMMERCE_CONSUMER_KEY'),
            self::required('WOOCOMMERCE_CONSUMER_SECRET'),
            (float) self::optional('HTTP_TIMEOUT', '30'),
            (float) self::optional('HTTP_CONNECT_TIMEOUT', '10'),
        );
    }

    /**
     * The store's address without a trailing slash. A real store must use
     * https://, because the consumer key and secret go with every request.
     */
    private static function storeUrl(): string
    {
        $url = rtrim(self::required('WOOCOMMERCE_URL'), '/');
        if (self::isPlainHttpToAnotherComputer($url)) {
            throw new RuntimeException("WOOCOMMERCE_URL must start with https://: {$url}. Plain http:// would send the API keys unencrypted; it is allowed only for a test store on this computer.");
        }

        return $url;
    }

    private static function isPlainHttpToAnotherComputer(string $url): bool
    {
        if (! str_starts_with(strtolower($url), 'http://')) {
            return false;
        }
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return ! in_array($host, self::LOCAL_HOSTS, true);
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
