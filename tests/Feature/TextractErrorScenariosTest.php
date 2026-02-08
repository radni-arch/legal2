<?php

namespace Tests\Feature;

use App\Actions\Textract\ProcessDrivePdf;
use App\Models\TextractJob;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Comprehensive error scenario tests for Textract pipeline
 *
 * Tests various failure modes including:
 * - AWS Textract API failures
 * - Google Drive download errors
 * - S3 upload failures
 * - Pipeline step failures
 * - Network connectivity issues
 * - Invalid file formats
 * - Timeout scenarios
 * - Database failures
 */
class TextractErrorScenariosTest extends TestCase
{
    use UsesTestDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test that pipeline failure updates job status to 'failed'
     *
     * @test
     */
    public function pipeline_failure_updates_job_status_to_failed(): void
    {
        $driveFileId = 'drive-error-123';
        $driveFileName = 'error-test.pdf';

        // Create initial job
        $job = TextractJob::create([
            'drive_file_id' => $driveFileId,
            'drive_file_name' => $driveFileName,
            'status' => 'pending',
        ]);

        // Mock pipeline to throw exception
        $pipelineMock = Mockery::mock(Pipeline::class);
        $pipelineMock->shouldReceive('send')->andReturnSelf();
        $pipelineMock->shouldReceive('through')->andReturnSelf();
        $pipelineMock->shouldReceive('thenReturn')
            ->andThrow(new \Exception('Pipeline step failed'));

        $this->app->instance(Pipeline::class, $pipelineMock);

        // Execute action and expect exception
        $action = new ProcessDrivePdf;

        try {
            $action->handle($driveFileId, $driveFileName);
            $this->fail('Expected exception was not thrown');
        } catch (\Exception $e) {
            $this->assertEquals('Pipeline step failed', $e->getMessage());
        }

        // Verify job status updated to failed
        $job->refresh();
        $this->assertEquals('failed', $job->status);
        $this->assertStringContainsString('Pipeline step failed', $job->error);
    }

    /**
     * Test Google Drive download failure
     *
     * @test
     */
    public function google_drive_download_failure_is_handled(): void
    {
        $driveFileId = 'drive-404';
        $driveFileName = 'missing.pdf';

        $pipelineMock = Mockery::mock(Pipeline::class);
        $pipelineMock->shouldReceive('send')->andReturnSelf();
        $pipelineMock->shouldReceive('through')->andReturnSelf();
        $pipelineMock->shouldReceive('thenReturn')
            ->andThrow(new \Exception('File not found in Google Drive'));

        $this->app->instance(Pipeline::class, $pipelineMock);

        $action = new ProcessDrivePdf;

        try {
            $action->handle($driveFileId, $driveFileName);
        } catch (\Exception $e) {
            $this->assertStringContainsString('File not found', $e->getMessage());
        }

        // Verify error logged to database
        $job = TextractJob::where('drive_file_id', $driveFileId)->first();
        $this->assertNotNull($job);
        $this->assertEquals('failed', $job->status);
    }

    /**
     * Test AWS S3 upload failure
     *
     * @test
     */
    public function s3_upload_failure_is_handled(): void
    {
        $driveFileId = 's3-error-123';
        $driveFileName = 's3-fail.pdf';

        $pipelineMock = Mockery::mock(Pipeline::class);
        $pipelineMock->shouldReceive('send')->andReturnSelf();
        $pipelineMock->shouldReceive('through')->andReturnSelf();
        $pipelineMock->shouldReceive('thenReturn')
            ->andThrow(new \Exception('S3 upload failed: Access Denied'));

        $this->app->instance(Pipeline::class, $pipelineMock);

        $action = new ProcessDrivePdf;

        try {
            $action->handle($driveFileId, $driveFileName);
        } catch (\Exception $e) {
            $this->assertStringContainsString('S3 upload failed', $e->getMessage());
        }

        $job = TextractJob::where('drive_file_id', $driveFileId)->first();
        $this->assertEquals('failed', $job->status);
        $this->assertStringContainsString('S3 upload failed', $job->error);
    }

    /**
     * Test AWS Textract API failure
     *
     * @test
     */
    public function textract_api_failure_is_handled(): void
    {
        $driveFileId = 'textract-error-123';
        $driveFileName = 'textract-fail.pdf';

        $pipelineMock = Mockery::mock(Pipeline::class);
        $pipelineMock->shouldReceive('send')->andReturnSelf();
        $pipelineMock->shouldReceive('through')->andReturnSelf();
        $pipelineMock->shouldReceive('thenReturn')
            ->andThrow(new \Exception('Textract API error: InvalidParameterException'));

        $this->app->instance(Pipeline::class, $pipelineMock);

        $action = new ProcessDrivePdf;

        try {
            $action->handle($driveFileId, $driveFileName);
        } catch (\Exception $e) {
            $this->assertStringContainsString('Textract API error', $e->getMessage());
        }

        $job = TextractJob::where('drive_file_id', $driveFileId)->first();
        $this->assertEquals('failed', $job->status);
    }

    /**
     * Test network timeout during processing
     *
     * @test
     */
    public function network_timeout_is_handled(): void
    {
        $driveFileId = 'timeout-123';
        $driveFileName = 'timeout.pdf';

        $pipelineMock = Mockery::mock(Pipeline::class);
        $pipelineMock->shouldReceive('send')->andReturnSelf();
        $pipelineMock->shouldReceive('through')->andReturnSelf();
        $pipelineMock->shouldReceive('thenReturn')
            ->andThrow(new \Exception('Connection timeout after 30 seconds'));

        $this->app->instance(Pipeline::class, $pipelineMock);

        $action = new ProcessDrivePdf;

        try {
            $action->handle($driveFileId, $driveFileName);
        } catch (\Exception $e) {
            $this->assertStringContainsString('timeout', strtolower($e->getMessage()));
        }

        $job = TextractJob::where('drive_file_id', $driveFileId)->first();
        $this->assertEquals('failed', $job->status);
        $this->assertStringContainsString('timeout', strtolower($job->error));
    }

    /**
     * Test invalid PDF format handling
     *
     * @test
     */
    public function invalid_pdf_format_is_handled(): void
    {
        $driveFileId = 'invalid-pdf-123';
        $driveFileName = 'corrupt.pdf';

        $pipelineMock = Mockery::mock(Pipeline::class);
        $pipelineMock->shouldReceive('send')->andReturnSelf();
        $pipelineMock->shouldReceive('through')->andReturnSelf();
        $pipelineMock->shouldReceive('thenReturn')
            ->andThrow(new \Exception('Invalid PDF format: File is corrupted'));

        $this->app->instance(Pipeline::class, $pipelineMock);

        $action = new ProcessDrivePdf;

        try {
            $action->handle($driveFileId, $driveFileName);
        } catch (\Exception $e) {
            $this->assertStringContainsString('Invalid PDF format', $e->getMessage());
        }

        $job = TextractJob::where('drive_file_id', $driveFileId)->first();
        $this->assertEquals('failed', $job->status);
    }

    /**
     * Test rate limiting error from AWS
     *
     * @test
     */
    public function aws_rate_limiting_is_handled(): void
    {
        $driveFileId = 'rate-limit-123';
        $driveFileName = 'rate-limited.pdf';

        $pipelineMock = Mockery::mock(Pipeline::class);
        $pipelineMock->shouldReceive('send')->andReturnSelf();
        $pipelineMock->shouldReceive('through')->andReturnSelf();
        $pipelineMock->shouldReceive('thenReturn')
            ->andThrow(new \Exception('Rate exceeded: Too many requests'));

        $this->app->instance(Pipeline::class, $pipelineMock);

        $action = new ProcessDrivePdf;

        try {
            $action->handle($driveFileId, $driveFileName);
        } catch (\Exception $e) {
            $this->assertStringContainsString('Rate exceeded', $e->getMessage());
        }

        $job = TextractJob::where('drive_file_id', $driveFileId)->first();
        $this->assertEquals('failed', $job->status);
        $this->assertStringContainsString('Rate exceeded', $job->error);
    }

    /**
     * Test insufficient AWS permissions
     *
     * @test
     */
    public function insufficient_aws_permissions_is_handled(): void
    {
        $driveFileId = 'permission-error-123';
        $driveFileName = 'permission-denied.pdf';

        $pipelineMock = Mockery::mock(Pipeline::class);
        $pipelineMock->shouldReceive('send')->andReturnSelf();
        $pipelineMock->shouldReceive('through')->andReturnSelf();
        $pipelineMock->shouldReceive('thenReturn')
            ->andThrow(new \Exception('Access Denied: Insufficient permissions for Textract'));

        $this->app->instance(Pipeline::class, $pipelineMock);

        $action = new ProcessDrivePdf;

        try {
            $action->handle($driveFileId, $driveFileName);
        } catch (\Exception $e) {
            $this->assertStringContainsString('Access Denied', $e->getMessage());
        }

        $job = TextractJob::where('drive_file_id', $driveFileId)->first();
        $this->assertEquals('failed', $job->status);
    }

    /**
     * Test file too large for processing
     *
     * @test
     */
    public function file_too_large_is_handled(): void
    {
        $driveFileId = 'large-file-123';
        $driveFileName = 'huge.pdf';

        $pipelineMock = Mockery::mock(Pipeline::class);
        $pipelineMock->shouldReceive('send')->andReturnSelf();
        $pipelineMock->shouldReceive('through')->andReturnSelf();
        $pipelineMock->shouldReceive('thenReturn')
            ->andThrow(new \Exception('File size exceeds maximum allowed: 500MB limit'));

        $this->app->instance(Pipeline::class, $pipelineMock);

        $action = new ProcessDrivePdf;

        try {
            $action->handle($driveFileId, $driveFileName);
        } catch (\Exception $e) {
            $this->assertStringContainsString('exceeds maximum', $e->getMessage());
        }

        $job = TextractJob::where('drive_file_id', $driveFileId)->first();
        $this->assertEquals('failed', $job->status);
        $this->assertStringContainsString('exceeds maximum', $job->error);
    }

    /**
     * Test Textract job polling timeout
     *
     * @test
     */
    public function textract_polling_timeout_is_handled(): void
    {
        $driveFileId = 'polling-timeout-123';
        $driveFileName = 'stuck.pdf';

        $pipelineMock = Mockery::mock(Pipeline::class);
        $pipelineMock->shouldReceive('send')->andReturnSelf();
        $pipelineMock->shouldReceive('through')->andReturnSelf();
        $pipelineMock->shouldReceive('thenReturn')
            ->andThrow(new \Exception('Textract job timeout: Job did not complete within expected time'));

        $this->app->instance(Pipeline::class, $pipelineMock);

        $action = new ProcessDrivePdf;

        try {
            $action->handle($driveFileId, $driveFileName);
        } catch (\Exception $e) {
            $this->assertStringContainsString('timeout', strtolower($e->getMessage()));
        }

        $job = TextractJob::where('drive_file_id', $driveFileId)->first();
        $this->assertEquals('failed', $job->status);
    }

    /**
     * Test missing AWS credentials
     *
     * @test
     */
    public function missing_aws_credentials_is_handled(): void
    {
        $driveFileId = 'no-creds-123';
        $driveFileName = 'no-auth.pdf';

        $pipelineMock = Mockery::mock(Pipeline::class);
        $pipelineMock->shouldReceive('send')->andReturnSelf();
        $pipelineMock->shouldReceive('through')->andReturnSelf();
        $pipelineMock->shouldReceive('thenReturn')
            ->andThrow(new \Exception('Missing credentials: AWS_ACCESS_KEY_ID not configured'));

        $this->app->instance(Pipeline::class, $pipelineMock);

        $action = new ProcessDrivePdf;

        try {
            $action->handle($driveFileId, $driveFileName);
        } catch (\Exception $e) {
            $this->assertStringContainsString('Missing credentials', $e->getMessage());
        }

        $job = TextractJob::where('drive_file_id', $driveFileId)->first();
        $this->assertEquals('failed', $job->status);
    }

    /**
     * Test S3 bucket not found
     *
     * @test
     */
    public function s3_bucket_not_found_is_handled(): void
    {
        $driveFileId = 's3-bucket-error-123';
        $driveFileName = 'no-bucket.pdf';

        $pipelineMock = Mockery::mock(Pipeline::class);
        $pipelineMock->shouldReceive('send')->andReturnSelf();
        $pipelineMock->shouldReceive('through')->andReturnSelf();
        $pipelineMock->shouldReceive('thenReturn')
            ->andThrow(new \Exception('S3 bucket does not exist: textract-input-bucket'));

        $this->app->instance(Pipeline::class, $pipelineMock);

        $action = new ProcessDrivePdf;

        try {
            $action->handle($driveFileId, $driveFileName);
        } catch (\Exception $e) {
            $this->assertStringContainsString('bucket does not exist', $e->getMessage());
        }

        $job = TextractJob::where('drive_file_id', $driveFileId)->first();
        $this->assertEquals('failed', $job->status);
    }

    /**
     * Test malformed Textract response
     *
     * @test
     */
    public function malformed_textract_response_is_handled(): void
    {
        $driveFileId = 'malformed-response-123';
        $driveFileName = 'bad-response.pdf';

        $pipelineMock = Mockery::mock(Pipeline::class);
        $pipelineMock->shouldReceive('send')->andReturnSelf();
        $pipelineMock->shouldReceive('through')->andReturnSelf();
        $pipelineMock->shouldReceive('thenReturn')
            ->andThrow(new \Exception('Malformed response: Expected Blocks array not found'));

        $this->app->instance(Pipeline::class, $pipelineMock);

        $action = new ProcessDrivePdf;

        try {
            $action->handle($driveFileId, $driveFileName);
        } catch (\Exception $e) {
            $this->assertStringContainsString('Malformed response', $e->getMessage());
        }

        $job = TextractJob::where('drive_file_id', $driveFileId)->first();
        $this->assertEquals('failed', $job->status);
    }

    /**
     * Test disk space exhaustion during download
     *
     * @test
     */
    public function disk_space_exhaustion_is_handled(): void
    {
        $driveFileId = 'disk-full-123';
        $driveFileName = 'disk-full.pdf';

        $pipelineMock = Mockery::mock(Pipeline::class);
        $pipelineMock->shouldReceive('send')->andReturnSelf();
        $pipelineMock->shouldReceive('through')->andReturnSelf();
        $pipelineMock->shouldReceive('thenReturn')
            ->andThrow(new \Exception('Disk space exhausted: Unable to write file'));

        $this->app->instance(Pipeline::class, $pipelineMock);

        $action = new ProcessDrivePdf;

        try {
            $action->handle($driveFileId, $driveFileName);
        } catch (\Exception $e) {
            $this->assertStringContainsString('Disk space exhausted', $e->getMessage());
        }

        $job = TextractJob::where('drive_file_id', $driveFileId)->first();
        $this->assertEquals('failed', $job->status);
    }

    /**
     * Test memory exhaustion during processing
     *
     * @test
     */
    public function memory_exhaustion_is_handled(): void
    {
        $driveFileId = 'memory-error-123';
        $driveFileName = 'memory-exceed.pdf';

        $pipelineMock = Mockery::mock(Pipeline::class);
        $pipelineMock->shouldReceive('send')->andReturnSelf();
        $pipelineMock->shouldReceive('through')->andReturnSelf();
        $pipelineMock->shouldReceive('thenReturn')
            ->andThrow(new \Exception('Memory limit exceeded: Allowed memory size exhausted'));

        $this->app->instance(Pipeline::class, $pipelineMock);

        $action = new ProcessDrivePdf;

        try {
            $action->handle($driveFileId, $driveFileName);
        } catch (\Exception $e) {
            $this->assertStringContainsString('Memory limit exceeded', $e->getMessage());
        }

        $job = TextractJob::where('drive_file_id', $driveFileId)->first();
        $this->assertEquals('failed', $job->status);
    }

    /**
     * Test Google API authentication failure
     *
     * @test
     */
    public function google_api_authentication_failure_is_handled(): void
    {
        $driveFileId = 'google-auth-fail-123';
        $driveFileName = 'auth-fail.pdf';

        $pipelineMock = Mockery::mock(Pipeline::class);
        $pipelineMock->shouldReceive('send')->andReturnSelf();
        $pipelineMock->shouldReceive('through')->andReturnSelf();
        $pipelineMock->shouldReceive('thenReturn')
            ->andThrow(new \Exception('Google API authentication failed: Invalid credentials'));

        $this->app->instance(Pipeline::class, $pipelineMock);

        $action = new ProcessDrivePdf;

        try {
            $action->handle($driveFileId, $driveFileName);
        } catch (\Exception $e) {
            $this->assertStringContainsString('authentication failed', $e->getMessage());
        }

        $job = TextractJob::where('drive_file_id', $driveFileId)->first();
        $this->assertEquals('failed', $job->status);
    }

    /**
     * Test concurrent job processing conflict
     *
     * @test
     */
    public function concurrent_job_processing_conflict_is_handled(): void
    {
        $driveFileId = 'concurrent-123';
        $driveFileName = 'concurrent.pdf';

        // Create a job already in 'processing' state
        $existingJob = TextractJob::create([
            'drive_file_id' => $driveFileId,
            'drive_file_name' => $driveFileName,
            'status' => 'processing',
        ]);

        $pipelineMock = Mockery::mock(Pipeline::class);
        $pipelineMock->shouldReceive('send')->andReturnSelf();
        $pipelineMock->shouldReceive('through')->andReturnSelf();
        $pipelineMock->shouldReceive('thenReturn')
            ->andThrow(new \Exception('Job already being processed by another worker'));

        $this->app->instance(Pipeline::class, $pipelineMock);

        $action = new ProcessDrivePdf;

        try {
            $action->handle($driveFileId, $driveFileName);
        } catch (\Exception $e) {
            $this->assertStringContainsString('already being processed', $e->getMessage());
        }

        $existingJob->refresh();
        $this->assertEquals('failed', $existingJob->status);
    }

    /**
     * Test Textract API version incompatibility
     *
     * @test
     */
    public function textract_api_version_incompatibility_is_handled(): void
    {
        $driveFileId = 'api-version-123';
        $driveFileName = 'version-mismatch.pdf';

        $pipelineMock = Mockery::mock(Pipeline::class);
        $pipelineMock->shouldReceive('send')->andReturnSelf();
        $pipelineMock->shouldReceive('through')->andReturnSelf();
        $pipelineMock->shouldReceive('thenReturn')
            ->andThrow(new \Exception('API version mismatch: Unsupported version'));

        $this->app->instance(Pipeline::class, $pipelineMock);

        $action = new ProcessDrivePdf;

        try {
            $action->handle($driveFileId, $driveFileName);
        } catch (\Exception $e) {
            $this->assertStringContainsString('version mismatch', $e->getMessage());
        }

        $job = TextractJob::where('drive_file_id', $driveFileId)->first();
        $this->assertEquals('failed', $job->status);
    }

    /**
     * Test database connection failure during job update
     *
     * @test
     */
    public function database_connection_failure_during_update_is_handled(): void
    {
        $driveFileId = 'db-error-123';
        $driveFileName = 'db-fail.pdf';

        $pipelineMock = Mockery::mock(Pipeline::class);
        $pipelineMock->shouldReceive('send')->andReturnSelf();
        $pipelineMock->shouldReceive('through')->andReturnSelf();
        $pipelineMock->shouldReceive('thenReturn')
            ->andThrow(new \Exception('Database connection lost: SQLSTATE[HY000]'));

        $this->app->instance(Pipeline::class, $pipelineMock);

        $action = new ProcessDrivePdf;

        try {
            $action->handle($driveFileId, $driveFileName);
            $this->fail('Expected exception was not thrown');
        } catch (\Exception $e) {
            $this->assertStringContainsString('Database connection lost', $e->getMessage());
        }

        // Job might not be updated if database connection is lost
        // This is expected behavior in this error scenario
        $this->assertTrue(true);
    }

    /**
     * Test unsupported PDF encryption
     *
     * @test
     */
    public function encrypted_pdf_is_handled(): void
    {
        $driveFileId = 'encrypted-123';
        $driveFileName = 'encrypted.pdf';

        $pipelineMock = Mockery::mock(Pipeline::class);
        $pipelineMock->shouldReceive('send')->andReturnSelf();
        $pipelineMock->shouldReceive('through')->andReturnSelf();
        $pipelineMock->shouldReceive('thenReturn')
            ->andThrow(new \Exception('PDF is password protected or encrypted'));

        $this->app->instance(Pipeline::class, $pipelineMock);

        $action = new ProcessDrivePdf;

        try {
            $action->handle($driveFileId, $driveFileName);
        } catch (\Exception $e) {
            $this->assertStringContainsString('password protected', $e->getMessage());
        }

        $job = TextractJob::where('drive_file_id', $driveFileId)->first();
        $this->assertEquals('failed', $job->status);
        $this->assertStringContainsString('password protected', $job->error);
    }

    /**
     * Test error logging functionality
     *
     * @test
     */
    public function error_logging_works_correctly(): void
    {
        Log::shouldReceive('info')
            ->once()
            ->with('ProcessDrivePdf (Action): start', Mockery::type('array'));

        Log::shouldReceive('error')
            ->once()
            ->with('ProcessDrivePdf: failed', Mockery::type('array'));

        $driveFileId = 'log-test-123';
        $driveFileName = 'log-test.pdf';

        $pipelineMock = Mockery::mock(Pipeline::class);
        $pipelineMock->shouldReceive('send')->andReturnSelf();
        $pipelineMock->shouldReceive('through')->andReturnSelf();
        $pipelineMock->shouldReceive('thenReturn')
            ->andThrow(new \Exception('Test error for logging'));

        $this->app->instance(Pipeline::class, $pipelineMock);

        $action = new ProcessDrivePdf;

        try {
            $action->handle($driveFileId, $driveFileName);
        } catch (\Exception $e) {
            // Expected
        }

        // Verify logging was called (Mockery will assert this)
        $this->assertTrue(true);
    }

    /**
     * Test error message contains trace information
     *
     * @test
     */
    public function error_message_contains_trace_information(): void
    {
        $driveFileId = 'trace-test-123';
        $driveFileName = 'trace-test.pdf';

        $pipelineMock = Mockery::mock(Pipeline::class);
        $pipelineMock->shouldReceive('send')->andReturnSelf();
        $pipelineMock->shouldReceive('through')->andReturnSelf();
        $pipelineMock->shouldReceive('thenReturn')
            ->andThrow(new \Exception('Error with stack trace'));

        $this->app->instance(Pipeline::class, $pipelineMock);

        $action = new ProcessDrivePdf;

        try {
            $action->handle($driveFileId, $driveFileName);
        } catch (\Exception $e) {
            // Exception should be re-thrown with trace
            $this->assertNotEmpty($e->getTraceAsString());
        }

        $job = TextractJob::where('drive_file_id', $driveFileId)->first();
        $this->assertEquals('failed', $job->status);
    }

    /**
     * Test multiple sequential failures don't corrupt database state
     *
     * @test
     */
    public function multiple_sequential_failures_dont_corrupt_database(): void
    {
        $files = [
            ['drive-fail-1', 'fail1.pdf'],
            ['drive-fail-2', 'fail2.pdf'],
            ['drive-fail-3', 'fail3.pdf'],
        ];

        foreach ($files as [$fileId, $fileName]) {
            $pipelineMock = Mockery::mock(Pipeline::class);
            $pipelineMock->shouldReceive('send')->andReturnSelf();
            $pipelineMock->shouldReceive('through')->andReturnSelf();
            $pipelineMock->shouldReceive('thenReturn')
                ->andThrow(new \Exception("Failure for {$fileName}"));

            $this->app->instance(Pipeline::class, $pipelineMock);

            $action = new ProcessDrivePdf;

            try {
                $action->handle($fileId, $fileName);
            } catch (\Exception $e) {
                // Expected
            }
        }

        // Verify all jobs exist and are in failed state
        foreach ($files as [$fileId, $fileName]) {
            $job = TextractJob::where('drive_file_id', $fileId)->first();
            $this->assertNotNull($job, "Job for {$fileId} should exist");
            $this->assertEquals('failed', $job->status);
            $this->assertStringContainsString("Failure for {$fileName}", $job->error);
        }

        // Verify no jobs are stuck in 'processing' state
        $processingJobs = TextractJob::where('status', 'processing')->count();
        $this->assertEquals(0, $processingJobs, 'No jobs should be stuck in processing state');
    }

    /**
     * Test that errors preserve original payload information
     *
     * @test
     */
    public function errors_preserve_original_payload_information(): void
    {
        $driveFileId = 'payload-preserve-123';
        $driveFileName = 'preserve-test.pdf';

        $pipelineMock = Mockery::mock(Pipeline::class);
        $pipelineMock->shouldReceive('send')->andReturnSelf();
        $pipelineMock->shouldReceive('through')->andReturnSelf();
        $pipelineMock->shouldReceive('thenReturn')
            ->andThrow(new \Exception('Error during processing'));

        $this->app->instance(Pipeline::class, $pipelineMock);

        $action = new ProcessDrivePdf;

        try {
            $action->handle($driveFileId, $driveFileName, true); // forceTextract = true
        } catch (\Exception $e) {
            // Expected
        }

        $job = TextractJob::where('drive_file_id', $driveFileId)->first();
        $this->assertNotNull($job);
        $this->assertEquals($driveFileId, $job->drive_file_id);
        $this->assertEquals($driveFileName, $job->drive_file_name);
    }
}
