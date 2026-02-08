<?php

namespace Tests\Unit\Console\Commands;

use App\Console\Commands\TextractCancelJob;
use App\Models\TextractJob;
use App\Services\TextractService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Tests\TestCase;

/**
 * Tests for TextractCancelJob command
 *
 * TDD - RED Phase
 */
class TextractCancelJobCommandTest extends TestCase
{
    use DatabaseTransactions;

    protected $textractServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        // Set AWS environment variables for testing
        config(['app.env' => 'testing']);
        putenv('AWS_DEFAULT_REGION=us-east-1');
        putenv('AWS_ACCESS_KEY_ID=test-key');
        putenv('AWS_SECRET_ACCESS_KEY=test-secret');
        putenv('AWS_BUCKET=test-bucket');

        $this->textractServiceMock = Mockery::mock(TextractService::class);
        $this->app->instance(TextractService::class, $this->textractServiceMock);
    }

    /** @test */
    public function it_cancels_job_in_progress(): void
    {
        // Create a job in progress
        $job = TextractJob::factory()->create([
            'case_id' => null,
            'job_id' => 'aws-job-123',
            'status' => 'in_progress',
            'drive_file_name' => 'test-document.pdf',
        ]);

        // Mock AWS Textract client stopDocumentAnalysis call
        $this->textractServiceMock
            ->shouldReceive('cancelJob')
            ->once()
            ->with($job->job_id)
            ->andReturn(true);

        // Run the command
        $this->artisan('textract:cancel-job', ['jobId' => $job->id])
            ->expectsOutput("Cancelling Textract job: {$job->id}")
            ->expectsOutput("AWS Job ID: {$job->job_id}")
            ->expectsOutput('Job cancelled successfully')
            ->assertExitCode(0);

        // Verify job status updated to cancelled
        $job->refresh();
        $this->assertEquals('cancelled', $job->status);
    }

    /** @test */
    public function it_handles_job_that_cannot_be_cancelled(): void
    {
        // Create a completed job (cannot be cancelled)
        $job = TextractJob::factory()->create([
            'case_id' => null,
            'job_id' => 'aws-job-456',
            'status' => 'succeeded',
            'drive_file_name' => 'completed-document.pdf',
        ]);

        // Run the command
        $this->artisan('textract:cancel-job', ['jobId' => $job->id])
            ->expectsOutput("Job {$job->id} has status 'succeeded' and cannot be cancelled")
            ->assertExitCode(1);

        // Verify job status unchanged
        $job->refresh();
        $this->assertEquals('succeeded', $job->status);
    }
}
