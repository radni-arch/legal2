<?php

namespace Tests\Feature;

use App\Agents\DecisionDiscoveryAgent;
use App\Jobs\RunDecisionDiscovery;
use App\Models\DecisionDiscoveryRun;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class DecisionDiscoveryCommandTest extends TestCase
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
    public function it_runs_discovery_successfully()
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
            ->shouldReceive('getTopicsPerRun')
            ->andReturn(5);

        $this->agentMock
            ->shouldReceive('getDecisionsPerTopic')
            ->andReturn(50);

        $this->agentMock
            ->shouldReceive('getIngestPerTopic')
            ->andReturn(10);

        $this->agentMock
            ->shouldReceive('getRelevanceThreshold')
            ->andReturn(70.0);

        $this->agentMock
            ->shouldReceive('discover')
            ->once()
            ->andReturn([
                'topics_generated' => 5,
                'decisions_evaluated' => 250,
                'decisions_ingested' => 50,
                'errors' => [],
            ]);

        $this->artisan('decisions:discovery')
            ->expectsOutputToContain('Starting Decision Discovery')
            ->expectsOutputToContain('Discovery completed successfully')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_accepts_custom_configuration()
    {
        $this->agentMock
            ->shouldReceive('setTopicsPerRun')
            ->with(10)
            ->once()
            ->andReturnSelf();

        $this->agentMock
            ->shouldReceive('setDecisionsPerTopic')
            ->with(100)
            ->once()
            ->andReturnSelf();

        $this->agentMock
            ->shouldReceive('setIngestPerTopic')
            ->with(20)
            ->once()
            ->andReturnSelf();

        $this->agentMock
            ->shouldReceive('setRelevanceThreshold')
            ->with(80.0)
            ->once()
            ->andReturnSelf();

        $this->agentMock
            ->shouldReceive('getTopicsPerRun')
            ->andReturn(10);

        $this->agentMock
            ->shouldReceive('getDecisionsPerTopic')
            ->andReturn(100);

        $this->agentMock
            ->shouldReceive('getIngestPerTopic')
            ->andReturn(20);

        $this->agentMock
            ->shouldReceive('getRelevanceThreshold')
            ->andReturn(80.0);

        $this->agentMock
            ->shouldReceive('discover')
            ->andReturn([
                'topics_generated' => 10,
                'decisions_evaluated' => 1000,
                'decisions_ingested' => 200,
                'errors' => [],
            ]);

        $this->artisan('decisions:discovery', [
            '--topics' => 10,
            '--per-topic' => 100,
            '--ingest' => 20,
            '--threshold' => 80,
        ])
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
            ->shouldReceive('getTopicsPerRun')
            ->andReturn(5);

        $this->agentMock
            ->shouldReceive('getDecisionsPerTopic')
            ->andReturn(50);

        $this->agentMock
            ->shouldReceive('getIngestPerTopic')
            ->andReturn(10);

        $this->agentMock
            ->shouldReceive('getRelevanceThreshold')
            ->andReturn(70.0);

        $this->agentMock
            ->shouldReceive('discover')
            ->andReturn([
                'topics_generated' => 5,
                'decisions_evaluated' => 250,
                'decisions_ingested' => 50,
                'errors' => [],
            ]);

        $this->artisan('decisions:discovery')
            ->expectsOutputToContain('Configuration:')
            ->expectsOutputToContain('Topics per run')
            ->expectsOutputToContain('Decisions per topic')
            ->expectsOutputToContain('Ingest per topic')
            ->expectsOutputToContain('Relevance threshold')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_displays_statistics_table()
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
            ->shouldReceive('getTopicsPerRun')
            ->andReturn(5);

        $this->agentMock
            ->shouldReceive('getDecisionsPerTopic')
            ->andReturn(50);

        $this->agentMock
            ->shouldReceive('getIngestPerTopic')
            ->andReturn(10);

        $this->agentMock
            ->shouldReceive('getRelevanceThreshold')
            ->andReturn(70.0);

        $this->agentMock
            ->shouldReceive('discover')
            ->andReturn([
                'topics_generated' => 5,
                'decisions_evaluated' => 250,
                'decisions_ingested' => 50,
                'errors' => [],
            ]);

        $this->artisan('decisions:discovery')
            ->expectsOutputToContain('Topics Generated')
            ->expectsOutputToContain('Decisions Evaluated')
            ->expectsOutputToContain('Decisions Ingested')
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
            ->shouldReceive('getTopicsPerRun')
            ->andReturn(5);

        $this->agentMock
            ->shouldReceive('getDecisionsPerTopic')
            ->andReturn(50);

        $this->agentMock
            ->shouldReceive('getIngestPerTopic')
            ->andReturn(10);

        $this->agentMock
            ->shouldReceive('getRelevanceThreshold')
            ->andReturn(70.0);

        $this->agentMock
            ->shouldReceive('discover')
            ->andReturn([
                'topics_generated' => 5,
                'decisions_evaluated' => 250,
                'decisions_ingested' => 45,
                'errors' => [
                    ['topic' => 'Radno pravo', 'error' => 'Network timeout'],
                    ['topic' => 'Ugovorno pravo', 'error' => 'Invalid response'],
                ],
            ]);

        $this->artisan('decisions:discovery')
            ->expectsOutputToContain('Errors encountered')
            ->expectsOutputToContain('Radno pravo')
            ->expectsOutputToContain('Network timeout')
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
            ->shouldReceive('getTopicsPerRun')
            ->andReturn(5);

        $this->agentMock
            ->shouldReceive('getDecisionsPerTopic')
            ->andReturn(50);

        $this->agentMock
            ->shouldReceive('getIngestPerTopic')
            ->andReturn(10);

        $this->agentMock
            ->shouldReceive('getRelevanceThreshold')
            ->andReturn(70.0);

        $this->agentMock
            ->shouldReceive('discover')
            ->once()
            ->andThrow(new \Exception('Agent failure'));

        $this->artisan('decisions:discovery')
            ->expectsOutputToContain('Discovery failed')
            ->expectsOutputToContain('Agent failure')
            ->assertExitCode(1);
    }

    /** @test */
    public function it_dispatches_to_queue_when_queue_option_is_set()
    {
        Queue::fake();

        $this->artisan('decisions:discovery', ['--queue' => true])
            ->expectsOutputToContain('Dispatching to queue')
            ->expectsOutputToContain('Job dispatched to queue successfully')
            ->assertExitCode(0);

        Queue::assertPushed(RunDecisionDiscovery::class, function ($job) {
            return $job->topics === 5
                && $job->perTopic === 50
                && $job->ingest === 10
                && $job->threshold === 70.0;
        });
    }

    /** @test */
    public function it_dispatches_to_queue_with_custom_configuration()
    {
        Queue::fake();

        $this->artisan('decisions:discovery', [
            '--queue' => true,
            '--topics' => 10,
            '--per-topic' => 100,
            '--ingest' => 20,
            '--threshold' => 80,
        ])
            ->expectsOutputToContain('Dispatching to queue')
            ->assertExitCode(0);

        Queue::assertPushed(RunDecisionDiscovery::class, function ($job) {
            return $job->topics === 10
                && $job->perTopic === 100
                && $job->ingest === 20
                && $job->threshold === 80.0;
        });
    }

    /** @test */
    public function it_creates_decision_discovery_run_record()
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
            ->shouldReceive('getTopicsPerRun')
            ->andReturn(5);

        $this->agentMock
            ->shouldReceive('getDecisionsPerTopic')
            ->andReturn(50);

        $this->agentMock
            ->shouldReceive('getIngestPerTopic')
            ->andReturn(10);

        $this->agentMock
            ->shouldReceive('getRelevanceThreshold')
            ->andReturn(70.0);

        $this->agentMock
            ->shouldReceive('discover')
            ->andReturn([
                'topics_generated' => 5,
                'decisions_evaluated' => 250,
                'decisions_ingested' => 50,
                'errors' => [],
            ]);

        $this->assertDatabaseCount('decision_discovery_runs', 0);

        $this->artisan('decisions:discovery')
            ->assertExitCode(0);

        // The agent creates the run record
        $this->assertDatabaseCount('decision_discovery_runs', 1);

        $run = DecisionDiscoveryRun::first();
        $this->assertEquals('completed', $run->status);
        $this->assertEquals(5, $run->topics_generated);
        $this->assertEquals(250, $run->decisions_evaluated);
        $this->assertEquals(50, $run->decisions_ingested);
    }

    /** @test */
    public function it_displays_latest_run_summary()
    {
        // Create a previous run
        DecisionDiscoveryRun::create([
            'started_at' => now()->subHour(),
            'completed_at' => now()->subMinutes(30),
            'status' => 'completed',
            'topics_generated' => 5,
            'decisions_evaluated' => 200,
            'decisions_ingested' => 40,
            'topics' => ['Radno pravo', 'Ugovorno pravo'],
            'errors' => [],
        ]);

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
            ->shouldReceive('getTopicsPerRun')
            ->andReturn(5);

        $this->agentMock
            ->shouldReceive('getDecisionsPerTopic')
            ->andReturn(50);

        $this->agentMock
            ->shouldReceive('getIngestPerTopic')
            ->andReturn(10);

        $this->agentMock
            ->shouldReceive('getRelevanceThreshold')
            ->andReturn(70.0);

        $this->agentMock
            ->shouldReceive('discover')
            ->andReturn([
                'topics_generated' => 5,
                'decisions_evaluated' => 250,
                'decisions_ingested' => 50,
                'errors' => [],
            ]);

        $this->artisan('decisions:discovery')
            ->expectsOutputToContain('Latest Run Summary')
            ->expectsOutputToContain('Status:')
            ->assertExitCode(0);
    }
}
