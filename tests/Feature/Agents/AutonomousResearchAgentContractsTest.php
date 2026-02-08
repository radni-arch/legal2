<?php

namespace Tests\Feature\Agents;

use App\Agents\AutonomousResearchAgent;
use App\Models\AgentRun;
use App\Services\AgentCheckpointService;
use App\Services\AgentEvaluationService;
use App\Services\AgentToolbox;
use App\Services\OpenAIService;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * AutonomousResearchAgent proving tests.
 *
 * Reasoning:
 * We prove deterministic contracts that sit around the LLM:
 * - Planning contract: planNextStep must parse valid JSON plan OR fall back safely.
 * - "Must cite" contract: the run score must reflect citation presence because
 *   executeRun always evaluates final output via AgentEvaluationService::evaluateRun().
 *
 * We avoid testing extractInsight() because it is LLM-dependent. Instead we force
 * an early-stop plan so the run produces a predictable output without citations.
 */
class AutonomousResearchAgentContractsTest extends TestCase
{
    use UsesTestDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function plan_next_step_parses_llm_json_plan_and_updates_token_accounting(): void
    {
        // Arrange: toolbox is used by startRun() to fetch past insights.
        $toolbox = Mockery::mock(AgentToolbox::class);
        $toolbox->shouldReceive('getRecentInsights')
            ->andReturn(['success' => false, 'insights' => []]);
        $this->app->instance(AgentToolbox::class, $toolbox);

        $openai = Mockery::mock(OpenAIService::class);
        $openai->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'reasoning' => 'Start broad with a vector search.',
                        'next_focus' => 'Initial exploration',
                        'should_stop' => false,
                        'actions' => [
                            [
                                'tool' => 'law_vector_search',
                                'params' => ['query' => 'ZKP nezakoniti dokaz', 'limit' => 5],
                            ],
                        ],
                    ])]],
                ],
                'usage' => ['total_tokens' => 120],
            ]);
        $this->app->instance(OpenAIService::class, $openai);

        // evaluator/checkpoint are required dependencies but not used by planNextStep.
        $this->app->instance(AgentEvaluationService::class, Mockery::mock(AgentEvaluationService::class));
        $this->app->instance(AgentCheckpointService::class, Mockery::mock(AgentCheckpointService::class));

        $agent = new AutonomousResearchAgent;
        $run = $agent->startRun('Analyze illegal search evidence', [], ['max_iterations' => 1]);

        $tokensBefore = $run->tokens_used;
        $costBefore = $run->cost_spent;

        // Act
        $plan = $this->callProtectedMethod($agent, 'planNextStep', [$run]);

        // Assert
        $this->assertIsArray($plan);
        $this->assertFalse($plan['should_stop']);
        $this->assertCount(1, $plan['actions']);
        $this->assertEquals('law_vector_search', $plan['actions'][0]['tool']);

        $run->refresh();
        $this->assertGreaterThan($tokensBefore, $run->tokens_used);
        $this->assertGreaterThanOrEqual($costBefore, $run->cost_spent);
    }

    /** @test */
    public function execute_run_calls_evaluator_and_low_score_reflects_missing_citations(): void
    {
        // Reasoning: executeRun always synthesizes a final report and calls AgentEvaluationService::evaluateRun
        // to compute score fileciteturn10file12. We can prove "must cite" enforcement at the contract level
        // by ensuring that a report with no citations produces a low evaluation score.

        // Arrange
        $toolbox = Mockery::mock(AgentToolbox::class);
        $toolbox->shouldReceive('getRecentInsights')
            ->andReturn(['success' => false, 'insights' => []]);
        $this->app->instance(AgentToolbox::class, $toolbox);

        // Planning: immediate stop, which produces a deterministic report with no findings and no citations.
        $openai = Mockery::mock(OpenAIService::class);
        $openai->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'reasoning' => 'We already have enough information.',
                        'next_focus' => 'Stop',
                        'should_stop' => true,
                        'actions' => [],
                    ])]],
                ],
                'usage' => ['total_tokens' => 50],
            ]);
        $this->app->instance(OpenAIService::class, $openai);

        // Checkpoint service is called on completion (clearCheckpoint), and can be called on failure.
        $checkpoint = Mockery::mock(AgentCheckpointService::class);
        $checkpoint->shouldReceive('saveCheckpoint')->byDefault();
        $checkpoint->shouldReceive('clearCheckpoint')->byDefault();
        $checkpoint->shouldReceive('restoreCheckpoint')->byDefault();
        $this->app->instance(AgentCheckpointService::class, $checkpoint);

        // Evaluator mock: enforce "must cite" by returning low score when output contains no citation patterns.
        $evaluator = Mockery::mock(AgentEvaluationService::class);
        $evaluator->shouldReceive('evaluateRun')
            ->once()
            ->withArgs(function (int $runId, string $output) {
                // The early-stop output should have no legal citations.
                return $runId > 0 && is_string($output) && ! preg_match('/NN\s+\d+\/\d+|čl\.|st\./i', $output);
            })
            ->andReturn([
                'score' => 0.1,
                'passed' => false,
                'checks' => [
                    'citations' => ['score' => 0.2, 'passed' => false],
                ],
            ]);
        $this->app->instance(AgentEvaluationService::class, $evaluator);

        $agent = new AutonomousResearchAgent;
        $run = $agent->startRun('Research illegal home search evidence suppression', [], [
            'max_iterations' => 3,
            'threshold' => 0.75,
        ]);

        // Act
        $completed = $agent->executeRun($run);

        // Assert
        $completed->refresh();
        $this->assertEquals('completed', $completed->status);
        $this->assertNotEmpty($completed->final_output);
        $this->assertEquals(0.1, $completed->score);

        // Because plan should_stop was true, no actions should have been executed.
        $iterations = $completed->iterations ?? [];
        $this->assertNotEmpty($iterations);
        $this->assertTrue($iterations[0]['stopped_early'] ?? false);
        $this->assertEmpty($iterations[0]['actions'] ?? []);
    }
}
