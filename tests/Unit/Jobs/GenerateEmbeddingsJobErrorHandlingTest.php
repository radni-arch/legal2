<?php

namespace Tests\Unit\Jobs;

use App\Events\JobFailed;
use App\Jobs\GenerateEmbeddingsJob;
use App\Models\EmbeddingBatch;
use App\Models\TextractJob;
use App\Models\User;
use App\Services\CourtDecisionVectorStoreService;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Error handling tests for GenerateEmbeddingsJob.
 *
 * These tests verify:
 * 1. The brace fix allows broadcast for batch source type (not just textract_job)
 * 2. Exceptions propagate for retry regardless of sourceType
 * 3. The failed() method correctly marks TextractJob as permanently failed
 * 4. The failed() method broadcasts failure events to users
 * 5. The failed() method handles missing sources gracefully
 */
class GenerateEmbeddingsJobErrorHandlingTest extends TestCase
{
    use UsesTestDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test 1: Batch failures broadcast to UI.
     *
     * Verifies the brace fix ensures broadcastFailed() is called for
     * batch source type, not just textract_job. Without the fix, the
     * broadcastFailed call would be trapped inside the textract_job
     * if-block and never execute for batch failures.
     */
    public function test_batch_failures_broadcast_to_ui(): void
    {
        Event::fake();

        Log::shouldReceive('error')->andReturn(null);
        Log::shouldReceive('info')->andReturn(null);
        Log::shouldReceive('warning')->andReturn(null);
        Log::shouldReceive('debug')->andReturn(null);

        $user = User::factory()->create();
        $batch = EmbeddingBatch::create([
            'id' => '10000001-0000-0000-0000-000000000001',
            'source_type' => 'batch',
            'status' => 'pending',
            'total_items' => 1,
        ]);

        // Create a subclass that forces processBatch to throw,
        // so we enter the catch block in handle() with sourceType='batch'
        $job = new class($batch->id, 'batch', null, $user->id) extends GenerateEmbeddingsJob {
            protected function processBatch($openai, $vectorStore): void
            {
                throw new \RuntimeException('Test batch processing error');
            }
        };

        $mockOpenAI = Mockery::mock(OpenAIService::class);
        $mockVectorStore = Mockery::mock(CourtDecisionVectorStoreService::class);

        try {
            $job->handle($mockOpenAI, $mockVectorStore);
            $this->fail('Expected RuntimeException was not thrown');
        } catch (\RuntimeException $e) {
            $this->assertEquals('Test batch processing error', $e->getMessage());
        }

        // Verify broadcastFailed was called for batch type (proves brace fix works)
        Event::assertDispatched(JobFailed::class, function (JobFailed $event) use ($user, $batch) {
            return $event->userId === $user->id
                && $event->jobId === 'batch_' . $batch->id
                && str_contains($event->error, 'Test batch processing error')
                && $event->stage === 'Embedding Generation';
        });
    }

    /**
     * Test 2: Batch failures trigger retry via exception propagation.
     *
     * Verifies that when handle() catches an exception for batch source type,
     * the exception is re-thrown to trigger Laravel's retry mechanism.
     * Without the brace fix, retry logic would be inside the textract_job block.
     */
    public function test_batch_failures_trigger_retry_via_exception(): void
    {
        Event::fake();

        Log::shouldReceive('error')->andReturn(null);
        Log::shouldReceive('info')->andReturn(null);
        Log::shouldReceive('warning')->andReturn(null);
        Log::shouldReceive('debug')->andReturn(null);

        $batch = EmbeddingBatch::create([
            'id' => '10000002-0000-0000-0000-000000000002',
            'source_type' => 'batch',
            'status' => 'pending',
            'total_items' => 1,
        ]);

        // Force processBatch to throw
        $job = new class($batch->id, 'batch') extends GenerateEmbeddingsJob {
            protected function processBatch($openai, $vectorStore): void
            {
                throw new \RuntimeException('Retry-triggering error');
            }
        };

        $mockOpenAI = Mockery::mock(OpenAIService::class);
        $mockVectorStore = Mockery::mock(CourtDecisionVectorStoreService::class);

        // Assert the exception is re-thrown (which triggers Laravel retry)
        // attempts() returns 0 when not in queue, tries is 2, so 0 < 2 = true => re-throw
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Retry-triggering error');

        $job->handle($mockOpenAI, $mockVectorStore);
    }

    /**
     * Test 3: failed() method marks TextractJob as permanently failed.
     *
     * Verifies that the failed() method (called by Laravel when all retries
     * are exhausted) updates the TextractJob embedding_status to 'failed'
     * with a descriptive error message including attempt count.
     */
    public function test_failed_method_marks_textract_job_as_permanently_failed(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'GenerateEmbeddingsJob - Permanently failed'
                    && $context['source_type'] === 'textract_job'
                    && $context['attempts'] === 2;
            });
        Log::shouldReceive('info')->andReturn(null);
        Log::shouldReceive('warning')->andReturn(null);
        Log::shouldReceive('debug')->andReturn(null);

        $textractJob = TextractJob::factory()->succeeded()->create([
            'embedding_status' => 'processing',
        ]);

        $exception = new \RuntimeException('OpenAI permanently unavailable');

        $job = new GenerateEmbeddingsJob($textractJob->id, 'textract_job');
        $job->failed($exception);

        $textractJob->refresh();
        $this->assertEquals('failed', $textractJob->embedding_status);
        $this->assertStringContainsString(
            'permanently failed after 2 attempts',
            $textractJob->error
        );
        $this->assertStringContainsString(
            'OpenAI permanently unavailable',
            $textractJob->error
        );
    }

    /**
     * Test 4: failed() method broadcasts failure to user.
     *
     * Verifies that the failed() method dispatches a JobFailed event
     * when a userId is set, so the UI can display the permanent failure.
     */
    public function test_failed_method_broadcasts_failure_to_user(): void
    {
        Event::fake([JobFailed::class]);

        Log::shouldReceive('error')->andReturn(null);
        Log::shouldReceive('info')->andReturn(null);
        Log::shouldReceive('warning')->andReturn(null);
        Log::shouldReceive('debug')->andReturn(null);

        $user = User::factory()->create();
        $textractJob = TextractJob::factory()->succeeded()->create([
            'embedding_status' => 'processing',
        ]);

        $exception = new \RuntimeException('Final failure');

        $job = new GenerateEmbeddingsJob($textractJob->id, 'textract_job', null, $user->id);
        $job->failed($exception);

        Event::assertDispatched(JobFailed::class, function (JobFailed $event) use ($user, $textractJob) {
            return $event->userId === $user->id
                && $event->jobId === 'textract_job_' . $textractJob->id
                && str_contains($event->error, 'Permanently failed after 2 attempts')
                && str_contains($event->error, 'Final failure')
                && $event->stage === 'Embedding Generation';
        });
    }

    /**
     * Test 5: failed() handles missing source gracefully.
     *
     * Verifies that the failed() method does not throw when
     * TextractJob::find() returns null (e.g., source was deleted
     * between job dispatch and final failure).
     */
    public function test_failed_method_handles_missing_source_gracefully(): void
    {
        $nonExistentId = 999999999;

        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message, $context) use ($nonExistentId) {
                return $message === 'GenerateEmbeddingsJob - Permanently failed'
                    && $context['source_type'] === 'textract_job'
                    && $context['source_id'] === $nonExistentId;
            });
        Log::shouldReceive('info')->andReturn(null);
        Log::shouldReceive('warning')->andReturn(null);
        Log::shouldReceive('debug')->andReturn(null);

        $exception = new \RuntimeException('Some error after source deleted');

        // Use a numeric ID that does not exist in the database
        $job = new GenerateEmbeddingsJob($nonExistentId, 'textract_job');

        // Should NOT throw even when TextractJob::find returns null
        $job->failed($exception);

        // If we reach this assertion, the method handled the missing source gracefully
        $this->assertTrue(true);
    }
}
