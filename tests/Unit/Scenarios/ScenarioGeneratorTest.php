<?php

declare(strict_types=1);

namespace Tests\Unit\Scenarios;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tracezilla\WooCommerce\Scenarios\ScenarioGenerator;

final class ScenarioGeneratorTest extends TestCase
{
    private string $project;

    protected function setUp(): void
    {
        $this->project = sys_get_temp_dir().'/tracezilla-scenarios-'.bin2hex(random_bytes(6));
        mkdir($this->project);
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->project));
    }

    public function test_it_creates_the_four_scenario_files(): void
    {
        $files = (new ScenarioGenerator($this->project))->generate('confirm-credentials');

        $directory = $this->project.'/custom/Scenarios/WooCommerce/ConfirmCredentials';
        self::assertSame([
            $directory.'/WooCommerceRequest.php',
            $directory.'/TracezillaRequest.php',
            $directory.'/BusinessRules.php',
            $directory.'/BusinessRulesTest.php',
        ], $files);
        self::assertStringContainsString(
            'namespace Consultant\Scenarios\WooCommerce\ConfirmCredentials;',
            (string) file_get_contents($directory.'/BusinessRules.php'),
        );
        foreach ($files as $file) {
            exec('php -l '.escapeshellarg($file), $output, $exitCode);
            self::assertSame(0, $exitCode, "{$file} is not valid PHP");
        }
    }

    public function test_it_refuses_to_replace_an_existing_scenario(): void
    {
        $generator = new ScenarioGenerator($this->project);
        $generator->generate('confirm-credentials');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Scenario already exists');

        $generator->generate('confirm-credentials');
    }

    public function test_it_rejects_names_that_cannot_become_a_class(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new ScenarioGenerator($this->project))->generate('Confirm_Credentials');
    }
}
