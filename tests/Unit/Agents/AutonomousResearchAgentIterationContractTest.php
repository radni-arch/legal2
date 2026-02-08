<?php

namespace Tests\Unit\Agents;

use App\Agents\AutonomousResearchAgent;
use App\Services\AgentEvaluationService;
use App\Services\AgentToolbox;
use App\Services\CaseSearchService;
use App\Services\DecisionSearchService;
use App\Services\LawSearchService;
use Mockery;
use Tests\Doubles\FakeOpenAIService;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class AutonomousResearchAgentIterationContractTest extends TestCase
{
    use UsesTestDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Invariant: If the plan says should_stop=true, the iteration must stop early
     * and must not execute any actions.
     *
     * Reasoning: This prevents wasting budgets and prevents unintended side-effects
     * when the planner believes the objective is already satisfied.
     */
    public function test_execute_iteration_stops_early_when_plan_requests_stop(): void
    {
        $this->bindCommonServices();

        $agent = Mockery::mock(AutonomousResearchAgent::class)->makePartial();
        $agent->shouldAllowMockingProtectedMethods();

        $agent->shouldReceive('planNextStep')
            ->once()
            ->andReturn([
                'reasoning' => 'We already have sufficient legal sources and findings.',
                'next_focus' => 'Stop',
                'should_stop' => true,
                'actions' => [],
            ]);

        // Critical: should not execute tools if should_stop=true
        $agent->shouldReceive('executeActions')->never();
        $agent->shouldReceive('evaluateIteration')->never();
        $agent->shouldReceive('saveInsights')->never();

        $run = $agent->startRun('Validate home search warrant proportionality');

        $iteration = $this->callProtectedMethod($agent, 'executeIteration', [$run]);

        $this->assertTrue($iteration['stopped_early'] ?? false);
        $this->assertSame('We already have sufficient legal sources and findings.', $iteration['stop_reason'] ?? null);
        $this->assertSame([], $iteration['actions'] ?? null);
        $this->assertArrayNotHasKey('evaluation', $iteration);
    }

    /**
     * Invariant: If should_stop=false, the iteration must execute actions,
     * then evaluate, then persist insights (if any).
     *
     * Reasoning: This is the core plan→act→evaluate contract. If we break ordering
     * or skip persistence, the agent becomes non-deterministic and loses traceability.
     */
    public function test_execute_iteration_executes_actions_evaluates_and_saves_insights(): void
    {
        $this->bindCommonServices();

        $agent = Mockery::mock(AutonomousResearchAgent::class)->makePartial();
        $agent->shouldAllowMockingProtectedMethods();

        $plan = [
            'reasoning' => 'We need to identify the relevant ZKP articles on pretres doma and proportionality.',
            'next_focus' => 'ZKP home search rules',
            'should_stop' => false,
            'actions' => [
                [
                    'tool' => 'law_vector_search',
                    'params' => ['query' => 'ZKP pretres doma proporcionalnost', 'limit' => 3],
                ],
            ],
        ];

        $agent->shouldReceive('planNextStep')->once()->andReturn($plan);

        $agent->shouldReceive('executeActions')
            ->once()
            ->with($plan['actions'], Mockery::type('App\\Models\\AgentRun'))
            ->andReturn([
                [
                    'tool' => 'law_vector_search',
                    'params' => ['query' => 'ZKP pretres doma proporcionalnost', 'limit' => 3],
                    'result' => ['success' => true, 'data' => []],
                    'success' => true,
                ],
            ]);

        $agent->shouldReceive('evaluateIteration')
            ->once()
            ->andReturn([
                'insights' => [
                    'ZKP čl. 240. zahtijeva sudski nalog za pretres doma osim zakonom propisanih iznimki.',
                ],
                'insights_count' => 1,
                'should_stop' => false,
            ]);

        $agent->shouldReceive('saveInsights')
            ->once()
            ->with(
                Mockery::on(function ($insights) {
                    return is_array($insights)
                        && count($insights) === 1
                        && str_contains($insights[0], 'ZKP');
                }),
                Mockery::type('App\\Models\\AgentRun')
            )
            ->andReturnNull();

        $run = $agent->startRun('Find Croatian legal rules on illegal home searches');

        $iteration = $this->callProtectedMethod($agent, 'executeIteration', [$run]);

        $this->assertSame(1, $iteration['number'] ?? null);
        $this->assertArrayHasKey('plan', $iteration);
        $this->assertArrayHasKey('actions', $iteration);
        $this->assertArrayHasKey('evaluation', $iteration);
        $this->assertNotEmpty($iteration['completed_at'] ?? null);
        $this->assertSame(false, $iteration['evaluation']['should_stop'] ?? null);
    }

    /**
     * Invariant: If LLM planning response is invalid JSON / invalid schema,
     * planNextStep must fall back to a safe default plan.
     *
     * Reasoning: This prevents 500s / stuck runs when OpenAI returns malformed output.
     */
    public function test_plan_next_step_falls_back_on_invalid_json(): void
    {
        // Bind FakeOpenAIService to avoid real network calls and force invalid JSON
        $fakeOpenai = (new FakeOpenAIService)->queueChatResponse([
            'choices' => [
                ['message' => ['content' => 'NOT_JSON']],
            ],
            'usage' => ['total_tokens' => 10],
        ]);
        $this->app->instance(\App\Services\OpenAIService::class, $fakeOpenai);

        $this->bindCommonServices();

        $agent = new AutonomousResearchAgent;
        $run = $agent->startRun('Research ZKP rules for house search warrant defects');

        $plan = $this->callProtectedMethod($agent, 'planNextStep', [$run]);

        $this->assertIsArray($plan);
        $this->assertSame(false, $plan['should_stop'] ?? null);
        $this->assertNotEmpty($plan['reasoning'] ?? null);
        $this->assertIsArray($plan['actions'] ?? null);
        $this->assertNotEmpty($plan['actions']);

        // First-iteration fallback must start with law_vector_search
        $this->assertSame('law_vector_search', $plan['actions'][0]['tool'] ?? null);
        $this->assertSame($run->objective, $plan['actions'][0]['params']['query'] ?? null);
    }

    /**
     * Bind container services that AutonomousResearchAgent touches during startRun/iteration.
     */
    protected function bindCommonServices(): void
    {
        // Toolbox is called during startRun() (getRecentInsights)
        if (! $this->app->bound(AgentToolbox::class)) {
            $toolbox = Mockery::mock(AgentToolbox::class);
            $toolbox->shouldReceive('getRecentInsights')->andReturn(['success' => false, 'insights' => []])->byDefault();
            $this->app->instance(AgentToolbox::class, $toolbox);
        } else {
            $toolbox = $this->app->make(AgentToolbox::class);
            if ($toolbox instanceof \Mockery\MockInterface) {
                $toolbox->shouldReceive('getRecentInsights')->andReturn(['success' => false, 'insights' => []])->byDefault();
            }
        }

        // These services are lazily resolved by executeActions (we mock executeActions in most tests,
        // but binding them prevents accidental container resolution failures).
        if (! $this->app->bound(LawSearchService::class)) {
            $this->app->instance(LawSearchService::class, Mockery::mock(LawSearchService::class));
        }
        if (! $this->app->bound(DecisionSearchService::class)) {
            $this->app->instance(DecisionSearchService::class, Mockery::mock(DecisionSearchService::class));
        }
        if (! $this->app->bound(CaseSearchService::class)) {
            $this->app->instance(CaseSearchService::class, Mockery::mock(CaseSearchService::class));
        }
        if (! $this->app->bound(AgentEvaluationService::class)) {
            $this->app->instance(AgentEvaluationService::class, Mockery::mock(AgentEvaluationService::class));
        }
    }

    /**
     * Helper to call protected methods for testing.
     */
    protected function callProtectedMethod($object, string $method, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}
