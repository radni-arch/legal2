<?php

namespace Tests\Feature\Commands;

use App\Models\Law;
use App\Services\GraphDatabaseService;
use Tests\TestCase;
use Mockery;
use Illuminate\Foundation\Testing\RefreshDatabase;

class GraphConsistencyCheckCommandTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_reports_consistent_when_stores_match(): void
    {
        // Empty stores - consistent
        $graphService = Mockery::mock(GraphDatabaseService::class);
        $graphService->shouldReceive('run')->andReturn(collect([]));

        $this->app->instance(GraphDatabaseService::class, $graphService);

        $this->artisan('graph:consistency-check')
            ->expectsOutput('Graph and database are consistent')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_detects_orphaned_graph_nodes(): void
    {
        // Neo4j has a node that doesn't exist in PostgreSQL
        $graphService = Mockery::mock(GraphDatabaseService::class);
        $record = Mockery::mock();
        $record->shouldReceive('get')->with('id')->andReturn('01HRKORPHAN123456789ABCDEF');

        $graphService->shouldReceive('run')
            ->with(Mockery::on(fn($q) => str_contains($q, 'LawDocument')))
            ->andReturn(collect([$record]));

        $this->app->instance(GraphDatabaseService::class, $graphService);

        $this->artisan('graph:consistency-check')
            ->expectsOutputToContain('orphaned')
            ->assertExitCode(1);
    }

    /** @test */
    public function it_detects_missing_graph_nodes(): void
    {
        // PostgreSQL has a Law that's not in Neo4j
        Law::factory()->create(['id' => '01HRKMISSING123456789ABCDE']);

        $graphService = Mockery::mock(GraphDatabaseService::class);
        $graphService->shouldReceive('run')->andReturn(collect([]));

        $this->app->instance(GraphDatabaseService::class, $graphService);

        $this->artisan('graph:consistency-check')
            ->expectsOutputToContain('missing')
            ->assertExitCode(1);
    }

    /** @test */
    public function it_skips_check_when_sync_is_disabled(): void
    {
        config(['neo4j.sync.enabled' => false]);

        $graphService = Mockery::mock(GraphDatabaseService::class);
        $graphService->shouldNotReceive('run');

        $this->app->instance(GraphDatabaseService::class, $graphService);

        $this->artisan('graph:consistency-check')
            ->expectsOutputToContain('Neo4j sync is disabled')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_handles_graph_service_errors_gracefully(): void
    {
        config(['neo4j.sync.enabled' => true]);

        $graphService = Mockery::mock(GraphDatabaseService::class);
        $graphService->shouldReceive('run')
            ->andThrow(new \Exception('Connection refused'));

        $this->app->instance(GraphDatabaseService::class, $graphService);

        $this->artisan('graph:consistency-check')
            ->expectsOutputToContain('Error checking graph consistency')
            ->assertExitCode(1);
    }
}
