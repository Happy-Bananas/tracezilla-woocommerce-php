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

    public function test_it_lists_the_templates(): void
    {
        self::assertSame(['compare-catalogs', 'confirm-credentials'], (new ScenarioGenerator($this->project))->templates());
    }

    public function test_every_template_creates_three_valid_php_files_in_the_scenario_namespace(): void
    {
        $generator = new ScenarioGenerator($this->project);
        foreach ($generator->templates() as $template) {
            $files = $generator->generate("from-{$template}", $template);

            $className = str_replace(' ', '', ucwords(str_replace('-', ' ', "from-{$template}")));
            $directory = $this->project.'/custom/Scenarios/WooCommerce/'.$className;
            self::assertSame([$directory.'/DataReader.php', $directory.'/BusinessRules.php', $directory.'/BusinessRulesTest.php'], $files);
            foreach ($files as $file) {
                self::assertStringContainsString("namespace Consultant\\Scenarios\\WooCommerce\\{$className};", (string) file_get_contents($file));
                exec('php -l '.escapeshellarg($file), $output, $exitCode);
                self::assertSame(0, $exitCode, "{$file} is not valid PHP");
            }
        }
    }

    public function test_it_rejects_an_unknown_template(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Available templates: compare-catalogs, confirm-credentials');

        (new ScenarioGenerator($this->project))->generate('my-check', 'magic');
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
