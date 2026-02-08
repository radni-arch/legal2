<?php

namespace Tests\Feature\Agents;

use App\Agents\AutonomousResearchAgent;
use App\Models\AgentRun;
use App\Services\AgentToolbox;
use App\Services\OpenAIService;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class AutonomousResearchAgentIterationInvariantsTest extends TestCase
{
    use UsesTestDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Invariant: validatePlan must accept any tool that the agent can actually execute.
     *
     * Reasoning: if validatePlan rejects supported tools, the agent will silently fall back and
     * effectively stop using capabilities (regression risk + wasted budget).
     */
    public function test_validate_plan_accepts_supported_tools_and_limits_actions_to_three(): void
    {
        $agent = new AutonomousResearchAgent;

        $validPlan = [
            'reasoning' => 'We should search decisions and laws relevant to the objective.',
            'should_stop' => false,
            'actions' => [
                ['tool' => 'decision_vector_search', 'params' => ['query' => 'pretres doma ZKP čl. 250', 'limit' => 5]],
                ['tool' => 'law_keyword_search', 'params' => ['query' => 'ZKP čl. 250', 'limit' => 5]],
                ['tool' => 'graph_query', 'params' => ['cypher' => 'MATCH (n) RETURN n LIMIT 1', 'parameters' => []]],
            ],
        ];

        $this->assertTrue($this->callProtectedMethod($agent, 'validatePlan', [$validPlan]));

        $tooManyActions = $validPlan;
        $tooManyActions['actions'][] = ['tool' => 'case_search', 'params' => ['query' => 'P-123/2024']];

        $this->assertFalse($this->callProtectedMethod($agent, 'validatePlan', [$tooManyActions]));
    }

    /**
     * Invariant: note_save is a first-class tool: advertised + validated + executable.
     *
     * Reasoning: documented but non-executable tools cause hard-to-debug silent failures.
     */
    public function test_note_save_action_executes_via_toolbox(): void
    {
        $toolbox = Mockery::mock(AgentToolbox::class);
        $toolbox->shouldReceive('noteSave')
            ->once()
            ->with(
                'autonomous_research_agent',
                'Insight with citation: ZKP čl. 250 (NN 152/08)',
                Mockery::on(function ($opts) {
                    return ($opts['namespace'] ?? null) === 'research_insights'
                        && ($opts['source'] ?? null) === 'autonomous_research'
                        && isset($opts['source_id']);
                })
            )
            ->andReturn(['success' => true, 'id' => 'mem-1', 'status' => 'created']);

        $this->app->instance(AgentToolbox::class, $toolbox);

        $agent = new AutonomousResearchAgent;

        $run = AgentRun::factory()->create([
            'objective' => 'Test note_save',
            'status' => 'running',
            'current_iteration' => 0,
            'max_iterations' => 3,
            'tokens_used' => 0,
            'cost_spent' => 0,
            'started_at' => now(),
            'iterations' => [],
        ]);

        $results = $this->callProtectedMethod($agent, 'executeActions', [[
            [
                'tool' => 'note_save',
                'params' => [
                    'content' => 'Insight with citation: ZKP čl. 250 (NN 152/08)',
                    'namespace' => 'research_insights',
                ],
            ],
        ], $run]);

        $this->assertCount(1, $results);
        $this->assertTrue($results[0]['success']);
        $this->assertEquals('note_save', $results[0]['tool']);
        $this->assertEquals('mem-1', $results[0]['result']['id']);
    }

    /**
     * Invariant: should_stop=true means no action execution happens in that iteration.
     */
    public function test_execute_iteration_respects_should_stop_and_does_not_execute_actions(): void
    {
        $agent = new class extends AutonomousResearchAgent {
            public bool $executeActionsCalled = false;

            protected function planNextStep(AgentRun $run): array
            {
                return [
                    'reasoning' => 'We already have sufficient sources and citations.',
                    'should_stop' => true,
                    'actions' => [],
                ];
            }

            protected function executeActions(array $actions, AgentRun $run): array
            {
                $this->executeActionsCalled = true;

                return [];
            }
        };

        $run = AgentRun::factory()->create([
            'objective' => 'Stop early test',
            'status' => 'running',
            'current_iteration' => 0,
            'max_iterations' => 3,
            'tokens_used' => 0,
            'cost_spent' => 0,
            'started_at' => now(),
            'iterations' => [],
        ]);

        $iteration = $this->callProtectedMethod($agent, 'executeIteration', [$run]);

        $this->assertTrue($iteration['stopped_early']);
        $this->assertEquals('We already have sufficient sources and citations.', $iteration['stop_reason']);
        $this->assertFalse($agent->executeActionsCalled);
    }

    /**
     * Invariant: If LLM plan JSON is invalid, planNextStep returns fallback plan.
     *
     * Reasoning: prevents 500s / stuck runs when LLM output is malformed.
     */
    public function test_plan_next_step_falls_back_when_llm_returns_invalid_json(): void
    {
        $openai = Mockery::mock(OpenAIService::class);
        $openai->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => 'NOT JSON']],
                ],
                'usage' => ['total_tokens' => 10],
            ]);

        $this->app->instance(OpenAIService::class, $openai);

        $agent = new AutonomousResearchAgent;

        $run = AgentRun::factory()->create([
            'objective' => 'Fallback plan test',
            'status' => 'running',
            'current_iteration' => 0,
            'max_iterations' => 3,
            'tokens_used' => 0,
            'cost_spent' => 0,
            'started_at' => now(),
            'iterations' => [],
        ]);

        $plan = $this->callProtectedMethod($agent, 'planNextStep', [$run]);

        $this->assertIsArray($plan);
        $this->assertFalse($plan['should_stop']);
        $this->assertNotEmpty($plan['actions']);
        $this->assertEquals('law_vector_search', $plan['actions'][0]['tool']);
    }

    /**
     * Invariant: executeIteration must (plan -> act -> evaluate -> save insights) when not stopping.
     */
    public function test_execute_iteration_runs_full_loop_and_saves_insights(): void
    {
        $agent = new class extends AutonomousResearchAgent {
            public array $saved = [];
            public bool $executed = false;

            protected function planNextStep(AgentRun $run): array
            {
                return [
                    'reasoning' => 'Search for ZKP provisions relevant to pretres doma.',
                    'should_stop' => false,
                    'actions' => [
                        ['tool' => 'law_vector_search', 'params' => ['query' => 'pretres doma ZKP', 'limit' => 3]],
                    ],
                ];
            }

            protected function executeActions(array $actions, AgentRun $run): array
            {
                $this->executed = true;

                return [
                    [
                        'tool' => $actions[0]['tool'],
                        'params' => $actions[0]['params'],
                        'result' => ['laws' => [['title' => 'ZKP', 'law_number' => 'NN 152/08', 'content' => '...']]],
                        'success' => true,
                    ],
                ];
            }

            protected function evaluateIteration(AgentRun $run, array $iteration): array
            {
                return [
                    'insights' => ['ZKP (NN 152/08) čl. 250 uređuje uvjete pretresa doma.'],
                    'insights_count' => 1,
                    'should_stop' => false,
                ];
            }

            protected function saveInsights(array $insights, AgentRun $run): void
            {
                $this->saved = $insights;
            }
        };

        $run = AgentRun::factory()->create([
            'objective' => 'Full loop test',
            'status' => 'running',
            'current_iteration' => 0,
            'max_iterations' => 3,
            'tokens_used' => 0,
            'cost_spent' => 0,
            'started_at' => now(),
            'iterations' => [],
        ]);

        $iteration = $this->callProtectedMethod($agent, 'executeIteration', [$run]);

        $this->assertTrue($agent->executed);
        $this->assertArrayHasKey('evaluation', $iteration);
        $this->assertEquals(1, $iteration['evaluation']['insights_count']);
        $this->assertEquals(['ZKP (NN 152/08) čl. 250 uređuje uvjete pretresa doma.'], $agent->saved);
        $this->assertArrayHasKey('completed_at', $iteration);
    }

    /**
     * Helper to call protected methods for testing
     */
    protected function callProtectedMethod($object, $method, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}
