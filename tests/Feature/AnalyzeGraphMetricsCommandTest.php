<?php

namespace Tests\Feature;

use App\Models\GraphMetric;
use App\Services\GraphDatabaseService;
use App\Services\GraphQueryHelper;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class AnalyzeGraphMetricsCommandTest extends TestCase
{
    use UsesTestDatabase;

    protected $queryHelperMock;

    protected $graphDbMock;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock services
        $this->queryHelperMock = Mockery::mock(GraphQueryHelper::class);
        $this->graphDbMock = Mockery::mock(GraphDatabaseService::class);

        $this->app->instance(GraphQueryHelper::class, $this->queryHelperMock);
        $this->app->instance(GraphDatabaseService::class, $this->graphDbMock);

        // Enable Neo4j for tests
        Config::set('neo4j.sync.enabled', true);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_analyzes_pagerank_metrics()
    {
        $mockInfluential = [
            ['id' => '1', 'case_number' => 'Rev-123/2023', 'court' => 'Vrhovni sud', 'date' => '2023-01-15', 'rank' => 0.85],
            ['id' => '2', 'case_number' => 'Gž-456/2023', 'court' => 'Županijski sud', 'date' => '2023-02-20', 'rank' => 0.72],
        ];

        $this->queryHelperMock
            ->shouldReceive('findInfluentialDecisions')
            ->with(50)
            ->once()
            ->andReturn($mockInfluential);

        $this->graphDbMock
            ->shouldReceive('run')
            ->andReturn([['nodeCount' => 100, 'relCount' => 500]]);

        $this->artisan('graph:analyze-metrics --type=pagerank')
            ->expectsOutputToContain('PageRank analysis')
            ->assertExitCode(0);

        $this->assertDatabaseHas('graph_metrics', [
            'metric_type' => GraphMetric::TYPE_PAGERANK,
        ]);

        $metric = GraphMetric::where('metric_type', GraphMetric::TYPE_PAGERANK)->first();
        $this->assertNotNull($metric);
        $this->assertEquals(2, $metric->payload['count']);
        $this->assertEquals(2, $metric->node_count);
        $this->assertGreaterThan(0, $metric->execution_time);
    }

    /** @test */
    public function it_analyzes_cluster_metrics()
    {
        $mockClusters = [
            [
                'community_id' => 1,
                'members' => [
                    ['id' => '1', 'case_number' => 'Rev-123/2023', 'court' => 'Vrhovni sud'],
                    ['id' => '2', 'case_number' => 'Gž-456/2023', 'court' => 'Županijski sud'],
                ],
            ],
            [
                'community_id' => 2,
                'members' => [
                    ['id' => '3', 'case_number' => 'P-789/2023', 'court' => 'Općinski sud'],
                ],
            ],
        ];

        $this->queryHelperMock
            ->shouldReceive('detectCitationClusters')
            ->once()
            ->andReturn($mockClusters);

        $this->graphDbMock
            ->shouldReceive('run')
            ->andReturn([['nodeCount' => 100, 'relCount' => 500]]);

        $this->artisan('graph:analyze-metrics --type=clusters')
            ->expectsOutputToContain('Louvain clustering')
            ->assertExitCode(0);

        $metric = GraphMetric::where('metric_type', GraphMetric::TYPE_CLUSTERS)->first();
        $this->assertNotNull($metric);
        $this->assertEquals(2, $metric->payload['cluster_count']);
        $this->assertEquals(1.5, $metric->payload['avg_cluster_size']);
        $this->assertEquals(3, $metric->node_count);
    }

    /** @test */
    public function it_analyzes_network_stats()
    {
        $this->graphDbMock
            ->shouldReceive('run')
            ->times(3)
            ->andReturn(
                [['nodeCount' => 1000, 'relCount' => 5000]],
                [
                    ['nodeType' => 'CourtDecisionDocument', 'count' => 500],
                    ['nodeType' => 'LawDocument', 'count' => 300],
                ],
                [
                    ['relType' => 'CITES', 'count' => 3000],
                    ['relType' => 'REFERENCES', 'count' => 2000],
                ]
            );

        $this->artisan('graph:analyze-metrics --type=network_stats')
            ->expectsOutputToContain('network statistics')
            ->assertExitCode(0);

        $metric = GraphMetric::where('metric_type', GraphMetric::TYPE_NETWORK_STATS)->first();
        $this->assertNotNull($metric);
        $this->assertEquals(1000, $metric->payload['total_nodes']);
        $this->assertEquals(5000, $metric->payload['total_relationships']);
        $this->assertEquals(1000, $metric->node_count);
        $this->assertEquals(5000, $metric->relationship_count);
    }

    /** @test */
    public function it_analyzes_citation_metrics()
    {
        $mockCited = [
            ['id' => '1', 'case_number' => 'Rev-123/2023', 'court' => 'Vrhovni sud', 'date' => '2023-01-15', 'citation_count' => 150],
            ['id' => '2', 'case_number' => 'Gž-456/2023', 'court' => 'Županijski sud', 'date' => '2023-02-20', 'citation_count' => 120],
        ];

        $this->graphDbMock
            ->shouldReceive('run')
            ->once()
            ->andReturn($mockCited);

        $this->artisan('graph:analyze-metrics --type=citation_analysis')
            ->expectsOutputToContain('citation patterns')
            ->assertExitCode(0);

        $metric = GraphMetric::where('metric_type', GraphMetric::TYPE_CITATION_ANALYSIS)->first();
        $this->assertNotNull($metric);
        $this->assertEquals(2, $metric->payload['count']);
        $this->assertEquals(2, $metric->node_count);
        $this->assertEquals(270, $metric->relationship_count); // Sum of citation counts
    }

    /** @test */
    public function it_analyzes_all_metrics_when_no_type_specified()
    {
        // Mock all dependencies with non-empty results so metrics are created
        $this->queryHelperMock
            ->shouldReceive('findInfluentialDecisions')
            ->once()
            ->andReturn([
                ['id' => '1', 'case_number' => 'Rev-123/2023', 'court' => 'Vrhovni sud', 'rank' => 0.85],
            ]);

        $this->queryHelperMock
            ->shouldReceive('detectCitationClusters')
            ->once()
            ->andReturn([
                ['community_id' => 1, 'members' => [['id' => '1', 'case_number' => 'Rev-123/2023']]],
            ]);

        $this->graphDbMock
            ->shouldReceive('run')
            ->andReturn(
                [['nodeCount' => 100, 'relCount' => 500]],  // network stats total
                [['nodeType' => 'CourtDecisionDocument', 'count' => 100]],  // node types
                [['relType' => 'CITES', 'count' => 500]],  // rel types
                [['id' => '1', 'case_number' => 'Rev-123/2023', 'court' => 'Vrhovni sud', 'citation_count' => 50]]  // citation metrics
            );

        $this->artisan('graph:analyze-metrics')
            ->expectsOutputToContain('Analyzing all metric types')
            ->assertExitCode(0);

        $this->assertDatabaseCount('graph_metrics', 4);
    }

    /** @test */
    public function it_respects_limit_option()
    {
        $mockInfluential = array_fill(0, 100, [
            'id' => '1',
            'case_number' => 'Rev-123/2023',
            'court' => 'Vrhovni sud',
            'rank' => 0.85,
        ]);

        $this->queryHelperMock
            ->shouldReceive('findInfluentialDecisions')
            ->with(25)
            ->once()
            ->andReturn(array_slice($mockInfluential, 0, 25));

        $this->graphDbMock
            ->shouldReceive('run')
            ->andReturn([['nodeCount' => 100, 'relCount' => 500]]);

        $this->artisan('graph:analyze-metrics --type=pagerank --limit=25')
            ->assertExitCode(0);

        $metric = GraphMetric::where('metric_type', GraphMetric::TYPE_PAGERANK)->first();
        $this->assertEquals(25, $metric->payload['count']);
    }

    /** @test */
    public function it_fails_when_neo4j_is_disabled_without_force()
    {
        Config::set('neo4j.sync.enabled', false);

        $this->artisan('graph:analyze-metrics')
            ->expectsOutputToContain('Neo4j sync is disabled')
            ->assertExitCode(1);

        $this->assertDatabaseCount('graph_metrics', 0);
    }

    /** @test */
    public function it_runs_when_neo4j_disabled_with_force_flag()
    {
        Config::set('neo4j.sync.enabled', false);

        // Provide non-empty mock data so all metrics are created
        $this->queryHelperMock
            ->shouldReceive('findInfluentialDecisions')
            ->andReturn([
                ['id' => '1', 'case_number' => 'Rev-123/2023', 'court' => 'Vrhovni sud', 'rank' => 0.85],
            ]);

        $this->queryHelperMock
            ->shouldReceive('detectCitationClusters')
            ->andReturn([
                ['community_id' => 1, 'members' => [['id' => '1', 'case_number' => 'Rev-123/2023']]],
            ]);

        $this->graphDbMock
            ->shouldReceive('run')
            ->andReturn(
                [['nodeCount' => 100, 'relCount' => 500]],
                [['nodeType' => 'CourtDecisionDocument', 'count' => 100]],
                [['relType' => 'CITES', 'count' => 500]],
                [['id' => '1', 'case_number' => 'Rev-123/2023', 'court' => 'Vrhovni sud', 'citation_count' => 50]]
            );

        $this->artisan('graph:analyze-metrics --force')
            ->assertExitCode(0);

        $this->assertDatabaseCount('graph_metrics', 4);
    }

    /** @test */
    public function it_handles_empty_results_gracefully()
    {
        $this->queryHelperMock
            ->shouldReceive('findInfluentialDecisions')
            ->once()
            ->andReturn([]);

        $this->graphDbMock
            ->shouldReceive('run')
            ->andReturn([['nodeCount' => 0, 'relCount' => 0]]);

        $this->artisan('graph:analyze-metrics --type=pagerank')
            ->expectsOutputToContain('No influential decisions found')
            ->assertExitCode(0);

        // Should not create metric when no results
        $this->assertDatabaseMissing('graph_metrics', [
            'metric_type' => GraphMetric::TYPE_PAGERANK,
        ]);
    }

    /** @test */
    public function it_logs_execution_time()
    {
        $this->queryHelperMock
            ->shouldReceive('findInfluentialDecisions')
            ->once()
            ->andReturn([
                ['id' => '1', 'case_number' => 'Rev-123/2023', 'court' => 'Vrhovni sud', 'rank' => 0.85],
            ]);

        $this->graphDbMock
            ->shouldReceive('run')
            ->andReturn([['nodeCount' => 100, 'relCount' => 500]]);

        $this->artisan('graph:analyze-metrics --type=pagerank')
            ->assertExitCode(0);

        $metric = GraphMetric::where('metric_type', GraphMetric::TYPE_PAGERANK)->first();
        $this->assertGreaterThan(0, $metric->execution_time);
        $this->assertLessThan(10, $metric->execution_time); // Should complete in less than 10 seconds
    }

    /** @test */
    public function it_displays_verbose_output_when_requested()
    {
        $mockInfluential = [
            ['id' => '1', 'case_number' => 'Rev-123/2023', 'court' => 'Vrhovni sud', 'date' => '2023-01-15', 'rank' => 0.85],
        ];

        $this->queryHelperMock
            ->shouldReceive('findInfluentialDecisions')
            ->once()
            ->andReturn($mockInfluential);

        $this->graphDbMock
            ->shouldReceive('run')
            ->andReturn([['nodeCount' => 100, 'relCount' => 500]]);

        $this->artisan('graph:analyze-metrics --type=pagerank -v')
            ->expectsOutputToContain('Rev-123/2023')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_throws_exception_for_invalid_metric_type()
    {
        // Command catches exception and returns FAILURE
        $this->artisan('graph:analyze-metrics --type=invalid_type')
            ->expectsOutputToContain('Unknown metric type: invalid_type')
            ->assertExitCode(1);
    }
}
