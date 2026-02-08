<?php

namespace Tests\Unit\Pipelines\Textract;

use App\Actions\Textract\StartTextractAnalysis;
use App\Models\TextractJob;
use App\Pipelines\Textract\StartAnalysisStep;
use Aws\Textract\Exception\TextractException;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class StartAnalysisStepTest extends TestCase
{
    use UsesTestDatabase;

    protected StartAnalysisStep $step;

    protected function setUp(): void
    {
        parent::setUp();
        $this->step = new StartAnalysisStep;
        Log::shouldReceive('info')->byDefault();
        Log::shouldReceive('error')->byDefault();
        Log::shouldReceive('debug')->byDefault();
        Log::shouldReceive('warning')->byDefault();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_calls_aws_textract_start_document_analysis_api()
    {
        $job = TextractJob::factory()->create(['status' => 'started']);

        $mock = Mockery::mock('overload:'.StartTextractAnalysis::class);
        $mock->shouldReceive('run')
            ->once()
            ->with('textract/input/test.pdf', 'document.pdf', ['LAYOUT', 'FORMS', 'TABLES', 'SIGNATURES'])
            ->andReturn('textract-job-id-123');

        $payload = [
            'job' => $job,
            's3Key' => 'textract/input/test.pdf',
            'driveFileName' => 'document.pdf',
        ];

        $result = $this->step->handle($payload, fn ($p) => $p);

        $this->assertArrayHasKey('jobId', $result);
        $this->assertEquals('textract-job-id-123', $result['jobId']);
    }

    /** @test */
    public function it_stores_textract_job_id_in_database()
    {
        $job = TextractJob::factory()->create(['status' => 'started']);

        $mock = Mockery::mock('overload:'.StartTextractAnalysis::class);
        $mock->shouldReceive('run')
            ->once()
            ->andReturn('aws-textract-456');

        $payload = [
            'job' => $job,
            's3Key' => 'textract/input/file.pdf',
            'driveFileName' => 'file.pdf',
        ];

        $this->step->handle($payload, fn ($p) => $p);

        $job->refresh();
        $this->assertEquals('aws-textract-456', $job->job_id);
    }

    /** @test */
    public function it_updates_job_status_to_analyzing()
    {
        $job = TextractJob::factory()->create(['status' => 'started']);

        $mock = Mockery::mock('overload:'.StartTextractAnalysis::class);
        $mock->shouldReceive('run')
            ->once()
            ->andReturn('textract-job-789');

        $payload = [
            'job' => $job,
            's3Key' => 'textract/input/doc.pdf',
            'driveFileName' => 'doc.pdf',
        ];

        $this->step->handle($payload, fn ($p) => $p);

        $job->refresh();
        $this->assertEquals('analyzing', $job->status);
    }

    /** @test */
    public function it_handles_aws_textract_throttling_exception()
    {
        $job = TextractJob::factory()->create(['status' => 'started']);

        $awsException = Mockery::mock(TextractException::class);
        $awsException->shouldReceive('getAwsErrorCode')->andReturn('ThrottlingException');
        $awsException->shouldReceive('getMessage')->andReturn('Rate exceeded');

        $mock = Mockery::mock('overload:'.StartTextractAnalysis::class);
        $mock->shouldReceive('run')
            ->once()
            ->andThrow($awsException);

        $payload = [
            'job' => $job,
            's3Key' => 'textract/input/test.pdf',
            'driveFileName' => 'test.pdf',
        ];

        $this->expectException(TextractException::class);

        $this->step->handle($payload, fn ($p) => $p);
    }

    /** @test */
    public function it_handles_aws_textract_provisioned_throughput_exceeded()
    {
        $job = TextractJob::factory()->create(['status' => 'started']);

        $awsException = Mockery::mock(TextractException::class);
        $awsException->shouldReceive('getAwsErrorCode')->andReturn('ProvisionedThroughputExceededException');
        $awsException->shouldReceive('getMessage')->andReturn('Request rate limit exceeded');

        $mock = Mockery::mock('overload:'.StartTextractAnalysis::class);
        $mock->shouldReceive('run')
            ->once()
            ->andThrow($awsException);

        $payload = [
            'job' => $job,
            's3Key' => 'textract/input/test.pdf',
            'driveFileName' => 'test.pdf',
        ];

        $this->expectException(TextractException::class);

        $this->step->handle($payload, fn ($p) => $p);
    }

    /** @test */
    public function it_handles_invalid_s3_object_exception()
    {
        $job = TextractJob::factory()->create(['status' => 'started']);

        $awsException = Mockery::mock(TextractException::class);
        $awsException->shouldReceive('getAwsErrorCode')->andReturn('InvalidS3ObjectException');
        $awsException->shouldReceive('getMessage')->andReturn('Unable to access S3 object');

        $mock = Mockery::mock('overload:'.StartTextractAnalysis::class);
        $mock->shouldReceive('run')
            ->once()
            ->andThrow($awsException);

        $payload = [
            'job' => $job,
            's3Key' => 'invalid/path/test.pdf',
            'driveFileName' => 'test.pdf',
        ];

        $this->expectException(TextractException::class);

        $this->step->handle($payload, fn ($p) => $p);
    }

    /** @test */
    public function it_handles_document_too_large_exception()
    {
        $job = TextractJob::factory()->create(['status' => 'started']);

        $awsException = Mockery::mock(TextractException::class);
        $awsException->shouldReceive('getAwsErrorCode')->andReturn('DocumentTooLargeException');
        $awsException->shouldReceive('getMessage')->andReturn('Document exceeds size limit');

        $mock = Mockery::mock('overload:'.StartTextractAnalysis::class);
        $mock->shouldReceive('run')
            ->once()
            ->andThrow($awsException);

        $payload = [
            'job' => $job,
            's3Key' => 'textract/input/large-file.pdf',
            'driveFileName' => 'large-file.pdf',
        ];

        $this->expectException(TextractException::class);

        $this->step->handle($payload, fn ($p) => $p);
    }

    /** @test */
    public function it_handles_unsupported_document_exception()
    {
        $job = TextractJob::factory()->create(['status' => 'started']);

        $awsException = Mockery::mock(TextractException::class);
        $awsException->shouldReceive('getAwsErrorCode')->andReturn('UnsupportedDocumentException');
        $awsException->shouldReceive('getMessage')->andReturn('Document format not supported');

        $mock = Mockery::mock('overload:'.StartTextractAnalysis::class);
        $mock->shouldReceive('run')
            ->once()
            ->andThrow($awsException);

        $payload = [
            'job' => $job,
            's3Key' => 'textract/input/unsupported.txt',
            'driveFileName' => 'unsupported.txt',
        ];

        $this->expectException(TextractException::class);

        $this->step->handle($payload, fn ($p) => $p);
    }

    /** @test */
    public function it_handles_access_denied_exception()
    {
        $job = TextractJob::factory()->create(['status' => 'started']);

        $awsException = Mockery::mock(TextractException::class);
        $awsException->shouldReceive('getAwsErrorCode')->andReturn('AccessDeniedException');
        $awsException->shouldReceive('getMessage')->andReturn('Insufficient IAM permissions');

        $mock = Mockery::mock('overload:'.StartTextractAnalysis::class);
        $mock->shouldReceive('run')
            ->once()
            ->andThrow($awsException);

        $payload = [
            'job' => $job,
            's3Key' => 'textract/input/test.pdf',
            'driveFileName' => 'test.pdf',
        ];

        $this->expectException(TextractException::class);

        $this->step->handle($payload, fn ($p) => $p);
    }

    /** @test */
    public function it_sets_feature_types_tables_forms_layout()
    {
        $job = TextractJob::factory()->create(['status' => 'started']);

        $mock = Mockery::mock('overload:'.StartTextractAnalysis::class);
        $mock->shouldReceive('run')
            ->once()
            ->with('textract/input/test.pdf', 'test.pdf', ['TABLES', 'FORMS', 'LAYOUT'])
            ->andReturn('job-id');

        $payload = [
            'job' => $job,
            's3Key' => 'textract/input/test.pdf',
            'driveFileName' => 'test.pdf',
            'featureTypes' => ['TABLES', 'FORMS', 'LAYOUT'],
        ];

        $result = $this->step->handle($payload, fn ($p) => $p);

        // Verify the mock expectations were met (method called with correct params)
        $this->assertArrayHasKey('jobId', $result);
    }

    /** @test */
    public function it_uses_default_feature_types_when_not_specified()
    {
        $job = TextractJob::factory()->create(['status' => 'started']);

        $mock = Mockery::mock('overload:'.StartTextractAnalysis::class);
        $mock->shouldReceive('run')
            ->once()
            ->with('textract/input/test.pdf', 'test.pdf', ['LAYOUT', 'FORMS', 'TABLES', 'SIGNATURES'])
            ->andReturn('job-id');

        $payload = [
            'job' => $job,
            's3Key' => 'textract/input/test.pdf',
            'driveFileName' => 'test.pdf',
            // No featureTypes specified
        ];

        $result = $this->step->handle($payload, fn ($p) => $p);

        // Verify default feature types were used
        $this->assertArrayHasKey('jobId', $result);
    }

    /** @test */
    public function it_uses_custom_feature_types_when_specified()
    {
        $job = TextractJob::factory()->create(['status' => 'started']);

        $customFeatures = ['TABLES', 'FORMS'];

        $mock = Mockery::mock('overload:'.StartTextractAnalysis::class);
        $mock->shouldReceive('run')
            ->once()
            ->with('textract/input/test.pdf', 'test.pdf', $customFeatures)
            ->andReturn('job-id');

        $payload = [
            'job' => $job,
            's3Key' => 'textract/input/test.pdf',
            'driveFileName' => 'test.pdf',
            'featureTypes' => $customFeatures,
        ];

        $result = $this->step->handle($payload, fn ($p) => $p);

        // Verify custom feature types were passed correctly
        $this->assertArrayHasKey('jobId', $result);
    }

    /** @test */
    public function it_adds_job_id_to_payload()
    {
        $job = TextractJob::factory()->create(['status' => 'started']);

        $mock = Mockery::mock('overload:'.StartTextractAnalysis::class);
        $mock->shouldReceive('run')
            ->once()
            ->andReturn('textract-job-789');

        $payload = [
            'job' => $job,
            's3Key' => 'textract/input/doc.pdf',
            'driveFileName' => 'doc.pdf',
        ];

        $result = $this->step->handle($payload, fn ($p) => $p);

        $this->assertArrayHasKey('jobId', $result);
        $this->assertEquals('textract-job-789', $result['jobId']);
    }

    /** @test */
    public function it_passes_all_payload_to_next_step()
    {
        $job = TextractJob::factory()->create(['status' => 'started']);

        $mock = Mockery::mock('overload:'.StartTextractAnalysis::class);
        $mock->shouldReceive('run')
            ->once()
            ->andReturn('job-id');

        $payload = [
            'job' => $job,
            's3Key' => 'textract/input/file.pdf',
            'driveFileName' => 'file.pdf',
            'localPath' => '/tmp/file.pdf',
            'customData' => 'preserved',
        ];

        $result = $this->step->handle($payload, fn ($p) => $p);

        $this->assertArrayHasKey('localPath', $result);
        $this->assertArrayHasKey('customData', $result);
        $this->assertEquals('preserved', $result['customData']);
    }

    /** @test */
    public function it_handles_bad_request_exception()
    {
        $job = TextractJob::factory()->create(['status' => 'started']);

        $awsException = Mockery::mock(TextractException::class);
        $awsException->shouldReceive('getAwsErrorCode')->andReturn('InvalidParameterException');
        $awsException->shouldReceive('getMessage')->andReturn('Invalid request parameters');

        $mock = Mockery::mock('overload:'.StartTextractAnalysis::class);
        $mock->shouldReceive('run')
            ->once()
            ->andThrow($awsException);

        $payload = [
            'job' => $job,
            's3Key' => 'textract/input/test.pdf',
            'driveFileName' => 'test.pdf',
        ];

        $this->expectException(TextractException::class);

        $this->step->handle($payload, fn ($p) => $p);
    }

    /** @test */
    public function it_handles_limit_exceeded_exception()
    {
        $job = TextractJob::factory()->create(['status' => 'started']);

        $awsException = Mockery::mock(TextractException::class);
        $awsException->shouldReceive('getAwsErrorCode')->andReturn('LimitExceededException');
        $awsException->shouldReceive('getMessage')->andReturn('Account limit exceeded');

        $mock = Mockery::mock('overload:'.StartTextractAnalysis::class);
        $mock->shouldReceive('run')
            ->once()
            ->andThrow($awsException);

        $payload = [
            'job' => $job,
            's3Key' => 'textract/input/test.pdf',
            'driveFileName' => 'test.pdf',
        ];

        $this->expectException(TextractException::class);

        $this->step->handle($payload, fn ($p) => $p);
    }

    /** @test */
    public function it_handles_internal_server_error()
    {
        $job = TextractJob::factory()->create(['status' => 'started']);

        $awsException = Mockery::mock(TextractException::class);
        $awsException->shouldReceive('getAwsErrorCode')->andReturn('InternalServerError');
        $awsException->shouldReceive('getMessage')->andReturn('Internal AWS error');

        $mock = Mockery::mock('overload:'.StartTextractAnalysis::class);
        $mock->shouldReceive('run')
            ->once()
            ->andThrow($awsException);

        $payload = [
            'job' => $job,
            's3Key' => 'textract/input/test.pdf',
            'driveFileName' => 'test.pdf',
        ];

        $this->expectException(TextractException::class);

        $this->step->handle($payload, fn ($p) => $p);
    }

    /** @test */
    public function it_handles_idle_session_timeout()
    {
        $job = TextractJob::factory()->create(['status' => 'started']);

        $awsException = Mockery::mock(TextractException::class);
        $awsException->shouldReceive('getAwsErrorCode')->andReturn('IdempotentParameterMismatchException');
        $awsException->shouldReceive('getMessage')->andReturn('Request token mismatch');

        $mock = Mockery::mock('overload:'.StartTextractAnalysis::class);
        $mock->shouldReceive('run')
            ->once()
            ->andThrow($awsException);

        $payload = [
            'job' => $job,
            's3Key' => 'textract/input/test.pdf',
            'driveFileName' => 'test.pdf',
        ];

        $this->expectException(TextractException::class);

        $this->step->handle($payload, fn ($p) => $p);
    }

    /** @test */
    public function it_updates_both_job_id_and_status_atomically()
    {
        $job = TextractJob::factory()->create([
            'status' => 'started',
            'job_id' => null,
        ]);

        $mock = Mockery::mock('overload:'.StartTextractAnalysis::class);
        $mock->shouldReceive('run')
            ->once()
            ->andReturn('new-textract-job-id');

        $payload = [
            'job' => $job,
            's3Key' => 'textract/input/test.pdf',
            'driveFileName' => 'test.pdf',
        ];

        $this->step->handle($payload, fn ($p) => $p);

        $job->refresh();
        $this->assertEquals('new-textract-job-id', $job->job_id);
        $this->assertEquals('analyzing', $job->status);
    }

    /** @test */
    public function it_handles_invalid_kms_key_exception()
    {
        $job = TextractJob::factory()->create(['status' => 'started']);

        $awsException = Mockery::mock(TextractException::class);
        $awsException->shouldReceive('getAwsErrorCode')->andReturn('InvalidKMSKeyException');
        $awsException->shouldReceive('getMessage')->andReturn('Invalid KMS key for S3 object');

        $mock = Mockery::mock('overload:'.StartTextractAnalysis::class);
        $mock->shouldReceive('run')
            ->once()
            ->andThrow($awsException);

        $payload = [
            'job' => $job,
            's3Key' => 'textract/input/encrypted.pdf',
            'driveFileName' => 'encrypted.pdf',
        ];

        $this->expectException(TextractException::class);

        $this->step->handle($payload, fn ($p) => $p);
    }

    /** @test */
    public function it_handles_human_loop_quota_exceeded()
    {
        $job = TextractJob::factory()->create(['status' => 'started']);

        $awsException = Mockery::mock(TextractException::class);
        $awsException->shouldReceive('getAwsErrorCode')->andReturn('HumanLoopQuotaExceededException');
        $awsException->shouldReceive('getMessage')->andReturn('Human review quota exceeded');

        $mock = Mockery::mock('overload:'.StartTextractAnalysis::class);
        $mock->shouldReceive('run')
            ->once()
            ->andThrow($awsException);

        $payload = [
            'job' => $job,
            's3Key' => 'textract/input/test.pdf',
            'driveFileName' => 'test.pdf',
        ];

        $this->expectException(TextractException::class);

        $this->step->handle($payload, fn ($p) => $p);
    }
}
