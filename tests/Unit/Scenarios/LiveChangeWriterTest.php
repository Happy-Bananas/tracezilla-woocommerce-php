<?php

declare(strict_types=1);

namespace Tests\Unit\Scenarios;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tracezilla\WooCommerce\Scenarios\LiveChangeWriter;
use Tracezilla\WooCommerce\Tracezilla\SkuCreator;

final class LiveChangeWriterTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/tracezilla-writer-'.bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->directory));
    }

    public function test_it_refuses_an_action_it_does_not_know(): void
    {
        $client = FakeApis::tracezillaClient([], $this->directory);
        $writer = new LiveChangeWriter(new SkuCreator($client));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown change action: delete_everything');

        $writer->write('delete_everything', []);
    }
}
