<?php

namespace Tests\Unit\Jobs;

use App\Actions\Textract\ProcessDrivePdf;
use App\Jobs\ProcessDrivePdfJob;
use App\Models\TextractJob;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class ProcessDrivePdfJobTest extends TestCase
{
    use UsesTestDatabase;

    private function bindProcessDrivePdfStub(callable $handler): void
    {
        $this->app->instance(ProcessDrivePdf::class, new class($handler)
        {
            private $handler;

            public function __construct(callable $handler)
            {
                $this->handler = $handler;
            }

            public function handle(string $driveFileId, string $driveFileName, bool $forceTextract = false): array
            {
                return ($this->handler)($driveFileId, $driveFileName, $forceTextract) ?? ['success' => true];
            }
        });
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_implements_should_queue_interface()
    {
        $job = new ProcessDrivePdfJob('file-id', 'test.pdf');

        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $job);
    }

    /** @test */
    public function it_stores_drive_file_id_and_name()
    {
        $job = new ProcessDrivePdfJob('file-123', 'document.pdf');

        $this->assertEquals('file-123', $job->driveFileId);
        $this->assertEquals('document.pdf', $job->driveFileName);
    }

    /** @test */
    public function it_delegates_to_process_drive_pdf_action()
    {
        $this->bindProcessDrivePdfStub(function ($fileId, $fileName, $force) {
            $this->assertEquals('file-123', $fileId);
            $this->assertEquals('test.pdf', $fileName);
            $this->assertFalse($force);

            return ['success' => true];
        });

        $job = new ProcessDrivePdfJob('file-123', 'test.pdf');
        $job->handle();
        $this->addToAssertionCount(1);
    }

    /** @test */
    public function it_updates_textract_job_status_on_failure()
    {
        $textractJob = TextractJob::create([
            'drive_file_id' => 'file-123',
            'drive_file_name' => 'test.pdf',
            'status' => 'pending',
        ]);

        $exception = new \Exception('Processing failed');

        $job = new ProcessDrivePdfJob('file-123', 'test.pdf');
        $job->failed($exception);

        $textractJob->refresh();
        $this->assertEquals('failed', $textractJob->status);
        $this->assertEquals('Processing failed', $textractJob->error);
    }

    /** @test */
    public function it_handles_missing_textract_job_on_failure()
    {
        // No TextractJob exists for this file
        $exception = new \Exception('Test error');

        $job = new ProcessDrivePdfJob('non-existent-file', 'test.pdf');

        // Should not throw exception even if job doesn't exist
        $job->failed($exception);

        $this->assertDatabaseMissing('textract_jobs', [
            'drive_file_id' => 'non-existent-file',
        ]);
    }

    /** @test */
    public function it_can_be_dispatched_to_queue()
    {
        Queue::fake();

        ProcessDrivePdfJob::dispatch('file-456', 'document.pdf');

        Queue::assertPushed(ProcessDrivePdfJob::class, function ($job) {
            return $job->driveFileId === 'file-456'
                && $job->driveFileName === 'document.pdf';
        });
    }

    /** @test */
    public function it_can_be_dispatched_with_delay()
    {
        Queue::fake();

        ProcessDrivePdfJob::dispatch('file-789', 'delayed.pdf')
            ->delay(now()->addMinutes(5));

        Queue::assertPushed(ProcessDrivePdfJob::class, function ($job) {
            return $job->driveFileId === 'file-789';
        });
    }

    /** @test */
    public function it_can_be_dispatched_to_specific_queue()
    {
        Queue::fake();

        ProcessDrivePdfJob::dispatch('file-101', 'queued.pdf')
            ->onQueue('high-priority');

        Queue::assertPushedOn('high-priority', ProcessDrivePdfJob::class);
    }

    /** @test */
    public function it_handles_action_exception_during_processing()
    {
        $this->bindProcessDrivePdfStub(function () {
            throw new \Exception('Action failed');
        });

        $job = new ProcessDrivePdfJob('file-error', 'error.pdf');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Action failed');

        $job->handle();
    }

    /** @test */
    public function it_serializes_correctly()
    {
        $job = new ProcessDrivePdfJob('file-serialize', 'serialize.pdf');

        $serialized = serialize($job);
        $unserialized = unserialize($serialized);

        $this->assertEquals('file-serialize', $unserialized->driveFileId);
        $this->assertEquals('serialize.pdf', $unserialized->driveFileName);
    }

    /** @test */
    public function it_uses_dispatchable_trait()
    {
        $reflection = new \ReflectionClass(ProcessDrivePdfJob::class);
        $traits = $reflection->getTraitNames();

        $this->assertContains('Illuminate\Foundation\Bus\Dispatchable', $traits);
    }

    /** @test */
    public function it_uses_interacts_with_queue_trait()
    {
        $reflection = new \ReflectionClass(ProcessDrivePdfJob::class);
        $traits = $reflection->getTraitNames();

        $this->assertContains('Illuminate\Queue\InteractsWithQueue', $traits);
    }

    /** @test */
    public function it_uses_queueable_trait()
    {
        $reflection = new \ReflectionClass(ProcessDrivePdfJob::class);
        $traits = $reflection->getTraitNames();

        $this->assertContains('Illuminate\Bus\Queueable', $traits);
    }

    /** @test */
    public function it_uses_serializes_models_trait()
    {
        $reflection = new \ReflectionClass(ProcessDrivePdfJob::class);
        $traits = $reflection->getTraitNames();

        $this->assertContains('Illuminate\Queue\SerializesModels', $traits);
    }

    /** @test */
    public function it_updates_only_matching_textract_job_on_failure()
    {
        // Create two jobs
        $job1 = TextractJob::create([
            'drive_file_id' => 'file-1',
            'drive_file_name' => 'doc1.pdf',
            'status' => 'processing',
        ]);

        $job2 = TextractJob::create([
            'drive_file_id' => 'file-2',
            'drive_file_name' => 'doc2.pdf',
            'status' => 'processing',
        ]);

        $exception = new \Exception('Failure');

        $job = new ProcessDrivePdfJob('file-1', 'doc1.pdf');
        $job->failed($exception);

        // Only job1 should be updated
        $job1->refresh();
        $job2->refresh();

        $this->assertEquals('failed', $job1->status);
        $this->assertEquals('processing', $job2->status);
    }

    /** @test */
    public function it_preserves_error_message_in_textract_job()
    {
        $textractJob = TextractJob::create([
            'drive_file_id' => 'file-error-msg',
            'drive_file_name' => 'error.pdf',
            'status' => 'pending',
        ]);

        $exception = new \Exception('Detailed error message with context');

        $job = new ProcessDrivePdfJob('file-error-msg', 'error.pdf');
        $job->failed($exception);

        $textractJob->refresh();
        $this->assertEquals('Detailed error message with context', $textractJob->error);
    }
}
