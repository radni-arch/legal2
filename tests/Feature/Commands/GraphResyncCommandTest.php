<?php

namespace Tests\Feature\Commands;

use App\Models\CourtDecision;
use App\Services\Graph\DecisionGraphSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class GraphResyncCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_syncs_a_single_decision_by_id(): void
    {
        $decision = CourtDecision::factory()->create(['case_number' => 'Test-1/2024']);

        $mockSync = Mockery::mock(DecisionGraphSyncService::class);
        $mockSync->shouldReceive('sync')
            ->once()
            ->with($decision->id);

        $this->app->instance(DecisionGraphSyncService::class, $mockSync);

        $this->artisan('graph:resync', ['--decision-id' => $decision->id])
            ->assertExitCode(0)
            ->expectsOutputToContain('Successfully synced 1 decision');
    }

    /** @test */
    public function it_syncs_all_decisions_in_batch(): void
    {
        CourtDecision::factory()->count(3)->create();

        $mockSync = Mockery::mock(DecisionGraphSyncService::class);
        $mockSync->shouldReceive('sync')
            ->times(3);

        $this->app->instance(DecisionGraphSyncService::class, $mockSync);

        $this->artisan('graph:resync', ['--all' => true])
            ->assertExitCode(0)
            ->expectsOutputToContain('Successfully synced 3 decisions');
    }

    /** @test */
    public function it_requires_decision_id_or_all_flag(): void
    {
        $this->artisan('graph:resync')
            ->assertExitCode(1)
            ->expectsOutputToContain('Please provide --decision-id or --all');
    }

    /** @test */
    public function it_reports_error_for_non_existent_decision(): void
    {
        $this->artisan('graph:resync', ['--decision-id' => 'non-existent-id'])
            ->assertExitCode(1)
            ->expectsOutputToContain('Decision not found');
    }

    /** @test */
    public function it_supports_batch_size_option(): void
    {
        CourtDecision::factory()->count(5)->create();

        $mockSync = Mockery::mock(DecisionGraphSyncService::class);
        $mockSync->shouldReceive('sync')
            ->times(5);

        $this->app->instance(DecisionGraphSyncService::class, $mockSync);

        $this->artisan('graph:resync', ['--all' => true, '--batch' => 2])
            ->assertExitCode(0);
    }
}
