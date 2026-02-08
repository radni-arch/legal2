<?php

namespace Tests\Unit\Repositories;

use App\Models\GraphMetric;
use App\Repositories\GraphMetricsRepository;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class GraphMetricsRepositoryTest extends TestCase
{
    use UsesTestDatabase;

    protected GraphMetricsRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new GraphMetricsRepository;
    }

    /** @test */
    public function it_returns_dashboard_data()
    {
        // Create sample metrics
        $this->createSampleMetrics();

        $dashboard = $this->repository->getDashboard();

        $this->assertIsArray($dashboard);
        $this->assertArrayHasKey('pagerank', $dashboard);
        $this->assertArrayHasKey('clusters', $dashboard);
        $this->assertArrayHasKey('network_stats', $dashboard);
        $this->assertArrayHasKey('citation_analysis', $dashboard);
        $this->assertArrayHasKey('last_updated', $dashboard);
    }

    /** @test */
    public function it_gets_influential_decisions()
    {
        GraphMetric::create([
            'metric_type' => GraphMetric::TYPE_PAGERANK,
            'analyzed_at' => now(),
            'payload' => [
                'top_decisions' => [
                    ['id' => '1', 'case_number' => 'Rev-123/2023', 'rank' => 0.85],
                    ['id' => '2', 'case_number' => 'Gž-456/2023', 'rank' => 0.72],
                ],
                'count' => 2,
            ],
            'node_count' => 2,
            'execution_time' => 1.5,
        ]);

        $decisions = $this->repository->getInfluentialDecisions();

        $this->assertCount(2, $decisions);
        $this->assertEquals('Rev-123/2023', $decisions[0]['case_number']);
    }

    /** @test */
    public function it_gets_citation_clusters()
    {
        GraphMetric::create([
            'metric_type' => GraphMetric::TYPE_CLUSTERS,
            'analyzed_at' => now(),
            'payload' => [
                'clusters' => [
                    ['community_id' => 1, 'members' => [['id' => '1'], ['id' => '2']]],
                    ['community_id' => 2, 'members' => [['id' => '3']]],
                ],
                'cluster_count' => 2,
            ],
            'node_count' => 3,
            'execution_time' => 2.0,
        ]);

        $clusters = $this->repository->getCitationClusters();

        $this->assertCount(2, $clusters);
        $this->assertEquals(1, $clusters[0]['community_id']);
    }

    /** @test */
    public function it_gets_specific_cluster_by_id()
    {
        GraphMetric::create([
            'metric_type' => GraphMetric::TYPE_CLUSTERS,
            'analyzed_at' => now(),
            'payload' => [
                'clusters' => [
                    ['community_id' => 1, 'members' => [['id' => '1'], ['id' => '2']]],
                    ['community_id' => 2, 'members' => [['id' => '3']]],
                ],
            ],
            'node_count' => 3,
            'execution_time' => 2.0,
        ]);

        $cluster = $this->repository->getCluster(2);

        $this->assertNotNull($cluster);
        $this->assertEquals(2, $cluster['community_id']);
        $this->assertCount(1, $cluster['members']);
    }

    /** @test */
    public function it_returns_null_for_nonexistent_cluster()
    {
        $cluster = $this->repository->getCluster(999);

        $this->assertNull($cluster);
    }

    /** @test */
    public function it_gets_network_stats()
    {
        GraphMetric::create([
            'metric_type' => GraphMetric::TYPE_NETWORK_STATS,
            'analyzed_at' => now(),
            'payload' => [
                'total_nodes' => 1000,
                'total_relationships' => 5000,
            ],
            'node_count' => 1000,
            'relationship_count' => 5000,
            'execution_time' => 0.5,
        ]);

        $stats = $this->repository->getNetworkStats();

        $this->assertIsArray($stats);
        $this->assertEquals(1000, $stats['total_nodes']);
        $this->assertEquals(5000, $stats['total_relationships']);
    }

    /** @test */
    public function it_gets_most_cited_decisions()
    {
        GraphMetric::create([
            'metric_type' => GraphMetric::TYPE_CITATION_ANALYSIS,
            'analyzed_at' => now(),
            'payload' => [
                'top_cited' => [
                    ['id' => '1', 'case_number' => 'Rev-123/2023', 'citation_count' => 150],
                    ['id' => '2', 'case_number' => 'Gž-456/2023', 'citation_count' => 120],
                ],
                'count' => 2,
            ],
            'node_count' => 2,
            'relationship_count' => 270,
            'execution_time' => 1.0,
        ]);

        $cited = $this->repository->getMostCited();

        $this->assertCount(2, $cited);
        $this->assertEquals(150, $cited[0]['citation_count']);
    }

    /** @test */
    public function it_respects_limit_parameter()
    {
        $decisions = array_fill(0, 30, ['id' => '1', 'case_number' => 'Rev-123/2023', 'rank' => 0.85]);

        GraphMetric::create([
            'metric_type' => GraphMetric::TYPE_PAGERANK,
            'analyzed_at' => now(),
            'payload' => [
                'top_decisions' => $decisions,
                'count' => 30,
            ],
            'node_count' => 30,
            'execution_time' => 1.5,
        ]);

        $results = $this->repository->getInfluentialDecisions(10);

        $this->assertCount(10, $results);
    }

    /** @test */
    public function it_gets_metrics_history()
    {
        // Create multiple metrics over time
        GraphMetric::create([
            'metric_type' => GraphMetric::TYPE_PAGERANK,
            'analyzed_at' => now()->subDays(10),
            'payload' => ['count' => 10],
            'node_count' => 10,
            'execution_time' => 1.0,
        ]);

        GraphMetric::create([
            'metric_type' => GraphMetric::TYPE_PAGERANK,
            'analyzed_at' => now()->subDays(5),
            'payload' => ['count' => 15],
            'node_count' => 15,
            'execution_time' => 1.2,
        ]);

        GraphMetric::create([
            'metric_type' => GraphMetric::TYPE_PAGERANK,
            'analyzed_at' => now(),
            'payload' => ['count' => 20],
            'node_count' => 20,
            'execution_time' => 1.5,
        ]);

        $history = $this->repository->getHistory(GraphMetric::TYPE_PAGERANK, 30);

        $this->assertCount(3, $history);
    }

    /** @test */
    public function it_calculates_trending_data()
    {
        GraphMetric::create([
            'metric_type' => GraphMetric::TYPE_PAGERANK,
            'analyzed_at' => now()->subWeek(),
            'payload' => ['count' => 10],
            'node_count' => 10,
            'execution_time' => 1.0,
        ]);

        GraphMetric::create([
            'metric_type' => GraphMetric::TYPE_PAGERANK,
            'analyzed_at' => now(),
            'payload' => ['count' => 15],
            'node_count' => 15,
            'execution_time' => 1.2,
        ]);

        $trending = $this->repository->getTrending(GraphMetric::TYPE_PAGERANK);

        $this->assertEquals('up', $trending['trend']);
        $this->assertEquals(5, $trending['change']);
        $this->assertEquals(15, $trending['current']);
        $this->assertEquals(10, $trending['previous']);
    }

    /** @test */
    public function it_handles_insufficient_data_for_trending()
    {
        GraphMetric::create([
            'metric_type' => GraphMetric::TYPE_PAGERANK,
            'analyzed_at' => now(),
            'payload' => ['count' => 10],
            'node_count' => 10,
            'execution_time' => 1.0,
        ]);

        $trending = $this->repository->getTrending(GraphMetric::TYPE_PAGERANK);

        $this->assertEquals('insufficient_data', $trending['trend']);
    }

    /** @test */
    public function it_gets_summary_of_all_metrics()
    {
        $this->createSampleMetrics();

        $summary = $this->repository->getSummary();

        $this->assertIsArray($summary);
        $this->assertArrayHasKey(GraphMetric::TYPE_PAGERANK, $summary);
        $this->assertArrayHasKey(GraphMetric::TYPE_CLUSTERS, $summary);
        $this->assertArrayHasKey(GraphMetric::TYPE_NETWORK_STATS, $summary);
        $this->assertArrayHasKey(GraphMetric::TYPE_CITATION_ANALYSIS, $summary);
    }

    /** @test */
    public function it_finds_decision_by_case_number()
    {
        GraphMetric::create([
            'metric_type' => GraphMetric::TYPE_PAGERANK,
            'analyzed_at' => now(),
            'payload' => [
                'top_decisions' => [
                    ['id' => '1', 'case_number' => 'Rev-123/2023', 'rank' => 0.85],
                    ['id' => '2', 'case_number' => 'Gž-456/2023', 'rank' => 0.72],
                ],
            ],
            'node_count' => 2,
            'execution_time' => 1.0,
        ]);

        $result = $this->repository->findDecision('Rev-123/2023');

        $this->assertNotNull($result);
        $this->assertEquals('pagerank', $result['type']);
        $this->assertEquals('Rev-123/2023', $result['decision']['case_number']);
    }

    /** @test */
    public function it_returns_null_for_nonexistent_decision()
    {
        $this->createSampleMetrics();

        $result = $this->repository->findDecision('NONEXISTENT-999/2099');

        $this->assertNull($result);
    }

    /** @test */
    public function it_returns_empty_arrays_when_no_metrics_exist()
    {
        $decisions = $this->repository->getInfluentialDecisions();
        $clusters = $this->repository->getCitationClusters();
        $cited = $this->repository->getMostCited();
        $stats = $this->repository->getNetworkStats();

        $this->assertEmpty($decisions);
        $this->assertEmpty($clusters);
        $this->assertEmpty($cited);
        $this->assertNull($stats);
    }

    protected function createSampleMetrics(): void
    {
        GraphMetric::create([
            'metric_type' => GraphMetric::TYPE_PAGERANK,
            'analyzed_at' => now(),
            'payload' => [
                'top_decisions' => [
                    ['id' => '1', 'case_number' => 'Rev-123/2023', 'rank' => 0.85],
                ],
                'count' => 1,
            ],
            'node_count' => 1,
            'execution_time' => 1.0,
        ]);

        GraphMetric::create([
            'metric_type' => GraphMetric::TYPE_CLUSTERS,
            'analyzed_at' => now(),
            'payload' => [
                'clusters' => [
                    ['community_id' => 1, 'members' => [['id' => '1']]],
                ],
                'cluster_count' => 1,
            ],
            'node_count' => 1,
            'execution_time' => 1.0,
        ]);

        GraphMetric::create([
            'metric_type' => GraphMetric::TYPE_NETWORK_STATS,
            'analyzed_at' => now(),
            'payload' => [
                'total_nodes' => 100,
                'total_relationships' => 500,
            ],
            'node_count' => 100,
            'relationship_count' => 500,
            'execution_time' => 0.5,
        ]);

        GraphMetric::create([
            'metric_type' => GraphMetric::TYPE_CITATION_ANALYSIS,
            'analyzed_at' => now(),
            'payload' => [
                'top_cited' => [
                    ['id' => '1', 'citation_count' => 50],
                ],
                'count' => 1,
            ],
            'node_count' => 1,
            'relationship_count' => 50,
            'execution_time' => 1.0,
        ]);
    }
}
