<?php

namespace Tests\Unit\Tools\Research;

use App\Services\LawSearchService;
use App\Tools\Research\LawHybridSearchTool;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Vizra\VizraADK\Memory\AgentMemory;
use Vizra\VizraADK\System\AgentContext;

class LawHybridSearchToolTest extends TestCase
{
    #[Test]
    public function it_has_correct_definition(): void
    {
        $mockLawSearch = Mockery::mock(LawSearchService::class);
        $tool = new LawHybridSearchTool($mockLawSearch);

        $definition = $tool->definition();

        $this->assertEquals('law_hybrid_search', $definition['name']);
        $this->assertArrayHasKey('parameters', $definition);
        $this->assertContains('query', $definition['parameters']['required']);
    }

    #[Test]
    public function it_executes_hybrid_search(): void
    {
        $mockLawSearch = Mockery::mock(LawSearchService::class);
        $mockLawSearch->shouldReceive('hybridSearch')
            ->once()
            ->with('proportionality', ['limit' => 5, 'jurisdiction' => null])
            ->andReturn([
                'success' => true,
                'data' => [
                    ['id' => 1, 'content' => 'test', 'match_type' => 'vector'],
                    ['id' => 2, 'content' => 'test2', 'match_type' => 'keyword'],
                ],
            ]);

        $tool = new LawHybridSearchTool($mockLawSearch);

        $context = Mockery::mock(AgentContext::class);
        $memory = Mockery::mock(AgentMemory::class);

        $result = $tool->execute(
            ['query' => 'proportionality', 'limit' => 5],
            $context,
            $memory
        );

        $this->assertStringContainsString('success', $result);
        $decoded = json_decode($result, true);
        $this->assertTrue($decoded['success']);
        $this->assertEquals('hybrid', $decoded['search_type']);
    }

    #[Test]
    public function it_handles_errors_gracefully(): void
    {
        $mockLawSearch = Mockery::mock(LawSearchService::class);
        $mockLawSearch->shouldReceive('hybridSearch')
            ->once()
            ->andThrow(new \Exception('Search failed'));

        $tool = new LawHybridSearchTool($mockLawSearch);

        $context = Mockery::mock(AgentContext::class);
        $memory = Mockery::mock(AgentMemory::class);

        $result = $tool->execute(
            ['query' => 'test query'],
            $context,
            $memory
        );

        $decoded = json_decode($result, true);
        $this->assertFalse($decoded['success']);
        $this->assertArrayHasKey('error', $decoded);
    }
}
