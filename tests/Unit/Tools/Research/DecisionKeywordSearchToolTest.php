<?php

namespace Tests\Unit\Tools\Research;

use App\Services\DecisionSearchService;
use App\Tools\Research\DecisionKeywordSearchTool;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Vizra\VizraADK\Memory\AgentMemory;
use Vizra\VizraADK\System\AgentContext;

class DecisionKeywordSearchToolTest extends TestCase
{
    #[Test]
    public function it_has_correct_definition(): void
    {
        $mockDecisionSearch = Mockery::mock(DecisionSearchService::class);
        $tool = new DecisionKeywordSearchTool($mockDecisionSearch);

        $definition = $tool->definition();

        $this->assertEquals('decision_keyword_search', $definition['name']);
        $this->assertStringContainsString('keyword', strtolower($definition['description']));
        $this->assertArrayHasKey('parameters', $definition);
        $this->assertArrayHasKey('required', $definition['parameters']);
        $this->assertContains('query', $definition['parameters']['required']);
    }

    #[Test]
    public function it_executes_keyword_search(): void
    {
        $mockDecisionSearch = Mockery::mock(DecisionSearchService::class);
        $mockDecisionSearch->shouldReceive('keywordSearch')
            ->once()
            ->with('habeas corpus', Mockery::on(function ($filters) {
                return $filters['limit'] === 10 && $filters['page'] === 1;
            }))
            ->andReturn([
                'success' => true,
                'data' => [
                    ['id' => 1, 'case_number' => 'U-I-123/2024', 'title' => 'Test Decision'],
                ],
                'search_type' => 'keyword',
                'pagination' => [
                    'total' => 1,
                    'page' => 1,
                    'limit' => 10,
                    'pages' => 1,
                ],
            ]);

        $tool = new DecisionKeywordSearchTool($mockDecisionSearch);

        $context = Mockery::mock(AgentContext::class);
        $memory = Mockery::mock(AgentMemory::class);

        $result = $tool->execute(
            ['query' => 'habeas corpus', 'limit' => 10, 'page' => 1],
            $context,
            $memory
        );

        $resultData = json_decode($result, true);

        $this->assertTrue($resultData['success']);
        $this->assertEquals('keyword', $resultData['search_type']);
        $this->assertArrayHasKey('pagination', $resultData);
        $this->assertEquals(1, $resultData['pagination']['total']);
    }

    #[Test]
    public function it_applies_filters(): void
    {
        $mockDecisionSearch = Mockery::mock(DecisionSearchService::class);
        $mockDecisionSearch->shouldReceive('keywordSearch')
            ->once()
            ->with('test', Mockery::on(function ($filters) {
                return $filters['court'] === 'Ustavni sud' &&
                       $filters['jurisdiction'] === 'constitutional' &&
                       $filters['limit'] === 5;
            }))
            ->andReturn([
                'success' => true,
                'data' => [],
                'search_type' => 'keyword',
                'pagination' => ['total' => 0, 'page' => 1, 'limit' => 5, 'pages' => 0],
            ]);

        $tool = new DecisionKeywordSearchTool($mockDecisionSearch);

        $context = Mockery::mock(AgentContext::class);
        $memory = Mockery::mock(AgentMemory::class);

        $result = $tool->execute(
            [
                'query' => 'test',
                'limit' => 5,
                'court' => 'Ustavni sud',
                'jurisdiction' => 'constitutional',
            ],
            $context,
            $memory
        );

        $resultData = json_decode($result, true);
        $this->assertTrue($resultData['success']);
    }

    #[Test]
    public function it_handles_search_errors_gracefully(): void
    {
        $mockDecisionSearch = Mockery::mock(DecisionSearchService::class);
        $mockDecisionSearch->shouldReceive('keywordSearch')
            ->once()
            ->andThrow(new \Exception('Database connection failed'));

        $tool = new DecisionKeywordSearchTool($mockDecisionSearch);

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
        $this->assertStringContainsString('Database connection failed', $resultData['error']);
    }
}
