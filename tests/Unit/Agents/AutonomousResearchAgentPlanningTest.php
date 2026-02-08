<?php

namespace Tests\Unit\Agents;

use App\Agents\AutonomousResearchAgent;
use App\Models\AgentRun;
use App\Services\OpenAIService;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class AutonomousResearchAgentPlanningTest extends TestCase
{
    use UsesTestDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_builds_planning_context_with_objective()
    {
        $agent = new AutonomousResearchAgent;
        $run = AgentRun::create([
            'agent_name' => 'test_agent',
            'objective' => 'Research Croatian labor law',
            'context' => [],
            'current_iteration' => 0,
            'max_iterations' => 10,
        ]);

        $context = $this->invokeMethod($agent, 'buildPlanningContext', [$run]);

        $this->assertStringContainsString('Research Croatian labor law', $context);
        $this->assertStringContainsString('Iteration: 0', $context);
        $this->assertStringContainsString('# Research Objective', $context);
        $this->assertStringContainsString('# Current Progress', $context);
    }

    /** @test */
    public function it_builds_context_with_previous_insights()
    {
        $agent = new AutonomousResearchAgent;
        $run = AgentRun::create([
            'agent_name' => 'test_agent',
            'objective' => 'Research employment law',
            'context' => [],
            'current_iteration' => 2,
            'max_iterations' => 10,
        ]);

        // Set insights as an attribute (not persisted to DB)
        $run->insights = [
            'Article 93 requires notice period',
            'Supreme Court ruling in Gž-1234/2023',
        ];

        $context = $this->invokeMethod($agent, 'buildPlanningContext', [$run]);

        $this->assertStringContainsString('Article 93 requires notice period', $context);
        $this->assertStringContainsString('Supreme Court ruling', $context);
        $this->assertStringContainsString('# Current Run Insights', $context);
    }

    /** @test */
    public function it_includes_previous_iterations_in_context()
    {
        $agent = new AutonomousResearchAgent;
        $run = AgentRun::create([
            'agent_name' => 'test_agent',
            'objective' => 'Research labor law',
            'context' => [],
            'current_iteration' => 3,
            'max_iterations' => 10,
            'iterations' => [
                ['iteration' => 1, 'actions_taken' => 3, 'insights_found' => 2],
                ['iteration' => 2, 'actions_taken' => 2, 'insights_found' => 1],
                ['iteration' => 3, 'actions_taken' => 1, 'insights_found' => 3],
            ],
        ]);

        $context = $this->invokeMethod($agent, 'buildPlanningContext', [$run]);

        $this->assertStringContainsString('# Previous Actions & Results', $context);
        $this->assertStringContainsString('Iteration 1: 3 actions', $context);
        $this->assertStringContainsString('2 insights', $context);
    }

    /** @test */
    public function it_calls_llm_for_planning()
    {
        // Mock OpenAI to return valid plan
        $mockOpenAI = Mockery::mock(OpenAIService::class);
        $mockOpenAI->shouldReceive('chat')
            ->once()
            ->withArgs(function ($messages, $model, $options) {
                return is_array($messages)
                    && count($messages) === 2
                    && $messages[0]['role'] === 'system'
                    && $messages[1]['role'] === 'user'
                    && isset($options['response_format'])
                    && $options['response_format']['type'] === 'json_object';
            })
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'reasoning' => 'Start with broad vector search to understand legal landscape',
                        'actions' => [
                            [
                                'tool' => 'law_vector_search',
                                'params' => ['query' => 'employment law', 'limit' => 5],
                                'rationale' => 'Understand broad legal landscape',
                            ],
                        ],
                    ])]],
                ],
                'usage' => ['total_tokens' => 150],
            ]);

        $this->app->instance(OpenAIService::class, $mockOpenAI);

        $agent = new AutonomousResearchAgent;
        $run = AgentRun::create([
            'agent_name' => 'test_agent',
            'objective' => 'Research employment law',
            'context' => [],
            'current_iteration' => 0,
            'max_iterations' => 10,
            'tokens_used' => 0,
            'cost_spent' => 0,
        ]);

        $plan = $this->invokeMethod($agent, 'planNextStep', [$run]);

        $this->assertIsArray($plan);
        $this->assertArrayHasKey('reasoning', $plan);
        $this->assertArrayHasKey('actions', $plan);
        $this->assertCount(1, $plan['actions']);
        $this->assertEquals('law_vector_search', $plan['actions'][0]['tool']);
    }

    /** @test */
    public function it_validates_plan_structure()
    {
        // Mock OpenAI to return plan with proper structure
        $mockOpenAI = Mockery::mock(OpenAIService::class);
        $mockOpenAI->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'reasoning' => 'Search for relevant laws',
                        'actions' => [
                            [
                                'tool' => 'law_vector_search',
                                'params' => ['query' => 'test', 'limit' => 5],
                                'rationale' => 'Find laws',
                            ],
                        ],
                    ])]],
                ],
                'usage' => ['total_tokens' => 100],
            ]);

        $this->app->instance(OpenAIService::class, $mockOpenAI);

        $agent = new AutonomousResearchAgent;
        $run = AgentRun::create([
            'agent_name' => 'test_agent',
            'objective' => 'Test',
            'context' => [],
            'tokens_used' => 0,
            'cost_spent' => 0,
        ]);

        $plan = $this->invokeMethod($agent, 'planNextStep', [$run]);

        $this->assertArrayHasKey('reasoning', $plan);
        $this->assertArrayHasKey('actions', $plan);
        $this->assertIsArray($plan['actions']);
        foreach ($plan['actions'] as $action) {
            $this->assertArrayHasKey('tool', $action);
            $this->assertArrayHasKey('params', $action);
        }
    }

    /** @test */
    public function it_falls_back_when_llm_planning_fails()
    {
        // Mock OpenAI to throw exception
        $mockOpenAI = Mockery::mock(OpenAIService::class);
        $mockOpenAI->shouldReceive('chat')
            ->once()
            ->andThrow(new \Exception('API error'));

        $this->app->instance(OpenAIService::class, $mockOpenAI);

        $agent = new AutonomousResearchAgent;
        $run = AgentRun::create([
            'agent_name' => 'test_agent',
            'objective' => 'Research employment law',
            'context' => [],
            'current_iteration' => 0,
            'max_iterations' => 10,
        ]);

        $plan = $this->invokeMethod($agent, 'planNextStep', [$run]);

        // Should return fallback plan
        $this->assertIsArray($plan);
        $this->assertArrayHasKey('reasoning', $plan);
        $this->assertStringContainsString('fallback', strtolower($plan['reasoning']));
        $this->assertArrayHasKey('actions', $plan);
    }

    /** @test */
    public function it_falls_back_when_llm_returns_invalid_json()
    {
        // Mock OpenAI to return invalid JSON
        $mockOpenAI = Mockery::mock(OpenAIService::class);
        $mockOpenAI->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => 'not valid json']],
                ],
                'usage' => ['total_tokens' => 50],
            ]);

        $this->app->instance(OpenAIService::class, $mockOpenAI);

        $agent = new AutonomousResearchAgent;
        $run = AgentRun::create([
            'agent_name' => 'test_agent',
            'objective' => 'Research',
            'context' => [],
            'tokens_used' => 0,
            'cost_spent' => 0,
        ]);

        $plan = $this->invokeMethod($agent, 'planNextStep', [$run]);

        // Should return fallback plan
        $this->assertStringContainsString('fallback', strtolower($plan['reasoning']));
    }

    /** @test */
    public function it_tracks_token_usage_from_planning()
    {
        $mockOpenAI = Mockery::mock(OpenAIService::class);
        $mockOpenAI->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'reasoning' => 'Test reasoning',
                        'actions' => [
                            ['tool' => 'law_vector_search', 'params' => ['query' => 'test']],
                        ],
                    ])]],
                ],
                'usage' => ['total_tokens' => 250],
            ]);

        $this->app->instance(OpenAIService::class, $mockOpenAI);

        $agent = new AutonomousResearchAgent;
        $run = AgentRun::create([
            'agent_name' => 'test_agent',
            'objective' => 'Test',
            'context' => [],
            'tokens_used' => 100,
            'cost_spent' => 0.01,
        ]);

        $this->invokeMethod($agent, 'planNextStep', [$run]);

        // Check in-memory values (no need to refresh in transaction)
        $this->assertEquals(350, $run->tokens_used); // 100 + 250
        $this->assertGreaterThan(0.01, $run->cost_spent);
    }

    /** @test */
    public function it_calculates_cost_correctly()
    {
        $mockOpenAI = Mockery::mock(OpenAIService::class);
        $mockOpenAI->shouldReceive('chat')
            ->once()
            ->andReturn([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'reasoning' => 'Test',
                        'actions' => [
                            ['tool' => 'law_vector_search', 'params' => ['query' => 'test']],
                        ],
                    ])]],
                ],
                'usage' => ['total_tokens' => 1000000], // 1M tokens
            ]);

        $this->app->instance(OpenAIService::class, $mockOpenAI);

        $agent = new AutonomousResearchAgent;
        $run = AgentRun::create([
            'agent_name' => 'test_agent',
            'objective' => 'Test',
            'context' => [],
            'tokens_used' => 0,
            'cost_spent' => 0,
        ]);

        $this->invokeMethod($agent, 'planNextStep', [$run]);

        // Check in-memory values (no need to refresh in transaction)
        // Cost = (1000000 / 1000000) * 0.15 = 0.15
        $this->assertEquals(0.15, round($run->cost_spent, 2));
    }

    /** @test */
    public function it_generates_fallback_actions_for_first_iteration()
    {
        $agent = new AutonomousResearchAgent;
        $run = AgentRun::create([
            'agent_name' => 'test_agent',
            'objective' => 'Research Croatian labor law',
            'context' => [],
            'current_iteration' => 0,
        ]);

        $actions = $this->invokeMethod($agent, 'generateFallbackActions', [$run]);

        $this->assertIsArray($actions);
        $this->assertNotEmpty($actions);
        $this->assertEquals('law_vector_search', $actions[0]['tool']);
        $this->assertArrayHasKey('params', $actions[0]);
        $this->assertEquals('Research Croatian labor law', $actions[0]['params']['query']);
    }

    /** @test */
    public function it_generates_fallback_actions_for_subsequent_iterations()
    {
        $agent = new AutonomousResearchAgent;
        $run = AgentRun::create([
            'agent_name' => 'test_agent',
            'objective' => 'Research employment',
            'context' => [],
            'current_iteration' => 2,
            'topics' => ['labor law', 'contracts', 'termination'],
        ]);

        $actions = $this->invokeMethod($agent, 'generateFallbackActions', [$run]);

        $this->assertIsArray($actions);
        $this->assertNotEmpty($actions);
        // Should have both law search and graph query
        $this->assertGreaterThanOrEqual(1, count($actions));
    }

    /**
     * Helper to invoke protected methods
     */
    protected function invokeMethod($object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}
