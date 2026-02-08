<?php

namespace Tests\Feature\Agents;

use App\Agents\AutonomousResearchAgent;
use App\Models\AgentRun;
use App\Services\AgentToolbox;
use App\Services\OpenAIService;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Proving tests for the AutonomousResearchAgent iteration loop.
 *
 * These tests assert deterministic control-flow invariants:
 * - stop plans short-circuit execution
 * - invalid LLM output triggers fallback plan
 * - validatePlan accepts the same tools executeActions supports
 * - note_save is executable (not just documented)
 */
class AutonomousResearchAgentIterationTest extends TestCase
{
    use UsesTestDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_short_circuits_iteration_when_plan_requests_stop(): void
    {
        $this->bindToolboxForStartRun();

        $agent = new AutonomousResearchAgentHarness;
        $agent->stubPlan = [
            'reasoning' => 'We already have sufficient information.',
            'next_focus' => 'Stop',
            'should_stop' => true,
            'actions' => [],
        ];

        $run = $agent->startRun('Research ZKP čl. 9 and nezakoniti dokazi');

        $iteration = $agent->publicExecuteIteration($run);

        $this->assertTrue($iteration['stopped_early']);
        $this->assertEquals('We already have sufficient information.', $iteration['stop_reason']);
        $this->assertFalse($agent->executeActionsCalled, 'executeActions must not be called when should_stop=true');
    }

    /** @test */
    public function it_uses_fallback_plan_when_llm_returns_invalid_json(): void
    {
        $this->bindToolboxForStartRun();

        $openai = Mockery::mock(OpenAIService::class);
        $openai->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => '{ this is not json']]
                ],
                'usage' => ['total_tokens' => 50],
            ]);

        $this->app->instance(OpenAIService::class, $openai);

        $agent = new AutonomousResearchAgentHarness;
        $run = $agent->startRun('Research pretres doma i zakonitost dokaza');

        $plan = $agent->publicPlanNextStep($run);

        $this->assertFalse($plan['should_stop']);
        $this->assertNotEmpty($plan['actions']);
        $this->assertEquals('law_vector_search', $plan['actions'][0]['tool']);
    }

    /** @test */
    public function it_executes_actions_then_saves_insights_when_evaluation_has_insights(): void
    {
        $this->bindToolboxForStartRun();

        $agent = new AutonomousResearchAgentHarness;
        $agent->stubPlan = [
            'reasoning' => 'Need more sources.',
            'next_focus' => 'Find relevant law and decisions.',
            'should_stop' => false,
            'actions' => [
                ['tool' => 'law_vector_search', 'params' => ['query' => 'ZKP čl. 9 nezakoniti dokazi']],
            ],
        ];

        $agent->stubActionResults = [
            ['tool' => 'law_vector_search', 'params' => ['query' => 'x'], 'result' => ['laws' => []], 'success' => true],
        ];

        $agent->stubEvaluation = [
            'insights' => ['ZKP (NN 152/08) čl. 9: zabrana nezakonitih dokaza.'],
            'insights_count' => 1,
            'should_stop' => false,
        ];

        $run = $agent->startRun('Research illegal evidence in Croatian criminal procedure');

        $iteration = $agent->publicExecuteIteration($run);

        $this->assertArrayHasKey('evaluation', $iteration);
        $this->assertNotNull($agent->capturedSavedInsights);
        $this->assertCount(1, $agent->capturedSavedInsights);
        $this->assertStringContainsString('ZKP', $agent->capturedSavedInsights[0]);
    }

    /** @test */
    public function validate_plan_accepts_supported_tools_and_enforces_max_3_actions(): void
    {
        $this->bindToolboxForStartRun();

        $agent = new AutonomousResearchAgentHarness;

        $validPlan = [
            'reasoning' => 'Need decisions and related laws.',
            'should_stop' => false,
            'actions' => [
                ['tool' => 'decision_vector_search', 'params' => ['query' => 'pretres doma nezakoniti dokazi']],
                ['tool' => 'law_keyword_search', 'params' => ['query' => 'NN 152/08']],
                ['tool' => 'case_search', 'params' => ['query' => 'P-123/2024']],
            ],
        ];

        $this->assertTrue($agent->publicValidatePlan($validPlan));

        $tooMany = $validPlan;
        $tooMany['actions'][] = ['tool' => 'web_fetch', 'params' => ['url' => 'https://example.com']];
        $this->assertFalse($agent->publicValidatePlan($tooMany));

        $unknownTool = $validPlan;
        $unknownTool['actions'][0] = ['tool' => 'totally_unknown_tool', 'params' => ['query' => 'x']];
        $this->assertFalse($agent->publicValidatePlan($unknownTool));
    }

    /** @test */
    public function note_save_tool_is_executable_via_execute_actions(): void
    {
        $toolbox = Mockery::mock(AgentToolbox::class);
        $toolbox->shouldReceive('getRecentInsights')
            ->andReturn(['success' => false, 'insights' => []]);

        $toolbox->shouldReceive('noteSave')
            ->once()
            ->with(
                'autonomous_research_agent',
                'Key finding with citation: ZKP (NN 152/08) čl. 9',
                Mockery::on(function ($opts) {
                    return ($opts['namespace'] ?? null) === 'research_insights'
                        && ($opts['source'] ?? null) === 'autonomous_research'
                        && isset($opts['metadata']['run_id']);
                })
            )
            ->andReturn(['success' => true, 'id' => 'mem-1', 'status' => 'created']);

        $this->app->instance(AgentToolbox::class, $toolbox);

        $agent = new AutonomousResearchAgentHarness;
        $run = $agent->startRun('Test note_save');

        $results = $agent->publicExecuteActions([
            [
                'tool' => 'note_save',
                'params' => [
                    'content' => 'Key finding with citation: ZKP (NN 152/08) čl. 9',
                    'namespace' => 'research_insights',
                    'metadata' => ['tag' => 'test'],
                ],
            ],
        ], $run);

        $this->assertCount(1, $results);
        $this->assertTrue($results[0]['success']);
        $this->assertEquals('note_save', $results[0]['tool']);
        $this->assertEquals('created', $results[0]['result']['status']);
    }

    private function bindToolboxForStartRun(): void
    {
        $toolbox = Mockery::mock(AgentToolbox::class);
        $toolbox->shouldReceive('getRecentInsights')
            ->andReturn(['success' => false, 'insights' => []]);

        $this->app->instance(AgentToolbox::class, $toolbox);
    }
}

/**
 * Test harness that exposes protected methods and allows deterministic stubbing.
 */
class AutonomousResearchAgentHarness extends AutonomousResearchAgent
{
    public ?array $stubPlan = null;
    public ?array $stubActionResults = null;
    public ?array $stubEvaluation = null;

    public bool $executeActionsCalled = false;
    public ?array $capturedSavedInsights = null;

    public function publicExecuteIteration(AgentRun $run): array
    {
        return $this->executeIteration($run);
    }

    public function publicPlanNextStep(AgentRun $run): array
    {
        return $this->planNextStep($run);
    }

    public function publicValidatePlan(array $plan): bool
    {
        return $this->validatePlan($plan);
    }

    public function publicExecuteActions(array $actions, AgentRun $run): array
    {
        return $this->executeActions($actions, $run);
    }

    protected function planNextStep(AgentRun $run): array
    {
        if ($this->stubPlan !== null) {
            return $this->stubPlan;
        }

        return parent::planNextStep($run);
    }

    protected function executeActions(array $actions, AgentRun $run): array
    {
        $this->executeActionsCalled = true;

        if ($this->stubActionResults !== null) {
            return $this->stubActionResults;
        }

        return parent::executeActions($actions, $run);
    }

    protected function evaluateIteration(AgentRun $run, array $iteration): array
    {
        if ($this->stubEvaluation !== null) {
            return $this->stubEvaluation;
        }

        return parent::evaluateIteration($run, $iteration);
    }

    protected function saveInsights(array $insights, AgentRun $run): void
    {
        $this->capturedSavedInsights = $insights;
    }
}
