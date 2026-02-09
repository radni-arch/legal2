<?php

namespace Tests\Unit\Models;

use App\Models\IngestRun;
use App\Models\IngestStepLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TDD Tests for IngestStepLog model
 *
 * SOT-013: Verifies step log creation, timing, duration calculation,
 * and relationships to IngestRun.
 */
class IngestStepLogTest extends TestCase
{
    use RefreshDatabase;

    protected function createIngestRun(): IngestRun
    {
        $user = User::factory()->create();

        return IngestRun::create([
            'user_id' => $user->id,
            'source' => 'uploader',
            'original_filename' => 'test-document.pdf',
            'stored_path' => 'uploads/test-document.pdf',
            'stored_disk' => 'public',
            'status' => 'processing',
            'started_at' => now(),
        ]);
    }

    /** @test */
    public function it_can_be_created_with_required_attributes(): void
    {
        $ingestRun = $this->createIngestRun();

        $stepLog = IngestStepLog::create([
            'ingest_run_id' => $ingestRun->id,
            'step_name' => 'upload',
            'status' => 'started',
            'started_at' => now(),
        ]);

        $this->assertNotNull($stepLog->id);
        $this->assertEquals($ingestRun->id, $stepLog->ingest_run_id);
        $this->assertEquals('upload', $stepLog->step_name);
        $this->assertEquals('started', $stepLog->status);
        $this->assertNotNull($stepLog->started_at);
    }

    /** @test */
    public function it_supports_all_valid_step_names(): void
    {
        $ingestRun = $this->createIngestRun();
        $validSteps = ['upload', 'ocr', 'extraction', 'analysis'];

        foreach ($validSteps as $stepName) {
            $stepLog = IngestStepLog::create([
                'ingest_run_id' => $ingestRun->id,
                'step_name' => $stepName,
                'status' => 'started',
                'started_at' => now(),
            ]);

            $this->assertEquals($stepName, $stepLog->step_name, "Step name '$stepName' should be valid");
        }
    }

    /** @test */
    public function it_supports_all_valid_statuses(): void
    {
        $ingestRun = $this->createIngestRun();
        $validStatuses = ['started', 'completed', 'failed', 'skipped'];

        foreach ($validStatuses as $status) {
            $stepLog = IngestStepLog::create([
                'ingest_run_id' => $ingestRun->id,
                'step_name' => 'upload',
                'status' => $status,
                'started_at' => now(),
            ]);

            $this->assertEquals($status, $stepLog->status, "Status '$status' should be valid");
        }
    }

    /** @test */
    public function it_stores_duration_in_milliseconds(): void
    {
        $ingestRun = $this->createIngestRun();

        $stepLog = IngestStepLog::create([
            'ingest_run_id' => $ingestRun->id,
            'step_name' => 'ocr',
            'status' => 'completed',
            'started_at' => now()->subSeconds(5),
            'completed_at' => now(),
            'duration_ms' => 5000,
        ]);

        $this->assertEquals(5000, $stepLog->duration_ms);
    }

    /** @test */
    public function it_stores_metadata_as_json(): void
    {
        $ingestRun = $this->createIngestRun();
        $metadata = ['pages' => 10, 'confidence' => 0.95];

        $stepLog = IngestStepLog::create([
            'ingest_run_id' => $ingestRun->id,
            'step_name' => 'ocr',
            'status' => 'completed',
            'started_at' => now(),
            'metadata' => $metadata,
        ]);

        $stepLog->refresh();
        $this->assertEquals($metadata, $stepLog->metadata);
    }

    /** @test */
    public function it_stores_error_message_on_failure(): void
    {
        $ingestRun = $this->createIngestRun();

        $stepLog = IngestStepLog::create([
            'ingest_run_id' => $ingestRun->id,
            'step_name' => 'ocr',
            'status' => 'failed',
            'started_at' => now(),
            'error_message' => 'Textract timeout after 30s',
        ]);

        $this->assertEquals('Textract timeout after 30s', $stepLog->error_message);
    }

    /** @test */
    public function it_belongs_to_an_ingest_run(): void
    {
        $ingestRun = $this->createIngestRun();

        $stepLog = IngestStepLog::create([
            'ingest_run_id' => $ingestRun->id,
            'step_name' => 'upload',
            'status' => 'completed',
            'started_at' => now(),
        ]);

        $this->assertInstanceOf(IngestRun::class, $stepLog->ingestRun);
        $this->assertEquals($ingestRun->id, $stepLog->ingestRun->id);
    }

    /** @test */
    public function it_casts_datetime_fields(): void
    {
        $ingestRun = $this->createIngestRun();

        $stepLog = IngestStepLog::create([
            'ingest_run_id' => $ingestRun->id,
            'step_name' => 'ocr',
            'status' => 'completed',
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $stepLog->refresh();

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $stepLog->started_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $stepLog->completed_at);
    }

    /** @test */
    public function it_allows_nullable_fields(): void
    {
        $ingestRun = $this->createIngestRun();

        $stepLog = IngestStepLog::create([
            'ingest_run_id' => $ingestRun->id,
            'step_name' => 'upload',
            'status' => 'started',
            'started_at' => now(),
        ]);

        $this->assertNull($stepLog->completed_at);
        $this->assertNull($stepLog->duration_ms);
        $this->assertNull($stepLog->metadata);
        $this->assertNull($stepLog->error_message);
    }
}
