<?php

namespace Tests\Unit\Models;

use App\Models\GraphMetric;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class GraphMetricTest extends TestCase
{
    use UsesTestDatabase;

    /** @test */
    public function it_casts_payload_to_array()
    {
        $metric = GraphMetric::create([
            'metric_type' => GraphMetric::TYPE_PAGERANK,
            'analyzed_at' => now(),
            'payload' => ['test' => 'data'],
            'node_count' => 10,
            'execution_time' => 1.5,
        ]);

        $this->assertIsArray($metric->payload);
        $this->assertEquals('data', $metric->payload['test']);
    }

    /** @test */
    public function it_casts_analyzed_at_to_datetime()
    {
        $metric = GraphMetric::create([
            'metric_type' => GraphMetric::TYPE_PAGERANK,
            'analyzed_at' => '2023-01-15 10:30:00',
            'payload' => [],
            'node_count' => 10,
            'execution_time' => 1.5,
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $metric->analyzed_at);
    }

    /** @test */
    public function it_scopes_by_metric_type()
    {
        GraphMetric::create([
            'metric_type' => GraphMetric::TYPE_PAGERANK,
            'analyzed_at' => now(),
            'payload' => [],
            'node_count' => 10,
            'execution_time' => 1.0,
        ]);

        GraphMetric::create([
            'metric_type' => GraphMetric::TYPE_CLUSTERS,
            'analyzed_at' => now(),
            'payload' => [],
            'node_count' => 5,
            'execution_time' => 1.0,
        ]);

        $pageRankMetrics = GraphMetric::ofType(GraphMetric::TYPE_PAGERANK)->get();

        $this->assertCount(1, $pageRankMetrics);
        $this->assertEquals(GraphMetric::TYPE_PAGERANK, $pageRankMetrics->first()->metric_type);
    }

    /** @test */
    public function it_scopes_recent_metrics()
    {
        GraphMetric::create([
            'metric_type' => GraphMetric::TYPE_PAGERANK,
            'analyzed_at' => now()->subDays(5),
            'payload' => [],
            'node_count' => 10,
            'execution_time' => 1.0,
        ]);

        GraphMetric::create([
            'metric_type' => GraphMetric::TYPE_PAGERANK,
            'analyzed_at' => now()->subDays(40),
            'payload' => [],
            'node_count' => 10,
            'execution_time' => 1.0,
        ]);

        $recentMetrics = GraphMetric::recent(30)->get();

        $this->assertCount(1, $recentMetrics);
    }

    /** @test */
    public function it_gets_latest_metric_by_type()
    {
        GraphMetric::create([
            'metric_type' => GraphMetric::TYPE_PAGERANK,
            'analyzed_at' => now()->subDays(5),
            'payload' => ['old' => true],
            'node_count' => 10,
            'execution_time' => 1.0,
        ]);

        GraphMetric::create([
            'metric_type' => GraphMetric::TYPE_PAGERANK,
            'analyzed_at' => now(),
            'payload' => ['new' => true],
            'node_count' => 15,
            'execution_time' => 1.0,
        ]);

        $latest = GraphMetric::getLatest(GraphMetric::TYPE_PAGERANK);

        $this->assertNotNull($latest);
        $this->assertTrue($latest->payload['new']);
    }

    /** @test */
    public function it_gets_latest_of_each_type()
    {
        GraphMetric::create([
            'metric_type' => GraphMetric::TYPE_PAGERANK,
            'analyzed_at' => now(),
            'payload' => [],
            'node_count' => 10,
            'execution_time' => 1.0,
        ]);

        GraphMetric::create([
            'metric_type' => GraphMetric::TYPE_CLUSTERS,
            'analyzed_at' => now(),
            'payload' => [],
            'node_count' => 5,
            'execution_time' => 1.0,
        ]);

        GraphMetric::create([
            'metric_type' => GraphMetric::TYPE_PAGERANK,
            'analyzed_at' => now()->subDay(),
            'payload' => [],
            'node_count' => 8,
            'execution_time' => 1.0,
        ]);

        $latest = GraphMetric::getLatestAll();

        $this->assertCount(2, $latest);
        $this->assertArrayHasKey(GraphMetric::TYPE_PAGERANK, $latest);
        $this->assertArrayHasKey(GraphMetric::TYPE_CLUSTERS, $latest);
    }

    /** @test */
    public function it_gets_metric_history()
    {
        GraphMetric::create([
            'metric_type' => GraphMetric::TYPE_PAGERANK,
            'analyzed_at' => now()->subDays(10),
            'payload' => [],
            'node_count' => 10,
            'execution_time' => 1.0,
        ]);

        GraphMetric::create([
            'metric_type' => GraphMetric::TYPE_PAGERANK,
            'analyzed_at' => now()->subDays(5),
            'payload' => [],
            'node_count' => 15,
            'execution_time' => 1.0,
        ]);

        GraphMetric::create([
            'metric_type' => GraphMetric::TYPE_CLUSTERS,
            'analyzed_at' => now(),
            'payload' => [],
            'node_count' => 5,
            'execution_time' => 1.0,
        ]);

        $history = GraphMetric::getHistory(GraphMetric::TYPE_PAGERANK, 30);

        $this->assertCount(2, $history);
    }

    /** @test */
    public function it_calculates_summary_statistics()
    {
        GraphMetric::create([
            'metric_type' => GraphMetric::TYPE_PAGERANK,
            'analyzed_at' => now(),
            'payload' => [],
            'node_count' => 10,
            'relationship_count' => 50,
            'execution_time' => 1.0,
        ]);

        GraphMetric::create([
            'metric_type' => GraphMetric::TYPE_PAGERANK,
            'analyzed_at' => now()->subDay(),
            'payload' => [],
            'node_count' => 15,
            'relationship_count' => 75,
            'execution_time' => 1.5,
        ]);

        $summary = GraphMetric::getSummary(30);

        $this->assertArrayHasKey(GraphMetric::TYPE_PAGERANK, $summary);
        $this->assertEquals(2, $summary[GraphMetric::TYPE_PAGERANK]['count']);
        $this->assertEquals(1.25, $summary[GraphMetric::TYPE_PAGERANK]['avg_execution_time']);
        $this->assertEquals(12.5, $summary[GraphMetric::TYPE_PAGERANK]['avg_node_count']);
    }

    /** @test */
    public function it_has_metric_type_constants()
    {
        $this->assertEquals('pagerank', GraphMetric::TYPE_PAGERANK);
        $this->assertEquals('clusters', GraphMetric::TYPE_CLUSTERS);
        $this->assertEquals('network_stats', GraphMetric::TYPE_NETWORK_STATS);
        $this->assertEquals('citation_analysis', GraphMetric::TYPE_CITATION_ANALYSIS);
    }

    /** @test */
    public function it_stores_json_payload_correctly()
    {
        $complexPayload = [
            'top_decisions' => [
                ['id' => '1', 'case_number' => 'Rev-123/2023', 'rank' => 0.85],
                ['id' => '2', 'case_number' => 'Gž-456/2023', 'rank' => 0.72],
            ],
            'count' => 2,
            'metadata' => [
                'algorithm' => 'PageRank',
                'version' => '1.0',
            ],
        ];

        $metric = GraphMetric::create([
            'metric_type' => GraphMetric::TYPE_PAGERANK,
            'analyzed_at' => now(),
            'payload' => $complexPayload,
            'node_count' => 2,
            'execution_time' => 1.5,
        ]);

        $retrieved = GraphMetric::find($metric->id);

        $this->assertEquals($complexPayload, $retrieved->payload);
        $this->assertEquals(2, $retrieved->payload['count']);
        $this->assertEquals('PageRank', $retrieved->payload['metadata']['algorithm']);
    }

    /** @test */
    public function it_allows_nullable_notes()
    {
        $metric = GraphMetric::create([
            'metric_type' => GraphMetric::TYPE_PAGERANK,
            'analyzed_at' => now(),
            'payload' => [],
            'node_count' => 10,
            'execution_time' => 1.0,
        ]);

        $this->assertNull($metric->notes);

        $metric->update(['notes' => 'Test note']);
        $this->assertEquals('Test note', $metric->fresh()->notes);
    }
}
