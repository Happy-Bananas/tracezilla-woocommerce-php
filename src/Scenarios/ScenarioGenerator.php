<?php

declare(strict_types=1);

namespace Tracezilla\WooCommerce\Scenarios;

use RuntimeException;

/**
 * Creates the four consultant-owned files of a new scenario. The generated
 * scenario is a read-only "hello world" that confirms both sets of credentials.
 */
final readonly class ScenarioGenerator
{
    public function __construct(private string $projectDirectory)
    {
    }

    /** @return list<string> The created files */
    public function generate(string $slug): array
    {
        $scenario = new Scenario($this->projectDirectory, $slug);
        if (file_exists($scenario->directory)) {
            throw new RuntimeException("Scenario already exists: {$slug}");
        }
        if (! mkdir($scenario->directory, 0750, true) && ! is_dir($scenario->directory)) {
            throw new RuntimeException("Could not create scenario directory: {$scenario->directory}");
        }

        $templates = [
            'WooCommerceRequest.php' => $this->wooCommerceRequest($scenario->namespace),
            'TracezillaRequest.php' => $this->tracezillaRequest($scenario->namespace),
            'BusinessRules.php' => $this->businessRules($scenario->namespace),
            'BusinessRulesTest.php' => $this->businessRulesTest($scenario->namespace),
        ];
        $files = [];
        foreach ($templates as $name => $contents) {
            $path = $scenario->directory.'/'.$name;
            if (file_put_contents($path, $contents, LOCK_EX) === false) {
                throw new RuntimeException("Could not write generated file: {$path}");
            }
            $files[] = $path;
        }

        return $files;
    }

    private function wooCommerceRequest(string $namespace): string
    {
        return <<<PHP
            <?php

            declare(strict_types=1);

            namespace {$namespace};

            /**
             * The read-only WooCommerce REST request, relative to /wp-json/wc/v3/.
             * https://woocommerce.github.io/woocommerce-rest-api-docs/
             */
            final class WooCommerceRequest
            {
                public function path(): string
                {
                    return 'system_status';
                }

                /** @return array<string, scalar> */
                public function query(): array
                {
                    return [];
                }
            }

            PHP;
    }

    private function tracezillaRequest(string $namespace): string
    {
        return <<<PHP
            <?php

            declare(strict_types=1);

            namespace {$namespace};

            /**
             * The read-only tracezilla request, relative to /api/v1/{team-slug}/.
             */
            final class TracezillaRequest
            {
                public function path(): string
                {
                    return '/skus';
                }

                /** @return array<string, scalar> */
                public function query(): array
                {
                    return ['perPage' => 1];
                }
            }

            PHP;
    }

    private function businessRules(string $namespace): string
    {
        return <<<PHP
            <?php

            declare(strict_types=1);

            namespace {$namespace};

            use RuntimeException;

            /**
             * The customer's decisions, in plain PHP. It receives both API responses
             * and returns the result. It never calls an API itself.
             */
            final class BusinessRules
            {
                /**
                 * @param array<mixed> \$wooCommerce
                 * @param array<mixed> \$tracezilla
                 * @return array<string, mixed>
                 */
                public function apply(array \$wooCommerce, array \$tracezilla): array
                {
                    \$environment = \$wooCommerce['environment'] ?? null;
                    if (! is_array(\$environment) || ! is_string(\$environment['site_url'] ?? null)) {
                        throw new RuntimeException('WooCommerce credential check returned no store.');
                    }
                    if (! is_array(\$tracezilla['data'] ?? null)) {
                        throw new RuntimeException('tracezilla credential check returned no data collection.');
                    }

                    return [
                        'status' => 'ok',
                        'woocommerce' => [
                            'credentials_valid' => true,
                            'store_url' => \$environment['site_url'],
                            'version' => \$environment['version'] ?? null,
                        ],
                        'tracezilla' => ['credentials_valid' => true],
                        'message' => 'Hello! Both API credentials are working.',
                    ];
                }
            }

            PHP;
    }

    private function businessRulesTest(string $namespace): string
    {
        return <<<PHP
            <?php

            declare(strict_types=1);

            namespace {$namespace};

            use PHPUnit\Framework\TestCase;
            use RuntimeException;

            /**
             * Examples of the customer's rules that run without contacting either API.
             */
            final class BusinessRulesTest extends TestCase
            {
                public function test_it_confirms_both_connections(): void
                {
                    \$result = (new BusinessRules())->apply(
                        ['environment' => ['site_url' => 'https://shop.example.test', 'version' => '11.2.1']],
                        ['data' => []],
                    );

                    self::assertSame('ok', \$result['status']);
                    self::assertSame('https://shop.example.test', \$result['woocommerce']['store_url']);
                    self::assertTrue(\$result['tracezilla']['credentials_valid']);
                }

                public function test_it_rejects_an_unexpected_woocommerce_response(): void
                {
                    \$this->expectException(RuntimeException::class);

                    (new BusinessRules())->apply([], ['data' => []]);
                }
            }

            PHP;
    }
}
