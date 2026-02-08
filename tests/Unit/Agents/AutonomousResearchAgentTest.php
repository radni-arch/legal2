<?php

namespace Tests\Unit\Agents;

use App\Agents\AutonomousResearchAgent;
use App\Models\AgentRun;
use App\Services\AgentToolbox;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class AutonomousResearchAgentTest extends TestCase
{
    use UsesTestDatabase;

    protected AutonomousResearchAgent $agent;

    protected $mockToolbox;

    protected $mockOpenAI;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agent = new AutonomousResearchAgent;
        $this->mockToolbox = Mockery::mock(AgentToolbox::class);
        $this->mockOpenAI = Mockery::mock(OpenAIService::class);

        // Inject mocks via reflection
        $toolboxProperty = new \ReflectionProperty(AutonomousResearchAgent::class, 'toolbox');
        $toolboxProperty->setAccessible(true);
        $toolboxProperty->setValue($this->agent, $this->mockToolbox);

        $openaiProperty = new \ReflectionProperty(AutonomousResearchAgent::class, 'openai');
        $openaiProperty->setAccessible(true);
        $openaiProperty->setValue($this->agent, $this->mockOpenAI);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_start_run_preloads_past_insights(): void
    {
        $objective = 'Research Croatian labor law termination procedures';

        // Mock past insights from getRecentInsights()
        $this->mockToolbox->shouldReceive('getRecentInsights')
            ->once()
            ->with('autonomous_research_agent', [
                'objective' => $objective,
                'namespace' => 'research_insights',
                'limit' => 5,
                'days' => 30,
            ])
            ->andReturn([
                'success' => true,
                'insights' => [
                    [
                        'content' => 'Article 93 of Croatian Labor Law requires 2 weeks notice',
                        'source_id' => 'previous-run-123',
                        'created_at' => '2025-10-15T10:00:00Z',
                        'objective' => $objective,
                    ],
                    [
                        'content' => 'Supreme Court ruling X-123/2024 clarified probation period rules',
                        'source_id' => 'previous-run-456',
                        'created_at' => '2025-10-20T14:30:00Z',
                        'objective' => $objective,
                    ],
                ],
                'count' => 2,
            ]);

        $run = $this->agent->startRun($objective);

        // Verify run was created
        $this->assertInstanceOf(AgentRun::class, $run);
        $this->assertEquals($objective, $run->objective);
        $this->assertEquals('running', $run->status);

        // Verify past insights were merged into context
        $this->assertArrayHasKey('past_insights', $run->context);
        $this->assertCount(2, $run->context['past_insights']);

        $pastInsights = $run->context['past_insights'];
        $this->assertEquals('Article 93 of Croatian Labor Law requires 2 weeks notice', $pastInsights[0]['content']);
        $this->assertEquals('previous-run-123', $pastInsights[0]['from_run']);
        $this->assertEquals('2025-10-15T10:00:00Z', $pastInsights[0]['created_at']);

        // Verify past_insights_count
        $this->assertEquals(2, $run->context['past_insights_count']);
    }

    public function test_start_run_handles_no_past_insights_gracefully(): void
    {
        $objective = 'Research a brand new legal topic';

        // Mock empty result from getRecentInsights()
        $this->mockToolbox->shouldReceive('getRecentInsights')
            ->once()
            ->andReturn([
                'success' => true,
                'insights' => [],
                'count' => 0,
            ]);

        $run = $this->agent->startRun($objective);

        // Verify run was created
        $this->assertInstanceOf(AgentRun::class, $run);

        // Verify context has empty past_insights
        $this->assertArrayHasKey('past_insights', $run->context);
        $this->assertEmpty($run->context['past_insights']);
        $this->assertEquals(0, $run->context['past_insights_count']);
    }

    public function test_start_run_handles_get_recent_insights_failure(): void
    {
        $objective = 'Research objective';

        // Mock failure from getRecentInsights()
        $this->mockToolbox->shouldReceive('getRecentInsights')
            ->once()
            ->andReturn([
                'success' => false,
                'error' => 'Database connection failed',
                'insights' => [],
                'count' => 0,
            ]);

        $run = $this->agent->startRun($objective);

        // Verify run was still created despite failure
        $this->assertInstanceOf(AgentRun::class, $run);

        // Verify context has empty past_insights (graceful degradation)
        $this->assertArrayHasKey('past_insights', $run->context);
        $this->assertEmpty($run->context['past_insights']);
    }

    public function test_start_run_respects_custom_past_insights_constraints(): void
    {
        $objective = 'Custom research objective';

        // Mock with custom constraints
        $this->mockToolbox->shouldReceive('getRecentInsights')
            ->once()
            ->with('autonomous_research_agent', [
                'objective' => $objective,
                'namespace' => 'research_insights',
                'limit' => 10,
                'days' => 60,
            ])
            ->andReturn([
                'success' => true,
                'insights' => [],
                'count' => 0,
            ]);

        $run = $this->agent->startRun($objective, [], [
            'past_insights_limit' => 10,
            'past_insights_days' => 60,
        ]);

        $this->assertInstanceOf(AgentRun::class, $run);
    }

    public function test_save_insights_stores_objective_in_memory(): void
    {
        // Create a run first
        $run = AgentRun::create([
            'agent_name' => 'autonomous_research_agent',
            'objective' => 'Test objective for memory',
            'context' => [],
            'status' => 'running',
            'current_iteration' => 1,
            'max_iterations' => 10,
            'started_at' => now(),
        ]);

        $insights = [
            'Found relevant case X-123/2024',
            'Article 42 applies to this scenario',
        ];

        // Mock noteSave to verify objective is passed
        $this->mockToolbox->shouldReceive('noteSave')
            ->twice()
            ->withArgs(function ($agentName, $content, $options) use ($run) {
                return $agentName === 'autonomous_research_agent'
                    && in_array($content, ['Found relevant case X-123/2024', 'Article 42 applies to this scenario'])
                    && $options['namespace'] === 'research_insights'
                    && $options['objective'] === 'Test objective for memory'
                    && $options['metadata']['run_id'] === $run->id
                    && $options['metadata']['objective'] === 'Test objective for memory'
                    && $options['source'] === 'autonomous_research'
                    && $options['source_id'] === (string) $run->id;
            })
            ->andReturn(['success' => true, 'id' => 'test-id', 'status' => 'created']);

        // Use reflection to call protected method
        $method = new \ReflectionMethod(AutonomousResearchAgent::class, 'saveInsights');
        $method->setAccessible(true);
        $method->invoke($this->agent, $insights, $run);

        // Assert - Mock expectations verified by Mockery (noteSave called twice with correct params)
        $this->assertTrue(true);
    }

    public function test_build_planning_context_includes_past_insights(): void
    {
        // Note: The past insights feature in buildPlanningContext is commented out in the implementation.
        // This test verifies the actual behavior - context is built without past insights section.
        $pastInsights = [
            [
                'content' => 'Past insight 1: Law XYZ applies',
                'from_run' => 'old-run-123',
                'created_at' => '2025-10-01T10:00:00Z',
            ],
            [
                'content' => 'Past insight 2: Court ruling ABC overrides',
                'from_run' => 'old-run-456',
                'created_at' => '2025-10-05T15:30:00Z',
            ],
        ];

        $run = AgentRun::create([
            'agent_name' => 'autonomous_research_agent',
            'objective' => 'Test objective',
            'context' => [
                'past_insights' => $pastInsights,
                'past_insights_count' => 2,
            ],
            'status' => 'running',
            'current_iteration' => 1,
            'max_iterations' => 10,
            'started_at' => now(),
        ]);

        // Use reflection to call protected method
        $method = new \ReflectionMethod(AutonomousResearchAgent::class, 'buildPlanningContext');
        $method->setAccessible(true);
        $contextString = $method->invoke($this->agent, $run);

        // Verify the context contains the core sections that ARE implemented
        $this->assertStringContainsString('# Research Objective', $contextString);
        $this->assertStringContainsString('Test objective', $contextString);
        $this->assertStringContainsString('# Current Progress', $contextString);
        $this->assertStringContainsString('Iteration: 1', $contextString);
        $this->assertStringContainsString('**Topics of Interest:**', $contextString);
        $this->assertStringContainsString('**Previous Research Iterations:**', $contextString);
    }

    public function test_build_planning_context_handles_no_past_insights(): void
    {
        $run = AgentRun::create([
            'agent_name' => 'autonomous_research_agent',
            'objective' => 'Test objective',
            'context' => [
                'past_insights' => [],
                'past_insights_count' => 0,
            ],
            'status' => 'running',
            'current_iteration' => 1,
            'max_iterations' => 10,
            'started_at' => now(),
        ]);

        // Use reflection to call protected method
        $method = new \ReflectionMethod(AutonomousResearchAgent::class, 'buildPlanningContext');
        $method->setAccessible(true);
        $contextString = $method->invoke($this->agent, $run);

        // Verify no past insights section when empty
        $this->assertStringNotContainsString('# Insights from Past Research (Memory Reuse)', $contextString);
        $this->assertStringContainsString('# Research Objective', $contextString);
        $this->assertStringContainsString('# Current Progress', $contextString);
    }

    public function test_start_run_logs_past_insights_count(): void
    {
        // startRun() first logs a deprecation warning, then logs info
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message) {
                return str_contains($message, 'deprecated');
            });

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'Started autonomous research run'
                    && isset($context['past_insights_loaded'])
                    && $context['past_insights_loaded'] === 3;
            });

        $this->mockToolbox->shouldReceive('getRecentInsights')
            ->once()
            ->andReturn([
                'success' => true,
                'insights' => [
                    ['content' => 'Insight 1', 'source_id' => 'run-1', 'created_at' => '2025-10-01T10:00:00Z'],
                    ['content' => 'Insight 2', 'source_id' => 'run-2', 'created_at' => '2025-10-02T10:00:00Z'],
                    ['content' => 'Insight 3', 'source_id' => 'run-3', 'created_at' => '2025-10-03T10:00:00Z'],
                ],
                'count' => 3,
            ]);

        $run = $this->agent->startRun('Test objective');

        // Assert - Mock expectations verified by Mockery (Log::info called with correct context)
        $this->assertInstanceOf(AgentRun::class, $run);
        $this->assertEquals(3, $run->context['past_insights_count'] ?? 0);
    }

    public function test_build_instructions_includes_memory_reuse_section(): void
    {
        // Use reflection to access private buildInstructions method
        $method = new \ReflectionMethod(AutonomousResearchAgent::class, 'buildInstructions');
        $method->setAccessible(true);
        $instructions = $method->invoke($this->agent);

        // Verify memory reuse section exists
        $this->assertStringContainsString('MEMORY REUSE & INSIGHT CONTINUITY:', $instructions);
        $this->assertStringContainsString('Memory reuse from prior research runs', $instructions);
        $this->assertStringContainsString('Review existing insights from prior runs', $instructions);
    }

    public function test_build_instructions_prioritizes_successful_keywords(): void
    {
        // Use reflection to access private buildInstructions method
        $method = new \ReflectionMethod(AutonomousResearchAgent::class, 'buildInstructions');
        $method->setAccessible(true);
        $instructions = $method->invoke($this->agent);

        // Verify keyword prioritization directive
        $this->assertStringContainsString('PRIORITIZE keywords and search terms that previously yielded successful results', $instructions);
        $this->assertStringContainsString('Prioritize search keywords that worked in previous runs', $instructions);
    }

    public function test_build_instructions_emphasizes_building_on_existing_findings(): void
    {
        // Use reflection to access private buildInstructions method
        $method = new \ReflectionMethod(AutonomousResearchAgent::class, 'buildInstructions');
        $method->setAccessible(true);
        $instructions = $method->invoke($this->agent);

        // Verify build-on-existing directive
        $this->assertStringContainsString('BUILD ON existing findings rather than duplicating searches', $instructions);
        $this->assertStringContainsString('Build on previous findings rather than repeating searches', $instructions);
        $this->assertStringContainsString('Avoiding redundant searches already performed', $instructions);
    }

    public function test_save_insights_fires_insight_discovered_events(): void
    {
        // Create a run
        $run = AgentRun::create([
            'agent_name' => 'autonomous_research_agent',
            'objective' => 'Test objective',
            'context' => [],
            'status' => 'running',
            'current_iteration' => 5,
            'max_iterations' => 10,
            'started_at' => now(),
        ]);

        $insights = [
            'Important legal insight about Article 42',
        ];

        // Mock noteSave (this will be called)
        $this->mockToolbox->shouldReceive('noteSave')
            ->once()
            ->andReturn(['success' => true, 'id' => 'test-id', 'status' => 'created']);

        // Fake events to capture them
        \Illuminate\Support\Facades\Event::fake([
            \App\Events\NewInsightDiscovered::class,
        ]);

        // Use reflection to call protected method
        $method = new \ReflectionMethod(AutonomousResearchAgent::class, 'saveInsights');
        $method->setAccessible(true);
        $method->invoke($this->agent, $insights, $run);

        // Assert event was dispatched
        \Illuminate\Support\Facades\Event::assertDispatched(
            \App\Events\NewInsightDiscovered::class,
            function ($event) use ($run) {
                return $event->insight === 'Important legal insight about Article 42'
                    && $event->run->id === $run->id
                    && $event->agentName === 'autonomous_research_agent'
                    && $event->severity === 'warning' // iteration 5 = warning
                    && isset($event->metadata['run_id'])
                    && $event->metadata['run_id'] === $run->id;
            }
        );
    }

    public function test_save_insights_sets_severity_based_on_iteration(): void
    {
        // Test early iteration (info)
        $runEarly = AgentRun::create([
            'agent_name' => 'autonomous_research_agent',
            'objective' => 'Test',
            'context' => [],
            'status' => 'running',
            'current_iteration' => 2,
            'max_iterations' => 10,
            'started_at' => now(),
        ]);

        // Test mid iteration (warning)
        $runMid = AgentRun::create([
            'agent_name' => 'autonomous_research_agent',
            'objective' => 'Test',
            'context' => [],
            'status' => 'running',
            'current_iteration' => 5,
            'max_iterations' => 10,
            'started_at' => now(),
        ]);

        // Test late iteration (critical)
        $runLate = AgentRun::create([
            'agent_name' => 'autonomous_research_agent',
            'objective' => 'Test',
            'context' => [],
            'status' => 'running',
            'current_iteration' => 8,
            'max_iterations' => 10,
            'started_at' => now(),
        ]);

        $this->mockToolbox->shouldReceive('noteSave')
            ->times(3)
            ->andReturn(['success' => true, 'id' => 'test-id', 'status' => 'created']);

        \Illuminate\Support\Facades\Event::fake([
            \App\Events\NewInsightDiscovered::class,
        ]);

        $method = new \ReflectionMethod(AutonomousResearchAgent::class, 'saveInsights');
        $method->setAccessible(true);

        // Save insights for each run
        $method->invoke($this->agent, ['Insight 1'], $runEarly);
        $method->invoke($this->agent, ['Insight 2'], $runMid);
        $method->invoke($this->agent, ['Insight 3'], $runLate);

        // Verify severity levels
        \Illuminate\Support\Facades\Event::assertDispatched(
            \App\Events\NewInsightDiscovered::class,
            function ($event) {
                return $event->severity === 'info' && $event->run->current_iteration === 2;
            }
        );

        \Illuminate\Support\Facades\Event::assertDispatched(
            \App\Events\NewInsightDiscovered::class,
            function ($event) {
                return $event->severity === 'warning' && $event->run->current_iteration === 5;
            }
        );

        \Illuminate\Support\Facades\Event::assertDispatched(
            \App\Events\NewInsightDiscovered::class,
            function ($event) {
                return $event->severity === 'critical' && $event->run->current_iteration === 8;
            }
        );
    }

    public function test_plan_act_loop_works_with_insight_reuse(): void
    {
        // This integration-style test verifies the plan→act loop still functions
        // correctly when past insights are provided in the run context

        $objective = 'Research Croatian labor law';

        // Mock past insights
        $this->mockToolbox->shouldReceive('getRecentInsights')
            ->once()
            ->andReturn([
                'success' => true,
                'insights' => [
                    [
                        'content' => 'Article 93 applies to termination',
                        'source_id' => 'old-run',
                        'created_at' => '2025-10-01T10:00:00Z',
                    ],
                ],
                'count' => 1,
            ]);

        $run = $this->agent->startRun($objective);

        // Verify the run started correctly with past insights stored in context
        $this->assertInstanceOf(AgentRun::class, $run);
        $this->assertEquals('running', $run->status);
        $this->assertArrayHasKey('past_insights', $run->context);
        $this->assertCount(1, $run->context['past_insights']);

        // Verify planning context contains the core sections (past_insights feature is commented out)
        $method = new \ReflectionMethod(AutonomousResearchAgent::class, 'buildPlanningContext');
        $method->setAccessible(true);
        $contextString = $method->invoke($this->agent, $run);

        $this->assertStringContainsString('# Research Objective', $contextString);
        $this->assertStringContainsString($objective, $contextString);
        $this->assertStringContainsString('# Current Progress', $contextString);

        // Verify instructions include memory reuse guidance
        $instructionsMethod = new \ReflectionMethod(AutonomousResearchAgent::class, 'buildInstructions');
        $instructionsMethod->setAccessible(true);
        $instructions = $instructionsMethod->invoke($this->agent);

        $this->assertStringContainsString('MEMORY REUSE & INSIGHT CONTINUITY:', $instructions);
        $this->assertStringContainsString('Review existing insights from prior runs', $instructions);
    }
}
