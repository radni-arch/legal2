<?php

namespace Tests\Integration;

use App\Services\ResearchOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Vizra\VizraADK\Agents\BaseLlmAgent;

class ResearchOrchestratorIntegrationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_be_resolved_from_container(): void
    {
        $orchestrator = app(ResearchOrchestrator::class);
        $this->assertInstanceOf(ResearchOrchestrator::class, $orchestrator);
        $this->assertInstanceOf(BaseLlmAgent::class, $orchestrator);
    }

    #[Test]
    public function it_has_all_required_tools(): void
    {
        $orchestrator = app(ResearchOrchestrator::class);
        $tools = $orchestrator->getLoadedTools();

        $requiredTools = [
            'law_vector_search',
            'law_keyword_search',
            'law_hybrid_search',
            'law_lookup',
            'decision_vector_search',
            'decision_keyword_search',
            'decision_hybrid_search',
            'decision_lookup',
            'case_vector_search',
            'case_search',
            'graph_query',
            'note_save',
        ];

        foreach ($requiredTools as $tool) {
            $this->assertArrayHasKey($tool, $tools, "Missing tool: {$tool}");
        }
    }

    #[Test]
    public function it_has_correct_agent_configuration(): void
    {
        $orchestrator = app(ResearchOrchestrator::class);

        // Use reflection to check protected properties
        $reflection = new \ReflectionClass($orchestrator);

        $nameProperty = $reflection->getProperty('name');
        $nameProperty->setAccessible(true);
        $this->assertEquals('research_orchestrator', $nameProperty->getValue($orchestrator));

        $modelProperty = $reflection->getProperty('model');
        $modelProperty->setAccessible(true);
        $this->assertEquals('gpt-4o-mini', $modelProperty->getValue($orchestrator));
    }
}
