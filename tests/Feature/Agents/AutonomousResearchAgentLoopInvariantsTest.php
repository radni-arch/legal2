<?php

namespace Tests\Feature\Agents;

use App\Agents\AutonomousResearchAgent;
use App\Models\AgentRun;
use App\Services\AgentToolbox;
use App\Services\OpenAIService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class AutonomousResearchAgentLoopInvariantsTest extends TestCase
{
    use UsesTestDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Invariant: If the LLM planning response is malformed (invalid JSON / wrong shape),
     * the agent MUST fall back to a safe default plan rather than crashing.
     *
     * Reasoning: planNextStep() is on the critical path for every research loop iteration,
     * and the system must remain robust when the LLM returns garbage.
     */
    public function test_plan_next_step_returns_fallback_when_llm_response_is_invalid_json(): void
    {
        $toolbox = Mockery::mock(AgentToolbox::class);
        $toolbox->shouldReceive('getRecentInsights')->andReturn(['success' => false, 'insights' => []]);
        $this->app->instance(AgentToolbox::class, $toolbox);

        $openai = Mockery::mock(OpenAIService::class);
        $openai->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => 'NOT-JSON']],
                ],
                'usage' => ['total_tokens' => 10],
            ]);
        $this->app->instance(OpenAIService::class, $openai);

        $agent = new AutonomousResearchAgent;
        $run = $agent->startRun('Research ZKP proportionality for home search warrants');

        $plan = $this->callProtectedMethod($agent, 'planNextStep', [$run]);

        $this->assertIsArray($plan);
        $this->assertFalse($plan['should_stop']);
        $this->assertNotEmpty($plan['actions']);
        $this->assertStringContainsString('LLM planning failed', $plan['reasoning']);

        // First-iteration fallback should start with law_vector_search on the objective
        $this->assertSame('law_vector_search', $plan['actions'][0]['tool']);
        $this->assertSame('Research ZKP proportionality for home search warrants', $plan['actions'][0]['params']['query']);
    }

    /**
     * Invariant: If the plan explicitly sets should_stop=true, executeIteration MUST NOT execute tools.
     *
     * Reasoning: this prevents wasteful (and potentially harmful) side effects after the agent
     * decides it has sufficient information.
     */
    public function test_execute_iteration_honors_should_stop_and_skips_actions(): void
    {
        $agent = new class extends AutonomousResearchAgent {
            public bool $executeActionsCalled = false;
            public bool $evaluateIterationCalled = false;
            public bool $saveInsightsCalled = false;

            protected function planNextStep(AgentRun $run): array
            {
                return [
                    'reasoning' => 'We have sufficient sources already.',
                    'should_stop' => true,
                    'actions' => [],
                ];
            }

            protected function executeActions(array $actions, AgentRun $run): array
            {
                $this->executeActionsCalled = true;

                return [];
            }

            protected function evaluateIteration(AgentRun $run, array $iteration): array
            {
                $this->evaluateIterationCalled = true;

                return ['insights' => [], 'should_stop' => true];
            }

            protected function saveInsights(array $insights, AgentRun $run): void
            {
                $this->saveInsightsCalled = true;
            }

            public function executeIterationPublic(AgentRun $run): array
            {
                return parent::executeIteration($run);
            }
        };

        $run = AgentRun::factory()->create([
            'agent_name' => 'autonomous_research_agent',
            'objective' => 'Test objective',
            'status' => 'running',
            'current_iteration' => 0,
            'max_iterations' => 10,
            'threshold' => 0.75,
            'tokens_used' => 0,
            'cost_spent' => 0,
            'started_at' => now(),
            'iterations' => [],
        ]);

        $iteration = $agent->executeIterationPublic($run);

        $this->assertTrue($iteration['stopped_early']);
        $this->assertSame('We have sufficient sources already.', $iteration['stop_reason']);
        $this->assertSame([], $iteration['actions']);

        $this->assertFalse($agent->executeActionsCalled);
        $this->assertFalse($agent->evaluateIterationCalled);
        $this->assertFalse($agent->saveInsightsCalled);
    }

    /**
     * Invariant: If evaluation produces insights, executeIteration MUST attempt to persist them
     * (via saveInsights), even if there were zero actions.
     *
     * Reasoning: insights persistence is the core of the “memory reuse” feature used by startRun(),
     * which preloads prior insights from the toolbox. If we drop insights, we lose continuity.
     */
    public function test_execute_iteration_saves_insights_when_evaluation_provides_them(): void
    {
        $agent = new class extends AutonomousResearchAgent {
            public bool $saveInsightsCalled = false;
            public array $savedInsights = [];

            protected function planNextStep(AgentRun $run): array
            {
                return [
                    'reasoning' => 'Take a small step',
                    'should_stop' => false,
                    'actions' => [],
                ];
            }

            protected function executeActions(array $actions, AgentRun $run): array
            {
                return [];
            }

            protected function evaluateIteration(AgentRun $run, array $iteration): array
            {
                return [
                    'insights' => ['ZKP čl. 10 – nezakoniti dokaz (test fixture).'],
                    'insights_count' => 1,
                    'should_stop' => false,
                ];
            }

            protected function saveInsights(array $insights, AgentRun $run): void
            {
                $this->saveInsightsCalled = true;
                $this->savedInsights = $insights;
            }

            public function executeIterationPublic(AgentRun $run): array
            {
                return parent::executeIteration($run);
            }
        };

        $run = AgentRun::factory()->create([
            'agent_name' => 'autonomous_research_agent',
            'objective' => 'Test objective',
            'status' => 'running',
            'current_iteration' => 0,
            'max_iterations' => 10,
            'threshold' => 0.75,
            'tokens_used' => 0,
            'cost_spent' => 0,
            'started_at' => now(),
            'iterations' => [],
        ]);

        $iteration = $agent->executeIterationPublic($run);

        $this->assertArrayHasKey('evaluation', $iteration);
        $this->assertTrue($agent->saveInsightsCalled);
        $this->assertSame(['ZKP čl. 10 – nezakoniti dokaz (test fixture).'], $agent->savedInsights);
    }

    /**
     * Invariant: saveInsights MUST write to AgentToolbox::noteSave using namespace research_insights
     * and include run_id/objective/iteration metadata.
     *
     * Reasoning: without consistent metadata, later filtering in getRecentInsights() and UI
     * becomes unreliable.
     */
    public function test_save_insights_persists_to_toolbox_with_expected_metadata(): void
    {
        Event::fake();

        $toolbox = Mockery::mock(AgentToolbox::class);
        $toolbox->shouldReceive('noteSave')
            ->once()
            ->with(
                'autonomous_research_agent',
                'Insight with citation: NN 94/14, čl. 1',
                Mockery::on(function ($options) {
                    return ($options['namespace'] ?? null) === 'research_insights'
                        && ($options['objective'] ?? null) === 'Objective ABC'
                        && ($options['metadata']['run_id'] ?? null) !== null
                        && ($options['metadata']['objective'] ?? null) === 'Objective ABC'
                        && array_key_exists('iteration', $options['metadata'] ?? []);
                })
            )
            ->andReturn(['success' => true, 'id' => 'mem-1', 'status' => 'created']);

        // startRun() also reads recent insights
        $toolbox->shouldReceive('getRecentInsights')->andReturn(['success' => false, 'insights' => []]);

        $this->app->instance(AgentToolbox::class, $toolbox);

        $agent = new class extends AutonomousResearchAgent {
            public function saveInsightsPublic(array $insights, AgentRun $run): void
            {
                parent::saveInsights($insights, $run);
            }
        };

        $run = AgentRun::factory()->create([
            'agent_name' => 'autonomous_research_agent',
            'objective' => 'Objective ABC',
            'status' => 'running',
            'current_iteration' => 2,
            'max_iterations' => 10,
            'threshold' => 0.75,
            'tokens_used' => 0,
            'cost_spent' => 0,
            'started_at' => now(),
            'iterations' => [],
        ]);

        $agent->saveInsightsPublic(['Insight with citation: NN 94/14, čl. 1'], $run);
    }

    /**
     * Invariant: shouldContinue MUST enforce iteration/time/token/cost budgets.
     *
     * Reasoning: without hard stops the job/queue workers can hang indefinitely,
     * creating operational and cost risk.
     */
    public function test_should_continue_enforces_budgets(): void
    {
        $agent = new class extends AutonomousResearchAgent {
            public function shouldContinuePublic(AgentRun $run): bool
            {
                return parent::shouldContinue($run);
            }
        };

        // 1) Iteration limit
        $run = AgentRun::factory()->create([
            'current_iteration' => 5,
            'max_iterations' => 5,
            'tokens_used' => 0,
            'cost_spent' => 0,
        ]);
        $this->assertFalse($agent->shouldContinuePublic($run));

        // 2) Token budget
        $run = AgentRun::factory()->create([
            'current_iteration' => 0,
            'max_iterations' => 10,
            'token_budget' => 100,
            'tokens_used' => 100,
            'cost_spent' => 0,
        ]);
        $this->assertFalse($agent->shouldContinuePublic($run));

        // 3) Cost budget
        $run = AgentRun::factory()->create([
            'current_iteration' => 0,
            'max_iterations' => 10,
            'cost_budget' => 0.50,
            'cost_spent' => 0.50,
            'tokens_used' => 0,
        ]);
        $this->assertFalse($agent->shouldContinuePublic($run));

        // 4) Time budget
        Carbon::setTestNow(now());
        $run = AgentRun::factory()->create([
            'current_iteration' => 0,
            'max_iterations' => 10,
            'time_limit_seconds' => 10,
            'started_at' => now()->subSeconds(20),
            'tokens_used' => 0,
            'cost_spent' => 0,
        ]);
        $this->assertFalse($agent->shouldContinuePublic($run));
    }

    /**
     * Helper to call protected methods for testing.
     */
    protected function callProtectedMethod($object, $method, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}
