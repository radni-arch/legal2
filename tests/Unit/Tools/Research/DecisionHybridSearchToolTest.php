<?php

namespace Tests\Unit\Tools\Research;

use App\Services\DecisionSearchService;
use App\Tools\Research\DecisionHybridSearchTool;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Vizra\VizraADK\Memory\AgentMemory;
use Vizra\VizraADK\System\AgentContext;

class DecisionHybridSearchToolTest extends TestCase
{
    #[Test]
    public function it_has_correct_definition(): void
    {
        $mockDecisionSearch = Mockery::mock(DecisionSearchService::class);
        $tool = new DecisionHybridSearchTool($mockDecisionSearch);

        $definition = $tool->definition();

        $this->assertEquals('decision_hybrid_search', $definition['name']);
        $this->assertStringContainsString('hybrid', strtolower($definition['description']));
        $this->assertArrayHasKey('parameters', $definition);
        $this->assertArrayHasKey('required', $definition['parameters']);
        $this->assertContains('query', $definition['parameters']['required']);
    }

    #[Test]
    public function it_executes_hybrid_search(): void
    {
        $mockDecisionSearch = Mockery::mock(DecisionSearchService::class);
        $mockDecisionSearch->shouldReceive('hybridSearch')
            ->once()
            ->with('proportionality principle', Mockery::on(function ($filters) {
                return $filters['limit'] === 8;
            }))
            ->andReturn([
                'success' => true,
                'data' => [
                    ['id' => 1, 'title' => 'Vector Result', 'match_type' => 'vector', 'score' => 0.85],
                    ['id' => 2, 'title' => 'Keyword Result', 'match_type' => 'keyword', 'score' => 0.65],
                ],
                'search_type' => 'hybrid',
                'count' => 2,
            ]);

        $tool = new DecisionHybridSearchTool($mockDecisionSearch);

        $context = Mockery::mock(AgentContext::class);
        $memory = Mockery::mock(AgentMemory::class);

        $result = $tool->execute(
            ['query' => 'proportionality principle', 'limit' => 8],
            $context,
            $memory
        );

        $resultData = json_decode($result, true);

        $this->assertTrue($resultData['success']);
        $this->assertEquals('hybrid', $resultData['search_type']);
        $this->assertEquals(2, $resultData['count']);
        $this->assertCount(2, $resultData['data']);
    }

    #[Test]
    public function it_combines_vector_and_keyword_results(): void
    {
        $mockDecisionSearch = Mockery::mock(DecisionSearchService::class);
        $mockDecisionSearch->shouldReceive('hybridSearch')
            ->once()
            ->andReturn([
                'success' => true,
                'data' => [
                    ['id' => 1, 'match_type' => 'vector', 'score' => 0.9],
                    ['id' => 2, 'match_type' => 'keyword', 'score' => 0.7],
                    ['id' => 3, 'match_type' => 'vector', 'score' => 0.8],
                ],
                'search_type' => 'hybrid',
                'count' => 3,
            ]);

        $tool = new DecisionHybridSearchTool($mockDecisionSearch);

        $context = Mockery::mock(AgentContext::class);
        $memory = Mockery::mock(AgentMemory::class);

        $result = $tool->execute(
            ['query' => 'test query', 'limit' => 10],
            $context,
            $memory
        );

        $resultData = json_decode($result, true);

        $this->assertTrue($resultData['success']);
        $this->assertCount(3, $resultData['data']);

        // Verify both match types are present
        $matchTypes = array_column($resultData['data'], 'match_type');
        $this->assertContains('vector', $matchTypes);
        $this->assertContains('keyword', $matchTypes);
    }

    #[Test]
    public function it_handles_search_errors_gracefully(): void
    {
        $mockDecisionSearch = Mockery::mock(DecisionSearchService::class);
        $mockDecisionSearch->shouldReceive('hybridSearch')
            ->once()
            ->andThrow(new \Exception('Hybrid search failed'));

        $tool = new DecisionHybridSearchTool($mockDecisionSearch);

        $context = Mockery::mock(AgentContext::class);
        $memory = Mockery::mock(AgentMemory::class);

        $result = $tool->execute(
            ['query' => 'test query'],
            $context,
            $memory
        );

        $resultData = json_decode($result, true);

        $this->assertFalse($resultData['success']);
        $this->assertArrayHasKey('error', $resultData);
        $this->assertStringContainsString('Hybrid search failed', $resultData['error']);
    }
}
