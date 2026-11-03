<?php

declare(strict_types=1);

namespace Tests\Unit\Scenarios;

use DateTimeImmutable;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Tracezilla\WooCommerce\Configuration;
use Tracezilla\WooCommerce\Scenarios\TracezillaReader;
use Tracezilla\WooCommerce\Scenarios\WooCommerceReader;
use Tracezilla\WooCommerce\Tracezilla\Client as TracezillaClient;
use Tracezilla\WooCommerce\Tracezilla\OAuthTokens;
use Tracezilla\WooCommerce\Tracezilla\TokenProvider;
use Tracezilla\WooCommerce\Tracezilla\TokenStore;
use Tracezilla\WooCommerce\Tracezilla\TracezillaConfiguration;
use Tracezilla\WooCommerce\WooCommerce\Client as WooCommerceClient;

/**
 * Readers backed by queued fake API answers, so no test contacts a service.
 */
final class FakeApis
{
    /** @param list<mixed> $answers JSON bodies in the order they are requested */
    public static function wooCommerce(array $answers): WooCommerceReader
    {
        $configuration = new Configuration('https://shop.test', 'ck', 'cs', 5, 5);

        return new WooCommerceReader(new WooCommerceClient(
            $configuration,
            new HttpClient(['handler' => HandlerStack::create(self::responses($answers)), 'base_uri' => 'https://shop.test/wp-json/wc/v3/']),
        ));
    }

    /** @param list<mixed> $answers JSON bodies in the order they are requested */
    public static function tracezilla(array $answers, string $tokenDirectory): TracezillaReader
    {
        $store = new TokenStore($tokenDirectory.'/token.json');
        $store->save(new OAuthTokens('access', 'refresh', new DateTimeImmutable('+300 days')));
        $configuration = new TracezillaConfiguration('https://tracezilla.test', 'team', 'id', 'secret', $store->path(), 5, 5);

        return new TracezillaReader(new TracezillaClient(
            $configuration,
            new TokenProvider(new HttpClient(['handler' => HandlerStack::create(new MockHandler())]), $store, 'id', 'secret'),
            new HttpClient(['handler' => HandlerStack::create(self::responses($answers)), 'base_uri' => 'https://tracezilla.test/api/v1/team/']),
        ));
    }

    /** @param list<mixed> $answers */
    private static function responses(array $answers): MockHandler
    {
        $responses = [];
        foreach ($answers as $answer) {
            $responses[] = new Response(200, [], json_encode($answer, JSON_THROW_ON_ERROR));
        }

        return new MockHandler($responses);
    }
}
