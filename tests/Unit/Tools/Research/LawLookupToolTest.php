<?php

namespace Tests\Unit\Tools\Research;

use App\Services\LawSearchService;
use App\Tools\Research\LawLookupTool;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Vizra\VizraADK\Memory\AgentMemory;
use Vizra\VizraADK\System\AgentContext;

class LawLookupToolTest extends TestCase
{
    #[Test]
    public function it_has_correct_definition(): void
    {
        $mockLawSearch = Mockery::mock(LawSearchService::class);
        $tool = new LawLookupTool($mockLawSearch);

        $definition = $tool->definition();

        $this->assertEquals('law_lookup', $definition['name']);
        $this->assertArrayHasKey('parameters', $definition);
        $this->assertContains('law_number', $definition['parameters']['required']);
    }

    #[Test]
    public function it_executes_law_lookup(): void
    {
        $mockLawSearch = Mockery::mock(LawSearchService::class);
        $mockLawSearch->shouldReceive('lookupByNumber')
            ->once()
            ->with('NN 152/08', null)
            ->andReturn([
                'doc_id' => 'zkp-2008',
                'title' => 'Zakon o kaznenom postupku',
                'law_number' => 'NN 152/08',
                'chunks' => [],
            ]);

        $tool = new LawLookupTool($mockLawSearch);

        $context = Mockery::mock(AgentContext::class);
        $memory = Mockery::mock(AgentMemory::class);

        $result = $tool->execute(
            ['law_number' => 'NN 152/08'],
            $context,
            $memory
        );

        $this->assertStringContainsString('success', $result);
        $decoded = json_decode($result, true);
        $this->assertTrue($decoded['success']);
        $this->assertEquals('Zakon o kaznenom postupku', $decoded['data']['title']);
    }

    #[Test]
    public function it_handles_errors_gracefully(): void
    {
        $mockLawSearch = Mockery::mock(LawSearchService::class);
        $mockLawSearch->shouldReceive('lookupByNumber')
            ->once()
            ->andThrow(new \Exception('Law not found'));

        $tool = new LawLookupTool($mockLawSearch);

        $context = Mockery::mock(AgentContext::class);
        $memory = Mockery::mock(AgentMemory::class);

        $result = $tool->execute(
            ['law_number' => 'NN 999/99'],
            $context,
            $memory
        );

        $decoded = json_decode($result, true);
        $this->assertFalse($decoded['success']);
        $this->assertArrayHasKey('error', $decoded);
    }
}
