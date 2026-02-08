<?php

namespace Tests\Feature\Console;

use App\Services\GraphDatabaseService;
use App\Services\TaggingService;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Test suite for GraphInitCommand
 *
 * Tests initialization of Neo4j graph database schema,
 * constraints, indexes, and tag hierarchy.
 */
class GraphInitCommandTest extends TestCase
{
    use UsesTestDatabase;

    protected $graphMock;

    protected $taggingMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->graphMock = Mockery::mock(GraphDatabaseService::class);
        $this->taggingMock = Mockery::mock(TaggingService::class);

        $this->app->instance(GraphDatabaseService::class, $this->graphMock);
        $this->app->instance(TaggingService::class, $this->taggingMock);
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

        $this->artisan('graph:init')
            ->expectsOutput('Neo4j integration is disabled. Enable it in config/neo4j.php')
            ->assertExitCode(1);
    }

    /** @test */
    public function it_initializes_graph_database_successfully()
    {
        Config::set('neo4j.sync.enabled', true);

        $this->graphMock->shouldReceive('initializeSchema')
            ->once()
            ->andReturn(true);

        $this->taggingMock->shouldReceive('initializeTagHierarchy')
            ->once()
            ->andReturn(true);

        $this->artisan('graph:init')
            ->expectsOutput('Initializing Neo4j graph database...')
            ->expectsOutput('Creating constraints and indexes...')
            ->expectsOutput('✓ Schema initialized')
            ->expectsOutput('Creating tag hierarchy...')
            ->expectsOutput('✓ Tag hierarchy created')
            ->expectsOutput('Graph database initialized successfully!')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_displays_next_steps_after_initialization()
    {
        Config::set('neo4j.sync.enabled', true);

        $this->graphMock->shouldReceive('initializeSchema')->once();
        $this->taggingMock->shouldReceive('initializeTagHierarchy')->once();

        $this->artisan('graph:init')
            ->expectsOutput('Next steps:')
            ->expectsOutput('1. Run: php artisan graph:sync --all')
            ->expectsOutput('2. Or sync specific types: php artisan graph:sync --laws')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_handles_schema_initialization_failure()
    {
        Config::set('neo4j.sync.enabled', true);

        $this->graphMock->shouldReceive('initializeSchema')
            ->once()
            ->andThrow(new \Exception('Database connection failed'));

        $this->artisan('graph:init')
            ->expectsOutputToContain('Failed to initialize graph database: Database connection failed')
            ->assertExitCode(1);
    }

    /** @test */
    public function it_handles_tag_hierarchy_initialization_failure()
    {
        Config::set('neo4j.sync.enabled', true);

        $this->graphMock->shouldReceive('initializeSchema')
            ->once()
            ->andReturn(true);

        $this->taggingMock->shouldReceive('initializeTagHierarchy')
            ->once()
            ->andThrow(new \Exception('Tag hierarchy creation failed'));

        $this->artisan('graph:init')
            ->expectsOutputToContain('Failed to initialize graph database: Tag hierarchy creation failed')
            ->assertExitCode(1);
    }

    /** @test */
    public function it_supports_force_option()
    {
        Config::set('neo4j.sync.enabled', true);

        $this->graphMock->shouldReceive('initializeSchema')->once();
        $this->taggingMock->shouldReceive('initializeTagHierarchy')->once();

        $this->artisan('graph:init', ['--force' => true])
            ->assertExitCode(0);
    }

    /** @test */
    public function it_calls_initialize_schema_before_tag_hierarchy()
    {
        Config::set('neo4j.sync.enabled', true);

        $callOrder = [];

        $this->graphMock->shouldReceive('initializeSchema')
            ->once()
            ->andReturnUsing(function () use (&$callOrder) {
                $callOrder[] = 'schema';

                return true;
            });

        $this->taggingMock->shouldReceive('initializeTagHierarchy')
            ->once()
            ->andReturnUsing(function () use (&$callOrder) {
                $callOrder[] = 'tags';

                return true;
            });

        $this->artisan('graph:init')->assertExitCode(0);

        $this->assertEquals(['schema', 'tags'], $callOrder);
    }

    /** @test */
    public function it_displays_stack_trace_on_error()
    {
        Config::set('neo4j.sync.enabled', true);

        $exception = new \Exception('Test error');

        $this->graphMock->shouldReceive('initializeSchema')
            ->once()
            ->andThrow($exception);

        $this->artisan('graph:init')
            ->expectsOutputToContain('Stack trace:')
            ->assertExitCode(1);
    }
}
