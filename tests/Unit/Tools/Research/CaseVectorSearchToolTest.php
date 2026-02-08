<?php

namespace Tests\Unit\Tools\Research;

use App\Tools\Research\CaseVectorSearchTool;
use App\Services\CaseSearchService;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Vizra\VizraADK\Memory\AgentMemory;
use Vizra\VizraADK\System\AgentContext;

class CaseVectorSearchToolTest extends TestCase
{
    #[Test]
    public function it_has_correct_definition(): void
    {
        $mockCaseSearch = Mockery::mock(CaseSearchService::class);
        $tool = new CaseVectorSearchTool($mockCaseSearch);

        $definition = $tool->definition();

        $this->assertEquals('case_vector_search', $definition['name']);
        $this->assertArrayHasKey('description', $definition);
        $this->assertArrayHasKey('parameters', $definition);
        $this->assertArrayHasKey('properties', $definition['parameters']);
        $this->assertArrayHasKey('query', $definition['parameters']['properties']);
        $this->assertContains('query', $definition['parameters']['required']);
    }

    #[Test]
    public function it_executes_vector_search(): void
    {
        $mockCaseSearch = Mockery::mock(CaseSearchService::class);
        $mockCaseSearch->shouldReceive('vectorSearch')
            ->once()
            ->with('search warrant case law', Mockery::type('array'))
            ->andReturn([
                'success' => true,
                'data' => [
                    ['id' => 1, 'content' => 'Case document 1', 'similarity' => 0.95],
                    ['id' => 2, 'content' => 'Case document 2', 'similarity' => 0.88],
                ],
                'count' => 2,
            ]);

        $tool = new CaseVectorSearchTool($mockCaseSearch);

        $context = Mockery::mock(AgentContext::class);
        $memory = Mockery::mock(AgentMemory::class);

        $result = $tool->execute(
            ['query' => 'search warrant case law', 'limit' => 5],
            $context,
            $memory
        );

        $this->assertJson($result);
        $decoded = json_decode($result, true);
        $this->assertTrue($decoded['success']);
        $this->assertCount(2, $decoded['data']);
    }

    #[Test]
    public function it_handles_errors_gracefully(): void
    {
        $mockCaseSearch = Mockery::mock(CaseSearchService::class);
        $mockCaseSearch->shouldReceive('vectorSearch')
            ->once()
            ->andThrow(new \Exception('Search service error'));

        $tool = new CaseVectorSearchTool($mockCaseSearch);

        $context = Mockery::mock(AgentContext::class);
        $memory = Mockery::mock(AgentMemory::class);

        $result = $tool->execute(
            ['query' => 'test query'],
            $context,
            $memory
        );

        $this->assertJson($result);
        $decoded = json_decode($result, true);
        $this->assertFalse($decoded['success']);
        $this->assertStringContainsString('Search service error', $decoded['error']);
    }
}
