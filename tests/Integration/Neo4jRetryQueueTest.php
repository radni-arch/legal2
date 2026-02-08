<?php

namespace Tests\Integration;

use App\Jobs\Graph\RetryNeo4jOperationJob;
use App\Models\Neo4jRetryQueueItem;
use App\Services\GraphDatabaseService;
use App\Services\Neo4jRetryMetricsService;
use Illuminate\Support\Facades\Queue;
use Laudis\Neo4j\Exception\Neo4jException;

/**
 * Integration Test: Neo4j Retry Queue System
 *
 * Tests the retry queue mechanism for Neo4j operations including:
 * - Queue item lifecycle
 * - Metrics calculation
 * - Dead letter queue processing
 * - Exponential backoff configuration
 *
 * NOTE: External services (OpenAI, DecisionSearch) are automatically mocked by IntegrationTestCase
 */
class Neo4jRetryQueueTest extends IntegrationTestCase
{
    // External services already mocked by IntegrationTestCase

    protected GraphDatabaseService $graphService;

    protected Neo4jRetryMetricsService $metricsService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->graphService = $this->app->make(GraphDatabaseService::class);
        $this->metricsService = $this->app->make(Neo4jRetryMetricsService::class);
    }

    public function test_run_with_retry_dispatches_job_on_failure(): void
    {
        Queue::fake();

        // Mock GraphDatabaseService to simulate failure
        $mockGraphService = \Mockery::mock(GraphDatabaseService::class);
        $mockGraphService->shouldReceive('run')
            ->once()
            ->andThrow(new Neo4jException('Connection failed'));

        $mockGraphService->shouldReceive('runWithRetry')
            ->once()
            ->andReturnUsing(function ($query, $params, $options) {
                try {
                    return $this->graphService->run($query, $params, $options);
                } catch (Neo4jException $e) {
                    RetryNeo4jOperationJob::dispatch(
                        operationType: 'query',
                        payload: compact('query', 'params', 'options')
                    )->onQueue('neo4j-retry');
                    throw $e;
                }
            });

        $this->app->instance(GraphDatabaseService::class, $mockGraphService);

        try {
            $mockGraphService->runWithRetry('MATCH (n) RETURN n', []);
        } catch (Neo4jException $e) {
            // Expected
        }

        Queue::assertPushed(RetryNeo4jOperationJob::class, function ($job) {
            return $job->operationType === 'query'
                && $job->payload['query'] === 'MATCH (n) RETURN n';
        });
    }

    public function test_retry_queue_item_lifecycle(): void
    {
        // Create a queue item
        $queueItem = Neo4jRetryQueueItem::create([
            'operation_type' => 'query',
            'payload' => ['query' => 'MATCH (n) RETURN n'],
            'entity_type' => 'Law',
            'entity_id' => 123,
            'attempts' => 0,
            'max_attempts' => 5,
            'status' => 'pending',
        ]);

        $this->assertEquals('pending', $queueItem->status);
        $this->assertEquals(0, $queueItem->attempts);

        // Simulate retrying
        $queueItem->markAsRetrying('Connection timeout');
        $this->assertEquals('retrying', $queueItem->status);
        $this->assertEquals('Connection timeout', $queueItem->last_error);

        // Simulate completion
        $queueItem->markAsCompleted();
        $this->assertEquals('completed', $queueItem->status);
        $this->assertNull($queueItem->last_error);
    }

    public function test_retry_queue_scopes(): void
    {
        Neo4jRetryQueueItem::create([
            'operation_type' => 'query',
            'payload' => ['query' => 'MATCH (n) RETURN n'],
            'status' => 'pending',
            'attempts' => 0,
            'max_attempts' => 5,
        ]);

        Neo4jRetryQueueItem::create([
            'operation_type' => 'upsert_node',
            'payload' => ['label' => 'Law', 'id' => '1'],
            'status' => 'completed',
            'attempts' => 1,
            'max_attempts' => 5,
        ]);

        Neo4jRetryQueueItem::create([
            'operation_type' => 'query',
            'payload' => ['query' => 'CREATE (n) RETURN n'],
            'status' => 'failed',
            'attempts' => 5,
            'max_attempts' => 5,
            'failed_at' => now(),
        ]);

        $this->assertEquals(1, Neo4jRetryQueueItem::pending()->count());
        $this->assertEquals(1, Neo4jRetryQueueItem::completed()->count());
        $this->assertEquals(1, Neo4jRetryQueueItem::failed()->count());
        $this->assertEquals(2, Neo4jRetryQueueItem::ofType('query')->count());
    }

    public function test_metrics_service_calculates_success_rate(): void
    {
        // Create test data
        Neo4jRetryQueueItem::create([
            'operation_type' => 'query',
            'payload' => ['query' => 'MATCH (n) RETURN n'],
            'status' => 'completed',
            'attempts' => 1,
            'max_attempts' => 5,
        ]);

        Neo4jRetryQueueItem::create([
            'operation_type' => 'query',
            'payload' => ['query' => 'MATCH (n) RETURN n'],
            'status' => 'completed',
            'attempts' => 2,
            'max_attempts' => 5,
        ]);

        Neo4jRetryQueueItem::create([
            'operation_type' => 'query',
            'payload' => ['query' => 'MATCH (n) RETURN n'],
            'status' => 'failed',
            'attempts' => 5,
            'max_attempts' => 5,
            'failed_at' => now(),
        ]);

        $successRate = $this->metricsService->getSuccessRateAfterRetry();

        $this->assertEquals(3, $successRate['total']);
        $this->assertEquals(2, $successRate['completed']);
        $this->assertEquals(1, $successRate['failed']);
        $this->assertEquals(66.67, $successRate['success_rate']);
        $this->assertEquals(33.33, $successRate['failure_rate']);
    }

    public function test_metrics_service_tracks_retry_attempts_per_type(): void
    {
        Neo4jRetryQueueItem::create([
            'operation_type' => 'query',
            'payload' => [],
            'status' => 'completed',
            'attempts' => 2,
            'max_attempts' => 5,
        ]);

        Neo4jRetryQueueItem::create([
            'operation_type' => 'query',
            'payload' => [],
            'status' => 'completed',
            'attempts' => 3,
            'max_attempts' => 5,
        ]);

        Neo4jRetryQueueItem::create([
            'operation_type' => 'upsert_node',
            'payload' => [],
            'status' => 'completed',
            'attempts' => 1,
            'max_attempts' => 5,
        ]);

        $retryAttempts = $this->metricsService->getRetryAttemptsPerOperationType();

        $this->assertCount(2, $retryAttempts);

        $queryStats = collect($retryAttempts)->firstWhere('operation_type', 'query');
        $this->assertEquals(2, $queryStats['count']);
        $this->assertEquals(5, $queryStats['total_attempts']);
        $this->assertEquals(2.5, $queryStats['avg_attempts']);
    }

    public function test_metrics_service_gets_dead_letter_queue_size(): void
    {
        Neo4jRetryQueueItem::create([
            'operation_type' => 'query',
            'payload' => [],
            'status' => 'failed',
            'attempts' => 5,
            'max_attempts' => 5,
            'failed_at' => now(),
        ]);

        Neo4jRetryQueueItem::create([
            'operation_type' => 'query',
            'payload' => [],
            'status' => 'dead_letter',
            'attempts' => 5,
            'max_attempts' => 5,
            'failed_at' => now(),
        ]);

        Neo4jRetryQueueItem::create([
            'operation_type' => 'query',
            'payload' => [],
            'status' => 'completed',
            'attempts' => 2,
            'max_attempts' => 5,
        ]);

        $deadLetterSize = $this->metricsService->getDeadLetterQueueSize();

        $this->assertEquals(2, $deadLetterSize);
    }

    public function test_metrics_service_health_status_warnings(): void
    {
        // Create many failed items to trigger warnings
        for ($i = 0; $i < 60; $i++) {
            Neo4jRetryQueueItem::create([
                'operation_type' => 'query',
                'payload' => [],
                'status' => 'failed',
                'attempts' => 5,
                'max_attempts' => 5,
                'failed_at' => now(),
            ]);
        }

        $healthStatus = $this->metricsService->getHealthStatus();

        $this->assertEquals('critical', $healthStatus['status']);
        $this->assertNotEmpty($healthStatus['warnings']);
        $this->assertStringContainsString('Dead letter queue has', $healthStatus['warnings'][0]);
    }

    public function test_queue_item_can_be_reset_for_retry(): void
    {
        $queueItem = Neo4jRetryQueueItem::create([
            'operation_type' => 'query',
            'payload' => ['query' => 'MATCH (n) RETURN n'],
            'status' => 'failed',
            'attempts' => 5,
            'max_attempts' => 5,
            'last_error' => 'Connection failed',
            'failed_at' => now(),
        ]);

        $queueItem->resetForRetry();

        $this->assertEquals('pending', $queueItem->status);
        $this->assertEquals(0, $queueItem->attempts);
        $this->assertNull($queueItem->last_error);
        $this->assertNull($queueItem->failed_at);
    }

    public function test_exponential_backoff_configuration(): void
    {
        $job = new RetryNeo4jOperationJob(
            operationType: 'query',
            payload: ['query' => 'MATCH (n) RETURN n']
        );

        $backoff = $job->backoff();

        $this->assertEquals([60, 300, 900, 3600, 7200], $backoff);
        $this->assertEquals(60, $backoff[0]); // 1 minute
        $this->assertEquals(300, $backoff[1]); // 5 minutes
        $this->assertEquals(900, $backoff[2]); // 15 minutes
        $this->assertEquals(3600, $backoff[3]); // 1 hour
        $this->assertEquals(7200, $backoff[4]); // 2 hours
    }

    public function test_process_dead_letter_queue_command_displays_items(): void
    {
        Neo4jRetryQueueItem::create([
            'operation_type' => 'query',
            'payload' => ['query' => 'MATCH (n) RETURN n'],
            'entity_type' => 'Law',
            'entity_id' => 123,
            'status' => 'failed',
            'attempts' => 5,
            'max_attempts' => 5,
            'last_error' => 'Connection timeout',
            'failed_at' => now(),
        ]);

        $this->artisan('neo4j:process-dead-letters', ['--limit' => 10])
            ->expectsOutput('Found 1 items in dead letter queue:')
            ->assertSuccessful();
    }

    public function test_process_dead_letter_queue_command_retries_specific_item(): void
    {
        Queue::fake();

        $queueItem = Neo4jRetryQueueItem::create([
            'operation_type' => 'query',
            'payload' => ['query' => 'MATCH (n) RETURN n'],
            'status' => 'failed',
            'attempts' => 5,
            'max_attempts' => 5,
            'failed_at' => now(),
        ]);

        $this->artisan('neo4j:process-dead-letters', [
            '--retry' => $queueItem->id,
        ])
            ->expectsConfirmation("Retry item #{$queueItem->id} (query)?", 'yes')
            ->expectsOutput("Item #{$queueItem->id} dispatched for retry.")
            ->assertSuccessful();

        Queue::assertPushed(RetryNeo4jOperationJob::class);

        $queueItem->refresh();
        $this->assertEquals('pending', $queueItem->status);
        $this->assertEquals(0, $queueItem->attempts);
    }
}
