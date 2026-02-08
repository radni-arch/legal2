<?php

namespace Tests\Unit\Tools\Research;

use App\Tools\Research\CaseSearchTool;
use App\Services\CaseSearchService;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Vizra\VizraADK\Memory\AgentMemory;
use Vizra\VizraADK\System\AgentContext;

class CaseSearchToolTest extends TestCase
{
    #[Test]
    public function it_has_correct_definition(): void
    {
        $mockCaseSearch = Mockery::mock(CaseSearchService::class);
        $tool = new CaseSearchTool($mockCaseSearch);

        $definition = $tool->definition();

        $this->assertEquals('case_search', $definition['name']);
        $this->assertArrayHasKey('description', $definition);
        $this->assertArrayHasKey('parameters', $definition);
        $this->assertArrayHasKey('properties', $definition['parameters']);
        $this->assertArrayHasKey('query', $definition['parameters']['properties']);
        $this->assertContains('query', $definition['parameters']['required']);
    }

    #[Test]
    public function it_executes_case_search(): void
    {
        $mockCaseSearch = Mockery::mock(CaseSearchService::class);
        $mockCaseSearch->shouldReceive('searchCases')
            ->once()
            ->with('robbery case', Mockery::type('array'))
            ->andReturn([
                'success' => true,
                'data' => [
                    ['id' => 'case-1', 'case_number' => 'K-123/2024', 'title' => 'Robbery case'],
                    ['id' => 'case-2', 'case_number' => 'K-456/2024', 'title' => 'Another robbery'],
                ],
                'pagination' => [
                    'total' => 2,
                    'page' => 1,
                    'limit' => 10,
                ],
            ]);

        $tool = new CaseSearchTool($mockCaseSearch);

        $context = Mockery::mock(AgentContext::class);
        $memory = Mockery::mock(AgentMemory::class);

        $result = $tool->execute(
            ['query' => 'robbery case', 'limit' => 10, 'page' => 1],
            $context,
            $memory
        );

        $this->assertJson($result);
        $decoded = json_decode($result, true);
        $this->assertTrue($decoded['success']);
        $this->assertCount(2, $decoded['data']);
        $this->assertArrayHasKey('pagination', $decoded);
    }

    #[Test]
    public function it_handles_errors_gracefully(): void
    {
        $mockCaseSearch = Mockery::mock(CaseSearchService::class);
        $mockCaseSearch->shouldReceive('searchCases')
            ->once()
            ->andThrow(new \Exception('Database error'));

        $tool = new CaseSearchTool($mockCaseSearch);

        $context = Mockery::mock(AgentContext::class);
        $memory = Mockery::mock(AgentMemory::class);

        $result = $tool->execute(
            ['query' => 'test'],
            $context,
            $memory
        );

        $this->assertJson($result);
        $decoded = json_decode($result, true);
        $this->assertFalse($decoded['success']);
        $this->assertStringContainsString('Database error', $decoded['error']);
    }
}
