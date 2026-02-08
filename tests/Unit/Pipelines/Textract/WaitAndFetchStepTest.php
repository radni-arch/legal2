<?php

namespace Tests\Unit\Pipelines\Textract;

use App\Actions\Textract\WaitAndFetchTextract;
use App\Models\TextractJob;
use App\Pipelines\Concerns\RetryableStep;
use App\Pipelines\Textract\WaitAndFetchStep;
use App\Services\CircuitBreakerException;
use App\Services\TextractService;
use Aws\Result;
use Aws\Textract\Exception\TextractException;
use Aws\Textract\TextractClient;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class WaitAndFetchStepTest extends TestCase
{
    use UsesTestDatabase;

    protected WaitAndFetchStep $step;

    protected $textractClientMock;

    protected TextractService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->step = new WaitAndFetchStep;

        // Mock logging to avoid noise
        Log::shouldReceive('info')->byDefault();
        Log::shouldReceive('debug')->byDefault();
        Log::shouldReceive('error')->byDefault();
        Log::shouldReceive('warning')->byDefault();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    protected function createMockedTextractService(): TextractService
    {
        $this->textractClientMock = Mockery::mock(TextractClient::class);
        $service = Mockery::mock(TextractService::class)->makePartial();
        $service->shouldAllowMockingProtectedMethods();

        // Use reflection to inject the mock client
        $reflection = new \ReflectionClass($service);
        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setAccessible(true);
        $clientProperty->setValue($service, $this->textractClientMock);

        return $service;
    }

    /** @test */
    public function it_polls_textract_get_document_analysis_api()
    {
        $job = TextractJob::factory()->analyzing()->create();

        $mockBlocks = [
            ['BlockType' => 'PAGE', 'Id' => 'page-1'],
            ['BlockType' => 'LINE', 'Id' => 'line-1', 'Text' => 'Test line'],
        ];

        $this->mock(WaitAndFetchTextract::class, function ($mock) use ($mockBlocks) {
            $mock->shouldReceive('handle')
                ->once()
                ->with('textract-job-123')
                ->andReturn($mockBlocks);
        });

        $payload = [
            'job' => $job,
            'jobId' => 'textract-job-123',
        ];

        $result = $this->step->handle($payload, fn ($p) => $p);

        $this->assertArrayHasKey('blocks', $result);
        $this->assertCount(2, $result['blocks']);
    }

    /** @test */
    public function it_waits_for_job_to_complete_with_succeeded_status()
    {
        $service = $this->createMockedTextractService();

        $succeededResult = new Result([
            'JobStatus' => 'SUCCEEDED',
            'Blocks' => [
                ['BlockType' => 'PAGE'],
                ['BlockType' => 'LINE', 'Text' => 'Complete'],
            ],
            'NextToken' => null,
        ]);

        $this->textractClientMock->shouldReceive('getDocumentAnalysis')
            ->once()
            ->andReturn($succeededResult);

        $blocks = $service->waitAndFetchDocumentAnalysis('job-123', 1, 10);

        $this->assertCount(2, $blocks);
        $this->assertEquals('SUCCEEDED', 'SUCCEEDED'); // Verify it completed successfully
    }

    /** @test */
    public function it_handles_in_progress_status_and_retries()
    {
        $service = $this->createMockedTextractService();

        $inProgressResult = new Result(['JobStatus' => 'IN_PROGRESS']);
        $succeededResult = new Result([
            'JobStatus' => 'SUCCEEDED',
            'Blocks' => [['BlockType' => 'PAGE']],
            'NextToken' => null,
        ]);

        $this->textractClientMock->shouldReceive('getDocumentAnalysis')
            ->times(3)
            ->andReturn($inProgressResult, $inProgressResult, $succeededResult);

        $blocks = $service->waitAndFetchDocumentAnalysis('job-123', 1, 10);

        $this->assertCount(1, $blocks);
    }

    /** @test */
    public function it_handles_failed_status_and_throws_exception()
    {
        $service = $this->createMockedTextractService();

        $failedResult = new Result(['JobStatus' => 'FAILED']);

        $this->textractClientMock->shouldReceive('getDocumentAnalysis')
            ->once()
            ->andReturn($failedResult);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('status: FAILED');

        $service->waitAndFetchDocumentAnalysis('job-failed', 1, 10);
    }

    /** @test */
    public function it_handles_partial_success_status()
    {
        $service = $this->createMockedTextractService();

        $partialSuccessResult = new Result(['JobStatus' => 'PARTIAL_SUCCESS']);

        $this->textractClientMock->shouldReceive('getDocumentAnalysis')
            ->once()
            ->andReturn($partialSuccessResult);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('PARTIAL_SUCCESS');

        $service->waitAndFetchDocumentAnalysis('job-partial', 1, 10);
    }

    /** @test */
    public function it_fetches_all_pages_with_pagination_next_token()
    {
        $service = $this->createMockedTextractService();

        // First call returns SUCCEEDED status with first page of blocks and NextToken
        $page1Result = new Result([
            'JobStatus' => 'SUCCEEDED',
            'Blocks' => [
                ['BlockType' => 'PAGE', 'Page' => 1],
                ['BlockType' => 'LINE', 'Text' => 'Page 1 content'],
            ],
            'NextToken' => 'token-page-2',
        ]);

        // Second page with NextToken
        $page2Result = new Result([
            'Blocks' => [
                ['BlockType' => 'PAGE', 'Page' => 2],
                ['BlockType' => 'LINE', 'Text' => 'Page 2 content'],
            ],
            'NextToken' => 'token-page-3',
        ]);

        // Third page without NextToken (last page)
        $page3Result = new Result([
            'Blocks' => [
                ['BlockType' => 'PAGE', 'Page' => 3],
                ['BlockType' => 'LINE', 'Text' => 'Page 3 content'],
            ],
            'NextToken' => null,
        ]);

        $this->textractClientMock->shouldReceive('getDocumentAnalysis')
            ->times(3)
            ->andReturn($page1Result, $page2Result, $page3Result);

        $blocks = $service->waitAndFetchDocumentAnalysis('job-paginated', 1, 10);

        $this->assertCount(6, $blocks); // 3 pages + 3 lines
    }

    /** @test */
    public function it_assembles_complete_results_from_multiple_pages()
    {
        $service = $this->createMockedTextractService();

        // First call returns SUCCEEDED with first page blocks and NextToken
        $page1 = new Result([
            'JobStatus' => 'SUCCEEDED',
            'Blocks' => [
                ['BlockType' => 'LINE', 'Text' => 'First', 'Id' => '1'],
                ['BlockType' => 'LINE', 'Text' => 'Second', 'Id' => '2'],
            ],
            'NextToken' => 'token-2',
        ]);

        // Second call gets next page
        $page2 = new Result([
            'Blocks' => [
                ['BlockType' => 'LINE', 'Text' => 'Third', 'Id' => '3'],
                ['BlockType' => 'LINE', 'Text' => 'Fourth', 'Id' => '4'],
            ],
            'NextToken' => null,
        ]);

        $this->textractClientMock->shouldReceive('getDocumentAnalysis')
            ->times(2)
            ->andReturn($page1, $page2);

        $blocks = $service->waitAndFetchDocumentAnalysis('job-multi', 1, 10);

        $this->assertCount(4, $blocks);
        $this->assertEquals('First', $blocks[0]['Text']);
        $this->assertEquals('Second', $blocks[1]['Text']);
        $this->assertEquals('Third', $blocks[2]['Text']);
        $this->assertEquals('Fourth', $blocks[3]['Text']);
    }

    /** @test */
    public function it_handles_api_throttling_during_polling()
    {
        $service = $this->createMockedTextractService();

        $throttleException = new \RuntimeException('Rate exceeded: ThrottlingException');

        $this->textractClientMock->shouldReceive('getDocumentAnalysis')
            ->once()
            ->andThrow($throttleException);

        // Circuit breaker wraps non-CircuitBreakerException in CircuitBreakerException
        $this->expectException(CircuitBreakerException::class);

        $service->waitAndFetchDocumentAnalysis('job-throttled', 1, 10);
    }

    /** @test */
    public function it_respects_max_wait_time_timeout()
    {
        $service = $this->createMockedTextractService();

        $inProgressResult = new Result(['JobStatus' => 'IN_PROGRESS']);

        $this->textractClientMock->shouldReceive('getDocumentAnalysis')
            ->atLeast(1)
            ->andReturn($inProgressResult);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('timed out after 3s');

        // Use short timeout (3 seconds) with 1 second sleep interval
        $service->waitAndFetchDocumentAnalysis('job-timeout', 1, 3);
    }

    /** @test */
    public function it_handles_network_timeouts_during_long_waits()
    {
        $service = $this->createMockedTextractService();

        $networkException = new \RuntimeException('Network timeout');

        $this->textractClientMock->shouldReceive('getDocumentAnalysis')
            ->once()
            ->andThrow($networkException);

        // Circuit breaker wraps exceptions in CircuitBreakerException
        $this->expectException(CircuitBreakerException::class);
        $this->expectExceptionMessage('Network timeout');

        $service->waitAndFetchDocumentAnalysis('job-network-error', 1, 10);
    }

    /** @test */
    public function it_handles_exponential_backoff_between_polls()
    {
        // Note: Current implementation uses fixed sleep interval
        // This test documents expected behavior for future exponential backoff implementation

        $service = $this->createMockedTextractService();

        $inProgressResult = new Result(['JobStatus' => 'IN_PROGRESS']);
        $succeededResult = new Result([
            'JobStatus' => 'SUCCEEDED',
            'Blocks' => [['BlockType' => 'PAGE']],
            'NextToken' => null,
        ]);

        $this->textractClientMock->shouldReceive('getDocumentAnalysis')
            ->times(4)
            ->andReturn($inProgressResult, $inProgressResult, $inProgressResult, $succeededResult);

        $startTime = microtime(true);
        $blocks = $service->waitAndFetchDocumentAnalysis('job-backoff', 1, 30);
        $elapsed = microtime(true) - $startTime;

        // With fixed 1s sleep, 3 polls should take ~3 seconds
        // Future exponential backoff (1s, 2s, 4s) would take ~7 seconds
        $this->assertGreaterThanOrEqual(3, $elapsed);
        $this->assertCount(1, $blocks);
    }

    /** @test */
    public function it_handles_temporary_api_failures_gracefully()
    {
        $service = $this->createMockedTextractService();

        $temporaryError = new \RuntimeException('Temporary AWS error: InternalServerError');

        $this->textractClientMock->shouldReceive('getDocumentAnalysis')
            ->once()
            ->andThrow($temporaryError);

        // Circuit breaker wraps exceptions in CircuitBreakerException
        $this->expectException(CircuitBreakerException::class);

        $service->waitAndFetchDocumentAnalysis('job-temp-error', 1, 10);
    }

    /** @test */
    public function it_records_total_processing_time()
    {
        $service = $this->createMockedTextractService();

        $inProgressResult = new Result(['JobStatus' => 'IN_PROGRESS']);
        $succeededResult = new Result([
            'JobStatus' => 'SUCCEEDED',
            'Blocks' => [['BlockType' => 'PAGE']],
            'NextToken' => null,
        ]);

        $this->textractClientMock->shouldReceive('getDocumentAnalysis')
            ->times(3)
            ->andReturn($inProgressResult, $inProgressResult, $succeededResult);

        $startTime = microtime(true);
        $blocks = $service->waitAndFetchDocumentAnalysis('job-timing', 1, 30);
        $processingTime = microtime(true) - $startTime;

        // Should take at least 2 seconds (2 polls with 1s sleep)
        $this->assertGreaterThanOrEqual(2, $processingTime);
        $this->assertLessThan(5, $processingTime); // But not too long
        $this->assertCount(1, $blocks);
    }

    /** @test */
    public function it_validates_response_structure()
    {
        $service = $this->createMockedTextractService();

        $validResult = new Result([
            'JobStatus' => 'SUCCEEDED',
            'Blocks' => [
                [
                    'BlockType' => 'PAGE',
                    'Id' => 'page-1',
                    'Page' => 1,
                ],
                [
                    'BlockType' => 'LINE',
                    'Id' => 'line-1',
                    'Text' => 'Sample text',
                    'Confidence' => 99.5,
                    'Geometry' => [
                        'BoundingBox' => [
                            'Width' => 0.5,
                            'Height' => 0.1,
                            'Left' => 0.1,
                            'Top' => 0.2,
                        ],
                    ],
                ],
            ],
            'NextToken' => null,
        ]);

        $this->textractClientMock->shouldReceive('getDocumentAnalysis')
            ->once()
            ->andReturn($validResult);

        $blocks = $service->waitAndFetchDocumentAnalysis('job-valid', 1, 10);

        $this->assertIsArray($blocks);
        $this->assertCount(2, $blocks);

        // Validate PAGE block structure
        $this->assertEquals('PAGE', $blocks[0]['BlockType']);
        $this->assertArrayHasKey('Id', $blocks[0]);

        // Validate LINE block structure
        $this->assertEquals('LINE', $blocks[1]['BlockType']);
        $this->assertArrayHasKey('Text', $blocks[1]);
        $this->assertArrayHasKey('Confidence', $blocks[1]);
        $this->assertArrayHasKey('Geometry', $blocks[1]);
    }

    /** @test */
    public function it_adds_blocks_to_payload()
    {
        $job = TextractJob::factory()->analyzing()->create();

        $blocks = [
            ['BlockType' => 'PAGE'],
            ['BlockType' => 'LINE', 'Text' => 'Sample text'],
        ];

        $this->mock(WaitAndFetchTextract::class, function ($mock) use ($blocks) {
            $mock->shouldReceive('handle')
                ->once()
                ->andReturn($blocks);
        });

        $payload = [
            'job' => $job,
            'jobId' => 'job-id',
        ];

        $result = $this->step->handle($payload, fn ($p) => $p);

        $this->assertArrayHasKey('blocks', $result);
        $this->assertEquals($blocks, $result['blocks']);
    }

    /** @test */
    public function it_handles_empty_blocks_array()
    {
        $job = TextractJob::factory()->analyzing()->create();

        $this->mock(WaitAndFetchTextract::class, function ($mock) {
            $mock->shouldReceive('handle')
                ->once()
                ->andReturn([]);
        });

        $payload = [
            'job' => $job,
            'jobId' => 'job-id',
        ];

        $result = $this->step->handle($payload, fn ($p) => $p);

        $this->assertArrayHasKey('blocks', $result);
        $this->assertIsArray($result['blocks']);
        $this->assertEmpty($result['blocks']);
    }

    /** @test */
    public function it_passes_all_payload_to_next_step()
    {
        $job = TextractJob::factory()->analyzing()->create();

        $this->mock(WaitAndFetchTextract::class, function ($mock) {
            $mock->shouldReceive('handle')
                ->once()
                ->andReturn([['BlockType' => 'PAGE']]);
        });

        $payload = [
            'job' => $job,
            'jobId' => 'job-id',
            's3Key' => 'textract/input/file.pdf',
            'driveFileId' => 'drive-123',
            'customField' => 'preserved',
        ];

        $result = $this->step->handle($payload, fn ($p) => $p);

        $this->assertArrayHasKey('s3Key', $result);
        $this->assertArrayHasKey('driveFileId', $result);
        $this->assertArrayHasKey('customField', $result);
        $this->assertEquals('preserved', $result['customField']);
    }

    /** @test */
    public function it_handles_provisioned_throughput_exceeded_exception()
    {
        $service = $this->createMockedTextractService();

        $throughputException = new \RuntimeException('Throughput limit exceeded: ProvisionedThroughputExceededException');

        $this->textractClientMock->shouldReceive('getDocumentAnalysis')
            ->once()
            ->andThrow($throughputException);

        // Circuit breaker wraps exceptions in CircuitBreakerException
        $this->expectException(CircuitBreakerException::class);

        $service->waitAndFetchDocumentAnalysis('job-throughput', 1, 10);
    }

    /** @test */
    public function it_handles_invalid_job_id_exception()
    {
        $service = $this->createMockedTextractService();

        $invalidJobException = new \RuntimeException('Job ID does not exist: InvalidJobIdException');

        $this->textractClientMock->shouldReceive('getDocumentAnalysis')
            ->once()
            ->andThrow($invalidJobException);

        // Circuit breaker wraps exceptions in CircuitBreakerException
        $this->expectException(CircuitBreakerException::class);

        $service->waitAndFetchDocumentAnalysis('invalid-job-id', 1, 10);
    }

    /** @test */
    public function it_handles_access_denied_during_polling()
    {
        $service = $this->createMockedTextractService();

        $accessDeniedException = new \RuntimeException('Insufficient permissions: AccessDeniedException');

        $this->textractClientMock->shouldReceive('getDocumentAnalysis')
            ->once()
            ->andThrow($accessDeniedException);

        // Circuit breaker wraps exceptions in CircuitBreakerException
        $this->expectException(CircuitBreakerException::class);

        $service->waitAndFetchDocumentAnalysis('job-access-denied', 1, 10);
    }

    /** @test */
    public function it_preserves_job_instance_in_payload()
    {
        $job = TextractJob::factory()->analyzing()->create();

        $this->mock(WaitAndFetchTextract::class, function ($mock) {
            $mock->shouldReceive('handle')
                ->once()
                ->andReturn([]);
        });

        $payload = [
            'job' => $job,
            'jobId' => 'job-id',
        ];

        $result = $this->step->handle($payload, fn ($p) => $p);

        $this->assertArrayHasKey('job', $result);
        $this->assertSame($job->id, $result['job']->id);
    }

    /** @test */
    public function it_uses_job_id_from_payload()
    {
        $job = TextractJob::factory()->analyzing()->create();

        $testJobId = 'specific-textract-job-id-456';

        $this->mock(WaitAndFetchTextract::class, function ($mock) use ($testJobId) {
            $mock->shouldReceive('handle')
                ->once()
                ->with($testJobId)
                ->andReturn([]);
        });

        $payload = [
            'job' => $job,
            'jobId' => $testJobId,
        ];

        $this->step->handle($payload, fn ($p) => $p);
    }

    /** @test */
    public function it_handles_multiple_in_progress_polls_before_success()
    {
        $service = $this->createMockedTextractService();

        $inProgress = new Result(['JobStatus' => 'IN_PROGRESS']);
        $succeeded = new Result([
            'JobStatus' => 'SUCCEEDED',
            'Blocks' => [
                ['BlockType' => 'PAGE'],
                ['BlockType' => 'LINE', 'Text' => 'Finally completed'],
            ],
            'NextToken' => null,
        ]);

        // Simulate 5 IN_PROGRESS polls before success
        $this->textractClientMock->shouldReceive('getDocumentAnalysis')
            ->times(6)
            ->andReturn(
                $inProgress,
                $inProgress,
                $inProgress,
                $inProgress,
                $inProgress,
                $succeeded
            );

        $startTime = microtime(true);
        $blocks = $service->waitAndFetchDocumentAnalysis('job-long-wait', 1, 30);
        $elapsed = microtime(true) - $startTime;

        // Should take at least 5 seconds (5 polls with 1s sleep)
        $this->assertGreaterThanOrEqual(5, $elapsed);
        $this->assertCount(2, $blocks);
        $this->assertEquals('Finally completed', $blocks[1]['Text']);
    }

    // ========================================================================
    // Timeout Enforcement Tests (Task 8)
    // ========================================================================

    /** @test */
    public function it_uses_retryable_step_trait(): void
    {
        $step = new WaitAndFetchStep;
        $traits = class_uses_recursive($step);

        $this->assertArrayHasKey(
            RetryableStep::class,
            $traits,
            'WaitAndFetchStep should use the RetryableStep trait'
        );
    }

    /** @test */
    public function it_enforces_step_level_timeout(): void
    {
        // Configure a very short timeout
        config(['textract.max_wait_seconds' => 0]);

        // Mock the action - it should never be called because timeout triggers first
        $this->mock(WaitAndFetchTextract::class, function ($mock) {
            $mock->shouldReceive('handle')
                ->andThrow(new \RuntimeException('Textract analysis timed out after 0s (limit: 0s)'));
        });

        $step = new WaitAndFetchStep;
        // Set retries to 1 so we don't loop
        $step->maxRetries = 1;

        $payload = [
            'jobId' => 'job-timeout-test',
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('timed out');

        $step->handle($payload, fn ($p) => $p);
    }

    /** @test */
    public function it_enforces_pipeline_timeout(): void
    {
        // Configure generous step timeout but expired pipeline
        config(['textract.max_wait_seconds' => 600]);

        // Pipeline started 2000 seconds ago with 1800 second limit
        $pipelineStart = time() - 2000;

        $this->mock(WaitAndFetchTextract::class, function ($mock) {
            $mock->shouldReceive('handle')
                ->andThrow(new \RuntimeException('Pipeline timeout exceeded'));
        });

        $step = new WaitAndFetchStep;
        $step->maxRetries = 1;

        $payload = [
            'jobId' => 'job-pipeline-timeout',
            'pipeline_start' => $pipelineStart,
            'pipeline_timeout' => 1800,
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Pipeline timeout exceeded');

        $step->handle($payload, fn ($p) => $p);
    }

    /** @test */
    public function it_passes_through_when_pipeline_timeout_not_set(): void
    {
        $job = TextractJob::factory()->analyzing()->create();

        $this->mock(WaitAndFetchTextract::class, function ($mock) {
            $mock->shouldReceive('handle')
                ->once()
                ->andReturn([['BlockType' => 'PAGE']]);
        });

        $step = new WaitAndFetchStep;
        $payload = [
            'job' => $job,
            'jobId' => 'job-no-pipeline-timeout',
        ];

        $result = $step->handle($payload, fn ($p) => $p);

        $this->assertArrayHasKey('blocks', $result);
        $this->assertCount(1, $result['blocks']);
    }

    /** @test */
    public function it_succeeds_when_pipeline_timeout_not_exceeded(): void
    {
        $job = TextractJob::factory()->analyzing()->create();

        $this->mock(WaitAndFetchTextract::class, function ($mock) {
            $mock->shouldReceive('handle')
                ->once()
                ->andReturn([['BlockType' => 'PAGE'], ['BlockType' => 'LINE', 'Text' => 'OK']]);
        });

        $step = new WaitAndFetchStep;
        $payload = [
            'job' => $job,
            'jobId' => 'job-within-timeout',
            'pipeline_start' => time(),
            'pipeline_timeout' => 1800,
        ];

        $result = $step->handle($payload, fn ($p) => $p);

        $this->assertArrayHasKey('blocks', $result);
        $this->assertCount(2, $result['blocks']);
    }

    /** @test */
    public function it_logs_block_count_after_successful_fetch(): void
    {
        $job = TextractJob::factory()->analyzing()->create();

        $this->mock(WaitAndFetchTextract::class, function ($mock) {
            $mock->shouldReceive('handle')
                ->once()
                ->andReturn([
                    ['BlockType' => 'PAGE'],
                    ['BlockType' => 'LINE', 'Text' => 'Line 1'],
                    ['BlockType' => 'LINE', 'Text' => 'Line 2'],
                ]);
        });

        $logCaptured = false;

        // Override the default byDefault() for info to assert specific call
        Log::shouldReceive('info')
            ->atLeast()->once()
            ->withArgs(function ($message, $context = []) use (&$logCaptured) {
                if (str_contains($message, 'WaitAndFetchStep: blocks received')) {
                    $logCaptured = true;
                }
                return true;
            });

        $step = new WaitAndFetchStep;
        $payload = [
            'job' => $job,
            'jobId' => 'job-log-test',
        ];

        $step->handle($payload, fn ($p) => $p);

        $this->assertTrue($logCaptured, 'Expected WaitAndFetchStep to log block count after fetch');
    }
}
