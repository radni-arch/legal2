<?php

namespace Tests\Feature\Agents;

use App\Agents\AutonomousResearchAgent;
use App\Services\AgentToolbox;
use App\Services\OpenAIService;
use Mockery;
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
     * Invariant: If plan says should_stop=true, executeIteration must not execute actions/evaluation.
     *
     * Reasoning: Prevents unnecessary tool calls and ensures the "stop" decision is respected.
     */
    public function test_execute_iteration_stops_early_when_plan_requests_stop(): void
    {
        $this->bindMinimalToolbox();

        $agent = Mockery::mock(AutonomousResearchAgent::class)->makePartial();
        $agent->shouldAllowMockingProtectedMethods();

        $run = $agent->startRun('Stop early objective');

        $agent->shouldReceive('planNextStep')
            ->once()
            ->andReturn([
                'reasoning' => 'We have enough information.',
                'should_stop' => true,
                'actions' => [],
            ]);

        $agent->shouldReceive('executeActions')->never();
        $agent->shouldReceive('evaluateIteration')->never();
        $agent->shouldReceive('saveInsights')->never();

        $iteration = $this->callProtectedMethod($agent, 'executeIteration', [$run]);

        $this->assertTrue($iteration['stopped_early']);
        $this->assertEquals('We have enough information.', $iteration['stop_reason']);
        $this->assertSame([], $iteration['actions']);
    }

    /**
     * Invariant: planNextStep falls back to safe default plan when LLM output is invalid.
     *
     * Reasoning: Prevents crashes / stuck runs caused by malformed model output.
     */
    public function test_plan_next_step_falls_back_on_invalid_llm_json(): void
    {
        $this->bindMinimalToolbox();

        $openai = Mockery::mock(OpenAIService::class);
        $openai->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => '{not valid json']],
                ],
                'usage' => ['total_tokens' => 10],
            ]);

        $this->app->instance(OpenAIService::class, $openai);

        $agent = new AutonomousResearchAgent;
        $run = $agent->startRun('Fallback planning objective');

        $plan = $this->callProtectedMethod($agent, 'planNextStep', [$run]);

        $this->assertIsArray($plan);
        $this->assertStringContainsString('LLM planning failed', $plan['reasoning']);
        $this->assertFalse($plan['should_stop']);
        $this->assertNotEmpty($plan['actions']);
        $this->assertEquals('law_vector_search', $plan['actions'][0]['tool']);
    }

    /**
     * Invariant: validatePlan must accept tools that executeActions supports (and reject unknown tools).
     *
     * Reasoning: If the validator whitelist is narrower than execution support, the agent silently degrades
     * into fallback planning and wastes iterations.
     */
    public function test_validate_plan_tool_whitelist_and_action_limit_contracts(): void
    {
        $this->bindMinimalToolbox();

        $agent = new AutonomousResearchAgent;

        $validPlan = [
            'reasoning' => 'Do a couple of searches then save a note.',
            'should_stop' => false,
            'actions' => [
                ['tool' => 'decision_vector_search', 'params' => ['query' => 'pretres doma nezakoniti dokazi', 'limit' => 3]],
                ['tool' => 'law_keyword_search', 'params' => ['query' => 'ZKP čl. 9', 'limit' => 3]],
                ['tool' => 'note_save', 'params' => ['content' => 'Found relevant sources: ZKP čl. 9 (NN ...)']],
            ],
        ];

        $this->assertTrue($this->callProtectedMethod($agent, 'validatePlan', [$validPlan]));

        $tooManyActions = $validPlan;
        $tooManyActions['actions'][] = ['tool' => 'case_search', 'params' => ['query' => 'P-123/2024']];
        $this->assertFalse($this->callProtectedMethod($agent, 'validatePlan', [$tooManyActions]));

        $unknownTool = $validPlan;
        $unknownTool['actions'][0]['tool'] = 'made_up_tool';
        $this->assertFalse($this->callProtectedMethod($agent, 'validatePlan', [$unknownTool]));

        $stopWithNoActions = [
            'reasoning' => 'Stopping now.',
            'should_stop' => true,
            'actions' => [],
        ];
        $this->assertTrue($this->callProtectedMethod($agent, 'validatePlan', [$stopWithNoActions]));
    }

    /**
     * Invariant: note_save is a real executable tool (documented + validated + executed).
     *
     * Reasoning: A tool that is documented and validated but not executable causes silent failures and
     * undermines trust in the agent loop.
     */
    public function test_execute_actions_supports_note_save_tool(): void
    {
        $toolbox = Mockery::mock(AgentToolbox::class);
        $toolbox->shouldReceive('getRecentInsights')
            ->andReturn(['success' => false, 'insights' => []])
            ->byDefault();

        $toolbox->shouldReceive('noteSave')
            ->once()
            ->with(
                'autonomous_research_agent',
                'Insight content',
                Mockery::on(function (array $opts) {
                    return ($opts['namespace'] ?? null) === 'research_insights'
                        && ($opts['source'] ?? null) === 'autonomous_research'
                        && isset($opts['source_id']);
                })
            )
            ->andReturn(['success' => true, 'id' => 'mem-1', 'status' => 'created']);

        $this->app->instance(AgentToolbox::class, $toolbox);

        $agent = new AutonomousResearchAgent;
        $run = $agent->startRun('Save note objective');

        $results = $this->callProtectedMethod($agent, 'executeActions', [
            [
                [
                    'tool' => 'note_save',
                    'params' => [
                        'content' => 'Insight content',
                        'namespace' => 'research_insights',
                        'metadata' => ['k' => 'v'],
                    ],
                ],
            ],
            $run,
        ]);

        $this->assertCount(1, $results);
        $this->assertTrue($results[0]['success']);
        $this->assertEquals('note_save', $results[0]['tool']);
        $this->assertEquals('mem-1', $results[0]['result']['id']);
    }

    private function bindMinimalToolbox(): void
    {
        if (! $this->app->bound(AgentToolbox::class)) {
            $toolbox = Mockery::mock(AgentToolbox::class);
            $toolbox->shouldReceive('getRecentInsights')
                ->andReturn(['success' => false, 'insights' => []])
                ->byDefault();
            $this->app->instance(AgentToolbox::class, $toolbox);
        }
    }

    /**
     * Helper to call protected methods for testing
     */
    protected function callProtectedMethod($object, string $method, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $methodRef = $reflection->getMethod($method);
        $methodRef->setAccessible(true);

        return $methodRef->invokeArgs($object, $parameters);
    }
}
