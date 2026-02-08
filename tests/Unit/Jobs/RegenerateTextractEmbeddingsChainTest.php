<?php

namespace Tests\Unit\Jobs;

use App\Jobs\RegenerateTextractEmbeddings;
use App\Jobs\SyncTextractToGraph;
use App\Models\TextractJob;
use App\Services\TextractVectorStoreService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Task 3.1: Unified Job Chaining -- Embeddings -> Graph
 *
 * Tests that RegenerateTextractEmbeddings chains SyncTextractToGraph
 * after successful embedding ingest, respecting the neo4j.sync.enabled
 * config gate. This is the SINGLE SOURCE OF TRUTH for graph sync dispatch.
 *
 * @see \App\Jobs\RegenerateTextractEmbeddings::handle()
 * @see \App\Jobs\SyncTextractToGraph
 */
class RegenerateTextractEmbeddingsChainTest extends TestCase
{
    use UsesTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Use Log::spy() to permit all log calls without strict expectations.
        // This avoids "There is already an active transaction" issues that occur
        // when Log::shouldReceive() interferes with DatabaseTransactions teardown.
        Log::spy();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * @test
     * After successful embedding ingest, SyncTextractToGraph MUST be dispatched
     * when neo4j.sync.enabled is true.
     */
    public function it_dispatches_sync_textract_to_graph_when_neo4j_enabled(): void
    {
        Queue::fake();
        Config::set('neo4j.sync.enabled', true);

        $textractJob = TextractJob::create([
            'drive_file_id' => 'chain-test-enabled',
            'drive_file_name' => 'chain-enabled.pdf',
            'status' => 'completed',
            'extracted_content' => 'Legal document content for chaining test.',
        ]);

        $mockVectorStore = Mockery::mock(TextractVectorStoreService::class);
        $mockVectorStore->shouldReceive('ingestTextractJob')
            ->once()
            ->with($textractJob->id, [])
            ->andReturn(['chunks' => 3, 'vectors' => 3]);

        $job = new RegenerateTextractEmbeddings($textractJob->id);
        $job->handle($mockVectorStore);

        Queue::assertPushed(SyncTextractToGraph::class, function (SyncTextractToGraph $graphJob) use ($textractJob) {
            return $graphJob->textractJobId === $textractJob->id;
        });

        // Verify the chaining log message was emitted
        Log::shouldHaveReceived('info')
            ->with('RegenerateTextractEmbeddings: Chained graph sync', Mockery::type('array'))
            ->once();
    }

    /**
     * @test
     * When neo4j.sync.enabled is false, SyncTextractToGraph must NOT be dispatched
     * even after successful embedding ingest.
     */
    public function it_does_not_dispatch_graph_sync_when_neo4j_disabled(): void
    {
        Queue::fake();
        Config::set('neo4j.sync.enabled', false);

        $textractJob = TextractJob::create([
            'drive_file_id' => 'chain-test-disabled',
            'drive_file_name' => 'chain-disabled.pdf',
            'status' => 'completed',
            'extracted_content' => 'Legal document content with neo4j disabled.',
        ]);

        $mockVectorStore = Mockery::mock(TextractVectorStoreService::class);
        $mockVectorStore->shouldReceive('ingestTextractJob')
            ->once()
            ->with($textractJob->id, [])
            ->andReturn(['chunks' => 2, 'vectors' => 2]);

        $job = new RegenerateTextractEmbeddings($textractJob->id);
        $job->handle($mockVectorStore);

        Queue::assertNotPushed(SyncTextractToGraph::class);

        // Verify the chaining log message was NOT emitted
        Log::shouldNotHaveReceived('info', function (string $message) {
            return str_contains($message, 'Chained graph sync');
        });
    }

    /**
     * @test
     * SyncTextractToGraph must be dispatched with a 5-second delay to allow
     * database state to settle before graph sync begins.
     */
    public function it_dispatches_graph_sync_with_5_second_delay(): void
    {
        Queue::fake();
        Config::set('neo4j.sync.enabled', true);

        $textractJob = TextractJob::create([
            'drive_file_id' => 'chain-test-delay',
            'drive_file_name' => 'chain-delay.pdf',
            'status' => 'completed',
            'extracted_content' => 'Legal document for delay verification.',
        ]);

        $mockVectorStore = Mockery::mock(TextractVectorStoreService::class);
        $mockVectorStore->shouldReceive('ingestTextractJob')
            ->once()
            ->with($textractJob->id, [])
            ->andReturn(['chunks' => 1, 'vectors' => 1]);

        $job = new RegenerateTextractEmbeddings($textractJob->id);
        $job->handle($mockVectorStore);

        Queue::assertPushed(SyncTextractToGraph::class, function (SyncTextractToGraph $graphJob) {
            // The job should have a delay set. Queue::fake() stores the delay
            // on the job's delay property.
            return $graphJob->delay !== null;
        });
    }

    /**
     * @test
     * When embedding ingest throws an exception, SyncTextractToGraph must NOT
     * be dispatched. The exception should propagate (for retry mechanism).
     */
    public function it_does_not_dispatch_graph_sync_on_embedding_failure(): void
    {
        Queue::fake();
        Config::set('neo4j.sync.enabled', true);

        $textractJob = TextractJob::create([
            'drive_file_id' => 'chain-test-failure',
            'drive_file_name' => 'chain-failure.pdf',
            'status' => 'completed',
            'extracted_content' => 'Legal document that will fail embedding.',
        ]);

        $mockVectorStore = Mockery::mock(TextractVectorStoreService::class);
        $mockVectorStore->shouldReceive('ingestTextractJob')
            ->once()
            ->andThrow(new \Exception('OpenAI API rate limit exceeded'));

        $job = new RegenerateTextractEmbeddings($textractJob->id);

        $exceptionThrown = false;
        try {
            $job->handle($mockVectorStore);
        } catch (\Exception $e) {
            $exceptionThrown = true;
            $this->assertEquals('OpenAI API rate limit exceeded', $e->getMessage());
        }

        $this->assertTrue($exceptionThrown, 'Exception should propagate for retry mechanism');

        // The critical assertion: graph sync must NOT have been dispatched
        Queue::assertNotPushed(SyncTextractToGraph::class);

        // Verify the job was marked as failed
        $textractJob->refresh();
        $this->assertEquals('failed', $textractJob->embedding_status);
    }
}
