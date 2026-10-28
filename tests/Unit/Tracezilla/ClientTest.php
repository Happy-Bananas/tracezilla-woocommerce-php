<?php

declare(strict_types=1);

namespace Tests\Unit\Tracezilla;

use DateTimeImmutable;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tracezilla\WooCommerce\Tracezilla\Client;
use Tracezilla\WooCommerce\Tracezilla\OAuthTokens;
use Tracezilla\WooCommerce\Tracezilla\TokenProvider;
use Tracezilla\WooCommerce\Tracezilla\TokenStore;
use Tracezilla\WooCommerce\Tracezilla\TracezillaConfiguration;

final class ClientTest extends TestCase
{
    private string $directory;
    private MockHandler $api;
    private MockHandler $oauth;
    /** @var list<array{request: \Psr\Http\Message\RequestInterface}> */
    private array $apiHistory = [];

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/tz-client-'.bin2hex(random_bytes(4));
        $this->api = new MockHandler();
        $this->oauth = new MockHandler();
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->directory.'/*') ?: []);
        @rmdir($this->directory);
    }

    public function test_it_sends_the_access_token(): void
    {
        $this->api->append(new Response(200, [], '{"data":[]}'));

        $this->client()->get('/skus');

        self::assertSame('Bearer access-1', $this->apiHistory[0]['request']->getHeaderLine('Authorization'));
    }

    public function test_a_rejected_token_is_renewed_and_the_request_retried(): void
    {
        $this->api->append(new Response(401, [], '{"message":"Unauthenticated."}'), new Response(200, [], '{"data":[]}'));
        $this->oauth->append(new Response(200, [], '{"expires_in":31536000,"access_token":"access-2","refresh_token":"refresh-2"}'));

        self::assertSame(['data' => []], $this->client()->get('/skus'));
        self::assertSame('Bearer access-2', $this->apiHistory[1]['request']->getHeaderLine('Authorization'));
    }

    public function test_it_gives_up_when_the_renewed_token_is_also_rejected(): void
    {
        $this->api->append(new Response(401, [], '{"message":"Unauthenticated."}'), new Response(401, [], '{"message":"Unauthenticated."}'));
        $this->oauth->append(new Response(200, [], '{"expires_in":31536000,"access_token":"access-2","refresh_token":"refresh-2"}'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('GET /skus failed with HTTP 401');

        $this->client()->get('/skus');
    }

    private function client(): Client
    {
        $store = new TokenStore($this->directory.'/token.json');
        $store->save(new OAuthTokens('access-1', 'refresh-1', new DateTimeImmutable('+300 days')));

        $apiStack = HandlerStack::create($this->api);
        $apiStack->push(Middleware::history($this->apiHistory));
        $tokens = new TokenProvider(
            new HttpClient(['handler' => HandlerStack::create($this->oauth), 'base_uri' => 'https://tracezilla.test/']),
            $store,
            'client-id',
            'client-secret',
        );

        return new Client(
            new TracezillaConfiguration('https://tracezilla.test', 'team', 'client-id', 'client-secret', $store->path(), 5, 5),
            $tokens,
            new HttpClient(['handler' => $apiStack, 'base_uri' => 'https://tracezilla.test/api/v1/team/']),
        );
    }
}
