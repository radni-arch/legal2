<?php

namespace Tests\Unit\Services\Ingest;

use App\Jobs\Ingest\ProcessIngestRunJob;
use App\Models\IngestRun;
use App\Models\User;
use App\Services\Ingest\IngestOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * TDD Tests for IngestOrchestrator service
 *
 * SOT-001: Verifies the orchestrator creates IngestRun records
 * and dispatches the correct processing jobs.
 */
class IngestOrchestratorTest extends TestCase
{
    use RefreshDatabase;

    protected IngestOrchestrator $orchestrator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orchestrator = app(IngestOrchestrator::class);
    }

    /** @test */
    public function it_creates_an_ingest_run_record(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $ingestRun = $this->orchestrator->ingest(
            storedPath: 'uploads/abc123-test.pdf',
            disk: 'public',
            originalFilename: 'test.pdf',
            userId: $user->id,
        );

        $this->assertInstanceOf(IngestRun::class, $ingestRun);
        $this->assertEquals('uploads/abc123-test.pdf', $ingestRun->stored_path);
        $this->assertEquals('public', $ingestRun->stored_disk);
        $this->assertEquals('test.pdf', $ingestRun->original_filename);
        $this->assertEquals($user->id, $ingestRun->user_id);
        $this->assertEquals('uploader', $ingestRun->source);
        $this->assertEquals('pending', $ingestRun->status);
        $this->assertNotNull($ingestRun->correlation_id);
    }

    /** @test */
    public function it_accepts_optional_case_id(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $ingestRun = $this->orchestrator->ingest(
            storedPath: 'uploads/abc123-test.pdf',
            disk: 'public',
            originalFilename: 'test.pdf',
            userId: $user->id,
            caseId: 'case-abc-123',
        );

        $this->assertEquals('case-abc-123', $ingestRun->case_id);
    }

    /** @test */
    public function it_accepts_custom_source(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $ingestRun = $this->orchestrator->ingest(
            storedPath: 'uploads/abc123-test.pdf',
            disk: 'public',
            originalFilename: 'test.pdf',
            userId: $user->id,
            source: 'drive',
        );

        $this->assertEquals('drive', $ingestRun->source);
    }

    /** @test */
    public function it_dispatches_process_ingest_run_job(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $ingestRun = $this->orchestrator->ingest(
            storedPath: 'uploads/abc123-test.pdf',
            disk: 'public',
            originalFilename: 'test.pdf',
            userId: $user->id,
        );

        Queue::assertPushed(ProcessIngestRunJob::class, function ($job) use ($ingestRun) {
            return $job->ingestRunId === $ingestRun->id;
        });
    }

    /** @test */
    public function it_persists_ingest_run_to_database(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $ingestRun = $this->orchestrator->ingest(
            storedPath: 'uploads/abc123-test.pdf',
            disk: 'public',
            originalFilename: 'test.pdf',
            userId: $user->id,
        );

        $this->assertDatabaseHas('ingest_runs', [
            'id' => $ingestRun->id,
            'stored_path' => 'uploads/abc123-test.pdf',
            'stored_disk' => 'public',
            'original_filename' => 'test.pdf',
            'user_id' => $user->id,
            'source' => 'uploader',
            'status' => 'pending',
        ]);
    }

    /** @test */
    public function it_dispatches_job_on_source_specific_queue(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $this->orchestrator->ingest(
            storedPath: 'uploads/abc123-test.pdf',
            disk: 'public',
            originalFilename: 'test.pdf',
            userId: $user->id,
            source: 'uploader',
        );

        Queue::assertPushed(ProcessIngestRunJob::class, function ($job) {
            return $job->queue === 'ingest-upload';
        });
    }
}
