<?php

namespace Tests\Feature\Api;

use App\Models\AgentRun;
use App\Models\User;
use App\Services\AgentEvaluationService;
use App\Services\ResearchService;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Test Suite for Consolidated Api\AgentController
 *
 * Verifies the migrated controller endpoints work correctly:
 * - Research run lifecycle (start, get, list, delete)
 * - Evaluation report generation
 * - Error handling and validation
 *
 * Updated to use ResearchService instead of deprecated AutonomousResearchAgent.
 */
class AgentControllerTest extends TestCase
{
    use UsesTestDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Bypass API token middleware for testing
        $this->withoutMiddleware(\App\Http\Middleware\ApiTokenAuth::class);

        // Create user for testing
        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        // Bypass authorization gates for testing
        \Illuminate\Support\Facades\Gate::before(function () {
            return true;
        });

        // Clear service bindings to allow mocks to work properly
        $this->app->forgetInstance(ResearchService::class);
        $this->app->forgetInstance(AgentEvaluationService::class);
    }

    /**
     * Test 1: Verify consolidated controller starts research runs asynchronously
     *
     * @test
     */
    public function it_starts_research_run_via_consolidated_controller()
    {
        // Arrange: Create a pending research run
        $run = AgentRun::factory()->create([
            'objective' => 'Analyze proportionality in home search warrants',
            'status' => 'pending',
            'agent_name' => 'autonomous_research',
        ]);

        // Mock the research service
        $mockService = Mockery::mock(ResearchService::class);
        $mockService->shouldReceive('researchAsync')
            ->once()
            ->with(
                'Analyze proportionality in home search warrants',
                Mockery::type('array')
            )
            ->andReturn($run);

        $this->app->instance(ResearchService::class, $mockService);

        // Act: Start research via API
        $response = $this->postJson('/api/agent/research/start', [
            'objective' => 'Analyze proportionality in home search warrants',
            'topics' => ['criminal procedure', 'proportionality', 'ZKP'],
            'jurisdiction' => 'Croatia',
            'max_iterations' => 5,
            'threshold' => 0.8,
            'async' => true,
        ]);

        // Assert: Verify response structure and data
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'async' => true,
                    'run' => [
                        'id' => $run->id,
                        'objective' => 'Analyze proportionality in home search warrants',
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

        // Assert: Verify the response includes instructions
        $this->assertStringContainsString(
            'Check status using GET /api/agent/research/',
            $response->json('data.run.message')
        );
    }

    /**
     * Test 2: Verify consolidated controller retrieves research run details
     *
     * @test
     */
    public function it_retrieves_research_run_details_via_consolidated_controller()
    {
        // Arrange: Create a completed research run with all fields
        $run = AgentRun::factory()->completed()->create([
            'agent_name' => 'autonomous_research',
            'objective' => 'Research Article 9 ZKP violations',
            'topics' => ['ZKP', 'Article 9', 'constitutional rights'],
            'score' => 0.92,
            'current_iteration' => 4,
            'max_iterations' => 10,
            'threshold' => 0.75,
            'tokens_used' => 15000,
            'cost_spent' => 0.45,
            'elapsed_seconds' => 120,
            'final_output' => 'Comprehensive analysis of Article 9 violations...',

        ]);

        // Act: Retrieve the research run
        $response = $this->getJson("/api/agent/research/{$run->id}");

        // Assert: Verify complete response structure
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'run' => [
                        'id' => $run->id,
                        'agent_name' => 'autonomous_research',
                        'objective' => 'Research Article 9 ZKP violations',
                        'status' => 'completed',
                        'score' => 0.92,
                        'current_iteration' => 4,
                        'max_iterations' => 10,
                        'threshold' => 0.75,
                        'tokens_used' => 15000,
                        'cost_spent' => 0.45,
                        'elapsed_seconds' => 120,
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
                        'elapsed_seconds',
                        'started_at',
                        'completed_at',
                        'final_output',
                        'error',
                        'iterations',
                    ],
                ],
                'meta',
            ]);

        // Assert: Verify topics are returned correctly
        $this->assertEquals(['ZKP', 'Article 9', 'constitutional rights'], $response->json('data.run.topics'));

        // Assert: Verify final output is included
        $this->assertNotNull($response->json('data.run.final_output'));
    }

    /**
     * Test 3: Verify consolidated controller lists and filters research runs
     *
     * @test
     */
    public function it_lists_and_filters_research_runs_via_consolidated_controller()
    {
        // Arrange: Create multiple research runs with different statuses and agent names
        $completedRuns = AgentRun::factory()->count(3)->completed()->create([
            'agent_name' => 'autonomous_research',
            'score' => 0.85,

        ]);

        $runningRuns = AgentRun::factory()->count(2)->running()->create([
            'agent_name' => 'autonomous_research',

        ]);

        $failedRuns = AgentRun::factory()->count(1)->failed()->create([
            'agent_name' => 'decision_discovery',

        ]);

        // Act: List all runs without filters
        $allRunsResponse = $this->getJson('/api/agent/research');

        // Assert: Verify all runs are returned
        $allRunsResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'count' => 6,
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
                            'started_at',
                            'completed_at',
                            'elapsed_seconds',
                        ],
                    ],
                    'count',
                ],
                'meta',
            ]);

        // Act: Filter by status=completed
        $completedFilterResponse = $this->getJson('/api/agent/research?status=completed');

        // Assert: Verify only completed runs are returned
        $completedFilterResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'count' => 3,
                ],
            ]);

        $completedFilterRuns = $completedFilterResponse->json('data.runs');
        foreach ($completedFilterRuns as $run) {
            $this->assertEquals('completed', $run['status']);
        }

        // Act: Filter by agent_name=autonomous_research
        $agentFilterResponse = $this->getJson('/api/agent/research?agent_name=autonomous_research');

        // Assert: Verify only autonomous_research runs are returned
        $agentFilterResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'count' => 5, // 3 completed + 2 running
                ],
            ]);

        $agentFilterRuns = $agentFilterResponse->json('data.runs');
        foreach ($agentFilterRuns as $run) {
            $this->assertEquals('autonomous_research', $run['agent_name']);
        }

        // Act: Test limit parameter
        $limitedResponse = $this->getJson('/api/agent/research?limit=2');

        // Assert: Verify limit is respected
        $limitedResponse->assertStatus(200);
        $this->assertCount(2, $limitedResponse->json('data.runs'));

        // Act: Test combined filters
        $combinedResponse = $this->getJson('/api/agent/research?status=completed&agent_name=autonomous_research&limit=2');

        // Assert: Verify combined filters work
        $combinedResponse->assertStatus(200);
        $combinedRuns = $combinedResponse->json('data.runs');
        $this->assertLessThanOrEqual(2, count($combinedRuns));

        foreach ($combinedRuns as $run) {
            $this->assertEquals('completed', $run['status']);
            $this->assertEquals('autonomous_research', $run['agent_name']);
        }
    }
}
