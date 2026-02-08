<?php

namespace Tests\Feature\Agents;

use App\Agents\AutonomousResearchAgent;
use App\Models\AgentRun;
use App\Services\AgentCheckpointService;
use App\Services\AgentEvaluationService;
use Illuminate\Support\Facades\Event;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class AutonomousResearchAgentInvariantsTest extends TestCase
{
    use UsesTestDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Invariant: If plan returns should_stop=true, the iteration must stop early
     * and must not execute actions or evaluation.
     *
     * Reasoning: This prevents wasted budget and prevents side effects when the
     * planner indicates the objective is already satisfied.
     */
    public function test_execute_iteration_stops_early_and_skips_actions_and_evaluation(): void
    {
        $run = AgentRun::factory()->create([
            'status' => 'running',
            'current_iteration' => 0,
            'max_iterations' => 5,
            'started_at' => now(),
            'iterations' => [],
        ]);

        $agent = Mockery::mock(AutonomousResearchAgent::class)->makePartial();
        $agent->shouldAllowMockingProtectedMethods();

        $agent->shouldReceive('planNextStep')
            ->once()
            ->with($run)
            ->andReturn([
                'reasoning' => 'Already enough sources collected. Stopping.',
                'should_stop' => true,
                'actions' => [],
            ]);

        $agent->shouldReceive('executeActions')->never();
        $agent->shouldReceive('evaluateIteration')->never();
        $agent->shouldReceive('saveInsights')->never();

        $iteration = $this->callProtectedMethod($agent, 'executeIteration', [$run]);

        $this->assertTrue($iteration['stopped_early'] ?? false);
        $this->assertSame('Already enough sources collected. Stopping.', $iteration['stop_reason'] ?? null);
        $this->assertSame([], $iteration['actions'] ?? null);
    }

    /**
     * Invariant: If evaluation returns insights, iteration must persist them to memory.
     *
     * Reasoning: The whole point of the agent loop is accumulating usable knowledge.
     * If insights are not saved, later runs lose continuity.
     */
    public function test_execute_iteration_saves_insights_when_present(): void
    {
        $run = AgentRun::factory()->create([
            'status' => 'running',
            'current_iteration' => 0,
            'max_iterations' => 5,
            'started_at' => now(),
            'iterations' => [],
        ]);

        $agent = Mockery::mock(AutonomousResearchAgent::class)->makePartial();
        $agent->shouldAllowMockingProtectedMethods();

        $agent->shouldReceive('planNextStep')
            ->once()
            ->andReturn([
                'reasoning' => 'Search laws relevant to house search proportionality.',
                'should_stop' => false,
                'actions' => [
                    [
                        'tool' => 'law_vector_search',
                        'params' => ['query' => 'pretres doma razmjernost ZKP'],
                    ],
                ],
            ]);

        $agent->shouldReceive('executeActions')
            ->once()
            ->andReturn([
                [
                    'tool' => 'law_vector_search',
                    'params' => ['query' => 'pretres doma razmjernost ZKP'],
                    'result' => ['success' => true, 'data' => []],
                    'success' => true,
                ],
            ]);

        $agent->shouldReceive('evaluateIteration')
            ->once()
            ->andReturn([
                'insights' => ['ZKP čl. 34 zahtijeva obrazloženje i razmjernost pretresa doma.'],
                'insights_count' => 1,
                'should_stop' => false,
            ]);

        $agent->shouldReceive('saveInsights')
            ->once()
            ->with(
                ['ZKP čl. 34 zahtijeva obrazloženje i razmjernost pretresa doma.'],
                Mockery::type(AgentRun::class)
            );

        $iteration = $this->callProtectedMethod($agent, 'executeIteration', [$run]);

        $this->assertArrayHasKey('plan', $iteration);
        $this->assertArrayHasKey('actions', $iteration);
        $this->assertArrayHasKey('evaluation', $iteration);
        $this->assertSame(1, $iteration['evaluation']['insights_count'] ?? null);
    }

    /**
     * Invariant: validatePlan must reject unknown tools.
     *
     * Reasoning: LLM tool hallucinations must not be executed.
     */
    public function test_validate_plan_rejects_unknown_tool(): void
    {
        $agent = new AutonomousResearchAgent;

        $valid = $this->callProtectedMethod($agent, 'validatePlan', [[
            'reasoning' => 'Need to search.',
            'should_stop' => false,
            'actions' => [
                ['tool' => 'definitely_not_a_tool', 'params' => ['query' => 'x']],
            ],
        ]]);

        $this->assertFalse($valid);
    }

    /**
     * Invariant: shouldContinue must enforce budgets and time/iteration limits.
     *
     * Reasoning: Prevent runaway runs in production and keep agent predictable.
     */
    public function test_should_continue_enforces_limits(): void
    {
        $agent = new AutonomousResearchAgent;

        $tooManyIterations = AgentRun::factory()->create([
            'status' => 'running',
            'current_iteration' => 3,
            'max_iterations' => 3,
            'started_at' => now(),
        ]);
        $this->assertFalse($this->callProtectedMethod($agent, 'shouldContinue', [$tooManyIterations]));

        $timeExceeded = AgentRun::factory()->create([
            'status' => 'running',
            'current_iteration' => 0,
            'max_iterations' => 10,
            'time_limit_seconds' => 10,
            'started_at' => now()->subSeconds(30),
        ]);
        $this->assertFalse($this->callProtectedMethod($agent, 'shouldContinue', [$timeExceeded]));

        $tokensExceeded = AgentRun::factory()->create([
            'status' => 'running',
            'current_iteration' => 0,
            'max_iterations' => 10,
            'token_budget' => 100,
            'tokens_used' => 100,
            'started_at' => now(),
        ]);
        $this->assertFalse($this->callProtectedMethod($agent, 'shouldContinue', [$tokensExceeded]));

        $costExceeded = AgentRun::factory()->create([
            'status' => 'running',
            'current_iteration' => 0,
            'max_iterations' => 10,
            'cost_budget' => 1.0,
            'cost_spent' => 1.0,
            'started_at' => now(),
        ]);
        $this->assertFalse($this->callProtectedMethod($agent, 'shouldContinue', [$costExceeded]));
    }

    /**
     * Invariant: executeRun must produce a final_output string and mark run completed
     * even if there are 0 iterations (max_iterations=0).
     *
     * Reasoning: Clients depend on a stable run lifecycle and a final report.
     */
    public function test_execute_run_completes_and_persists_final_output_even_when_zero_iterations(): void
    {
        Event::fake();

        $mockEvaluator = Mockery::mock(AgentEvaluationService::class);
        $mockEvaluator->shouldReceive('evaluateRun')
            ->once()
            ->andReturn(['score' => 0.9]);
        $this->app->instance(AgentEvaluationService::class, $mockEvaluator);

        $mockCheckpoint = Mockery::mock(AgentCheckpointService::class);
        $mockCheckpoint->shouldReceive('clearCheckpoint')->once();
        $mockCheckpoint->shouldReceive('saveCheckpoint')->byDefault();
        $this->app->instance(AgentCheckpointService::class, $mockCheckpoint);

        $run = AgentRun::factory()->create([
            'agent_name' => 'autonomous_research_agent',
            'status' => 'running',
            'current_iteration' => 0,
            'max_iterations' => 0,
            'started_at' => now(),
            'iterations' => [],
        ]);

        $agent = new AutonomousResearchAgent;
        $completed = $agent->executeRun($run);

        $this->assertSame('completed', $completed->status);
        $this->assertSame(0.9, $completed->score);
        $this->assertIsString($completed->final_output);
        $this->assertStringContainsString('# Research Report:', $completed->final_output);
    }

    /**
     * Helper to call protected methods for testing.
     */
    protected function callProtectedMethod($object, string $method, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $m = $reflection->getMethod($method);
        $m->setAccessible(true);

        return $m->invokeArgs($object, $parameters);
    }
}
