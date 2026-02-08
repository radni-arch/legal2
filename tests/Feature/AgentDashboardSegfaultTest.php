<?php

namespace Tests\Feature;

use App\Http\Controllers\AgentController;
use App\Models\AgentRun;
use App\Models\User;
use App\Services\AgentEvaluationService;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Tests for the agent dashboard segfault fix.
 *
 * Root cause: AgentController constructor eagerly resolved ResearchService
 * and AgentEvaluationService. ResearchService depends on ResearchOrchestrator
 * which extends BaseLlmAgent, which eagerly loads 12 tools via app().
 * Those tools depend on search services that have circular dependencies
 * with MCP tools, causing infinite recursion and memory exhaustion (segfault).
 *
 * Fix: Use method injection instead of constructor injection for heavy services.
 * The dashboard() method doesn't use these services at all.
 */
class AgentDashboardSegfaultTest extends TestCase
{
    use UsesTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    /** @test */
    public function dashboard_route_returns_200_without_resolving_heavy_services(): void
    {
        // This is the core test: GET /agent/dashboard must work without
        // triggering resolution of ResearchService (which causes OOM/segfault).
        $response = $this->get('/agent/dashboard');

        $response->assertStatus(200);
        $response->assertViewIs('agent.dashboard');
    }

    /** @test */
    public function dashboard_passes_runs_and_stats_to_view(): void
    {
        AgentRun::factory()->count(3)->completed()->create();
        AgentRun::factory()->count(2)->running()->create();
        AgentRun::factory()->count(1)->failed()->create();

        $response = $this->get('/agent/dashboard');

        $response->assertStatus(200);
        $response->assertViewHas('runs');
        $response->assertViewHas('stats');

        $stats = $response->viewData('stats');
        $this->assertEquals(6, $stats['total']);
        $this->assertEquals(2, $stats['running']);
        $this->assertEquals(3, $stats['completed']);
        $this->assertEquals(1, $stats['failed']);
    }

    /** @test */
    public function dashboard_limits_runs_to_50(): void
    {
        AgentRun::factory()->count(60)->create();

        $response = $this->get('/agent/dashboard');

        $response->assertStatus(200);
        $runs = $response->viewData('runs');
        $this->assertCount(50, $runs);
    }

    /** @test */
    public function view_run_route_works_with_method_injected_evaluator(): void
    {
        $run = AgentRun::factory()->completed()->create([
            'final_output' => '# Research Results',
        ]);

        // Mock the evaluator with data matching the view's expected structure
        $mockEvaluator = \Mockery::mock(AgentEvaluationService::class);
        $mockEvaluator->shouldReceive('evaluateRun')
            ->once()
            ->with($run->id, '# Research Results')
            ->andReturn([
                'score' => 0.85,
                'passed' => true,
                'checks' => [
                    'relevance' => ['score' => 0.9, 'passed' => true],
                    'completeness' => ['score' => 0.8, 'passed' => true],
                ],
            ]);
        $this->app->instance(AgentEvaluationService::class, $mockEvaluator);

        $response = $this->get("/agent/run/{$run->id}");

        $response->assertStatus(200);
        $response->assertViewIs('agent.run');
    }

    /** @test */
    public function controller_can_be_resolved_without_memory_exhaustion(): void
    {
        // After the fix, AgentController should resolve without triggering
        // the heavy ResearchService/ResearchOrchestrator dependency chain.
        $memBefore = memory_get_usage(true);

        $controller = app(AgentController::class);

        $memAfter = memory_get_usage(true);
        $memDelta = ($memAfter - $memBefore) / 1024 / 1024; // MB

        $this->assertNotNull($controller);
        // Controller resolution should not consume more than 50MB
        // Before the fix, it caused infinite recursion and OOM at 256MB+
        $this->assertLessThan(50, $memDelta, "Controller resolution consumed {$memDelta}MB - possible circular dependency");
    }
}
