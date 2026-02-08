<?php

namespace Tests\Unit\Services\Graph;

use App\Services\CourtDecisionVectorStoreService;
use App\Services\Graph\ContradictionDetectionService;
use App\Services\Graph\ContradictionPipelineService;
use App\Services\GraphDatabaseService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class ContradictionPipelineServiceTest extends TestCase
{
    protected ContradictionPipelineService $pipeline;

    protected GraphDatabaseService $mockGraph;

    protected ContradictionDetectionService $mockContradictionService;

    protected CourtDecisionVectorStoreService $mockVectorStore;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock HTTP for offline testing
        Http::fake([
            'api.openai.com/v1/embeddings' => Http::response([
                'data' => [['embedding' => array_fill(0, 1536, 0.1)]],
            ], 200),
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'Analysis complete']]],
            ], 200),
        ]);

        $this->mockGraph = Mockery::mock(GraphDatabaseService::class);
        $this->mockContradictionService = Mockery::mock(ContradictionDetectionService::class);
        $this->mockVectorStore = Mockery::mock(CourtDecisionVectorStoreService::class);

        $this->pipeline = new ContradictionPipelineService(
            $this->mockContradictionService,
            $this->mockVectorStore,
            $this->mockGraph
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Helper to create mock decision objects
     */
    protected function createMockDecision(array $attributes = []): object
    {
        $decision = new \stdClass;
        $decision->id = $attributes['id'] ?? 'decision-'.uniqid();
        $decision->case_number = $attributes['case_number'] ?? null;
        $decision->court = $attributes['court'] ?? null;
        $decision->summary = $attributes['summary'] ?? null;
        $decision->decision_date = $attributes['decision_date'] ?? null;
        $decision->description = $attributes['description'] ?? null;
        $decision->title = $attributes['title'] ?? null;
        $decision->jurisdiction = $attributes['jurisdiction'] ?? null;

        return $decision;
    }

    public function test_pipeline_returns_disabled_when_option_is_false(): void
    {
        // Arrange
        $decisionId = 'decision-123';

        // Act
        $result = $this->pipeline->checkNewDecision($decisionId, ['enabled' => false]);

        // Assert
        $this->assertFalse($result['enabled']);
        $this->assertEquals($decisionId, $result['decision_id']);
        $this->assertEquals(0, $result['contradictions_found']);
        $this->assertEquals(0, $result['relationships_created']);
    }

    public function test_get_statistics_returns_zero_when_graph_unavailable(): void
    {
        // Arrange
        $this->mockGraph->shouldReceive('isAvailable')->once()->andReturn(false);

        // Act
        $stats = $this->pipeline->getStatistics();

        // Assert
        $this->assertFalse($stats['graph_available']);
        $this->assertEquals(0, $stats['total_contradictions']);
    }

    public function test_get_statistics_returns_count_from_graph(): void
    {
        // Arrange
        $this->mockGraph->shouldReceive('isAvailable')->once()->andReturn(true);
        $this->mockGraph->shouldReceive('run')
            ->once()
            ->with('MATCH ()-[r:CONTRADICTS]->() RETURN count(r) as total')
            ->andReturn(collect([['total' => 15]]));

        // Act
        $stats = $this->pipeline->getStatistics();

        // Assert
        $this->assertTrue($stats['graph_available']);
        $this->assertEquals(15, $stats['total_contradictions']);
    }

    public function test_get_statistics_handles_query_errors_gracefully(): void
    {
        // Arrange
        Log::shouldReceive('error')->once();

        $this->mockGraph->shouldReceive('isAvailable')->once()->andReturn(true);
        $this->mockGraph->shouldReceive('run')
            ->once()
            ->andThrow(new \Exception('Neo4j connection failed'));

        // Act
        $stats = $this->pipeline->getStatistics();

        // Assert
        $this->assertTrue($stats['graph_available']);
        $this->assertEquals(0, $stats['total_contradictions']);
        $this->assertArrayHasKey('error', $stats);
    }

    public function test_pipeline_config_disabled_state(): void
    {
        // Test that when config is disabled, pipeline returns early
        $result = $this->pipeline->checkNewDecision('test-id', ['enabled' => false]);

        $this->assertFalse($result['enabled']);
        $this->assertEquals(0, $result['contradictions_found']);
    }
}
