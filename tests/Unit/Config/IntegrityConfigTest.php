<?php

namespace Tests\Unit\Config;

use Tests\TestCase;

/**
 * Tests for integrity configuration
 *
 * Task 4.3: Cross-Database Integrity Scheduling
 */
class IntegrityConfigTest extends TestCase
{
    /**
     * @test
     */
    public function it_has_integrity_config_file(): void
    {
        $configPath = config_path('integrity.php');

        $this->assertFileExists($configPath, 'Config file integrity.php should exist');
    }

    /**
     * @test
     */
    public function it_has_admin_email_config(): void
    {
        $adminEmail = config('integrity.admin_email');

        // Should not throw, config key should exist
        $this->assertTrue(
            config()->has('integrity.admin_email'),
            'Config should have integrity.admin_email key'
        );
    }

    /**
     * @test
     */
    public function it_has_auto_fix_config(): void
    {
        $autoFix = config('integrity.auto_fix');

        $this->assertIsBool($autoFix, 'integrity.auto_fix should be a boolean');
        $this->assertFalse($autoFix, 'integrity.auto_fix should default to false');
    }

    /**
     * @test
     */
    public function it_has_duplicate_merge_threshold_config(): void
    {
        $threshold = config('integrity.duplicate_merge_threshold');

        $this->assertIsFloat($threshold, 'integrity.duplicate_merge_threshold should be a float');
        $this->assertEquals(0.9, $threshold, 'integrity.duplicate_merge_threshold should default to 0.9');
    }

    /**
     * @test
     */
    public function it_has_email_on_failure_config(): void
    {
        $this->assertTrue(
            config()->has('integrity.email_on_failure'),
            'Config should have integrity.email_on_failure key'
        );
    }

    /**
     * @test
     */
    public function it_has_schedule_config(): void
    {
        $this->assertTrue(
            config()->has('integrity.schedule'),
            'Config should have integrity.schedule key'
        );

        $schedule = config('integrity.schedule');
        $this->assertIsArray($schedule);
        $this->assertArrayHasKey('integrity_check_time', $schedule);
        $this->assertArrayHasKey('quality_check_day', $schedule);
        $this->assertArrayHasKey('quality_check_time', $schedule);
    }
}
