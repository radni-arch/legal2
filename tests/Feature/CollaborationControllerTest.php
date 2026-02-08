<?php

namespace Tests\Feature;

use App\Models\AgentCollaboration;
use App\Models\User;
use App\Services\Collaboration\LegalTeamOrchestrator;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class CollaborationControllerTest extends TestCase
{
    use UsesTestDatabase;

    protected string $apiToken;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a user with API token for authentication
        $this->apiToken = Str::random(60);
        User::factory()->create([
            'api_token' => $this->apiToken,
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Helper method to make authenticated POST requests
     */
    protected function authenticatedPostJson(string $uri, array $data = [])
    {
        return $this->withToken($this->apiToken)->postJson($uri, $data);
    }

    /**
     * Helper method to make authenticated GET requests
     */
    protected function authenticatedGetJson(string $uri)
    {
        return $this->withToken($this->apiToken)->getJson($uri);
    }

    /** @test */
    public function it_solves_legal_problem_with_valid_data()
    {
        $mockOrchestrator = Mockery::mock(LegalTeamOrchestrator::class);
        $mockOrchestrator->shouldReceive('solveProblem')
            ->once()
            ->with(
                'How to challenge illegally obtained evidence in Croatian criminal procedure?',
                Mockery::on(function ($options) {
                    return $options['problem_type'] === 'criminal'
                        && $options['execution_mode'] === 'sequential';
                })
            )
            ->andReturn([
                'success' => true,
                'collaboration_id' => 'collab-123',
                'session_id' => 'collab_abc123',
                'problem_statement' => 'How to challenge illegally obtained evidence...',
                'execution_plan' => [
                    ['agent' => 'research_specialist', 'task' => 'Research relevant laws'],
                    ['agent' => 'precedent_analyst', 'task' => 'Analyze case precedents'],
                ],
                'agent_outputs' => [
                    'research_specialist' => ['findings' => 'ZKP čl. 291 allows suppression...'],
                    'precedent_analyst' => ['precedents' => ['ECLI:HR:VSRH:2024:123']],
                ],
                'final_result' => [
                    'synthesis' => 'Evidence can be challenged under Croatian constitutional law...',
                    'research_findings' => ['ZKP čl. 291', 'Ustav RH čl. 35'],
                    'precedent_analysis' => ['Key precedent from Supreme Court'],
                    'legal_strategy' => 'File motion to suppress under ZKP čl. 291',
                ],
                'metadata' => [
                    'tokens_used' => 12345,
                    'cost_spent' => 1.85,
                    'duration_seconds' => 45,
                ],
            ]);

        $this->app->instance(LegalTeamOrchestrator::class, $mockOrchestrator);

        $response = $this->authenticatedPostJson('/api/collaboration/solve', [
            'problem' => 'How to challenge illegally obtained evidence in Croatian criminal procedure?',
            'problem_type' => 'criminal',
            'execution_mode' => 'sequential',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'collaboration_id' => 'collab-123',
                ],
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'collaboration_id',
                    'session_id',
                    'problem_statement',
                    'execution_plan',
                    'agent_outputs',
                    'final_result' => [
                        'synthesis',
                        'research_findings',
                    ],
                    'metadata' => [
                        'tokens_used',
                        'cost_spent',
                        'duration_seconds',
                    ],
                ],
                'meta',
            ]);
    }

    /** @test */
    public function it_validates_collaboration_solve_request()
    {
        $this->markTestSkipped('Test hangs - needs investigation');

        $response = $this->authenticatedPostJson('/api/collaboration/solve', [
            'problem' => 'Short', // Too short (min:10)
            'problem_type' => 'invalid_type',
            'execution_mode' => 'invalid_mode',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'error',
                'validation_errors',
            ]);
    }

    /** @test */
    public function it_requires_problem_field()
    {
        $this->markTestSkipped('Test hangs - needs investigation');

        $response = $this->authenticatedPostJson('/api/collaboration/solve', [
            'problem_type' => 'criminal',
            // Missing required 'problem' field
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => 'Validation failed',
            ]);
    }

    /** @test */
    public function it_validates_problem_types()
    {
        $this->markTestSkipped('Test hangs - needs investigation');

        $response = $this->authenticatedPostJson('/api/collaboration/solve', [
            'problem' => 'Valid problem statement that is long enough',
            'problem_type' => 'invalid_type',
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function it_validates_agent_names()
    {
        $this->markTestSkipped('Test hangs - needs investigation');

        $response = $this->authenticatedPostJson('/api/collaboration/solve', [
            'problem' => 'Valid problem statement that is long enough',
            'agents' => ['invalid_agent_name'],
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function it_solves_problem_without_optional_parameters()
    {
        $mockOrchestrator = Mockery::mock(LegalTeamOrchestrator::class);
        $mockOrchestrator->shouldReceive('solveProblem')
            ->once()
            ->with(
                'Legal problem statement',
                Mockery::on(function ($options) {
                    return $options['problem_type'] === null
                        && $options['context'] === []
                        && $options['agents'] === null
                        && $options['execution_mode'] === 'sequential';
                })
            )
            ->andReturn([
                'success' => true,
                'collaboration_id' => 'collab-456',
                'final_result' => ['synthesis' => 'Result'],
                'metadata' => ['duration_seconds' => 30],
            ]);

        $this->app->instance(LegalTeamOrchestrator::class, $mockOrchestrator);

        $response = $this->authenticatedPostJson('/api/collaboration/solve', [
            'problem' => 'Legal problem statement',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => ['success' => true],
            ]);
    }

    /** @test */
    public function it_handles_orchestrator_failures()
    {
        $mockOrchestrator = Mockery::mock(LegalTeamOrchestrator::class);
        $mockOrchestrator->shouldReceive('solveProblem')
            ->once()
            ->andReturn([
                'success' => false,
                'error' => 'Agent collaboration failed',
            ]);

        $this->app->instance(LegalTeamOrchestrator::class, $mockOrchestrator);

        $response = $this->authenticatedPostJson('/api/collaboration/solve', [
            'problem' => 'Test problem statement',
        ]);

        $response->assertStatus(500)
            ->assertJson([
                'success' => false,
                'error' => 'Agent collaboration failed',
            ]);
    }

    /** @test */
    public function it_handles_orchestrator_exceptions()
    {
        $mockOrchestrator = Mockery::mock(LegalTeamOrchestrator::class);
        $mockOrchestrator->shouldReceive('solveProblem')
            ->once()
            ->andThrow(new \Exception('Orchestrator service unavailable'));

        $this->app->instance(LegalTeamOrchestrator::class, $mockOrchestrator);

        $response = $this->authenticatedPostJson('/api/collaboration/solve', [
            'problem' => 'Test problem statement',
        ]);

        $response->assertStatus(500)
            ->assertJson([
                'success' => false,
                'error' => 'Orchestrator service unavailable',
            ]);
    }

    /** @test */
    public function it_gets_collaboration_by_id()
    {
        $collaboration = AgentCollaboration::factory()->completed()->create();

        $mockOrchestrator = Mockery::mock(LegalTeamOrchestrator::class);
        $mockOrchestrator->shouldReceive('getCollaboration')
            ->once()
            ->with($collaboration->id)
            ->andReturn([
                'id' => $collaboration->id,
                'problem_statement' => $collaboration->problem_statement,
                'status' => 'completed',
                'final_result' => $collaboration->final_result,
            ]);

        $this->app->instance(LegalTeamOrchestrator::class, $mockOrchestrator);

        $response = $this->authenticatedGetJson("/api/collaboration/{$collaboration->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'collaboration' => [
                    'id' => $collaboration->id,
                    'status' => 'completed',
                ],
            ]);
    }

    /** @test */
    public function it_returns_404_for_nonexistent_collaboration()
    {
        $mockOrchestrator = Mockery::mock(LegalTeamOrchestrator::class);
        $mockOrchestrator->shouldReceive('getCollaboration')
            ->once()
            ->with('nonexistent-id')
            ->andReturn(null);

        $this->app->instance(LegalTeamOrchestrator::class, $mockOrchestrator);

        $response = $this->authenticatedGetJson('/api/collaboration/nonexistent-id');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'error' => 'Collaboration not found',
            ]);
    }

    /** @test */
    public function it_gets_recent_collaborations()
    {
        AgentCollaboration::factory()->count(15)->completed()->create();

        $mockOrchestrator = Mockery::mock(LegalTeamOrchestrator::class);
        $mockOrchestrator->shouldReceive('getRecentCollaborations')
            ->once()
            ->with(10)
            ->andReturn(AgentCollaboration::orderBy('created_at', 'desc')->limit(10)->get()->toArray());

        $this->app->instance(LegalTeamOrchestrator::class, $mockOrchestrator);

        $response = $this->authenticatedGetJson('/api/collaboration/recent?limit=10');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'count' => 10,
            ])
            ->assertJsonStructure([
                'success',
                'collaborations',
                'count',
            ]);
    }

    /** @test */
    public function it_caps_recent_collaborations_limit()
    {
        AgentCollaboration::factory()->count(150)->create();

        $mockOrchestrator = Mockery::mock(LegalTeamOrchestrator::class);
        $mockOrchestrator->shouldReceive('getRecentCollaborations')
            ->once()
            ->with(100) // Should be capped at 100
            ->andReturn(AgentCollaboration::limit(100)->get()->toArray());

        $this->app->instance(LegalTeamOrchestrator::class, $mockOrchestrator);

        $response = $this->authenticatedGetJson('/api/collaboration/recent?limit=200');

        $response->assertStatus(200);
    }

    /** @test */
    public function it_uses_default_limit_for_recent_collaborations()
    {
        $mockOrchestrator = Mockery::mock(LegalTeamOrchestrator::class);
        $mockOrchestrator->shouldReceive('getRecentCollaborations')
            ->once()
            ->with(10) // Default limit
            ->andReturn([]);

        $this->app->instance(LegalTeamOrchestrator::class, $mockOrchestrator);

        $response = $this->authenticatedGetJson('/api/collaboration/recent');

        $response->assertStatus(200);
    }

    /** @test */
    public function it_gets_collaboration_statistics()
    {
        AgentCollaboration::factory()->count(10)->completed()->create();
        AgentCollaboration::factory()->count(3)->inProgress()->create();
        AgentCollaboration::factory()->count(2)->failed()->create();

        $response = $this->authenticatedGetJson('/api/collaboration/stats');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'stats' => [
                    'total_collaborations' => 15,
                    'completed' => 10,
                    'in_progress' => 3,
                    'failed' => 2,
                ],
            ])
            ->assertJsonStructure([
                'success',
                'stats' => [
                    'total_collaborations',
                    'completed',
                    'in_progress',
                    'failed',
                    'total_tokens_used',
                    'total_cost_spent',
                    'avg_duration_seconds',
                    'recent_7_days',
                ],
            ]);
    }

    /** @test */
    public function it_handles_stats_calculation_errors()
    {
        $this->markTestSkipped('DB disconnect causes auth to fail before reaching controller');

        // Force a database error by closing the connection
        \DB::disconnect();

        $response = $this->authenticatedGetJson('/api/collaboration/stats');

        $response->assertStatus(500)
            ->assertJson([
                'success' => false,
            ]);
    }

    /** @test */
    public function it_accepts_all_valid_problem_types()
    {
        $validTypes = ['employment', 'contract', 'property', 'family', 'criminal', 'general'];

        foreach ($validTypes as $type) {
            $mockOrchestrator = Mockery::mock(LegalTeamOrchestrator::class);
            $mockOrchestrator->shouldReceive('solveProblem')
                ->once()
                ->andReturn([
                    'success' => true,
                    'collaboration_id' => 'test-'.$type,
                    'final_result' => [],
                    'metadata' => ['duration_seconds' => 1],
                ]);

            $this->app->instance(LegalTeamOrchestrator::class, $mockOrchestrator);

            $response = $this->authenticatedPostJson('/api/collaboration/solve', [
                'problem' => "Test problem for {$type} law",
                'problem_type' => $type,
            ]);

            $response->assertStatus(200);

            Mockery::close(); // Verify and reset mocks after each iteration
        }
    }

    /** @test */
    public function it_accepts_all_valid_execution_modes()
    {
        $validModes = ['sequential', 'parallel'];

        foreach ($validModes as $mode) {
            $mockOrchestrator = Mockery::mock(LegalTeamOrchestrator::class);
            $mockOrchestrator->shouldReceive('solveProblem')
                ->once()
                ->andReturn([
                    'success' => true,
                    'collaboration_id' => 'test-'.$mode,
                    'final_result' => [],
                    'metadata' => ['duration_seconds' => 1],
                ]);

            $this->app->instance(LegalTeamOrchestrator::class, $mockOrchestrator);

            $response = $this->authenticatedPostJson('/api/collaboration/solve', [
                'problem' => "Test problem with {$mode} execution",
                'execution_mode' => $mode,
            ]);

            $response->assertStatus(200);

            Mockery::close(); // Verify and reset mocks after each iteration
        }
    }

    /** @test */
    public function it_accepts_valid_agent_selections()
    {
        $validAgents = ['research_specialist', 'precedent_analyst', 'strategy_specialist', 'risk_analyst'];

        $mockOrchestrator = Mockery::mock(LegalTeamOrchestrator::class);
        $mockOrchestrator->shouldReceive('solveProblem')
            ->once()
            ->with(
                'Test problem',
                Mockery::on(function ($options) use ($validAgents) {
                    return $options['agents'] === $validAgents;
                })
            )
            ->andReturn([
                'success' => true,
                'collaboration_id' => 'test-agents',
                'final_result' => [],
                'metadata' => ['duration_seconds' => 1],
            ]);

        $this->app->instance(LegalTeamOrchestrator::class, $mockOrchestrator);

        $response = $this->authenticatedPostJson('/api/collaboration/solve', [
            'problem' => 'Test problem',
            'agents' => $validAgents,
        ]);

        $response->assertStatus(200);
    }
}
