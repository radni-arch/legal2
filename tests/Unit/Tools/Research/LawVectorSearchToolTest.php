<?php

namespace Tests\Unit\Tools\Research;

use App\Services\LawSearchService;
use App\Tools\Research\LawVectorSearchTool;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Vizra\VizraADK\Memory\AgentMemory;
use Vizra\VizraADK\System\AgentContext;

class LawVectorSearchToolTest extends TestCase
{
    #[Test]
    public function it_has_correct_definition(): void
    {
        $mockLawSearch = Mockery::mock(LawSearchService::class);
        $tool = new LawVectorSearchTool($mockLawSearch);

        $definition = $tool->definition();

        $this->assertEquals('law_vector_search', $definition['name']);
        $this->assertArrayHasKey('parameters', $definition);
        $this->assertContains('query', $definition['parameters']['required']);
    }

    #[Test]
    public function it_executes_vector_search(): void
    {
        $mockLawSearch = Mockery::mock(LawSearchService::class);
        $mockLawSearch->shouldReceive('vectorSearch')
            ->once()
            ->with('proportionality home search', ['limit' => 5, 'jurisdiction' => null])
            ->andReturn([
                'success' => true,
                'data' => [['id' => 1, 'content' => 'test']],
            ]);

        $tool = new LawVectorSearchTool($mockLawSearch);

        $context = Mockery::mock(AgentContext::class);
        $memory = Mockery::mock(AgentMemory::class);

        $result = $tool->execute(
            ['query' => 'proportionality home search', 'limit' => 5],
            $context,
            $memory
        );

        $this->assertStringContainsString('success', $result);
        $decoded = json_decode($result, true);
        $this->assertTrue($decoded['success']);
    }

    #[Test]
    public function it_handles_errors_gracefully(): void
    {
        $mockLawSearch = Mockery::mock(LawSearchService::class);
        $mockLawSearch->shouldReceive('vectorSearch')
            ->once()
            ->andThrow(new \Exception('Search failed'));

        $tool = new LawVectorSearchTool($mockLawSearch);

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
