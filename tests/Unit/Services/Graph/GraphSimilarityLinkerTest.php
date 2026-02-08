<?php

namespace Tests\Unit\Services\Graph;

use App\Contracts\VectorStore\VectorStoreInterface;
use App\Exceptions\GraphException;
use App\Services\CourtDecisionVectorStoreService;
use App\Services\Graph\GraphSimilarityLinker;
use App\Services\GraphDatabaseService;
use App\Services\LawVectorStoreService;
use Mockery;
use Tests\TestCase;

class GraphSimilarityLinkerTest extends TestCase
{
    protected GraphSimilarityLinker $linker;

    protected GraphDatabaseService $mockGraph;

    protected VectorStoreInterface $mockVectorStore;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockGraph = Mockery::mock(GraphDatabaseService::class);
        $this->linker = new GraphSimilarityLinker($this->mockGraph);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_link_similar_creates_relationships_above_threshold(): void
    {
        // Arrange
        $nodeType = 'Decision';
        $nodeId = 'decision-123';
        $threshold = 0.85;
        $sourceEmbedding = array_fill(0, 1536, 0.5);

        $similarDocs = [
            ['id' => 'decision-456', 'similarity_score' => 0.92],
            ['id' => 'decision-789', 'similarity_score' => 0.87],
        ];

        // Mock CourtDecisionVectorStoreService
        $mockVectorStore = Mockery::mock(CourtDecisionVectorStoreService::class);
        $mockVectorStore->shouldReceive('getEmbedding')
            ->once()
            ->with($nodeId)
            ->andReturn($sourceEmbedding);

        $mockVectorStore->shouldReceive('findSimilar')
            ->once()
            ->with($nodeId, $threshold, 20)
            ->andReturn($similarDocs);

        // Replace instance in container
        $this->app->instance(CourtDecisionVectorStoreService::class, $mockVectorStore);

        // Mock graph delete query
        $this->mockGraph->shouldReceive('run')
            ->once()
            ->with(
                Mockery::on(fn ($cypher) => str_contains($cypher, 'DELETE r')),
                ['nodeId' => $nodeId]
            );

        // Mock graph create queries (2 similar docs)
        $this->mockGraph->shouldReceive('run')
            ->times(2)
            ->with(
                Mockery::on(fn ($cypher) => str_contains($cypher, 'MERGE') && str_contains($cypher, 'SIMILAR_TO')),
                Mockery::type('array')
            );

        // Act
        $count = $this->linker->linkSimilar($nodeType, $nodeId, $threshold);

        // Assert
        $this->assertEquals(2, $count);
    }

    public function test_link_similar_returns_zero_when_no_embedding_found(): void
    {
        // Arrange
        $nodeType = 'Decision';
        $nodeId = 'decision-nonexistent';
        $threshold = 0.8;

        // Mock CourtDecisionVectorStoreService
        $mockVectorStore = Mockery::mock(CourtDecisionVectorStoreService::class);
        $mockVectorStore->shouldReceive('getEmbedding')
            ->once()
            ->with($nodeId)
            ->andReturn(null);

        // Replace instance in container
        $this->app->instance(CourtDecisionVectorStoreService::class, $mockVectorStore);

        // Graph should not be called at all
        $this->mockGraph->shouldReceive('run')->never();

        // Act
        $count = $this->linker->linkSimilar($nodeType, $nodeId, $threshold);

        // Assert
        $this->assertEquals(0, $count);
    }

    public function test_link_similar_deletes_existing_relationships_first(): void
    {
        // Arrange
        $nodeType = 'Law';
        $nodeId = 'law-123';
        $threshold = 0.8;
        $sourceEmbedding = array_fill(0, 1536, 0.3);

        $similarDocs = [
            ['id' => 'law-456', 'similarity_score' => 0.85],
        ];

        // Mock LawVectorStoreService
        $mockVectorStore = Mockery::mock(LawVectorStoreService::class);
        $mockVectorStore->shouldReceive('getEmbedding')
            ->once()
            ->with($nodeId)
            ->andReturn($sourceEmbedding);

        $mockVectorStore->shouldReceive('findSimilar')
            ->once()
            ->with($nodeId, $threshold, 20)
            ->andReturn($similarDocs);

        // Replace instance in container
        $this->app->instance(LawVectorStoreService::class, $mockVectorStore);

        // IMPORTANT: Verify DELETE query is executed BEFORE CREATE
        $callOrder = [];

        $this->mockGraph->shouldReceive('run')
            ->once()
            ->with(
                Mockery::on(function ($cypher) use (&$callOrder) {
                    if (str_contains($cypher, 'DELETE r')) {
                        $callOrder[] = 'DELETE';

                        return true;
                    }

                    return false;
                }),
                ['nodeId' => $nodeId]
            );

        $this->mockGraph->shouldReceive('run')
            ->once()
            ->with(
                Mockery::on(function ($cypher) use (&$callOrder) {
                    if (str_contains($cypher, 'MERGE') && str_contains($cypher, 'SIMILAR_TO')) {
                        $callOrder[] = 'CREATE';

                        return true;
                    }

                    return false;
                }),
                Mockery::type('array')
            );

        // Act
        $count = $this->linker->linkSimilar($nodeType, $nodeId, $threshold);

        // Assert
        $this->assertEquals(1, $count);
        $this->assertEquals(['DELETE', 'CREATE'], $callOrder);
    }

    public function test_link_similar_throws_exception_for_unsupported_node_type(): void
    {
        // Arrange
        $nodeType = 'UnsupportedType';
        $nodeId = 'some-id';
        $threshold = 0.8;

        // Expect GraphException
        $this->expectException(GraphException::class);
        $this->expectExceptionCode(GraphException::INVALID_NODE_TYPE);

        // Act
        $this->linker->linkSimilar($nodeType, $nodeId, $threshold);
    }

    public function test_unlink_all_removes_all_similarity_relationships(): void
    {
        // Arrange
        $nodeType = 'Decision';
        $nodeId = 'decision-123';

        // Mock graph run to return deleted count
        $this->mockGraph->shouldReceive('run')
            ->once()
            ->with(
                Mockery::on(function ($cypher) {
                    // Verify the cypher deletes SIMILAR_TO relationships
                    return str_contains($cypher, 'MATCH') &&
                           str_contains($cypher, 'SIMILAR_TO') &&
                           str_contains($cypher, 'DELETE r') &&
                           str_contains($cypher, 'count(r)');
                }),
                ['nodeId' => $nodeId]
            )
            ->andReturn([['deleted_count' => 3]]);

        // Act
        $count = $this->linker->unlinkAll($nodeType, $nodeId);

        // Assert
        $this->assertIsInt($count);
        $this->assertEquals(3, $count);
    }

    public function test_unlink_all_returns_zero_when_no_relationships_exist(): void
    {
        // Arrange
        $nodeType = 'Law';
        $nodeId = 'law-456';

        // Mock graph run to return zero deleted count
        $this->mockGraph->shouldReceive('run')
            ->once()
            ->with(
                Mockery::on(fn ($cypher) => str_contains($cypher, 'DELETE r')),
                ['nodeId' => $nodeId]
            )
            ->andReturn([['deleted_count' => 0]]);

        // Act
        $count = $this->linker->unlinkAll($nodeType, $nodeId);

        // Assert
        $this->assertEquals(0, $count);
    }

    public function test_unlink_all_handles_errors_gracefully(): void
    {
        // Arrange
        $nodeType = 'Decision';
        $nodeId = 'decision-error';

        // Mock graph run to throw exception
        $this->mockGraph->shouldReceive('run')
            ->once()
            ->andThrow(new \Exception('Neo4j connection failed'));

        // Act
        $count = $this->linker->unlinkAll($nodeType, $nodeId);

        // Assert - should return 0 on error
        $this->assertEquals(0, $count);
    }

    public function test_unlink_all_handles_both_incoming_and_outgoing_relationships(): void
    {
        // Arrange
        $nodeType = 'Case';
        $nodeId = 'case-789';

        // Mock graph run - the cypher should match both directions using undirected pattern
        $this->mockGraph->shouldReceive('run')
            ->once()
            ->with(
                Mockery::on(function ($cypher) {
                    // Verify it matches relationships in both directions
                    // Pattern like: -[r:SIMILAR_TO]-() matches both incoming and outgoing
                    return str_contains($cypher, '-[r:SIMILAR_TO]-()') ||
                           (str_contains($cypher, '-[r:SIMILAR_TO]->') && str_contains($cypher, '<-[r:SIMILAR_TO]-'));
                }),
                ['nodeId' => $nodeId]
            )
            ->andReturn([['deleted_count' => 5]]);

        // Act
        $count = $this->linker->unlinkAll($nodeType, $nodeId);

        // Assert
        $this->assertEquals(5, $count);
    }
}
