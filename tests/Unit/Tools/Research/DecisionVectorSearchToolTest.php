<?php

namespace Tests\Unit\Tools\Research;

use App\Services\DecisionSearchService;
use App\Tools\Research\DecisionVectorSearchTool;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Vizra\VizraADK\Memory\AgentMemory;
use Vizra\VizraADK\System\AgentContext;

class DecisionVectorSearchToolTest extends TestCase
{
    #[Test]
    public function it_has_correct_definition(): void
    {
        $mockDecisionSearch = Mockery::mock(DecisionSearchService::class);
        $tool = new DecisionVectorSearchTool($mockDecisionSearch);

        $definition = $tool->definition();

        $this->assertEquals('decision_vector_search', $definition['name']);
        $this->assertStringContainsString('semantic search', strtolower($definition['description']));
        $this->assertArrayHasKey('parameters', $definition);
        $this->assertArrayHasKey('required', $definition['parameters']);
        $this->assertContains('query', $definition['parameters']['required']);
    }

    #[Test]
    public function it_executes_vector_search(): void
    {
        $mockDecisionSearch = Mockery::mock(DecisionSearchService::class);
        $mockDecisionSearch->shouldReceive('vectorSearch')
            ->once()
            ->with('proportionality in home searches', Mockery::on(function ($filters) {
                return $filters['limit'] === 5 && $filters['jurisdiction'] === null;
            }))
            ->andReturn([
                'success' => true,
                'data' => [
                    ['id' => 1, 'title' => 'Test Decision', 'content' => 'test content'],
                ],
                'search_type' => 'vector',
                'count' => 1,
            ]);

        $tool = new DecisionVectorSearchTool($mockDecisionSearch);

        $context = Mockery::mock(AgentContext::class);
        $memory = Mockery::mock(AgentMemory::class);

        $result = $tool->execute(
            ['query' => 'proportionality in home searches', 'limit' => 5],
            $context,
            $memory
        );

        $resultData = json_decode($result, true);

        $this->assertTrue($resultData['success']);
        $this->assertEquals('vector', $resultData['search_type']);
        $this->assertEquals(1, $resultData['count']);
        $this->assertArrayHasKey('data', $resultData);
    }

    #[Test]
    public function it_handles_search_errors_gracefully(): void
    {
        $mockDecisionSearch = Mockery::mock(DecisionSearchService::class);
        $mockDecisionSearch->shouldReceive('vectorSearch')
            ->once()
            ->andThrow(new \Exception('Search service unavailable'));

        $tool = new DecisionVectorSearchTool($mockDecisionSearch);

        $context = Mockery::mock(AgentContext::class);
        $memory = Mockery::mock(AgentMemory::class);

        $result = $tool->execute(
            ['query' => 'test query', 'limit' => 10],
            $context,
            $memory
        );

        $resultData = json_decode($result, true);

        $this->assertFalse($resultData['success']);
        $this->assertArrayHasKey('error', $resultData);
        $this->assertStringContainsString('Search service unavailable', $resultData['error']);
    }
}
