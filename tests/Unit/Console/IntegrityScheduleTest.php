<?php

namespace Tests\Unit\Console;

use App\Services\Graph\DataQualityService;
use App\Services\Graph\GraphDataIntegrityService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Tests for Cross-Database Integrity Scheduling
 *
 * Task 4.3: Verifies that GraphDataIntegrityService and DataQualityService
 * are properly scheduled for automated integrity checks.
 *
 * The schedule entries must be registered in routes/console.php (Laravel 12+),
 * NOT in Kernel.php (which is no longer called by the framework).
 */
class IntegrityScheduleTest extends TestCase
{
    protected Schedule $schedule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->schedule = app(Schedule::class);
    }

    /**
     * Helper to find schedule events matching a command substring.
     */
    protected function findScheduleEvents(string $commandSubstring): Collection
    {
        return collect($this->schedule->events())
            ->filter(function ($event) use ($commandSubstring) {
                // Check command property (for command-based events)
                if (! empty($event->command) && str_contains($event->command, $commandSubstring)) {
                    return true;
                }

                // Check description/name property (for callback/job events)
                if (! empty($event->description) && str_contains($event->description, $commandSubstring)) {
                    return true;
                }

                return false;
            });
    }

    // ----------------------------------------------------------------
    // Test 1: graph-integrity-check is scheduled
    // ----------------------------------------------------------------

    /** @test */
    public function graph_integrity_check_is_scheduled(): void
    {
        $events = $this->findScheduleEvents('graph:integrity-check');

        $this->assertGreaterThan(
            0,
            $events->count(),
            'graph:integrity-check should be registered in the scheduler'
        );
    }

    // ----------------------------------------------------------------
    // Test 2: graph-integrity-check is scheduled daily
    // ----------------------------------------------------------------

    /** @test */
    public function graph_integrity_check_is_scheduled_daily(): void
    {
        $events = $this->findScheduleEvents('graph:integrity-check');
        $event = $events->first();

        $this->assertNotNull($event, 'graph:integrity-check should be scheduled');

        // Daily at 02:00 = cron expression "0 2 * * *"
        $this->assertEquals(
            '0 2 * * *',
            $event->expression,
            'graph:integrity-check should be scheduled daily at 02:00'
        );
    }

    // ----------------------------------------------------------------
    // Test 3: data-quality-audit is scheduled
    // ----------------------------------------------------------------

    /** @test */
    public function data_quality_audit_is_scheduled(): void
    {
        $events = $this->findScheduleEvents('graph:check-quality');

        $this->assertGreaterThan(
            0,
            $events->count(),
            'graph:check-quality should be registered in the scheduler'
        );
    }

    // ----------------------------------------------------------------
    // Test 4: data-quality-audit is scheduled weekly on Sundays
    // ----------------------------------------------------------------

    /** @test */
    public function data_quality_audit_is_scheduled_weekly_on_sundays(): void
    {
        $events = $this->findScheduleEvents('graph:check-quality');
        $event = $events->first();

        $this->assertNotNull($event, 'graph:check-quality should be scheduled');

        // Weekly on Sundays at 03:00 = cron expression "0 3 * * 0"
        $this->assertEquals(
            '0 3 * * 0',
            $event->expression,
            'graph:check-quality should be scheduled weekly on Sundays at 03:00'
        );
    }

    // ----------------------------------------------------------------
    // Test 5: integrity check uses withoutOverlapping
    // ----------------------------------------------------------------

    /** @test */
    public function integrity_check_uses_without_overlapping(): void
    {
        $events = $this->findScheduleEvents('graph:integrity-check');
        $event = $events->first();

        $this->assertNotNull($event, 'graph:integrity-check should be scheduled');
        $this->assertTrue(
            $event->withoutOverlapping,
            'graph:integrity-check should use withoutOverlapping'
        );
    }

    // ----------------------------------------------------------------
    // Test 6: quality audit uses withoutOverlapping
    // ----------------------------------------------------------------

    /** @test */
    public function quality_audit_uses_without_overlapping(): void
    {
        $events = $this->findScheduleEvents('graph:check-quality');
        $event = $events->first();

        $this->assertNotNull($event, 'graph:check-quality should be scheduled');
        $this->assertTrue(
            $event->withoutOverlapping,
            'graph:check-quality should use withoutOverlapping'
        );
    }

    // ----------------------------------------------------------------
    // Test 7: integrity check command calls GraphDataIntegrityService
    // ----------------------------------------------------------------

    /** @test */
    public function integrity_check_command_calls_generate_integrity_report(): void
    {
        $mock = $this->mock(GraphDataIntegrityService::class);
        $mock->shouldReceive('generateIntegrityReport')
            ->once()
            ->andReturn([
                'orphan_nodes' => [],
                'missing_nodes' => [],
                'dangling_relationships' => [],
                'generated_at' => now()->toIso8601String(),
                'summary' => [
                    'total_orphan_nodes' => 0,
                    'total_missing_nodes' => 0,
                    'total_dangling_relationships' => 0,
                    'health_status' => 'healthy',
                ],
            ]);

        $this->artisan('graph:integrity-check')
            ->assertSuccessful();
    }

    // ----------------------------------------------------------------
    // Test 8: quality audit command calls DataQualityService
    // ----------------------------------------------------------------

    /** @test */
    public function quality_audit_command_calls_generate_quality_report(): void
    {
        $mock = $this->mock(DataQualityService::class);
        $mock->shouldReceive('generateQualityReport')
            ->once()
            ->with(false)
            ->andReturn([
                'generated_at' => now()->toIso8601String(),
                'duration_ms' => 100,
                'overall_quality_score' => 100,
                'total_nodes' => 0,
                'metrics' => [
                    'duplicate_score' => 100,
                    'orphan_score' => 100,
                    'consistency_score' => 100,
                    'duplicate_nodes' => 0,
                    'duplicate_groups' => 0,
                    'orphan_nodes' => 0,
                    'inconsistent_nodes' => 0,
                ],
                'duplicates' => [
                    'total_groups' => 0,
                    'total_nodes' => 0,
                    'groups' => [],
                ],
                'orphans' => [
                    'total' => 0,
                    'nodes' => [],
                ],
                'inconsistencies' => [
                    'total' => 0,
                    'nodes' => [],
                ],
                'statistics' => [
                    'nodes' => [],
                    'relationships' => [],
                ],
                'recommendations' => [],
            ]);

        $this->artisan('graph:check-quality')
            ->assertSuccessful();
    }

    // ----------------------------------------------------------------
    // Test 9: integrity check uses onOneServer
    // ----------------------------------------------------------------

    /** @test */
    public function integrity_check_uses_on_one_server(): void
    {
        $events = $this->findScheduleEvents('graph:integrity-check');
        $event = $events->first();

        $this->assertNotNull($event, 'graph:integrity-check should be scheduled');
        $this->assertTrue(
            $event->onOneServer,
            'graph:integrity-check should use onOneServer for multi-server safety'
        );
    }

    // ----------------------------------------------------------------
    // Test 10: quality audit uses onOneServer
    // ----------------------------------------------------------------

    /** @test */
    public function quality_audit_uses_on_one_server(): void
    {
        $events = $this->findScheduleEvents('graph:check-quality');
        $event = $events->first();

        $this->assertNotNull($event, 'graph:check-quality should be scheduled');
        $this->assertTrue(
            $event->onOneServer,
            'graph:check-quality should use onOneServer for multi-server safety'
        );
    }
}
