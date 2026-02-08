<?php

namespace Tests\Unit\Jobs\Graph;

use App\Jobs\Graph\Neo4jDeadLetterJob;
use App\Jobs\Graph\RetryNeo4jOperationJob;
use App\Models\Neo4jRetryQueueItem;
use App\Services\GraphDatabaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laudis\Neo4j\Databags\Neo4jError;
use Laudis\Neo4j\Exception\Neo4jException;
use Mockery;
use Tests\TestCase;

class RetryNeo4jOperationJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    /**
     * Create a Neo4jException with proper error structure
     */
    protected function createNeo4jException(string $message): Neo4jException
    {
        $error = new Neo4jError(
            code: 'Neo.ClientError.General',
            message: $message,
            classification: 'ClientError',
            category: 'General',
            title: 'Test Error'
        );

        return new Neo4jException([$error]);
    }

    public function test_job_creates_queue_item_on_first_attempt(): void
    {
        $graphService = Mockery::mock(GraphDatabaseService::class);
        $graphService->shouldReceive('isAvailable')->andReturn(true);
        $graphService->shouldReceive('run')->once()->andThrow($this->createNeo4jException('Connection failed'));

        $this->app->instance(GraphDatabaseService::class, $graphService);

        $job = new RetryNeo4jOperationJob(
            operationType: 'query',
            payload: ['query' => 'MATCH (n) RETURN n', 'params' => []],
            entityType: 'Law',
            entityId: 123
        );

        try {
            $job->handle($graphService);
        } catch (Neo4jException $e) {
            // Expected to throw
        }

        $this->assertDatabaseHas('neo4j_retry_queue', [
            'operation_type' => 'query',
            'entity_type' => 'Law',
            'entity_id' => 123,
            'status' => 'retrying',
        ]);
    }

    public function test_job_throws_exception_when_neo4j_unavailable(): void
    {
        $graphService = Mockery::mock(GraphDatabaseService::class);
        $graphService->shouldReceive('isAvailable')->andReturn(false);

        $job = new RetryNeo4jOperationJob(
            operationType: 'query',
            payload: ['query' => 'MATCH (n) RETURN n']
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Neo4j is not available');

        $job->handle($graphService);
    }

    public function test_job_executes_query_operation_successfully(): void
    {
        $graphService = Mockery::mock(GraphDatabaseService::class);
        $graphService->shouldReceive('isAvailable')->andReturn(true);
        $graphService->shouldReceive('run')
            ->once()
            ->with('MATCH (n) RETURN n', ['id' => 1])
            ->andReturn(true);

        $this->app->instance(GraphDatabaseService::class, $graphService);

        $job = new RetryNeo4jOperationJob(
            operationType: 'query',
            payload: ['query' => 'MATCH (n) RETURN n', 'params' => ['id' => 1]]
        );

        $job->handle($graphService);

        $this->assertDatabaseHas('neo4j_retry_queue', [
            'operation_type' => 'query',
            'status' => 'completed',
        ]);
    }

    public function test_job_executes_upsert_node_operation(): void
    {
        $graphService = Mockery::mock(GraphDatabaseService::class);
        $graphService->shouldReceive('isAvailable')->andReturn(true);
        $graphService->shouldReceive('upsertNode')
            ->once()
            ->with('Law', '123', ['title' => 'Test Law'])
            ->andReturn(null);

        $this->app->instance(GraphDatabaseService::class, $graphService);

        $job = new RetryNeo4jOperationJob(
            operationType: 'upsert_node',
            payload: [
                'label' => 'Law',
                'id' => '123',
                'properties' => ['title' => 'Test Law'],
            ]
        );

        $job->handle($graphService);

        $this->assertDatabaseHas('neo4j_retry_queue', [
            'operation_type' => 'upsert_node',
            'status' => 'completed',
        ]);
    }

    public function test_job_executes_create_relationship_operation(): void
    {
        $graphService = Mockery::mock(GraphDatabaseService::class);
        $graphService->shouldReceive('isAvailable')->andReturn(true);
        $graphService->shouldReceive('createRelationship')
            ->once()
            ->with('Law', '1', 'CITES', 'Law', '2', [])
            ->andReturn(null);

        $this->app->instance(GraphDatabaseService::class, $graphService);

        $job = new RetryNeo4jOperationJob(
            operationType: 'create_relationship',
            payload: [
                'from_label' => 'Law',
                'from_id' => '1',
                'rel_type' => 'CITES',
                'to_label' => 'Law',
                'to_id' => '2',
                'properties' => [],
            ]
        );

        $job->handle($graphService);

        $this->assertDatabaseHas('neo4j_retry_queue', [
            'operation_type' => 'create_relationship',
            'status' => 'completed',
        ]);
    }

    public function test_job_has_correct_backoff_configuration(): void
    {
        $job = new RetryNeo4jOperationJob(
            operationType: 'query',
            payload: ['query' => 'MATCH (n) RETURN n']
        );

        $this->assertEquals(5, $job->tries);
        $this->assertEquals([60, 300, 900, 3600, 7200], $job->backoff);
    }

    public function test_failed_method_dispatches_to_dead_letter_queue(): void
    {
        Queue::fake();

        $queueItem = Neo4jRetryQueueItem::create([
            'operation_type' => 'query',
            'payload' => ['query' => 'MATCH (n) RETURN n'],
            'attempts' => 5,
            'max_attempts' => 5,
            'status' => 'retrying',
        ]);

        $job = new RetryNeo4jOperationJob(
            operationType: 'query',
            payload: ['query' => 'MATCH (n) RETURN n'],
            queueItemId: $queueItem->id
        );

        $exception = $this->createNeo4jException('Permanent failure');
        $job->failed($exception);

        Queue::assertPushed(Neo4jDeadLetterJob::class, function ($job) use ($queueItem) {
            return $job->queueItemId === $queueItem->id
                && $job->operationType === 'query'
                && str_contains($job->error, 'Permanent failure');
        });

        $queueItem->refresh();
        $this->assertEquals('failed', $queueItem->status);
        $this->assertStringContainsString('Permanent failure', $queueItem->last_error);

        $queueItem->refresh();
        $this->assertNotNull($queueItem->failed_at);
    }

    public function test_job_updates_attempts_on_retry(): void
    {
        $queueItem = Neo4jRetryQueueItem::create([
            'operation_type' => 'query',
            'payload' => ['query' => 'MATCH (n) RETURN n'],
            'attempts' => 2,
            'max_attempts' => 5,
            'status' => 'retrying',
        ]);

        $graphService = Mockery::mock(GraphDatabaseService::class);
        $graphService->shouldReceive('isAvailable')->andReturn(true);
        $graphService->shouldReceive('run')->once()->andThrow($this->createNeo4jException('Temporary failure'));

        $this->app->instance(GraphDatabaseService::class, $graphService);

        $job = new RetryNeo4jOperationJob(
            operationType: 'query',
            payload: ['query' => 'MATCH (n) RETURN n'],
            queueItemId: $queueItem->id
        );

        try {
            $job->handle($graphService);
        } catch (Neo4jException $e) {
            // Expected
        }

        $queueItem->refresh();
        $this->assertEquals('retrying', $queueItem->status);
        $this->assertStringContainsString('Temporary failure', $queueItem->last_error);
    }

    public function test_job_handles_unknown_operation_type(): void
    {
        $graphService = Mockery::mock(GraphDatabaseService::class);
        $graphService->shouldReceive('isAvailable')->andReturn(true);

        $job = new RetryNeo4jOperationJob(
            operationType: 'unknown_operation',
            payload: []
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown operation type: unknown_operation');

        $job->handle($graphService);
    }
}
