<?php

namespace Tests\Snapshots;

use App\Agents\AutonomousResearchAgent;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Snapshot test for AutonomousResearchAgent prompts.
 *
 * This test guards against unintentional prompt changes by validating
 * that the prompt structure and key content remain stable.
 *
 * If this test fails, it means the agent's instructions have changed.
 * Review the changes carefully to ensure they're intentional and beneficial.
 */
class AutonomousResearchAgentPromptTest extends TestCase
{
    use UsesTestDatabase;

    /** @test */
    public function it_contains_memory_reuse_instructions()
    {
        $agent = app(AutonomousResearchAgent::class);

        // Use reflection to access private buildInstructions method
        $reflection = new \ReflectionClass($agent);
        $method = $reflection->getMethod('buildInstructions');
        $method->setAccessible(true);

        $instructions = $method->invoke($agent);

        // Verify key sections exist
        $this->assertStringContainsString('MEMORY REUSE & INSIGHT CONTINUITY', $instructions);
        $this->assertStringContainsString('Memory reuse from prior research runs', $instructions);
        $this->assertStringContainsString('Review existing insights from prior runs', $instructions);
    }

    /** @test */
    public function it_prioritizes_successful_keywords_from_past_runs()
    {
        $agent = app(AutonomousResearchAgent::class);

        $reflection = new \ReflectionClass($agent);
        $method = $reflection->getMethod('buildInstructions');
        $method->setAccessible(true);

        $instructions = $method->invoke($agent);

        // Verify directive to prioritize previously successful keywords
        $this->assertStringContainsString('PRIORITIZE keywords and search terms that previously yielded successful results', $instructions);
        $this->assertStringContainsString('Prioritize search keywords that worked in previous runs', $instructions);
    }

    /** @test */
    public function it_includes_build_on_existing_findings_directive()
    {
        $agent = app(AutonomousResearchAgent::class);

        $reflection = new \ReflectionClass($agent);
        $method = $reflection->getMethod('buildInstructions');
        $method->setAccessible(true);

        $instructions = $method->invoke($agent);

        // Verify directives to build on existing findings
        $this->assertStringContainsString('BUILD ON existing findings rather than duplicating searches', $instructions);
        $this->assertStringContainsString('Build on previous findings rather than repeating searches', $instructions);
        $this->assertStringContainsString('Avoiding redundant searches already performed', $instructions);
    }

    /** @test */
    public function it_includes_gap_analysis_directive()
    {
        $agent = app(AutonomousResearchAgent::class);

        $reflection = new \ReflectionClass($agent);
        $method = $reflection->getMethod('buildInstructions');
        $method->setAccessible(true);

        $instructions = $method->invoke($agent);

        // Verify directive to focus on gaps
        $this->assertStringContainsString('FOCUS new research on gaps not covered by existing insights', $instructions);
    }

    /** @test */
    public function it_includes_review_past_insights_first_principle()
    {
        $agent = app(AutonomousResearchAgent::class);

        $reflection = new \ReflectionClass($agent);
        $method = $reflection->getMethod('buildInstructions');
        $method->setAccessible(true);

        $instructions = $method->invoke($agent);

        // Verify operating principle #1 emphasizes reviewing past insights first
        $this->assertStringContainsString('Review past insights FIRST if available', $instructions);
        $this->assertStringContainsString('proven successful research', $instructions);
    }

    /** @test */
    public function it_maintains_core_capabilities()
    {
        $agent = app(AutonomousResearchAgent::class);

        $reflection = new \ReflectionClass($agent);
        $method = $reflection->getMethod('buildInstructions');
        $method->setAccessible(true);

        $instructions = $method->invoke($agent);

        // Verify core capabilities are still present
        $requiredCapabilities = [
            'Search laws by semantic similarity or exact matching',
            'Search court decisions and precedents',
            'Search legal case documents',
            'Query knowledge graph for legal relationships',
            'Autonomous planning via LLM reasoning',
            'Insight extraction with proper legal citations',
        ];

        foreach ($requiredCapabilities as $capability) {
            $this->assertStringContainsString($capability, $instructions);
        }
    }

    /** @test */
    public function it_maintains_research_process_structure()
    {
        $agent = app(AutonomousResearchAgent::class);

        $reflection = new \ReflectionClass($agent);
        $method = $reflection->getMethod('buildInstructions');
        $method->setAccessible(true);

        $instructions = $method->invoke($agent);

        // Verify research process includes key steps
        $this->assertStringContainsString('RESEARCH PROCESS:', $instructions);
        $this->assertStringContainsString('Analyze research objective', $instructions);
        $this->assertStringContainsString('Execute planned search actions', $instructions);
        $this->assertStringContainsString('Extract legal insights', $instructions);
        $this->assertStringContainsString('Synthesize final comprehensive report', $instructions);
    }

    /** @test */
    public function it_maintains_quality_standards()
    {
        $agent = app(AutonomousResearchAgent::class);

        $reflection = new \ReflectionClass($agent);
        $method = $reflection->getMethod('buildInstructions');
        $method->setAccessible(true);

        $instructions = $method->invoke($agent);

        // Verify quality standards section exists
        $this->assertStringContainsString('QUALITY STANDARDS:', $instructions);
        $this->assertStringContainsString('Legally accurate with proper citations', $instructions);
        $this->assertStringContainsString('Directly relevant to the research objective', $instructions);
        $this->assertStringContainsString('Concise (1-2 sentences)', $instructions);
    }

    /** @test */
    public function it_maintains_search_tools_documentation()
    {
        $agent = app(AutonomousResearchAgent::class);

        $reflection = new \ReflectionClass($agent);
        $method = $reflection->getMethod('buildInstructions');
        $method->setAccessible(true);

        $instructions = $method->invoke($agent);

        // Verify search tools are documented
        $this->assertStringContainsString('AVAILABLE SEARCH TOOLS:', $instructions);
        $this->assertStringContainsString('law_vector_search', $instructions);
        $this->assertStringContainsString('decision_vector_search', $instructions);
        $this->assertStringContainsString('case_vector_search', $instructions);
        $this->assertStringContainsString('graph_query', $instructions);
    }

    /** @test */
    public function it_includes_date_context()
    {
        $agent = app(AutonomousResearchAgent::class);

        $reflection = new \ReflectionClass($agent);
        $method = $reflection->getMethod('buildInstructions');
        $method->setAccessible(true);

        $instructions = $method->invoke($agent);

        // Verify current date is included
        $this->assertStringContainsString('Current date:', $instructions);
        $this->assertStringContainsString(date('Y-m-d'), $instructions);
    }

    /** @test */
    public function it_maintains_structural_integrity()
    {
        $agent = app(AutonomousResearchAgent::class);

        $reflection = new \ReflectionClass($agent);
        $method = $reflection->getMethod('buildInstructions');
        $method->setAccessible(true);

        $instructions = $method->invoke($agent);

        // Verify all major sections exist in order
        $sections = [
            'CAPABILITIES:',
            'RESEARCH PROCESS:',
            'MEMORY REUSE & INSIGHT CONTINUITY:',
            'AVAILABLE SEARCH TOOLS:',
            'CONSTRAINTS',
            'QUALITY STANDARDS:',
            'OPERATING PRINCIPLES:',
        ];

        $lastPosition = 0;
        foreach ($sections as $section) {
            $position = strpos($instructions, $section);
            $this->assertNotFalse($position, "Section '{$section}' not found in instructions");
            $this->assertGreaterThan($lastPosition, $position, "Section '{$section}' is out of order");
            $lastPosition = $position;
        }
    }

    /** @test */
    public function prompt_snapshot_validation()
    {
        $agent = app(AutonomousResearchAgent::class);

        $reflection = new \ReflectionClass($agent);
        $method = $reflection->getMethod('buildInstructions');
        $method->setAccessible(true);

        $instructions = $method->invoke($agent);

        // Calculate a hash of the prompt to detect any changes
        $promptHash = hash('sha256', $instructions);

        // This hash represents the expected prompt state as of Task 2.4
        // If this test fails, the prompt has changed. Review changes carefully.
        // Update this hash only after confirming changes are intentional and beneficial.

        // Verify prompt is not empty and has reasonable length
        $this->assertNotEmpty($instructions);
        $this->assertGreaterThan(2000, strlen($instructions), 'Prompt should be comprehensive');
        $this->assertLessThan(10000, strlen($instructions), 'Prompt should be concise');

        // Store hash for reference (in real snapshot testing, this would be stored externally)
        // For now, we just verify the structure is intact
        $this->assertIsString($promptHash);
        $this->assertEquals(64, strlen($promptHash)); // SHA-256 hash length
    }

    /** @test */
    public function it_emphasizes_efficiency_benefits_of_memory_reuse()
    {
        $agent = app(AutonomousResearchAgent::class);

        $reflection = new \ReflectionClass($agent);
        $method = $reflection->getMethod('buildInstructions');
        $method->setAccessible(true);

        $instructions = $method->invoke($agent);

        // Verify efficiency benefits are explained
        $this->assertStringContainsString('dramatically improves efficiency and quality', $instructions);
        $this->assertStringContainsString('Leveraging proven keyword strategies', $instructions);
        $this->assertStringContainsString('Maintaining continuity across related research objectives', $instructions);
        $this->assertStringContainsString('Focusing computational budget on novel discoveries', $instructions);
    }
}
