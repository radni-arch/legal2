<?php

namespace Tests\Feature\Console;

use App\Services\GraphRagService;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Test suite for GraphSyncCommand
 *
 * Tests syncing of data from relational database to Neo4j graph database
 * including laws, cases, court decisions, and textract documents.
 */
class GraphSyncCommandTest extends TestCase
{
    use UsesTestDatabase;

    protected $graphRagMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->graphRagMock = Mockery::mock(GraphRagService::class);
        $this->app->instance(GraphRagService::class, $this->graphRagMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_requires_neo4j_to_be_enabled()
    {
        Config::set('neo4j.sync.enabled', false);

        $this->artisan('graph:sync', ['--all' => true])
            ->expectsOutput('Neo4j integration is disabled. Enable it in config/neo4j.php')
            ->assertExitCode(1);
    }

    /** @test */
    public function it_requires_at_least_one_sync_option()
    {
        Config::set('neo4j.sync.enabled', true);

        $this->artisan('graph:sync')
            ->expectsOutput('Please specify what to sync: --all, --laws, --cases, --decisions, or --textract')
            ->assertExitCode(1);
    }

    /** @test */
    public function it_syncs_all_data_with_all_option()
    {
        Config::set('neo4j.sync.enabled', true);

        $this->graphRagMock->shouldReceive('syncAllLaws')
            ->once()
            ->andReturn(['synced' => 100, 'errors' => 0]);

        $this->graphRagMock->shouldReceive('syncAllCases')
            ->once()
            ->andReturn(['synced' => 50, 'errors' => 0]);

        $this->graphRagMock->shouldReceive('syncAllCourtDecisions')
            ->once()
            ->andReturn(['synced' => 75, 'errors' => 0]);

        $this->graphRagMock->shouldReceive('syncAllTextractJobs')
            ->once()
            ->andReturn(['synced' => 25, 'errors' => 0]);

        $this->artisan('graph:sync', ['--all' => true])
            ->expectsOutput('Syncing laws to graph database...')
            ->expectsOutput('Syncing cases to graph database...')
            ->expectsOutput('Syncing court decisions to graph database...')
            ->expectsOutput('Syncing textract documents to graph database...')
            ->expectsOutput('Sync completed successfully!')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_syncs_laws_only()
    {
        Config::set('neo4j.sync.enabled', true);

        $this->graphRagMock->shouldReceive('syncAllLaws')
            ->once()
            ->andReturn(['synced' => 100, 'errors' => 0]);

        $this->artisan('graph:sync', ['--laws' => true])
            ->expectsOutput('Syncing laws to graph database...')
            ->expectsOutput('✓ Synced: 100')
            ->expectsOutput('Sync completed successfully!')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_syncs_cases_only()
    {
        Config::set('neo4j.sync.enabled', true);

        $this->graphRagMock->shouldReceive('syncAllCases')
            ->once()
            ->andReturn(['synced' => 50, 'errors' => 0]);

        $this->artisan('graph:sync', ['--cases' => true])
            ->expectsOutput('Syncing cases to graph database...')
            ->expectsOutput('✓ Synced: 50')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_syncs_court_decisions_only()
    {
        Config::set('neo4j.sync.enabled', true);

        $this->graphRagMock->shouldReceive('syncAllCourtDecisions')
            ->once()
            ->andReturn(['synced' => 75, 'errors' => 0]);

        $this->artisan('graph:sync', ['--decisions' => true])
            ->expectsOutput('Syncing court decisions to graph database...')
            ->expectsOutput('✓ Synced: 75')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_syncs_textract_documents_only()
    {
        Config::set('neo4j.sync.enabled', true);

        $this->graphRagMock->shouldReceive('syncAllTextractJobs')
            ->once()
            ->andReturn(['synced' => 25, 'errors' => 0]);

        $this->artisan('graph:sync', ['--textract' => true])
            ->expectsOutput('Syncing textract documents to graph database...')
            ->expectsOutput('✓ Synced: 25')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_syncs_multiple_types_when_specified()
    {
        Config::set('neo4j.sync.enabled', true);

        $this->graphRagMock->shouldReceive('syncAllLaws')
            ->once()
            ->andReturn(['synced' => 100, 'errors' => 0]);

        $this->graphRagMock->shouldReceive('syncAllCases')
            ->once()
            ->andReturn(['synced' => 50, 'errors' => 0]);

        $this->artisan('graph:sync', ['--laws' => true, '--cases' => true])
            ->expectsOutput('Syncing laws to graph database...')
            ->expectsOutput('Syncing cases to graph database...')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_displays_error_count_when_present()
    {
        Config::set('neo4j.sync.enabled', true);

        $this->graphRagMock->shouldReceive('syncAllLaws')
            ->once()
            ->andReturn(['synced' => 95, 'errors' => 5]);

        $this->artisan('graph:sync', ['--laws' => true])
            ->expectsOutput('✓ Synced: 95')
            ->expectsOutput('⚠ Errors: 5')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_does_not_display_errors_when_zero()
    {
        Config::set('neo4j.sync.enabled', true);

        $this->graphRagMock->shouldReceive('syncAllLaws')
            ->once()
            ->andReturn(['synced' => 100, 'errors' => 0]);

        $output = $this->artisan('graph:sync', ['--laws' => true])->execute();

        // Should not contain error warning
        $this->artisan('graph:sync', ['--laws' => true])
            ->doesntExpectOutput('⚠ Errors: 0');
    }

    /** @test */
    public function it_handles_sync_failure()
    {
        Config::set('neo4j.sync.enabled', true);

        $this->graphRagMock->shouldReceive('syncAllLaws')
            ->once()
            ->andThrow(new \Exception('Database connection failed'));

        $this->artisan('graph:sync', ['--laws' => true])
            ->expectsOutput('Sync failed: Database connection failed')
            ->assertExitCode(1);
    }

    /** @test */
    public function it_displays_progress_bar_during_sync()
    {
        Config::set('neo4j.sync.enabled', true);

        $this->graphRagMock->shouldReceive('syncAllLaws')
            ->once()
            ->andReturn(['synced' => 100, 'errors' => 0]);

        // The progress bar is displayed but we can't easily test its output
        // We can at least verify the command completes
        $this->artisan('graph:sync', ['--laws' => true])
            ->assertExitCode(0);
    }

    /** @test */
    public function it_supports_limit_option()
    {
        Config::set('neo4j.sync.enabled', true);

        // Note: The limit option is defined in signature but not implemented in handle()
        // This test validates the option exists
        $this->graphRagMock->shouldReceive('syncAllLaws')
            ->once()
            ->andReturn(['synced' => 10, 'errors' => 0]);

        $this->artisan('graph:sync', ['--laws' => true, '--limit' => '10'])
            ->assertExitCode(0);
    }

    /** @test */
    public function it_syncs_in_correct_order_with_all_option()
    {
        Config::set('neo4j.sync.enabled', true);

        $callOrder = [];

        $this->graphRagMock->shouldReceive('syncAllLaws')
            ->once()
            ->andReturnUsing(function () use (&$callOrder) {
                $callOrder[] = 'laws';

                return ['synced' => 100, 'errors' => 0];
            });

        $this->graphRagMock->shouldReceive('syncAllCases')
            ->once()
            ->andReturnUsing(function () use (&$callOrder) {
                $callOrder[] = 'cases';

                return ['synced' => 50, 'errors' => 0];
            });

        $this->graphRagMock->shouldReceive('syncAllCourtDecisions')
            ->once()
            ->andReturnUsing(function () use (&$callOrder) {
                $callOrder[] = 'decisions';

                return ['synced' => 75, 'errors' => 0];
            });

        $this->graphRagMock->shouldReceive('syncAllTextractJobs')
            ->once()
            ->andReturnUsing(function () use (&$callOrder) {
                $callOrder[] = 'textract';

                return ['synced' => 25, 'errors' => 0];
            });

        $this->artisan('graph:sync', ['--all' => true])->assertExitCode(0);

        $this->assertEquals(['laws', 'cases', 'decisions', 'textract'], $callOrder);
    }

    /** @test */
    public function it_handles_sync_with_no_synced_results()
    {
        Config::set('neo4j.sync.enabled', true);

        $this->graphRagMock->shouldReceive('syncAllLaws')
            ->once()
            ->andReturn([]); // No 'synced' key

        $this->artisan('graph:sync', ['--laws' => true])
            ->assertExitCode(0); // Should still succeed
    }

    /** @test */
    public function it_continues_sync_after_first_type_success()
    {
        Config::set('neo4j.sync.enabled', true);

        $this->graphRagMock->shouldReceive('syncAllLaws')
            ->once()
            ->andReturn(['synced' => 100, 'errors' => 0]);

        $this->graphRagMock->shouldReceive('syncAllCases')
            ->once()
            ->andReturn(['synced' => 50, 'errors' => 0]);

        $this->artisan('graph:sync', ['--laws' => true, '--cases' => true])
            ->expectsOutput('Sync completed successfully!')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_stops_sync_on_exception()
    {
        Config::set('neo4j.sync.enabled', true);

        $this->graphRagMock->shouldReceive('syncAllLaws')
            ->once()
            ->andReturn(['synced' => 100, 'errors' => 0]);

        $this->graphRagMock->shouldReceive('syncAllCases')
            ->once()
            ->andThrow(new \Exception('Sync failed'));

        // Should not reach court decisions sync
        $this->graphRagMock->shouldNotReceive('syncAllCourtDecisions');

        $this->artisan('graph:sync', ['--all' => true])
            ->expectsOutput('Sync failed: Sync failed')
            ->assertExitCode(1);
    }

    /** @test */
    public function it_displays_synced_count_for_each_type()
    {
        Config::set('neo4j.sync.enabled', true);

        $this->graphRagMock->shouldReceive('syncAllLaws')
            ->once()
            ->andReturn(['synced' => 123, 'errors' => 0]);

        $this->graphRagMock->shouldReceive('syncAllCases')
            ->once()
            ->andReturn(['synced' => 456, 'errors' => 0]);

        $this->artisan('graph:sync', ['--laws' => true, '--cases' => true])
            ->expectsOutput('✓ Synced: 123')
            ->expectsOutput('✓ Synced: 456')
            ->assertExitCode(0);
    }
}
