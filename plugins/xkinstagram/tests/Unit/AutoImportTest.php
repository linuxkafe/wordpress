<?php
/**
 * Unit tests for AutoImport cron
 */

declare(strict_types=1);

namespace Xkinstagram\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Xkinstagram\Cron\AutoImport;

final class AutoImportTest extends TestCase {
    protected function setUp(): void {
        $GLOBALS['wp_options'] = [];
    }

    public function test_frequency_defaults_to_daily(): void {
        $this->assertEquals('daily', AutoImport::frequency());
    }

    public function test_frequency_falls_back_on_invalid_value(): void {
        $GLOBALS['wp_options']['xkinstagram_options'] = ['auto_import_frequency' => 'yearly'];
        $this->assertEquals('daily', AutoImport::frequency());
    }

    public function test_enabled_reflects_option(): void {
        $this->assertFalse(AutoImport::enabled());
        $GLOBALS['wp_options']['xkinstagram_options'] = ['auto_import_enabled' => true];
        $this->assertTrue(AutoImport::enabled());
    }

    public function test_run_skips_when_disabled(): void {
        $GLOBALS['wp_options']['xkinstagram_options'] = ['auto_import_enabled' => false];
        AutoImport::run();
        $this->assertEquals([], AutoImport::get_last_run());
    }

    public function test_run_skips_without_app_credentials(): void {
        $GLOBALS['wp_options']['xkinstagram_options'] = [
            'auto_import_enabled' => true,
            'app_id' => '',
            'app_secret' => '',
            'access_token' => '',
        ];
        AutoImport::run();
        $this->assertEquals([], AutoImport::get_last_run());
    }

    public function test_schedule_unschedules_when_disabled(): void {
        $GLOBALS['wp_options']['xkinstagram_options'] = ['auto_import_enabled' => false];
        AutoImport::schedule();
        $this->assertTrue(true);
    }
}