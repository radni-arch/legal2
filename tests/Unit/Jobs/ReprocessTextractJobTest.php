<?php

namespace Tests\Unit\Jobs;

use App\Actions\Textract\ProcessDrivePdf as ProcessDrivePdfAction;
use App\Jobs\ReprocessTextractJob;
use App\Models\LegalCase;
use App\Models\TextractJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class ReprocessTextractJobTest extends TestCase
{
    use UsesTestDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_implements_should_queue_interface(): void
    {
        $job = new ReprocessTextractJob('file-id', 'file.pdf');

        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $job);
    }

    /** @test */
    public function it_stores_drive_file_id_and_name_and_force_flag(): void
    {
        $job = new ReprocessTextractJob('drive-123', 'document.pdf', false);

        $this->assertEquals('drive-123', $job->driveFileId);
        $this->assertEquals('document.pdf', $job->driveFileName);
        $this->assertFalse($job->forceTextract);
    }

    /** @test */
    public function it_defaults_force_textract_to_true(): void
    {
        $job = new ReprocessTextractJob('file-id', 'file.pdf');

        $this->assertTrue($job->forceTextract);
    }

    /** @test */
    public function it_archives_existing_job_before_reprocessing(): void
    {
        $legalCase = LegalCase::factory()->create();

        $existingJob = TextractJob::create([
            'drive_file_id' => 'reprocess-file',
            'drive_file_name' => 'old.pdf',
            'status' => 'succeeded',
            'case_id' => $legalCase->id,
        ]);

        $existingJobId = $existingJob->id;

        Log::shouldReceive('info')->atLeast()->once();
        Log::shouldReceive('error')->andReturn(null);
        Log::shouldReceive('warning')->andReturn(null);

        $mockAction = Mockery::mock(ProcessDrivePdfAction::class);
        $mockAction->shouldReceive('handle')
            ->once()
            ->with('reprocess-file', 'old.pdf')
            ->andReturn(null);

        $this->app->instance(ProcessDrivePdfAction::class, $mockAction);

        $job = new ReprocessTextractJob('reprocess-file', 'old.pdf', true);
        $job->handle();

        // The existing job should be hard-deleted after being marked superseded
        $this->assertNull(TextractJob::find($existingJobId));

        // A new job should be created with preserved case_id
        $newJob = TextractJob::where('drive_file_id', 'reprocess-file')->first();
        $this->assertNotNull($newJob);
        $this->assertEquals($legalCase->id, $newJob->case_id);
    }

    /** @test */
    public function it_throws_exception_when_existing_job_has_no_case_id(): void
    {
        $existingJob = TextractJob::create([
            'drive_file_id' => 'no-case-file',
            'drive_file_name' => 'nocase.pdf',
            'status' => 'succeeded',
            'case_id' => null,
        ]);

        Log::shouldReceive('info')->atLeast()->once();
        Log::shouldReceive('error')->atLeast()->once();

        $job = new ReprocessTextractJob('no-case-file', 'nocase.pdf', true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('no case_id associated');

        $job->handle();
    }

    /** @test */
    public function it_delegates_to_process_drive_pdf_action(): void
    {
        Log::shouldReceive('info')->atLeast()->once();
        Log::shouldReceive('error')->andReturn(null);
        Log::shouldReceive('warning')->andReturn(null);

        $mockAction = Mockery::mock(ProcessDrivePdfAction::class);
        $mockAction->shouldReceive('handle')
            ->once()
            ->with('file-process', 'process.pdf')
            ->andReturn(null);

        $this->app->instance(ProcessDrivePdfAction::class, $mockAction);

        $job = new ReprocessTextractJob('file-process', 'process.pdf', false);
        $job->handle();

        $this->addToAssertionCount(1);
    }

    /** @test */
    public function it_can_be_dispatched_to_queue(): void
    {
        Queue::fake();

        ReprocessTextractJob::dispatch('queue-file', 'queue.pdf', true);

        Queue::assertPushed(ReprocessTextractJob::class, function ($job) {
            return $job->driveFileId === 'queue-file'
                && $job->driveFileName === 'queue.pdf'
                && $job->forceTextract === true;
        });
    }

    /** @test */
    public function it_has_3_retry_attempts(): void
    {
        $job = new ReprocessTextractJob('file', 'file.pdf');

        $this->assertEquals(3, $job->tries);
    }

    /** @test */
    public function it_has_60_second_backoff(): void
    {
        $job = new ReprocessTextractJob('file', 'file.pdf');

        $this->assertEquals(60, $job->backoff);
    }

    /** @test */
    public function it_has_600_second_timeout(): void
    {
        $job = new ReprocessTextractJob('file', 'file.pdf');

        $this->assertEquals(600, $job->timeout);
    }

    /** @test */
    public function it_preserves_case_id_across_reprocessing(): void
    {
        $legalCase = LegalCase::factory()->create();

        $existingJob = TextractJob::create([
            'drive_file_id' => 'preserve-case',
            'drive_file_name' => 'preserve.pdf',
            'status' => 'succeeded',
            'case_id' => $legalCase->id,
        ]);

        $existingJobId = $existingJob->id;

        Log::shouldReceive('info')->atLeast()->once();
        Log::shouldReceive('error')->andReturn(null);
        Log::shouldReceive('warning')->andReturn(null);

        $mockAction = Mockery::mock(ProcessDrivePdfAction::class);
        $mockAction->shouldReceive('handle')
            ->once()
            ->with('preserve-case', 'preserve.pdf')
            ->andReturn(null);

        $this->app->instance(ProcessDrivePdfAction::class, $mockAction);

        $job = new ReprocessTextractJob('preserve-case', 'preserve.pdf', true);
        $job->handle();

        // Verify the existing job was hard-deleted
        $this->assertNull(TextractJob::find($existingJobId));

        // Verify new job was created with preserved case_id
        $newJob = TextractJob::where('drive_file_id', 'preserve-case')->first();
        $this->assertNotNull($newJob);
        $this->assertEquals($legalCase->id, $newJob->case_id);
    }
}
