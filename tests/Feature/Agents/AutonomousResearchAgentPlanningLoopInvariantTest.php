<?php

namespace Tests\Feature\Agents;

use App\Agents\AutonomousResearchAgent;
use App\Models\AgentRun;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Proving tests for AutonomousResearchAgent control flow.
 *
 * These tests are intentionally deterministic:
 * - No real OpenAI calls (planNextStep / evaluateIteration are stubbed)
 * - No real external tools (executeActions is stubbed)
 *
 * Goal: lock down contracts that prevent silent failures / infinite loops
 * in the plan→act→evaluate iteration process.
 */
class AutonomousResearchAgentPlanningLoopInvariantTest extends TestCase
{
    use UsesTestDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Invariant: LLM plans must include a non-empty reasoning string and an actions array.
     *
     * Reasoning: planNextStep() will accept/reject based on validatePlan();
     * missing fields can cause undefined behavior or repeated fallback planning.
     */
    public function test_validate_plan_rejects_missing_required_fields(): void
    {
        $agent = new AutonomousResearchAgent;

        $this->assertFalse($this->callProtectedMethod($agent, 'validatePlan', [[
            // missing reasoning
            'actions' => [
                ['tool' => 'law_vector_search', 'params' => ['query' => 'ZKP čl. 9']],
            ],
        ]]));

        $this->assertFalse($this->callProtectedMethod($agent, 'validatePlan', [[
            // actions not an array
            'reasoning' => 'Valid reasoning but invalid actions type',
            'actions' => 'not-an-array',
        ]]));

        $this->assertFalse($this->callProtectedMethod($agent, 'validatePlan', [[
            // action missing tool/params
            'reasoning' => 'Valid reasoning but invalid action structure',
            'actions' => [
                ['tool' => 'law_vector_search'],
            ],
        ]]));
    }

    /**
     * Invariant: should_stop=true is allowed with empty actions.
     *
     * Reasoning: the agent must be able to stop cleanly without attempting tool calls,
     * otherwise it may thrash or perform unnecessary actions.
     */
    public function test_validate_plan_allows_stop_with_no_actions(): void
    {
        $agent = new AutonomousResearchAgent;

        $this->assertTrue($this->callProtectedMethod($agent, 'validatePlan', [[
            'reasoning' => 'Objective already sufficiently addressed by existing insights.',
            'should_stop' => true,
            'actions' => [],
        ]]));
    }

    /**
     * Invariant: executeIteration must short-circuit when plan requests stop
     * and must NOT call executeActions.
     *
     * Reasoning: prevents wasted tool calls and prevents side-effects after the
     * agent declared completion.
     */
    public function test_execute_iteration_short_circuits_on_should_stop(): void
    {
        $run = AgentRun::factory()->create([
            'objective' => 'Test objective',
            'current_iteration' => 0,
            'max_iterations' => 5,
        ]);

        $agent = Mockery::mock(AutonomousResearchAgent::class)->makePartial();
        $agent->shouldAllowMockingProtectedMethods();

        $agent->shouldReceive('planNextStep')->once()->with($run)->andReturn([
            'reasoning' => 'We already have enough sources and insights.',
            'should_stop' => true,
            'actions' => [],
        ]);

        $agent->shouldReceive('executeActions')->never();

        $iteration = $this->callProtectedMethod($agent, 'executeIteration', [$run]);

        $this->assertTrue($iteration['stopped_early']);
        $this->assertEquals('We already have enough sources and insights.', $iteration['stop_reason']);
        $this->assertEquals([], $iteration['actions']);
        $this->assertArrayHasKey('plan', $iteration);
        $this->assertArrayNotHasKey('evaluation', $iteration);
    }

    /**
     * Invariant: executeIteration must:
     * - call executeActions with the planned actions
     * - call evaluateIteration with an iteration that already contains actions
     * - call saveInsights when evaluation yields insights
     *
     * Reasoning: ensures the plan→act→evaluate loop actually persists findings.
     */
    public function test_execute_iteration_runs_actions_evaluates_and_saves_insights(): void
    {
        $run = AgentRun::factory()->create([
            'objective' => 'Find relevant ZKP rules for illegal search (pretres doma)',
            'current_iteration' => 0,
            'max_iterations' => 5,
        ]);

        $plan = [
            'reasoning' => 'Start with a focused law search for ZKP provisions on home search and exclusionary rules.',
            'should_stop' => false,
            'actions' => [
                ['tool' => 'law_vector_search', 'params' => ['query' => 'ZKP pretres doma nezakoniti dokaz']],
            ],
        ];

        $actionResults = [
            [
                'tool' => 'law_vector_search',
                'params' => ['query' => 'ZKP pretres doma nezakoniti dokaz'],
                'result' => ['success' => true, 'data' => [], 'count' => 0],
                'success' => true,
            ],
        ];

        $evaluation = [
            'insights' => ['ZKP (NN ...) contains rules on home search procedure and evidentiary exclusion (čl. ...).'],
            'insights_count' => 1,
            'should_stop' => false,
        ];

        $agent = Mockery::mock(AutonomousResearchAgent::class)->makePartial();
        $agent->shouldAllowMockingProtectedMethods();

        $agent->shouldReceive('planNextStep')->once()->with($run)->andReturn($plan);

        $agent->shouldReceive('executeActions')
            ->once()
            ->with($plan['actions'], $run)
            ->andReturn($actionResults);

        $agent->shouldReceive('evaluateIteration')
            ->once()
            ->withArgs(function ($passedRun, $iteration) {
                return $passedRun instanceof AgentRun
                    && isset($iteration['actions'])
                    && count($iteration['actions']) === 1;
            })
            ->andReturn($evaluation);

        $agent->shouldReceive('saveInsights')
            ->once()
            ->with($evaluation['insights'], $run);

        $iteration = $this->callProtectedMethod($agent, 'executeIteration', [$run]);

        $this->assertArrayHasKey('plan', $iteration);
        $this->assertArrayHasKey('actions', $iteration);
        $this->assertArrayHasKey('evaluation', $iteration);
        $this->assertEquals(1, $iteration['evaluation']['insights_count']);
        $this->assertEquals($evaluation['insights'][0], $iteration['evaluation']['insights'][0]);
    }

    /**
     * Helper to call protected methods for testing
     */
    protected function callProtectedMethod($object, string $method, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}
