<?php

namespace Tests\Unit\Services;

use App\Services\Agents\IterationControllerService;
use App\Services\Research\SearchExecutorService;
use App\Services\Research\ResearchPlannerService;
use App\Services\Research\ResearchEvaluatorService;
use App\Services\Research\InsightExtractorService;
use App\Services\Research\ResearchCheckpointService;
use App\Services\ResearchOrchestrator;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Vizra\VizraADK\Agents\BaseLlmAgent;

/**
 * Test subclass that skips heavy tool loading from container.
 * This prevents 12+ container resolutions during tests.
 */
class TestableResearchOrchestrator extends ResearchOrchestrator
{
    public function loadTools(): void
    {
        // Skip tool loading - tests use mocks
    }

    public function loadSubAgents(): void
    {
        // Skip sub-agent loading
    }
}

/**
 * Unit tests for ResearchOrchestrator
 *
 * Tests the orchestration of autonomous research pipeline.
 * ResearchOrchestrator now extends BaseLlmAgent and uses Vizra SDK architecture.
 *
 * Note: RefreshDatabase removed - tests use mocks, no database access needed.
 * Uses TestableResearchOrchestrator to skip container-heavy tool loading.
 */
class ResearchOrchestratorTest extends TestCase
{
    protected TestableResearchOrchestrator $orchestrator;

    protected SearchExecutorService $searchExecutorMock;

    protected IterationControllerService $iterationControllerMock;

    protected ResearchPlannerService $plannerMock;

    protected ResearchEvaluatorService $evaluatorMock;

    protected InsightExtractorService $insightExtractorMock;

    protected ResearchCheckpointService $checkpointMock;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mocks for all dependencies
        $this->searchExecutorMock = Mockery::mock(SearchExecutorService::class);
        $this->iterationControllerMock = Mockery::mock(IterationControllerService::class);
        $this->plannerMock = Mockery::mock(ResearchPlannerService::class);
        $this->evaluatorMock = Mockery::mock(ResearchEvaluatorService::class);
        $this->insightExtractorMock = Mockery::mock(InsightExtractorService::class);
        $this->checkpointMock = Mockery::mock(ResearchCheckpointService::class);

        // Create testable orchestrator with all mocks (skips heavy tool loading)
        $this->orchestrator = new TestableResearchOrchestrator(
            $this->searchExecutorMock,
            $this->iterationControllerMock,
            $this->plannerMock,
            $this->evaluatorMock,
            $this->insightExtractorMock,
            $this->checkpointMock
        );

        // Default mock expectations for new services (can be overridden in individual tests)
        $this->plannerMock->shouldReceive('planNextIteration')
            ->andReturn([
                'reasoning' => 'Default plan',
                'should_stop' => false,
                'actions' => [['tool' => 'law_vector_search', 'params' => ['query' => 'test', 'limit' => 5]]],
            ])
            ->byDefault();

        $this->evaluatorMock->shouldReceive('evaluateIteration')
            ->andReturn([
                'quality_score' => 85,
                'coverage_assessment' => 'Good coverage',
                'gaps_identified' => [],
                'insights' => [],
                'recommendation' => 'continue',
                'tokens_used' => 100,
            ])
            ->byDefault();

        $this->insightExtractorMock->shouldReceive('extractInsights')
            ->andReturn([])
            ->byDefault();

        $this->checkpointMock->shouldReceive('saveCheckpoint')
            ->andReturn(null)
            ->byDefault();

        // Suppress logs
        Log::shouldReceive('info')->andReturn(null);
        Log::shouldReceive('debug')->andReturn(null);
        Log::shouldReceive('warning')->andReturn(null);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ===============================
    // NEW TESTS FOR VIZRA SDK INTEGRATION
    // Uses reflection to avoid slow container resolution
    // ===============================

    #[Test]
    public function it_extends_base_llm_agent(): void
    {
        // Use reflection to verify inheritance without triggering container resolution
        $reflection = new \ReflectionClass(ResearchOrchestrator::class);
        $parentClass = $reflection->getParentClass();

        $this->assertNotFalse($parentClass);
        $this->assertEquals(BaseLlmAgent::class, $parentClass->getName());
    }

    #[Test]
    public function it_has_12_research_tools_configured(): void
    {
        // Use reflection to check tools property without container resolution
        $reflection = new \ReflectionClass(ResearchOrchestrator::class);
        $toolsProperty = $reflection->getProperty('tools');
        $toolsProperty->setAccessible(true);

        // Get default value from the property
        $tools = $toolsProperty->getDefaultValue();

        $this->assertGreaterThanOrEqual(12, count($tools));

        // Verify expected tool classes are configured
        $expectedToolClasses = [
            \App\Tools\Research\LawVectorSearchTool::class,
            \App\Tools\Research\LawKeywordSearchTool::class,
            \App\Tools\Research\LawHybridSearchTool::class,
            \App\Tools\Research\LawLookupTool::class,
            \App\Tools\Research\DecisionVectorSearchTool::class,
            \App\Tools\Research\DecisionKeywordSearchTool::class,
            \App\Tools\Research\DecisionHybridSearchTool::class,
            \App\Tools\Research\DecisionLookupTool::class,
            \App\Tools\Research\CaseVectorSearchTool::class,
            \App\Tools\Research\CaseSearchTool::class,
            \App\Tools\Research\GraphQueryTool::class,
            \App\Tools\Research\NoteSaveTool::class,
        ];

        foreach ($expectedToolClasses as $toolClass) {
            $this->assertContains($toolClass, $tools, "Missing tool: {$toolClass}");
        }
    }

    #[Test]
    public function it_has_required_agent_properties(): void
    {
        // Use reflection to verify properties without calling protected methods
        $reflection = new \ReflectionClass(ResearchOrchestrator::class);

        $nameProperty = $reflection->getProperty('name');
        $nameProperty->setAccessible(true);
        $this->assertEquals('research_orchestrator', $nameProperty->getDefaultValue());

        $modelProperty = $reflection->getProperty('model');
        $modelProperty->setAccessible(true);
        $this->assertEquals('gpt-4o-mini', $modelProperty->getDefaultValue());

        $providerProperty = $reflection->getProperty('provider');
        $providerProperty->setAccessible(true);
        $this->assertEquals('openai', $providerProperty->getDefaultValue());

        $descProperty = $reflection->getProperty('description');
        $descProperty->setAccessible(true);
        $this->assertNotEmpty($descProperty->getDefaultValue());
    }

    // ===============================
    // EXISTING TESTS (updated for new services)
    // ===============================

    /** @test */
    public function test_research_executes_single_iteration(): void
    {
        $query = 'proportionality test in Croatian law';

        // Setup: Allow 1 iteration, then stop
        $this->iterationControllerMock
            ->shouldReceive('reset')
            ->once();

        $this->iterationControllerMock
            ->shouldReceive('shouldContinue')
            ->with(0, 0, Mockery::type('array'))
            ->once()
            ->andReturn(true);

        $this->iterationControllerMock
            ->shouldReceive('shouldContinue')
            ->with(1, Mockery::type('int'), Mockery::type('array'))
            ->once()
            ->andReturn(false);

        // Mock LLM planning
        $this->plannerMock
            ->shouldReceive('planNextIteration')
            ->once()
            ->andReturn([
                'reasoning' => 'Need to search laws',
                'should_stop' => false,
                'actions' => [['tool' => 'law_vector_search', 'params' => ['query' => $query, 'limit' => 5]]],
            ]);

        $this->searchExecutorMock
            ->shouldReceive('execute')
            ->once()
            ->andReturn([
                ['tool' => 'law_vector_search', 'success' => true, 'result' => [['id' => '1']]],
            ]);

        // Mock LLM evaluation (quality_score must meet default threshold of 85)
        $this->evaluatorMock
            ->shouldReceive('evaluateIteration')
            ->once()
            ->andReturn([
                'quality_score' => 85,
                'coverage_assessment' => 'Good coverage',
                'gaps_identified' => [],
                'insights' => [],
                'recommendation' => 'continue',
                'tokens_used' => 300,
            ]);

        $this->iterationControllerMock
            ->shouldReceive('trackUsage')
            ->once();

        // Mock checkpoint save
        $this->checkpointMock
            ->shouldReceive('saveCheckpoint')
            ->never(); // Not called on first iteration (checkpointFrequency = 2)

        $this->iterationControllerMock
            ->shouldReceive('getStopReason')
            ->andReturn('quality_threshold_met');

        $this->iterationControllerMock
            ->shouldReceive('getTotalTokens')
            ->andReturn(500);

        $result = $this->orchestrator->research($query);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['iterations']);
        $this->assertArrayHasKey('answer', $result);
        $this->assertArrayHasKey('insights', $result);
    }

    /** @test */
    public function test_research_executes_multiple_iterations(): void
    {
        $query = 'test query';

        $this->iterationControllerMock->shouldReceive('reset')->once();

        // Allow 3 iterations
        $this->iterationControllerMock
            ->shouldReceive('shouldContinue')
            ->andReturn(true, true, true, false);

        $this->searchExecutorMock
            ->shouldReceive('execute')
            ->times(3)
            ->andReturn([
                ['tool' => 'law_vector_search', 'success' => true, 'result' => [['id' => '1']]],
            ]);

        $this->iterationControllerMock
            ->shouldReceive('trackUsage')
            ->times(3);

        $this->iterationControllerMock
            ->shouldReceive('getStopReason')
            ->andReturn('max_iterations');

        $this->iterationControllerMock
            ->shouldReceive('getTotalTokens')
            ->andReturn(1500);

        $result = $this->orchestrator->research($query);

        $this->assertTrue($result['success']);
        $this->assertEquals(3, $result['iterations']);
        $this->assertEquals('max_iterations', $result['stopped_reason']);
    }

    /** @test */
    public function test_research_respects_custom_limits(): void
    {
        $query = 'test query';
        $customLimits = [
            'max_iterations' => 2,
            'quality_threshold' => 90,
        ];

        $this->iterationControllerMock->shouldReceive('reset')->once();

        // Check that custom limits are passed to shouldContinue
        $this->iterationControllerMock
            ->shouldReceive('shouldContinue')
            ->with(
                Mockery::type('int'),
                Mockery::type('int'),
                Mockery::on(function ($limits) {
                    return $limits['max_iterations'] === 2
                        && $limits['quality_threshold'] === 90;
                })
            )
            ->andReturn(false);

        $this->iterationControllerMock
            ->shouldReceive('getStopReason')
            ->andReturn('max_iterations');

        $this->iterationControllerMock
            ->shouldReceive('getTotalTokens')
            ->andReturn(0);

        $result = $this->orchestrator->research($query, ['limits' => $customLimits]);

        // No iterations ran (shouldContinue returned false), so hasResults is false
        // and success is false, but the test validates limits were passed correctly
        $this->assertFalse($result['success']);
        $this->assertEquals(0, $result['iterations']);
    }

    /** @test */
    public function test_research_tracks_resource_usage(): void
    {
        $query = 'test query';

        $this->iterationControllerMock->shouldReceive('reset')->once();

        $this->iterationControllerMock
            ->shouldReceive('shouldContinue')
            ->andReturn(true, false);

        $this->searchExecutorMock
            ->shouldReceive('execute')
            ->once()
            ->andReturn([
                ['tool' => 'law_vector_search', 'success' => true, 'result' => [['id' => '1']]],
            ]);

        // Verify trackUsage is called with tokens and time
        $this->iterationControllerMock
            ->shouldReceive('trackUsage')
            ->once()
            ->with(Mockery::on(function ($usage) {
                return isset($usage['tokens'])
                    && isset($usage['time'])
                    && $usage['tokens'] > 0
                    && $usage['time'] > 0;
            }));

        $this->iterationControllerMock
            ->shouldReceive('getStopReason')
            ->andReturn('quality_threshold_met');

        $this->iterationControllerMock
            ->shouldReceive('getTotalTokens')
            ->andReturn(500);

        $result = $this->orchestrator->research($query);

        $this->assertArrayHasKey('total_tokens', $result);
        $this->assertArrayHasKey('total_time_s', $result);
    }

    /** @test */
    public function test_research_aggregates_search_results(): void
    {
        $query = 'test query';

        $this->iterationControllerMock->shouldReceive('reset')->once();

        $this->iterationControllerMock
            ->shouldReceive('shouldContinue')
            ->andReturn(true, true, false);

        $searchResult1 = [
            ['tool' => 'law_vector_search', 'success' => true, 'result' => [['id' => '1']]],
        ];

        $searchResult2 = [
            ['tool' => 'decision_vector_search', 'success' => true, 'result' => [['id' => '2']]],
        ];

        $this->searchExecutorMock
            ->shouldReceive('execute')
            ->times(2)
            ->andReturn($searchResult1, $searchResult2);

        $this->iterationControllerMock
            ->shouldReceive('trackUsage')
            ->times(2);

        $this->iterationControllerMock
            ->shouldReceive('getStopReason')
            ->andReturn('quality_threshold_met');

        $this->iterationControllerMock
            ->shouldReceive('getTotalTokens')
            ->andReturn(1000);

        $result = $this->orchestrator->research($query);

        $this->assertArrayHasKey('search_results', $result);
        $this->assertCount(2, $result['search_results']);
        $this->assertEquals(1, $result['search_results'][0]['iteration']);
        $this->assertEquals(2, $result['search_results'][1]['iteration']);
    }

    /** @test */
    public function test_research_synthesizes_answer(): void
    {
        $query = 'test query';

        $this->iterationControllerMock->shouldReceive('reset')->once();

        $this->iterationControllerMock
            ->shouldReceive('shouldContinue')
            ->andReturn(true, false);

        $this->searchExecutorMock
            ->shouldReceive('execute')
            ->once()
            ->andReturn([
                ['tool' => 'law_vector_search', 'success' => true, 'result' => [['id' => '1'], ['id' => '2']]],
            ]);

        $this->iterationControllerMock
            ->shouldReceive('trackUsage')
            ->once();

        $this->iterationControllerMock
            ->shouldReceive('getStopReason')
            ->andReturn('quality_threshold_met');

        $this->iterationControllerMock
            ->shouldReceive('getTotalTokens')
            ->andReturn(500);

        $result = $this->orchestrator->research($query);

        $this->assertArrayHasKey('answer', $result);
        $this->assertStringContainsString($query, $result['answer']);
        $this->assertIsString($result['answer']);
        $this->assertNotEmpty($result['answer']);
    }

    /** @test */
    public function test_research_calculates_quality_score(): void
    {
        $query = 'test query';

        $this->iterationControllerMock->shouldReceive('reset')->once();

        $this->iterationControllerMock
            ->shouldReceive('shouldContinue')
            ->andReturn(true, false);

        $this->searchExecutorMock
            ->shouldReceive('execute')
            ->once()
            ->andReturn([
                ['tool' => 'law_vector_search', 'success' => true, 'result' => [['id' => '1']]],
            ]);

        $this->iterationControllerMock
            ->shouldReceive('trackUsage')
            ->once();

        $this->iterationControllerMock
            ->shouldReceive('getStopReason')
            ->andReturn('quality_threshold_met');

        $this->iterationControllerMock
            ->shouldReceive('getTotalTokens')
            ->andReturn(500);

        $result = $this->orchestrator->research($query);

        $this->assertArrayHasKey('quality_score', $result);
        $this->assertIsInt($result['quality_score']);
        $this->assertGreaterThanOrEqual(0, $result['quality_score']);
        $this->assertLessThanOrEqual(100, $result['quality_score']);
    }

    /** @test */
    public function test_research_handles_failed_searches(): void
    {
        $query = 'test query';

        $this->iterationControllerMock->shouldReceive('reset')->once();

        $this->iterationControllerMock
            ->shouldReceive('shouldContinue')
            ->andReturn(true, false);

        // Return failed search
        $this->searchExecutorMock
            ->shouldReceive('execute')
            ->once()
            ->andReturn([
                ['tool' => 'law_vector_search', 'success' => false, 'error' => 'Search failed'],
            ]);

        $this->iterationControllerMock
            ->shouldReceive('trackUsage')
            ->once();

        $this->iterationControllerMock
            ->shouldReceive('getStopReason')
            ->andReturn('max_iterations');

        $this->iterationControllerMock
            ->shouldReceive('getTotalTokens')
            ->andReturn(0);

        $result = $this->orchestrator->research($query);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('answer', $result);
    }

    /** @test */
    public function test_get_default_limits(): void
    {
        $limits = $this->orchestrator->getDefaultLimits();

        $this->assertIsArray($limits);
        $this->assertArrayHasKey('max_iterations', $limits);
        $this->assertArrayHasKey('quality_threshold', $limits);
        $this->assertEquals(5, $limits['max_iterations']);
        $this->assertEquals(85, $limits['quality_threshold']);
    }

    /** @test */
    public function test_set_default_limits(): void
    {
        $newLimits = [
            'max_iterations' => 10,
            'quality_threshold' => 90,
        ];

        $this->orchestrator->setDefaultLimits($newLimits);

        $limits = $this->orchestrator->getDefaultLimits();

        $this->assertEquals(10, $limits['max_iterations']);
        $this->assertEquals(90, $limits['quality_threshold']);
    }

    /** @test */
    public function test_research_resets_iteration_controller(): void
    {
        $query = 'test query';

        // Verify reset is called at the start
        $this->iterationControllerMock
            ->shouldReceive('reset')
            ->once()
            ->ordered();

        $this->iterationControllerMock
            ->shouldReceive('shouldContinue')
            ->andReturn(false);

        $this->iterationControllerMock
            ->shouldReceive('getStopReason')
            ->andReturn('max_iterations');

        $this->iterationControllerMock
            ->shouldReceive('getTotalTokens')
            ->andReturn(0);

        $result = $this->orchestrator->research($query);

        // Verify the research completed - 0 iterations means no results, so success=false
        // but reset WAS called (which is what this test verifies via the ordered() expectation)
        $this->assertFalse($result['success']);
        $this->assertEquals(0, $result['iterations']);
    }

    /** @test */
    public function test_research_returns_all_required_fields(): void
    {
        $query = 'test query';

        $this->iterationControllerMock->shouldReceive('reset')->once();

        $this->iterationControllerMock
            ->shouldReceive('shouldContinue')
            ->andReturn(false);

        $this->iterationControllerMock
            ->shouldReceive('getStopReason')
            ->andReturn('max_iterations');

        $this->iterationControllerMock
            ->shouldReceive('getTotalTokens')
            ->andReturn(0);

        $result = $this->orchestrator->research($query);

        // Verify all expected fields are present
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('answer', $result);
        $this->assertArrayHasKey('quality_score', $result);
        $this->assertArrayHasKey('iterations', $result);
        $this->assertArrayHasKey('stopped_reason', $result);
        $this->assertArrayHasKey('total_tokens', $result);
        $this->assertArrayHasKey('total_time_s', $result);
        $this->assertArrayHasKey('search_results', $result);
    }

    /** @test */
    public function test_research_merges_custom_and_default_limits(): void
    {
        $query = 'test query';
        $customLimits = ['max_iterations' => 3];

        $this->iterationControllerMock->shouldReceive('reset')->once();

        // Verify that default quality_threshold is preserved
        $this->iterationControllerMock
            ->shouldReceive('shouldContinue')
            ->with(
                Mockery::type('int'),
                Mockery::type('int'),
                Mockery::on(function ($limits) {
                    return $limits['max_iterations'] === 3
                        && $limits['quality_threshold'] === 85; // Default value
                })
            )
            ->andReturn(false);

        $this->iterationControllerMock
            ->shouldReceive('getStopReason')
            ->andReturn('max_iterations');

        $this->iterationControllerMock
            ->shouldReceive('getTotalTokens')
            ->andReturn(0);

        $result = $this->orchestrator->research($query, ['limits' => $customLimits]);

        // Verify limits were merged correctly - 0 iterations means no results, so success=false
        // but the test validates that custom max_iterations merged with default quality_threshold
        $this->assertFalse($result['success']);
        $this->assertEquals('max_iterations', $result['stopped_reason']);
    }
}
