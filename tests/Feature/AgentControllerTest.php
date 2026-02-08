<?php

namespace Tests\Feature;

use App\Agents\AutonomousResearchAgent;
use App\Models\AgentRun;
use App\Models\User;
use App\Services\AgentEvaluationService;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class AgentControllerTest extends TestCase
{
    use UsesTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Bypass API token middleware for testing
        $this->withoutMiddleware(\App\Http\Middleware\ApiTokenAuth::class);

        $this->actingAs(User::factory()->create());

        // Clear service bindings to allow mocks to work properly
        $this->app->forgetInstance(AutonomousResearchAgent::class);
        $this->app->forgetInstance(AgentEvaluationService::class);
    }

    /** @test */
    public function it_starts_research_with_valid_data_async()
    {
        $run = AgentRun::factory()->create([
            'objective' => 'Research Croatian criminal procedure law',
            'status' => 'pending',
        ]);

        $mockAgent = Mockery::mock(AutonomousResearchAgent::class);
        $mockAgent->shouldReceive('startRun')
            ->once()
            ->andReturn($run);

        $this->app->instance(AutonomousResearchAgent::class, $mockAgent);

        $response = $this->postJson('/api/agent/research/start', [
            'objective' => 'Research Croatian criminal procedure law',
            'topics' => ['criminal law', 'procedure'],
            'jurisdiction' => 'Croatia',
            'max_iterations' => 5,
            'async' => true,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'async' => true,
                    'run' => [
                        'id' => $run->id,
                        'objective' => $run->objective,
                        'status' => 'pending',
                    ],
                ],
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'async',
                    'run' => [
                        'id',
                        'objective',
                        'status',
                        'message',
                    ],
                ],
                'meta',
            ]);
    }

    /** @test */
    public function it_starts_research_with_valid_data_sync()
    {
        $run = AgentRun::factory()->completed()->create([
            'objective' => 'Research labor law',
            'score' => 0.85,
            'current_iteration' => 3,
        ]);

        $mockAgent = Mockery::mock(AutonomousResearchAgent::class);
        $mockAgent->shouldReceive('startRun')->once()->andReturn($run);
        $mockAgent->shouldReceive('executeRun')->once()->with($run)->andReturn($run);

        $this->app->instance(AutonomousResearchAgent::class, $mockAgent);

        $response = $this->postJson('/api/agent/research/start', [
            'objective' => 'Research labor law',
            'async' => false,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'async' => false,
                    'run' => [
                        'id' => $run->id,
                        'status' => 'completed',
                        'score' => 0.85,
                    ],
                ],
            ]);
    }

    /** @test */
    public function it_validates_start_research_request()
    {
        $response = $this->postJson('/api/agent/research/start', [
            'objective' => 'Short', // Too short (min:10)
            'max_iterations' => 100, // Too high (max:50)
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['objective', 'max_iterations']);
    }

    /** @test */
    public function it_validates_start_research_requires_objective()
    {
        $response = $this->postJson('/api/agent/research/start', [
            'topics' => ['law'],
            // Missing required 'objective'
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['objective']);
    }

    /** @test */
    public function it_gets_research_run_by_id()
    {
        $run = AgentRun::factory()->completed()->create([
            'agent_name' => 'researcher',
            'objective' => 'Research test',
            'topics' => ['topic1', 'topic2'],
            'score' => 0.92,
            'current_iteration' => 5,
            'max_iterations' => 10,
        ]);

        $response = $this->getJson("/api/agent/research/{$run->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'run' => [
                        'id' => $run->id,
                        'agent_name' => 'researcher',
                        'objective' => 'Research test',
                        'status' => 'completed',
                        'score' => 0.92,
                    ],
                ],
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'run' => [
                        'id',
                        'agent_name',
                        'objective',
                        'topics',
                        'status',
                        'score',
                        'threshold',
                        'current_iteration',
                        'max_iterations',
                        'tokens_used',
                        'cost_spent',
                    ],
                ],
                'meta',
            ]);
    }

    /** @test */
    public function it_returns_404_for_nonexistent_research_run()
    {
        $response = $this->getJson('/api/agent/research/99999');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Research run not found',
                ],
            ]);
    }

    /** @test */
    public function it_lists_research_runs()
    {
        AgentRun::factory()->count(5)->create(['agent_name' => 'researcher']);
        AgentRun::factory()->count(3)->create(['agent_name' => 'analyst']);

        $response = $this->getJson('/api/agent/research');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'count' => 8,
                ],
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'runs' => [
                        '*' => [
                            'id',
                            'agent_name',
                            'objective',
                            'status',
                            'score',
                            'iterations',
                        ],
                    ],
                    'count',
                ],
                'meta',
            ]);
    }

    /** @test */
    public function it_filters_research_runs_by_status()
    {
        AgentRun::factory()->count(3)->completed()->create();
        AgentRun::factory()->count(2)->running()->create();
        AgentRun::factory()->count(1)->failed()->create();

        $response = $this->getJson('/api/agent/research?status=completed');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'count' => 3,
                ],
            ]);

        $runs = $response->json('data.runs');
        foreach ($runs as $run) {
            $this->assertEquals('completed', $run['status']);
        }
    }

    /** @test */
    public function it_filters_research_runs_by_agent_name()
    {
        AgentRun::factory()->count(4)->create(['agent_name' => 'researcher']);
        AgentRun::factory()->count(2)->create(['agent_name' => 'analyst']);

        $response = $this->getJson('/api/agent/research?agent_name=researcher');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'count' => 4,
                ],
            ]);
    }

    /** @test */
    public function it_limits_research_runs_list()
    {
        AgentRun::factory()->count(50)->create();

        $response = $this->getJson('/api/agent/research?limit=10');

        $response->assertStatus(200);

        $this->assertCount(10, $response->json('data.runs'));
    }

    /** @test */
    public function it_caps_limit_at_100()
    {
        AgentRun::factory()->count(150)->create();

        $response = $this->getJson('/api/agent/research?limit=200');

        $response->assertStatus(200);

        // Should be capped at 100
        $this->assertLessThanOrEqual(100, count($response->json('data.runs')));
    }

    /** @test */
    public function it_gets_evaluation_for_completed_run()
    {
        $run = AgentRun::factory()->completed()->create();

        $mockEvaluator = Mockery::mock(AgentEvaluationService::class);
        $mockEvaluator->shouldReceive('generateReport')
            ->once()
            ->with($run->id)
            ->andReturn('# Evaluation Report\n\nQuality Score: 0.88\nCompleteness: 0.92');

        $this->app->instance(AgentEvaluationService::class, $mockEvaluator);

        $response = $this->getJson("/api/agent/research/{$run->id}/evaluation");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'run_id' => $run->id,
            ])
            ->assertJsonStructure([
                'success',
                'run_id',
                'report',
            ]);
    }

    /** @test */
    public function it_returns_404_for_evaluation_of_nonexistent_run()
    {
        $response = $this->getJson('/api/agent/research/99999/evaluation');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'error' => 'Run not found',
            ]);
    }

    /** @test */
    public function it_returns_error_for_evaluation_of_incomplete_run()
    {
        $run = AgentRun::factory()->running()->create(['status' => 'running']);

        $response = $this->getJson("/api/agent/research/{$run->id}/evaluation");

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error' => 'Run is not completed yet',
            ]);
    }

    /** @test */
    public function it_deletes_research_run()
    {
        $run = AgentRun::factory()->create();

        $response = $this->deleteJson("/api/agent/research/{$run->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Run deleted successfully',
            ]);

        $this->assertDatabaseMissing('agent_runs', ['id' => $run->id]);
    }

    /** @test */
    public function it_returns_404_when_deleting_nonexistent_run()
    {
        $response = $this->deleteJson('/api/agent/research/99999');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'error' => 'Run not found',
            ]);
    }

    /** @test */
    public function it_handles_agent_start_errors_gracefully()
    {
        $mockAgent = Mockery::mock(AutonomousResearchAgent::class);
        $mockAgent->shouldReceive('startRun')
            ->once()
            ->andThrow(new \Exception('Agent service unavailable'));

        $this->app->instance(AutonomousResearchAgent::class, $mockAgent);

        $response = $this->postJson('/api/agent/research/start', [
            'objective' => 'Test research objective',
        ]);

        $response->assertStatus(500)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'AGENT_EXECUTION_FAILED',
                    'message' => 'Failed to start research run',
                ],
            ]);
    }

    /** @test */
    public function it_handles_evaluation_generation_errors()
    {
        $run = AgentRun::factory()->completed()->create();

        $mockEvaluator = Mockery::mock(AgentEvaluationService::class);
        $mockEvaluator->shouldReceive('generateReport')
            ->once()
            ->with($run->id)
            ->andThrow(new \Exception('Evaluation service error'));

        $this->app->instance(AgentEvaluationService::class, $mockEvaluator);

        $response = $this->getJson("/api/agent/research/{$run->id}/evaluation");

        $response->assertStatus(500)
            ->assertJson([
                'success' => false,
                'error' => 'Evaluation service error',
            ]);
    }

    /** @test */
    public function it_uses_default_config_values_when_not_provided()
    {
        config(['agent.defaults.max_iterations' => 10]);
        config(['agent.defaults.threshold' => 0.75]);

        $run = AgentRun::factory()->create();

        $mockAgent = Mockery::mock(AutonomousResearchAgent::class);
        $mockAgent->shouldReceive('startRun')
            ->once()
            ->andReturn($run);

        $this->app->instance(AutonomousResearchAgent::class, $mockAgent);

        $response = $this->postJson('/api/agent/research/start', [
            'objective' => 'Research without constraints',
            'async' => true,
        ]);

        $response->assertStatus(200);
    }
}
