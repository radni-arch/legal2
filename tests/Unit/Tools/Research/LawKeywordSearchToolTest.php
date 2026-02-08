<?php

namespace Tests\Unit\Tools\Research;

use App\Services\LawSearchService;
use App\Tools\Research\LawKeywordSearchTool;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Vizra\VizraADK\Memory\AgentMemory;
use Vizra\VizraADK\System\AgentContext;

class LawKeywordSearchToolTest extends TestCase
{
    #[Test]
    public function it_has_correct_definition(): void
    {
        $mockLawSearch = Mockery::mock(LawSearchService::class);
        $tool = new LawKeywordSearchTool($mockLawSearch);

        $definition = $tool->definition();

        $this->assertEquals('law_keyword_search', $definition['name']);
        $this->assertArrayHasKey('parameters', $definition);
        $this->assertContains('query', $definition['parameters']['required']);
    }

    #[Test]
    public function it_executes_keyword_search(): void
    {
        $mockLawSearch = Mockery::mock(LawSearchService::class);
        $mockLawSearch->shouldReceive('keywordSearch')
            ->once()
            ->with('kazneni postupak', ['limit' => 5, 'jurisdiction' => null])
            ->andReturn([
                'success' => true,
                'data' => [['id' => 1, 'title' => 'test law']],
                'pagination' => ['total' => 1, 'page' => 1],
            ]);

        $tool = new LawKeywordSearchTool($mockLawSearch);

        $context = Mockery::mock(AgentContext::class);
        $memory = Mockery::mock(AgentMemory::class);

        $result = $tool->execute(
            ['query' => 'kazneni postupak', 'limit' => 5],
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
        $mockLawSearch->shouldReceive('keywordSearch')
            ->once()
            ->andThrow(new \Exception('Search failed'));

        $tool = new LawKeywordSearchTool($mockLawSearch);

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
