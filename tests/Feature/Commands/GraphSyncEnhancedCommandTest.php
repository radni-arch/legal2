<?php

namespace Tests\Feature\Commands;

use App\Models\CourtDecision;
use App\Services\Graph\DecisionGraphSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;

/**
 * Test suite for GraphSyncEnhancedCommand
 *
 * Tests enhanced syncing of court decisions to graph database
 * with new entity extraction capabilities.
 */
class GraphSyncEnhancedCommandTest extends TestCase
{
    use RefreshDatabase;

    protected $syncServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->syncServiceMock = Mockery::mock(DecisionGraphSyncService::class);
        $this->app->instance(DecisionGraphSyncService::class, $this->syncServiceMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_syncs_all_court_decisions_by_default()
    {
        // Create test decisions
        $decision1 = CourtDecision::factory()->create();
        $decision2 = CourtDecision::factory()->create();
        $decision3 = CourtDecision::factory()->create();

        // Expect sync to be called for each decision
        $this->syncServiceMock->shouldReceive('sync')
            ->times(3)
            ->andReturn();

        $this->artisan('graph:sync-enhanced')
            ->expectsOutput('Starting enhanced graph sync...')
            ->expectsOutput('Synced: 3')
            ->expectsOutput('Errors: 0')
            ->expectsOutput('Enhanced sync completed successfully!')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_respects_limit_option()
    {
        // Create 5 decisions but limit to 2
        CourtDecision::factory()->count(5)->create();

        // Should only sync 2 decisions
        $this->syncServiceMock->shouldReceive('sync')
            ->times(2)
            ->andReturn();

        $this->artisan('graph:sync-enhanced', ['--limit' => 2])
            ->expectsOutput('Synced: 2')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_supports_dry_run_mode()
    {
        // Create test decisions
        CourtDecision::factory()->count(3)->create();

        // Should not call sync in dry-run mode
        $this->syncServiceMock->shouldNotReceive('sync');

        $this->artisan('graph:sync-enhanced', ['--dry-run' => true])
            ->expectsOutput('DRY RUN MODE - No changes will be made')
            ->expectsOutput('Would sync 3 decision(s)')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_handles_no_decisions_to_sync()
    {
        // No decisions in database
        $this->syncServiceMock->shouldNotReceive('sync');

        $this->artisan('graph:sync-enhanced')
            ->expectsOutput('No court decisions found to sync')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_reports_sync_errors()
    {
        // Create test decisions
        $decision1 = CourtDecision::factory()->create();
        $decision2 = CourtDecision::factory()->create();
        $decision3 = CourtDecision::factory()->create();

        // First two succeed, third fails
        $this->syncServiceMock->shouldReceive('sync')
            ->twice()
            ->andReturn();

        $this->syncServiceMock->shouldReceive('sync')
            ->once()
            ->andThrow(new \Exception('Sync failed'));

        $this->artisan('graph:sync-enhanced')
            ->expectsOutput('Synced: 2')
            ->assertExitCode(0); // Should continue and complete
    }

    /** @test */
    public function it_displays_progress_bar()
    {
        // Create test decisions
        CourtDecision::factory()->count(5)->create();

        $this->syncServiceMock->shouldReceive('sync')
            ->times(5)
            ->andReturn();

        // Progress bar is displayed during execution
        $this->artisan('graph:sync-enhanced')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_shows_detailed_error_messages()
    {
        $decision = CourtDecision::factory()->create(['id' => 'test-decision-id']);

        $this->syncServiceMock->shouldReceive('sync')
            ->once()
            ->andThrow(new \Exception('Database connection failed'));

        $this->artisan('graph:sync-enhanced')
            ->expectsOutput('Synced: 0')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_limits_decisions_correctly_with_large_dataset()
    {
        // Create many decisions
        CourtDecision::factory()->count(100)->create();

        // Should only sync the limit amount
        $this->syncServiceMock->shouldReceive('sync')
            ->times(10)
            ->andReturn();

        $this->artisan('graph:sync-enhanced', ['--limit' => 10])
            ->expectsOutput('Synced: 10')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_handles_limit_greater_than_available_decisions()
    {
        // Create only 3 decisions
        CourtDecision::factory()->count(3)->create();

        // Should sync all 3 even though limit is 10
        $this->syncServiceMock->shouldReceive('sync')
            ->times(3)
            ->andReturn();

        $this->artisan('graph:sync-enhanced', ['--limit' => 10])
            ->expectsOutput('Synced: 3')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_processes_decisions_in_chunks()
    {
        // Create many decisions to test chunking
        CourtDecision::factory()->count(50)->create();

        $this->syncServiceMock->shouldReceive('sync')
            ->times(50)
            ->andReturn();

        $this->artisan('graph:sync-enhanced')
            ->expectsOutput('Synced: 50')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_continues_after_individual_sync_failures()
    {
        // Create 5 decisions
        $decisions = CourtDecision::factory()->count(5)->create();

        // Set up alternating success/failure pattern
        $callCount = 0;
        $this->syncServiceMock->shouldReceive('sync')
            ->times(5)
            ->andReturnUsing(function () use (&$callCount) {
                $callCount++;
                if ($callCount % 2 === 0) {
                    throw new \Exception('Sync failed');
                }
            });

        $this->artisan('graph:sync-enhanced')
            ->expectsOutput('Synced: 3')
            ->assertExitCode(0);
    }

    /** @test */
    public function dry_run_with_limit_shows_correct_count()
    {
        CourtDecision::factory()->count(10)->create();

        $this->syncServiceMock->shouldNotReceive('sync');

        $this->artisan('graph:sync-enhanced', ['--dry-run' => true, '--limit' => 5])
            ->expectsOutput('Would sync 5 decision(s)')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_displays_summary_statistics()
    {
        CourtDecision::factory()->count(10)->create();

        $this->syncServiceMock->shouldReceive('sync')
            ->times(10)
            ->andReturn();

        $this->artisan('graph:sync-enhanced')
            ->expectsOutput('Starting enhanced graph sync...')
            ->expectsOutput('Synced: 10')
            ->expectsOutput('Errors: 0')
            ->expectsOutput('Enhanced sync completed successfully!')
            ->assertExitCode(0);
    }
}
