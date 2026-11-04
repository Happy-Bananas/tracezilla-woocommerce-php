<?php

declare(strict_types=1);

namespace Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tracezilla\WooCommerce\WriteMode;

final class WriteModeTest extends TestCase
{
    public function test_dry_run_previews(): void
    {
        $mode = WriteMode::fromOptions(dryRun: true, execute: false, confirm: false);

        self::assertTrue($mode->isChosen());
        self::assertFalse($mode->writes());
    }

    public function test_execute_and_confirm_write(): void
    {
        $mode = WriteMode::fromOptions(dryRun: false, execute: true, confirm: true);

        self::assertTrue($mode->writes());
    }

    public function test_no_option_means_no_mode_was_chosen(): void
    {
        $mode = WriteMode::fromOptions(dryRun: false, execute: false, confirm: false);

        self::assertFalse($mode->isChosen());
        self::assertFalse($mode->writes());
    }

    public function test_execute_without_confirm_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Writing requires both --execute and --confirm.');

        WriteMode::fromOptions(dryRun: false, execute: true, confirm: false);
    }

    public function test_dry_run_with_execute_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Use either --dry-run or --execute --confirm, not both.');

        WriteMode::fromOptions(dryRun: true, execute: true, confirm: true);
    }
}
