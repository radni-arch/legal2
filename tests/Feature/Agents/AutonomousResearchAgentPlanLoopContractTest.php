<?php

namespace Tests\Feature\Agents;

use App\Agents\AutonomousResearchAgent;
use App\Agents\Validation\AgentPlanValidator;
use App\Models\AgentRun;
use App\Services\AgentToolbox;
use App\Services\OpenAIService;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class AutonomousResearchAgentPlanLoopContractTest extends TestCase
{
    use UsesTestDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Invariant: validatePlan() must accept every tool name in the canonical validator tool list.
     *
     * Reasoning: if plan tools drift from executor tools, the agent silently falls back and stops making progress.
     */
    public function test_validate_plan_accepts_all_known_tools(): void
    {
        $agent = new AutonomousResearchAgent;

        foreach (AgentPlanValidator::getValidTools() as $tool) {
            $plan = [
                'reasoning' => 'Sufficient reasoning for plan validation.',
                'should_stop' => false,
                'actions' => [
                    [
                        'tool' => $tool,
                        'params' => ['query' => 'test', 'content' => 'test content'],
                    ],
                ],
            ];

            $isValid = $this->callProtectedMethod($agent, 'validatePlan', [$plan]);
            $this->assertTrue($isValid, "Tool should be accepted by validatePlan: {$tool}");
        }
    }

    /**
     * Invariant: validatePlan() must reject unknown tools.
     */
    public function test_validate_plan_rejects_unknown_tool(): void
    {
        $agent = new AutonomousResearchAgent;

        $plan = [
            'reasoning' => 'Some reasoning.',
            'should_stop' => false,
            'actions' => [
                ['tool' => 'definitely_not_a_tool', 'params' => ['x' => 1]],
            ],
        ];

        $this->assertFalse($this->callProtectedMethod($agent, 'validatePlan', [$plan]));
    }

    /**
     * Invariant: planNextStep() must fall back when LLM returns invalid JSON.
     *
     * Reasoning: prevents crashes and prevents infinite loops due to broken LLM output.
     */
    public function test_plan_next_step_falls_back_on_invalid_json(): void
    {
        // Ensure toolbox used by startRun() is safe
        $toolbox = Mockery::mock(AgentToolbox::class);
        $toolbox->shouldReceive('getRecentInsights')
            ->andReturn(['success' => false, 'insights' => []]);
        $this->app->instance(AgentToolbox::class, $toolbox);

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
        $run = $agent->startRun('Test objective');

        $plan = $this->callProtectedMethod($agent, 'planNextStep', [$run]);

        $this->assertFalse($plan['should_stop'] ?? true);
        $this->assertNotEmpty($plan['actions'] ?? []);
        $this->assertEquals('law_vector_search', $plan['actions'][0]['tool']);
        $this->assertEquals('Test objective', $plan['actions'][0]['params']['query']);
    }

    /**
     * Invariant: if plan says should_stop=true, executeIteration() must not execute actions.
     */
    public function test_execute_iteration_stops_early_and_does_not_execute_actions(): void
    {
        $agent = new class extends AutonomousResearchAgent {
            public array $calls = [];

            protected function planNextStep(AgentRun $run): array
            {
                $this->calls[] = 'planNextStep';

                return [
                    'reasoning' => 'We have enough information.',
                    'should_stop' => true,
                    'actions' => [],
                ];
            }

            protected function executeActions(array $actions, AgentRun $run): array
            {
                $this->calls[] = 'executeActions';

                return [];
            }

            protected function evaluateIteration(AgentRun $run, array $iteration): array
            {
                $this->calls[] = 'evaluateIteration';

                return ['insights' => [], 'should_stop' => true];
            }

            protected function saveInsights(array $insights, AgentRun $run): void
            {
                $this->calls[] = 'saveInsights';
            }

            public function _executeIteration(AgentRun $run): array
            {
                return $this->executeIteration($run);
            }
        };

        $run = AgentRun::factory()->running()->create([
            'agent_name' => 'autonomous_research_agent',
            'objective' => 'ZKP čl. 9 pretraga doma',
            'current_iteration' => 0,
            'max_iterations' => 5,
        ]);

        $iteration = $agent->_executeIteration($run);

        $this->assertTrue($iteration['stopped_early'] ?? false);
        $this->assertArrayNotHasKey('evaluation', $iteration);
        $this->assertNotContains('executeActions', $agent->calls);
        $this->assertNotContains('evaluateIteration', $agent->calls);
        $this->assertNotContains('saveInsights', $agent->calls);
    }

    /**
     * Invariant: executeIteration() must execute actions, then evaluate, then save insights.
     */
    public function test_execute_iteration_executes_actions_then_saves_insights(): void
    {
        $agent = new class extends AutonomousResearchAgent {
            public array $calls = [];

            protected function planNextStep(AgentRun $run): array
            {
                $this->calls[] = 'planNextStep';

                return [
                    'reasoning' => 'Search for relevant decisions and laws.',
                    'should_stop' => false,
                    'actions' => [
                        ['tool' => 'law_vector_search', 'params' => ['query' => 'ZKP čl. 9', 'limit' => 3]],
                    ],
                ];
            }

            protected function executeActions(array $actions, AgentRun $run): array
            {
                $this->calls[] = 'executeActions';

                return [
                    ['tool' => 'law_vector_search', 'params' => $actions[0]['params'], 'result' => ['laws' => []], 'success' => true],
                ];
            }

            protected function evaluateIteration(AgentRun $run, array $iteration): array
            {
                $this->calls[] = 'evaluateIteration';

                return [
                    'insights' => ['ZKP čl. 9 (NN 152/08) zahtijeva zakonitost radnji pretrage.'],
                    'insights_count' => 1,
                    'should_stop' => false,
                ];
            }

            protected function saveInsights(array $insights, AgentRun $run): void
            {
                $this->calls[] = 'saveInsights';
            }

            public function _executeIteration(AgentRun $run): array
            {
                return $this->executeIteration($run);
            }
        };

        $run = AgentRun::factory()->running()->create([
            'agent_name' => 'autonomous_research_agent',
            'objective' => 'Nezakoniti dokazi kod pretrage doma',
            'current_iteration' => 0,
            'max_iterations' => 5,
        ]);

        $iteration = $agent->_executeIteration($run);

        $this->assertNotEmpty($iteration['actions']);
        $this->assertEquals(['planNextStep', 'executeActions', 'evaluateIteration', 'saveInsights'], $agent->calls);
    }

    /**
     * Invariant: note_save tool must be executable via executeActions().
     *
     * Reasoning: the planning prompt advertises note_save, and validatePlan allows it; it must not silently error.
     */
    public function test_execute_actions_can_execute_note_save_tool(): void
    {
        $toolbox = Mockery::mock(AgentToolbox::class);
        $toolbox->shouldReceive('getRecentInsights')
            ->andReturn(['success' => false, 'insights' => []]);
        $toolbox->shouldReceive('noteSave')
            ->once()
            ->with('autonomous_research_agent', 'Important insight', Mockery::type('array'))
            ->andReturn(['success' => true, 'id' => 'mem-1', 'status' => 'created']);

        $this->app->instance(AgentToolbox::class, $toolbox);

        $agent = new AutonomousResearchAgent;
        $run = $agent->startRun('Test note save');

        $results = $this->callProtectedMethod($agent, 'executeActions', [[
            ['tool' => 'note_save', 'params' => ['content' => 'Important insight', 'namespace' => 'research_insights']],
        ], $run]);

        $this->assertTrue($results[0]['success']);
        $this->assertEquals('note_save', $results[0]['tool']);
        $this->assertEquals('mem-1', $results[0]['result']['id']);
    }
}
