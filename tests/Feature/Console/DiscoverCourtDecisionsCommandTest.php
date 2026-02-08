<?php

namespace Tests\Feature\Console;

use App\Agents\DecisionDiscoveryAgent;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class DiscoverCourtDecisionsCommandTest extends TestCase
{
    use UsesTestDatabase;

    protected $agentMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agentMock = Mockery::mock(DecisionDiscoveryAgent::class);
        $this->app->instance(DecisionDiscoveryAgent::class, $this->agentMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_discovers_court_decisions_successfully()
    {
        $this->agentMock
            ->shouldReceive('setTopicsPerRun')
            ->with(5)
            ->once()
            ->andReturnSelf();

        $this->agentMock
            ->shouldReceive('setDecisionsPerTopic')
            ->with(50)
            ->once()
            ->andReturnSelf();

        $this->agentMock
            ->shouldReceive('setIngestPerTopic')
            ->with(10)
            ->once()
            ->andReturnSelf();

        $this->agentMock
            ->shouldReceive('setRelevanceThreshold')
            ->with(70.0)
            ->once()
            ->andReturnSelf();

        $this->agentMock
            ->shouldReceive('discover')
            ->once()
            ->andReturn([
                'topics_generated' => 5,
                'decisions_evaluated' => 250,
                'decisions_ingested' => 50,
                'errors' => [],
            ]);

        $this->artisan('decisions:discover')
            ->expectsOutputToContain('Starting Autonomous Court Decision Discovery')
            ->expectsOutputToContain('Discovery Completed')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_accepts_custom_topics_count()
    {
        $this->agentMock
            ->shouldReceive('setTopicsPerRun')
            ->with(10)
            ->once()
            ->andReturnSelf();

        $this->agentMock
            ->shouldReceive('setDecisionsPerTopic')
            ->andReturnSelf();

        $this->agentMock
            ->shouldReceive('setIngestPerTopic')
            ->andReturnSelf();

        $this->agentMock
            ->shouldReceive('setRelevanceThreshold')
            ->andReturnSelf();

        $this->agentMock
            ->shouldReceive('discover')
            ->andReturn([
                'topics_generated' => 10,
                'decisions_evaluated' => 500,
                'decisions_ingested' => 100,
                'errors' => [],
            ]);

        $this->artisan('decisions:discover', ['--topics' => 10])
            ->assertExitCode(0);
    }

    /** @test */
    public function it_accepts_custom_decisions_per_topic()
    {
        $this->agentMock
            ->shouldReceive('setTopicsPerRun')
            ->andReturnSelf();

        $this->agentMock
            ->shouldReceive('setDecisionsPerTopic')
            ->with(100)
            ->once()
            ->andReturnSelf();

        $this->agentMock
            ->shouldReceive('setIngestPerTopic')
            ->andReturnSelf();

        $this->agentMock
            ->shouldReceive('setRelevanceThreshold')
            ->andReturnSelf();

        $this->agentMock
            ->shouldReceive('discover')
            ->andReturn([
                'topics_generated' => 5,
                'decisions_evaluated' => 500,
                'decisions_ingested' => 50,
                'errors' => [],
            ]);

        $this->artisan('decisions:discover', ['--per-topic' => 100])
            ->assertExitCode(0);
    }

    /** @test */
    public function it_accepts_custom_ingest_count()
    {
        $this->agentMock
            ->shouldReceive('setTopicsPerRun')
            ->andReturnSelf();

        $this->agentMock
            ->shouldReceive('setDecisionsPerTopic')
            ->andReturnSelf();

        $this->agentMock
            ->shouldReceive('setIngestPerTopic')
            ->with(20)
            ->once()
            ->andReturnSelf();

        $this->agentMock
            ->shouldReceive('setRelevanceThreshold')
            ->andReturnSelf();

        $this->agentMock
            ->shouldReceive('discover')
            ->andReturn([
                'topics_generated' => 5,
                'decisions_evaluated' => 250,
                'decisions_ingested' => 100,
                'errors' => [],
            ]);

        $this->artisan('decisions:discover', ['--ingest' => 20])
            ->assertExitCode(0);
    }

    /** @test */
    public function it_accepts_custom_relevance_threshold()
    {
        $this->agentMock
            ->shouldReceive('setTopicsPerRun')
            ->andReturnSelf();

        $this->agentMock
            ->shouldReceive('setDecisionsPerTopic')
            ->andReturnSelf();

        $this->agentMock
            ->shouldReceive('setIngestPerTopic')
            ->andReturnSelf();

        $this->agentMock
            ->shouldReceive('setRelevanceThreshold')
            ->with(85.0)
            ->once()
            ->andReturnSelf();

        $this->agentMock
            ->shouldReceive('discover')
            ->andReturn([
                'topics_generated' => 5,
                'decisions_evaluated' => 250,
                'decisions_ingested' => 25,
                'errors' => [],
            ]);

        $this->artisan('decisions:discover', ['--threshold' => 85])
            ->assertExitCode(0);
    }

    /** @test */
    public function it_supports_dry_run_mode()
    {
        $this->agentMock
            ->shouldReceive('setTopicsPerRun')
            ->andReturnSelf();

        $this->agentMock
            ->shouldReceive('setDecisionsPerTopic')
            ->andReturnSelf();

        $this->agentMock
            ->shouldReceive('setIngestPerTopic')
            ->with(10) // Initial call
            ->once()
            ->andReturnSelf();

        $this->agentMock
            ->shouldReceive('setIngestPerTopic')
            ->with(0) // Dry run override
            ->once()
            ->andReturnSelf();

        $this->agentMock
            ->shouldReceive('setRelevanceThreshold')
            ->andReturnSelf();

        $this->agentMock
            ->shouldReceive('discover')
            ->once()
            ->andReturn([
                'topics_generated' => 5,
                'decisions_evaluated' => 250,
                'decisions_ingested' => 0,
                'errors' => [],
            ]);

        $this->artisan('decisions:discover', ['--dry-run' => true])
            ->expectsOutputToContain('DRY RUN MODE')
            ->expectsOutputToContain('This was a dry run')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_displays_configuration_table()
    {
        $this->agentMock
            ->shouldReceive('setTopicsPerRun')
            ->andReturnSelf();

        $this->agentMock
            ->shouldReceive('setDecisionsPerTopic')
            ->andReturnSelf();

        $this->agentMock
            ->shouldReceive('setIngestPerTopic')
            ->andReturnSelf();

        $this->agentMock
            ->shouldReceive('setRelevanceThreshold')
            ->andReturnSelf();

        $this->agentMock
            ->shouldReceive('discover')
            ->andReturn([
                'topics_generated' => 5,
                'decisions_evaluated' => 250,
                'decisions_ingested' => 50,
                'errors' => [],
            ]);

        $this->artisan('decisions:discover')
            ->expectsOutputToContain('Configuration:')
            ->expectsOutputToContain('Topics to generate')
            ->expectsOutputToContain('Decisions per topic')
            ->expectsOutputToContain('Top to ingest')
            ->expectsOutputToContain('Relevance threshold')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_displays_summary_statistics()
    {
        $this->agentMock
            ->shouldReceive('setTopicsPerRun')
            ->andReturnSelf();

        $this->agentMock
            ->shouldReceive('setDecisionsPerTopic')
            ->andReturnSelf();

        $this->agentMock
            ->shouldReceive('setIngestPerTopic')
            ->andReturnSelf();

        $this->agentMock
            ->shouldReceive('setRelevanceThreshold')
            ->andReturnSelf();

        $this->agentMock
            ->shouldReceive('discover')
            ->andReturn([
                'topics_generated' => 5,
                'decisions_evaluated' => 250,
                'decisions_ingested' => 50,
                'errors' => [],
            ]);

        $this->artisan('decisions:discover')
            ->expectsOutputToContain('Summary Statistics:')
            ->expectsOutputToContain('Topics generated')
            ->expectsOutputToContain('5')
            ->expectsOutputToContain('Decisions evaluated')
            ->expectsOutputToContain('250')
            ->expectsOutputToContain('Decisions ingested')
            ->expectsOutputToContain('50')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_displays_errors_when_present()
    {
        $this->agentMock
            ->shouldReceive('setTopicsPerRun')
            ->andReturnSelf();

        $this->agentMock
            ->shouldReceive('setDecisionsPerTopic')
            ->andReturnSelf();

        $this->agentMock
            ->shouldReceive('setIngestPerTopic')
            ->andReturnSelf();

        $this->agentMock
            ->shouldReceive('setRelevanceThreshold')
            ->andReturnSelf();

        $this->agentMock
            ->shouldReceive('discover')
            ->andReturn([
                'topics_generated' => 5,
                'decisions_evaluated' => 250,
                'decisions_ingested' => 45,
                'errors' => [
                    ['topic' => 'Criminal Law', 'error' => 'Network timeout'],
                    ['topic' => 'Civil Procedure', 'error' => 'Invalid response'],
                ],
            ]);

        $this->artisan('decisions:discover')
            ->expectsOutputToContain('Errors encountered:')
            ->expectsOutputToContain('Criminal Law')
            ->expectsOutputToContain('Network timeout')
            ->expectsOutputToContain('Civil Procedure')
            ->expectsOutputToContain('Invalid response')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_displays_execution_time()
    {
        $this->agentMock
            ->shouldReceive('setTopicsPerRun')
            ->andReturnSelf();

        $this->agentMock
            ->shouldReceive('setDecisionsPerTopic')
            ->andReturnSelf();

        $this->agentMock
            ->shouldReceive('setIngestPerTopic')
            ->andReturnSelf();

        $this->agentMock
            ->shouldReceive('setRelevanceThreshold')
            ->andReturnSelf();

        $this->agentMock
            ->shouldReceive('discover')
            ->andReturn([
                'topics_generated' => 5,
                'decisions_evaluated' => 250,
                'decisions_ingested' => 50,
                'errors' => [],
            ]);

        $this->artisan('decisions:discover')
            ->expectsOutputToContain('Discovery Completed in')
            ->expectsOutputToContain('seconds')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_handles_discovery_exceptions()
    {
        $this->agentMock
            ->shouldReceive('setTopicsPerRun')
            ->andReturnSelf();

        $this->agentMock
            ->shouldReceive('setDecisionsPerTopic')
            ->andReturnSelf();

        $this->agentMock
            ->shouldReceive('setIngestPerTopic')
            ->andReturnSelf();

        $this->agentMock
            ->shouldReceive('setRelevanceThreshold')
            ->andReturnSelf();

        $this->agentMock
            ->shouldReceive('discover')
            ->once()
            ->andThrow(new \Exception('Agent failure'));

        $this->artisan('decisions:discover')
            ->expectsOutputToContain('Discovery failed')
            ->expectsOutputToContain('Agent failure')
            ->assertExitCode(1);
    }

    /** @test */
    public function it_shows_verbose_stack_trace_on_error()
    {
        $this->agentMock
            ->shouldReceive('setTopicsPerRun')
            ->andReturnSelf();

        $this->agentMock
            ->shouldReceive('setDecisionsPerTopic')
            ->andReturnSelf();

        $this->agentMock
            ->shouldReceive('setIngestPerTopic')
            ->andReturnSelf();

        $this->agentMock
            ->shouldReceive('setRelevanceThreshold')
            ->andReturnSelf();

        $this->agentMock
            ->shouldReceive('discover')
            ->andThrow(new \Exception('Test error'));

        $this->artisan('decisions:discover', ['-v' => true])
            ->expectsOutputToContain('Discovery failed')
            ->assertExitCode(1);
    }

    /** @test */
    public function it_suggests_removing_dry_run_after_dry_run()
    {
        $this->agentMock
            ->shouldReceive('setTopicsPerRun')
            ->andReturnSelf();

        $this->agentMock
            ->shouldReceive('setDecisionsPerTopic')
            ->andReturnSelf();

        $this->agentMock
            ->shouldReceive('setIngestPerTopic')
            ->twice()
            ->andReturnSelf();

        $this->agentMock
            ->shouldReceive('setRelevanceThreshold')
            ->andReturnSelf();

        $this->agentMock
            ->shouldReceive('discover')
            ->andReturn([
                'topics_generated' => 5,
                'decisions_evaluated' => 250,
                'decisions_ingested' => 0,
                'errors' => [],
            ]);

        $this->artisan('decisions:discover', ['--dry-run' => true])
            ->expectsOutputToContain('Re-run without --dry-run to actually ingest')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_displays_mode_in_configuration()
    {
        $this->agentMock
            ->shouldReceive('setTopicsPerRun')
            ->andReturnSelf();

        $this->agentMock
            ->shouldReceive('setDecisionsPerTopic')
            ->andReturnSelf();

        $this->agentMock
            ->shouldReceive('setIngestPerTopic')
            ->twice()
            ->andReturnSelf();

        $this->agentMock
            ->shouldReceive('setRelevanceThreshold')
            ->andReturnSelf();

        $this->agentMock
            ->shouldReceive('discover')
            ->andReturn([
                'topics_generated' => 5,
                'decisions_evaluated' => 250,
                'decisions_ingested' => 0,
                'errors' => [],
            ]);

        $this->artisan('decisions:discover', ['--dry-run' => true])
            ->expectsOutputToContain('Mode')
            ->expectsOutputToContain('DRY RUN')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_shows_zero_ingested_in_dry_run_summary()
    {
        $this->agentMock
            ->shouldReceive('setTopicsPerRun')
            ->andReturnSelf();

        $this->agentMock
            ->shouldReceive('setDecisionsPerTopic')
            ->andReturnSelf();

        $this->agentMock
            ->shouldReceive('setIngestPerTopic')
            ->twice()
            ->andReturnSelf();

        $this->agentMock
            ->shouldReceive('setRelevanceThreshold')
            ->andReturnSelf();

        $this->agentMock
            ->shouldReceive('discover')
            ->andReturn([
                'topics_generated' => 5,
                'decisions_evaluated' => 250,
                'decisions_ingested' => 0,
                'errors' => [],
            ]);

        $this->artisan('decisions:discover', ['--dry-run' => true])
            ->expectsOutputToContain('0 (dry run)')
            ->assertExitCode(0);
    }
}
