<?php

namespace Tests\Feature\Console;

use App\Services\GraphDatabaseService;
use App\Services\GraphRagService;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Test suite for GraphQueryCommand
 *
 * Tests natural language querying of Neo4j graph database
 * with different content types and result formatting.
 */
class GraphQueryCommandTest extends TestCase
{
    use UsesTestDatabase;

    protected $graphRagMock;

    protected $graphMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->graphRagMock = Mockery::mock(GraphRagService::class);
        $this->graphMock = Mockery::mock(GraphDatabaseService::class);

        $this->app->instance(GraphRagService::class, $this->graphRagMock);
        $this->app->instance(GraphDatabaseService::class, $this->graphMock);
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

        $this->artisan('graph:query', ['query' => 'test query'])
            ->expectsOutput('Neo4j integration is disabled.')
            ->assertExitCode(1);
    }

    /** @test */
    public function it_queries_graph_database_with_natural_language()
    {
        Config::set('neo4j.sync.enabled', true);

        $this->graphRagMock->shouldReceive('enhancedQuery')
            ->once()
            ->with('home search warrants', 'both', 10)
            ->andReturn([
                'related_via_keywords' => [
                    [
                        'node' => ['id' => 'law-1', 'title' => 'ZKP Čl. 215'],
                        'weight' => 0.95,
                        'keyword' => 'home search',
                    ],
                ],
                'similar_documents' => [],
            ]);

        $this->artisan('graph:query', ['query' => 'home search warrants'])
            ->expectsOutput("Querying graph database: 'home search warrants'")
            ->expectsOutput('Related via Keywords:')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_displays_keyword_related_results()
    {
        Config::set('neo4j.sync.enabled', true);

        $this->graphRagMock->shouldReceive('enhancedQuery')
            ->once()
            ->andReturn([
                'related_via_keywords' => [
                    [
                        'node' => ['id' => 'law-1', 'title' => 'ZKP Čl. 215 - Pretres doma'],
                        'weight' => 0.95,
                        'keyword' => 'pretres',
                    ],
                    [
                        'node' => ['id' => 'law-2', 'title' => 'ZKP Čl. 217'],
                        'weight' => 0.85,
                        'keyword' => 'pretres',
                    ],
                ],
                'similar_documents' => [],
            ]);

        $this->artisan('graph:query', ['query' => 'pretres doma'])
            ->expectsOutputToContain('ZKP Čl. 215 - Pretres doma')
            ->expectsOutputToContain('weight: 0.95')
            ->expectsOutputToContain('keyword: pretres')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_warns_when_no_results_found()
    {
        Config::set('neo4j.sync.enabled', true);

        $this->graphRagMock->shouldReceive('enhancedQuery')
            ->once()
            ->andReturn([
                'related_via_keywords' => [],
                'similar_documents' => [],
            ]);

        $this->artisan('graph:query', ['query' => 'nonexistent query'])
            ->expectsOutput('No results found.')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_supports_type_option_for_laws_only()
    {
        Config::set('neo4j.sync.enabled', true);

        $this->graphRagMock->shouldReceive('enhancedQuery')
            ->once()
            ->with('criminal law', 'law', 10)
            ->andReturn(['related_via_keywords' => [], 'similar_documents' => []]);

        $this->artisan('graph:query', [
            'query' => 'criminal law',
            '--type' => 'law',
        ])->assertExitCode(0);
    }

    /** @test */
    public function it_supports_type_option_for_cases_only()
    {
        Config::set('neo4j.sync.enabled', true);

        $this->graphRagMock->shouldReceive('enhancedQuery')
            ->once()
            ->with('murder cases', 'case', 10)
            ->andReturn(['related_via_keywords' => [], 'similar_documents' => []]);

        $this->artisan('graph:query', [
            'query' => 'murder cases',
            '--type' => 'case',
        ])->assertExitCode(0);
    }

    /** @test */
    public function it_supports_limit_option()
    {
        Config::set('neo4j.sync.enabled', true);

        $this->graphRagMock->shouldReceive('enhancedQuery')
            ->once()
            ->with('test query', 'both', 5)
            ->andReturn(['related_via_keywords' => [], 'similar_documents' => []]);

        $this->artisan('graph:query', [
            'query' => 'test query',
            '--limit' => '5',
        ])->assertExitCode(0);
    }

    /** @test */
    public function it_defaults_to_both_type_and_limit_10()
    {
        Config::set('neo4j.sync.enabled', true);

        $this->graphRagMock->shouldReceive('enhancedQuery')
            ->once()
            ->with('default query', 'both', 10)
            ->andReturn(['related_via_keywords' => [], 'similar_documents' => []]);

        $this->artisan('graph:query', ['query' => 'default query'])
            ->assertExitCode(0);
    }

    /** @test */
    public function it_handles_query_failure()
    {
        Config::set('neo4j.sync.enabled', true);

        $this->graphRagMock->shouldReceive('enhancedQuery')
            ->once()
            ->andThrow(new \Exception('Connection timeout'));

        $this->artisan('graph:query', ['query' => 'test'])
            ->expectsOutput('Query failed: Connection timeout')
            ->assertExitCode(1);
    }

    /** @test */
    public function it_displays_multiple_keyword_results()
    {
        Config::set('neo4j.sync.enabled', true);

        $this->graphRagMock->shouldReceive('enhancedQuery')
            ->once()
            ->andReturn([
                'related_via_keywords' => [
                    [
                        'node' => ['id' => 'law-1', 'title' => 'Law 1'],
                        'weight' => 0.95,
                        'keyword' => 'keyword1',
                    ],
                    [
                        'node' => ['id' => 'law-2', 'title' => 'Law 2'],
                        'weight' => 0.85,
                        'keyword' => 'keyword2',
                    ],
                    [
                        'node' => ['id' => 'law-3', 'title' => 'Law 3'],
                        'weight' => 0.75,
                        'keyword' => 'keyword3',
                    ],
                ],
                'similar_documents' => [],
            ]);

        $this->artisan('graph:query', ['query' => 'test'])
            ->expectsOutputToContain('Law 1')
            ->expectsOutputToContain('Law 2')
            ->expectsOutputToContain('Law 3')
            ->expectsOutputToContain('0.95')
            ->expectsOutputToContain('0.85')
            ->expectsOutputToContain('0.75')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_handles_node_without_title()
    {
        Config::set('neo4j.sync.enabled', true);

        $this->graphRagMock->shouldReceive('enhancedQuery')
            ->once()
            ->andReturn([
                'related_via_keywords' => [
                    [
                        'node' => ['id' => 'law-123'], // No title
                        'weight' => 0.90,
                        'keyword' => 'test',
                    ],
                ],
                'similar_documents' => [],
            ]);

        $this->artisan('graph:query', ['query' => 'test'])
            ->expectsOutputToContain('law-123') // Should use ID when title missing
            ->assertExitCode(0);
    }

    /** @test */
    public function it_formats_weight_with_two_decimal_places()
    {
        Config::set('neo4j.sync.enabled', true);

        $this->graphRagMock->shouldReceive('enhancedQuery')
            ->once()
            ->andReturn([
                'related_via_keywords' => [
                    [
                        'node' => ['id' => 'law-1', 'title' => 'Test Law'],
                        'weight' => 0.123456789,
                        'keyword' => 'test',
                    ],
                ],
                'similar_documents' => [],
            ]);

        $this->artisan('graph:query', ['query' => 'test'])
            ->expectsOutputToContain('weight: 0.12') // Should format to 2 decimals
            ->assertExitCode(0);
    }

    /** @test */
    public function it_requires_query_argument()
    {
        Config::set('neo4j.sync.enabled', true);

        $this->artisan('graph:query')
            ->assertExitCode(1); // Laravel will fail validation
    }
}
