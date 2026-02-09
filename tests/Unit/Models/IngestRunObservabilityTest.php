<?php

namespace Tests\Unit\Models;

use App\Models\IngestRun;
use App\Models\IngestStepLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TDD Tests for IngestRun observability methods
 *
 * SOT-013: Verifies summary methods for monitoring the ingest pipeline:
 * totalDurationMs, failedSteps, isStale, pipelineProgress, and
 * the hasMany relationship to IngestStepLog.
 */
class IngestRunObservabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function createIngestRun(array $overrides = []): IngestRun
    {
        $user = User::factory()->create();

        return IngestRun::create(array_merge([
            'user_id' => $user->id,
            'source' => 'uploader',
            'original_filename' => 'test-document.pdf',
            'stored_path' => 'uploads/test-document.pdf',
            'stored_disk' => 'public',
            'status' => 'processing',
            'started_at' => now(),
        ], $overrides));
    }

    // --- Relationship tests ---

    /** @test */
    public function ingest_run_has_many_step_logs(): void
    {
        $ingestRun = $this->createIngestRun();

        IngestStepLog::create([
            'ingest_run_id' => $ingestRun->id,
            'step_name' => 'upload',
            'status' => 'completed',
            'started_at' => now()->subSeconds(10),
            'completed_at' => now()->subSeconds(8),
            'duration_ms' => 2000,
        ]);

        IngestStepLog::create([
            'ingest_run_id' => $ingestRun->id,
            'step_name' => 'ocr',
            'status' => 'completed',
            'started_at' => now()->subSeconds(8),
            'completed_at' => now()->subSeconds(3),
            'duration_ms' => 5000,
        ]);

        $this->assertCount(2, $ingestRun->stepLogs);
        $this->assertInstanceOf(IngestStepLog::class, $ingestRun->stepLogs->first());
    }

    // --- totalDurationMs tests ---

    /** @test */
    public function total_duration_ms_sums_all_step_durations(): void
    {
        $ingestRun = $this->createIngestRun();

        IngestStepLog::create([
            'ingest_run_id' => $ingestRun->id,
            'step_name' => 'upload',
            'status' => 'completed',
            'started_at' => now(),
            'duration_ms' => 1500,
        ]);

        IngestStepLog::create([
            'ingest_run_id' => $ingestRun->id,
            'step_name' => 'ocr',
            'status' => 'completed',
            'started_at' => now(),
            'duration_ms' => 3500,
        ]);

        IngestStepLog::create([
            'ingest_run_id' => $ingestRun->id,
            'step_name' => 'extraction',
            'status' => 'completed',
            'started_at' => now(),
            'duration_ms' => 2000,
        ]);

        $this->assertEquals(7000, $ingestRun->totalDurationMs());
    }

    /** @test */
    public function total_duration_ms_returns_zero_when_no_steps(): void
    {
        $ingestRun = $this->createIngestRun();

        $this->assertEquals(0, $ingestRun->totalDurationMs());
    }

    /** @test */
    public function total_duration_ms_ignores_null_durations(): void
    {
        $ingestRun = $this->createIngestRun();

        IngestStepLog::create([
            'ingest_run_id' => $ingestRun->id,
            'step_name' => 'upload',
            'status' => 'completed',
            'started_at' => now(),
            'duration_ms' => 2000,
        ]);

        IngestStepLog::create([
            'ingest_run_id' => $ingestRun->id,
            'step_name' => 'ocr',
            'status' => 'started',
            'started_at' => now(),
            'duration_ms' => null,
        ]);

        $this->assertEquals(2000, $ingestRun->totalDurationMs());
    }

    // --- failedSteps tests ---

    /** @test */
    public function failed_steps_returns_steps_with_failed_status(): void
    {
        $ingestRun = $this->createIngestRun();

        IngestStepLog::create([
            'ingest_run_id' => $ingestRun->id,
            'step_name' => 'upload',
            'status' => 'completed',
            'started_at' => now(),
        ]);

        IngestStepLog::create([
            'ingest_run_id' => $ingestRun->id,
            'step_name' => 'ocr',
            'status' => 'failed',
            'started_at' => now(),
            'error_message' => 'Textract timeout',
        ]);

        IngestStepLog::create([
            'ingest_run_id' => $ingestRun->id,
            'step_name' => 'extraction',
            'status' => 'failed',
            'started_at' => now(),
            'error_message' => 'Parser error',
        ]);

        $failedSteps = $ingestRun->failedSteps();

        $this->assertCount(2, $failedSteps);
        $failedNames = $failedSteps->pluck('step_name')->sort()->values()->all();
        $this->assertEquals(['extraction', 'ocr'], $failedNames);
    }

    /** @test */
    public function failed_steps_returns_empty_collection_when_no_failures(): void
    {
        $ingestRun = $this->createIngestRun();

        IngestStepLog::create([
            'ingest_run_id' => $ingestRun->id,
            'step_name' => 'upload',
            'status' => 'completed',
            'started_at' => now(),
        ]);

        $failedSteps = $ingestRun->failedSteps();

        $this->assertCount(0, $failedSteps);
    }

    // --- isStale tests ---

    /** @test */
    public function is_stale_returns_true_when_started_over_30_minutes_ago_and_not_completed(): void
    {
        $ingestRun = $this->createIngestRun([
            'status' => 'processing',
            'started_at' => now()->subMinutes(31),
            'completed_at' => null,
        ]);

        $this->assertTrue($ingestRun->isStale());
    }

    /** @test */
    public function is_stale_returns_false_when_started_within_30_minutes(): void
    {
        $ingestRun = $this->createIngestRun([
            'status' => 'processing',
            'started_at' => now()->subMinutes(20),
            'completed_at' => null,
        ]);

        $this->assertFalse($ingestRun->isStale());
    }

    /** @test */
    public function is_stale_returns_false_when_completed(): void
    {
        $ingestRun = $this->createIngestRun([
            'status' => 'completed',
            'started_at' => now()->subMinutes(60),
            'completed_at' => now()->subMinutes(30),
        ]);

        $this->assertFalse($ingestRun->isStale());
    }

    /** @test */
    public function is_stale_returns_false_when_failed(): void
    {
        $ingestRun = $this->createIngestRun([
            'status' => 'failed',
            'started_at' => now()->subMinutes(60),
            'completed_at' => now()->subMinutes(30),
        ]);

        $this->assertFalse($ingestRun->isStale());
    }

    /** @test */
    public function is_stale_returns_false_when_not_started(): void
    {
        $ingestRun = $this->createIngestRun([
            'status' => 'pending',
            'started_at' => null,
            'completed_at' => null,
        ]);

        $this->assertFalse($ingestRun->isStale());
    }

    // --- pipelineProgress tests ---

    /** @test */
    public function pipeline_progress_returns_percentage_of_completed_steps(): void
    {
        $ingestRun = $this->createIngestRun();

        IngestStepLog::create([
            'ingest_run_id' => $ingestRun->id,
            'step_name' => 'upload',
            'status' => 'completed',
            'started_at' => now(),
        ]);

        IngestStepLog::create([
            'ingest_run_id' => $ingestRun->id,
            'step_name' => 'ocr',
            'status' => 'completed',
            'started_at' => now(),
        ]);

        IngestStepLog::create([
            'ingest_run_id' => $ingestRun->id,
            'step_name' => 'extraction',
            'status' => 'started',
            'started_at' => now(),
        ]);

        IngestStepLog::create([
            'ingest_run_id' => $ingestRun->id,
            'step_name' => 'analysis',
            'status' => 'started',
            'started_at' => now(),
        ]);

        // 2 completed out of 4 total = 50%
        $this->assertEquals(50.0, $ingestRun->pipelineProgress());
    }

    /** @test */
    public function pipeline_progress_returns_zero_when_no_steps(): void
    {
        $ingestRun = $this->createIngestRun();

        $this->assertEquals(0.0, $ingestRun->pipelineProgress());
    }

    /** @test */
    public function pipeline_progress_returns_100_when_all_steps_completed(): void
    {
        $ingestRun = $this->createIngestRun();

        foreach (['upload', 'ocr', 'extraction', 'analysis'] as $step) {
            IngestStepLog::create([
                'ingest_run_id' => $ingestRun->id,
                'step_name' => $step,
                'status' => 'completed',
                'started_at' => now(),
            ]);
        }

        $this->assertEquals(100.0, $ingestRun->pipelineProgress());
    }

    /** @test */
    public function pipeline_progress_counts_only_completed_steps(): void
    {
        $ingestRun = $this->createIngestRun();

        IngestStepLog::create([
            'ingest_run_id' => $ingestRun->id,
            'step_name' => 'upload',
            'status' => 'completed',
            'started_at' => now(),
        ]);

        IngestStepLog::create([
            'ingest_run_id' => $ingestRun->id,
            'step_name' => 'ocr',
            'status' => 'failed',
            'started_at' => now(),
        ]);

        IngestStepLog::create([
            'ingest_run_id' => $ingestRun->id,
            'step_name' => 'extraction',
            'status' => 'skipped',
            'started_at' => now(),
        ]);

        // 1 completed out of 3 total = 33.33
        $this->assertEqualsWithDelta(33.33, $ingestRun->pipelineProgress(), 0.01);
    }
}
