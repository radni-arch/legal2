<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\GraphViewer;
use App\Repositories\GraphMetricsRepository;
use App\Services\GraphDatabaseService;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Tests for GraphViewer Livewire component metrics functionality
 *
 * Tests dashboard enhancements including:
 * - Loading influential decisions from PageRank metrics
 * - Loading citation clusters from Louvain analysis
 * - Displaying network statistics
 * - Interacting with metrics panels
 * - Error handling for metrics loading
 */
class GraphViewerMetricsTest extends TestCase
{
    use UsesTestDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_loads_influential_decisions_on_mount()
    {
        // Mock GraphDatabaseService
        $graphServiceMock = Mockery::mock(GraphDatabaseService::class);
        $graphServiceMock->shouldReceive('run')->andReturn(collect([]));
        $this->app->instance(GraphDatabaseService::class, $graphServiceMock);

        // Create mock metrics repository
        $metricsRepoMock = Mockery::mock(GraphMetricsRepository::class);
        $metricsRepoMock->shouldReceive('getInfluentialDecisions')
            ->with(10)
            ->once()
            ->andReturn([
                [
                    'id' => 'decision-1',
                    'case_number' => 'Rev-123/2023',
                    'court' => 'Vrhovni sud',
                    'date' => '2023-01-15',
                    'rank' => 0.85,
                ],
                [
                    'id' => 'decision-2',
                    'case_number' => 'Gž-456/2023',
                    'court' => 'Županijski sud',
                    'date' => '2023-02-20',
                    'rank' => 0.72,
                ],
            ]);

        $metricsRepoMock->shouldReceive('getCitationClusters')
            ->with(5)
            ->once()
            ->andReturn([]);

        $metricsRepoMock->shouldReceive('getNetworkStats')
            ->once()
            ->andReturn(null);

        $this->app->instance(GraphMetricsRepository::class, $metricsRepoMock);

        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('error')->zeroOrMoreTimes();

        Livewire::test(GraphViewer::class)
            ->assertSet('metricsLoaded', true)
            ->assertSet('influentialDecisions', function ($decisions) {
                return count($decisions) === 2
                    && $decisions[0]['case_number'] === 'Rev-123/2023'
                    && $decisions[1]['rank'] === 0.72;
            });
    }

    /** @test */
    public function it_loads_citation_clusters_on_mount()
    {
        $graphServiceMock = Mockery::mock(GraphDatabaseService::class);
        $graphServiceMock->shouldReceive('run')->andReturn(collect([]));
        $this->app->instance(GraphDatabaseService::class, $graphServiceMock);

        $metricsRepoMock = Mockery::mock(GraphMetricsRepository::class);
        $metricsRepoMock->shouldReceive('getInfluentialDecisions')->andReturn([]);
        $metricsRepoMock->shouldReceive('getCitationClusters')
            ->with(5)
            ->once()
            ->andReturn([
                [
                    'community_id' => 1,
                    'modularity' => 0.42,
                    'members' => [
                        ['id' => 'dec-1', 'case_number' => 'Case-1', 'court' => 'Court A'],
                        ['id' => 'dec-2', 'case_number' => 'Case-2', 'court' => 'Court B'],
                    ],
                ],
                [
                    'community_id' => 2,
                    'modularity' => 0.38,
                    'members' => [
                        ['id' => 'dec-3', 'case_number' => 'Case-3', 'court' => 'Court C'],
                    ],
                ],
            ]);
        $metricsRepoMock->shouldReceive('getNetworkStats')->andReturn(null);

        $this->app->instance(GraphMetricsRepository::class, $metricsRepoMock);

        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('error')->zeroOrMoreTimes();

        Livewire::test(GraphViewer::class)
            ->assertSet('metricsLoaded', true)
            ->assertSet('citationClusters', function ($clusters) {
                return count($clusters) === 2
                    && $clusters[0]['community_id'] === 1
                    && count($clusters[0]['members']) === 2
                    && $clusters[1]['modularity'] === 0.38;
            });
    }

    /** @test */
    public function it_loads_network_stats_on_mount()
    {
        $graphServiceMock = Mockery::mock(GraphDatabaseService::class);
        $graphServiceMock->shouldReceive('run')->andReturn(collect([]));
        $this->app->instance(GraphDatabaseService::class, $graphServiceMock);

        $metricsRepoMock = Mockery::mock(GraphMetricsRepository::class);
        $metricsRepoMock->shouldReceive('getInfluentialDecisions')->andReturn([]);
        $metricsRepoMock->shouldReceive('getCitationClusters')->andReturn([]);
        $metricsRepoMock->shouldReceive('getNetworkStats')
            ->once()
            ->andReturn([
                'total_nodes' => 1000,
                'total_relationships' => 5000,
                'nodes_by_type' => [
                    'CourtDecisionDocument' => 500,
                    'LawDocument' => 300,
                ],
            ]);

        $this->app->instance(GraphMetricsRepository::class, $metricsRepoMock);

        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('error')->zeroOrMoreTimes();

        Livewire::test(GraphViewer::class)
            ->assertSet('metricsLoaded', true)
            ->assertSet('networkStats.total_nodes', 1000)
            ->assertSet('networkStats.total_relationships', 5000);
    }

    /** @test */
    public function it_handles_metrics_loading_errors_gracefully()
    {
        $graphServiceMock = Mockery::mock(GraphDatabaseService::class);
        $graphServiceMock->shouldReceive('run')->andReturn(collect([]));
        $this->app->instance(GraphDatabaseService::class, $graphServiceMock);

        $metricsRepoMock = Mockery::mock(GraphMetricsRepository::class);
        $metricsRepoMock->shouldReceive('getInfluentialDecisions')
            ->andThrow(new \Exception('Database connection failed'));

        $this->app->instance(GraphMetricsRepository::class, $metricsRepoMock);

        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'Failed to load graph metrics'
                    && isset($context['error']);
            });

        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('error')->zeroOrMoreTimes();

        Livewire::test(GraphViewer::class)
            ->assertSet('metricsLoaded', false);
    }

    /** @test */
    public function it_toggles_metrics_visibility()
    {
        $graphServiceMock = Mockery::mock(GraphDatabaseService::class);
        $graphServiceMock->shouldReceive('run')->andReturn(collect([]));
        $this->app->instance(GraphDatabaseService::class, $graphServiceMock);

        $metricsRepoMock = Mockery::mock(GraphMetricsRepository::class);
        $metricsRepoMock->shouldReceive('getInfluentialDecisions')->andReturn([]);
        $metricsRepoMock->shouldReceive('getCitationClusters')->andReturn([]);
        $metricsRepoMock->shouldReceive('getNetworkStats')->andReturn(null);

        $this->app->instance(GraphMetricsRepository::class, $metricsRepoMock);

        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('error')->zeroOrMoreTimes();

        Livewire::test(GraphViewer::class)
            ->assertSet('showMetrics', true)
            ->call('toggleMetrics')
            ->assertSet('showMetrics', false)
            ->call('toggleMetrics')
            ->assertSet('showMetrics', true);
    }

    /** @test */
    public function it_loads_decision_from_metrics()
    {
        $graphServiceMock = Mockery::mock(GraphDatabaseService::class);
        $graphServiceMock->shouldReceive('run')->andReturn(collect([]));

        // Mock graph data fetch
        $graphDataResult = collect([
            (object) [
                'get' => function ($key) {
                    if ($key === 'center') {
                        return (object) [
                            'getProperty' => fn ($prop) => $prop === 'id' ? 'decision-123' : 'Decision Title',
                            'getProperties' => fn () => ['id' => 'decision-123', 'title' => 'Decision Title'],
                            'getLabels' => fn () => ['CourtDecisionDocument'],
                        ];
                    }

                    return [];
                },
            ],
        ]);

        $graphServiceMock->shouldReceive('run')->andReturn($graphDataResult);

        $this->app->instance(GraphDatabaseService::class, $graphServiceMock);

        $metricsRepoMock = Mockery::mock(GraphMetricsRepository::class);
        $metricsRepoMock->shouldReceive('getInfluentialDecisions')->andReturn([]);
        $metricsRepoMock->shouldReceive('getCitationClusters')->andReturn([]);
        $metricsRepoMock->shouldReceive('getNetworkStats')->andReturn(null);

        $this->app->instance(GraphMetricsRepository::class, $metricsRepoMock);

        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('error')->zeroOrMoreTimes();

        Livewire::test(GraphViewer::class)
            ->call('loadDecisionFromMetrics', 'decision-123')
            ->assertSet('selectedNodeType', 'CourtDecisionDocument')
            ->assertSet('selectedNodeId', 'decision-123');
    }

    /** @test */
    public function it_views_cluster_and_loads_first_member()
    {
        $graphServiceMock = Mockery::mock(GraphDatabaseService::class);
        $graphServiceMock->shouldReceive('run')->andReturn(collect([]));

        // Mock graph data fetch for cluster member
        $graphDataResult = collect([
            (object) [
                'get' => function ($key) {
                    if ($key === 'center') {
                        return (object) [
                            'getProperty' => fn ($prop) => $prop === 'id' ? 'cluster-member-1' : 'Case Title',
                            'getProperties' => fn () => ['id' => 'cluster-member-1'],
                            'getLabels' => fn () => ['CourtDecisionDocument'],
                        ];
                    }

                    return [];
                },
            ],
        ]);

        $graphServiceMock->shouldReceive('run')->andReturn($graphDataResult);

        $this->app->instance(GraphDatabaseService::class, $graphServiceMock);

        $metricsRepoMock = Mockery::mock(GraphMetricsRepository::class);
        $metricsRepoMock->shouldReceive('getInfluentialDecisions')->andReturn([]);
        $metricsRepoMock->shouldReceive('getCitationClusters')->andReturn([]);
        $metricsRepoMock->shouldReceive('getNetworkStats')->andReturn(null);
        $metricsRepoMock->shouldReceive('getCluster')
            ->with(1)
            ->once()
            ->andReturn([
                'community_id' => 1,
                'members' => [
                    ['id' => 'cluster-member-1', 'case_number' => 'Case-1'],
                    ['id' => 'cluster-member-2', 'case_number' => 'Case-2'],
                ],
            ]);

        $this->app->instance(GraphMetricsRepository::class, $metricsRepoMock);

        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('error')->zeroOrMoreTimes();

        Livewire::test(GraphViewer::class)
            ->call('viewCluster', 1)
            ->assertSet('selectedNodeType', 'CourtDecisionDocument')
            ->assertSet('selectedNodeId', 'cluster-member-1');
    }

    /** @test */
    public function it_handles_cluster_not_found()
    {
        $graphServiceMock = Mockery::mock(GraphDatabaseService::class);
        $graphServiceMock->shouldReceive('run')->andReturn(collect([]));
        $this->app->instance(GraphDatabaseService::class, $graphServiceMock);

        $metricsRepoMock = Mockery::mock(GraphMetricsRepository::class);
        $metricsRepoMock->shouldReceive('getInfluentialDecisions')->andReturn([]);
        $metricsRepoMock->shouldReceive('getCitationClusters')->andReturn([]);
        $metricsRepoMock->shouldReceive('getNetworkStats')->andReturn(null);
        $metricsRepoMock->shouldReceive('getCluster')
            ->with(999)
            ->once()
            ->andReturn(null);

        $this->app->instance(GraphMetricsRepository::class, $metricsRepoMock);

        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('error')->zeroOrMoreTimes();

        Livewire::test(GraphViewer::class)
            ->call('viewCluster', 999)
            ->assertSet('error', 'Cluster not found');
    }

    /** @test */
    public function it_handles_cluster_view_exceptions()
    {
        $graphServiceMock = Mockery::mock(GraphDatabaseService::class);
        $graphServiceMock->shouldReceive('run')->andReturn(collect([]));
        $this->app->instance(GraphDatabaseService::class, $graphServiceMock);

        $metricsRepoMock = Mockery::mock(GraphMetricsRepository::class);
        $metricsRepoMock->shouldReceive('getInfluentialDecisions')->andReturn([]);
        $metricsRepoMock->shouldReceive('getCitationClusters')->andReturn([]);
        $metricsRepoMock->shouldReceive('getNetworkStats')->andReturn(null);
        $metricsRepoMock->shouldReceive('getCluster')
            ->with(1)
            ->once()
            ->andThrow(new \Exception('Database error'));

        $this->app->instance(GraphMetricsRepository::class, $metricsRepoMock);

        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'Failed to view cluster'
                    && $context['cluster_id'] === 1;
            });

        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('error')->zeroOrMoreTimes();

        Livewire::test(GraphViewer::class)
            ->call('viewCluster', 1)
            ->assertSet('error', 'Failed to load cluster: Database error');
    }

    /** @test */
    public function it_refreshes_metrics_with_statistics()
    {
        $graphServiceMock = Mockery::mock(GraphDatabaseService::class);
        $graphServiceMock->shouldReceive('run')->andReturn(collect([]));

        // Mock refresh statistics calls
        $nodeCountsResult = collect([
            (object) ['get' => fn ($key) => $key === 'label' ? 'CourtDecisionDocument' : 250],
        ]);
        $relCountResult = collect([
            (object) ['get' => fn ($key) => 800],
        ]);

        $graphServiceMock->shouldReceive('run')
            ->andReturn($nodeCountsResult, $relCountResult, collect([]));

        $this->app->instance(GraphDatabaseService::class, $graphServiceMock);

        $metricsRepoMock = Mockery::mock(GraphMetricsRepository::class);

        // Initial load
        $metricsRepoMock->shouldReceive('getInfluentialDecisions')->andReturn([]);
        $metricsRepoMock->shouldReceive('getCitationClusters')->andReturn([]);
        $metricsRepoMock->shouldReceive('getNetworkStats')->andReturn(null);

        // Refresh call
        $metricsRepoMock->shouldReceive('getInfluentialDecisions')
            ->once()
            ->andReturn([
                ['id' => 'new-decision', 'case_number' => 'New-Case', 'rank' => 0.95],
            ]);
        $metricsRepoMock->shouldReceive('getCitationClusters')->once()->andReturn([]);
        $metricsRepoMock->shouldReceive('getNetworkStats')->once()->andReturn(null);

        $this->app->instance(GraphMetricsRepository::class, $metricsRepoMock);

        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('error')->zeroOrMoreTimes();

        Livewire::test(GraphViewer::class)
            ->call('refreshStatistics')
            ->assertSet('influentialDecisions', function ($decisions) {
                return count($decisions) === 1
                    && $decisions[0]['case_number'] === 'New-Case';
            });
    }

    /** @test */
    public function it_initializes_with_metrics_properties()
    {
        $graphServiceMock = Mockery::mock(GraphDatabaseService::class);
        $graphServiceMock->shouldReceive('run')->andReturn(collect([]));
        $this->app->instance(GraphDatabaseService::class, $graphServiceMock);

        $metricsRepoMock = Mockery::mock(GraphMetricsRepository::class);
        $metricsRepoMock->shouldReceive('getInfluentialDecisions')->andReturn([]);
        $metricsRepoMock->shouldReceive('getCitationClusters')->andReturn([]);
        $metricsRepoMock->shouldReceive('getNetworkStats')->andReturn(null);

        $this->app->instance(GraphMetricsRepository::class, $metricsRepoMock);

        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('error')->zeroOrMoreTimes();

        Livewire::test(GraphViewer::class)
            ->assertSet('influentialDecisions', [])
            ->assertSet('citationClusters', [])
            ->assertSet('networkStats', null)
            ->assertSet('metricsLoaded', true)
            ->assertSet('showMetrics', true);
    }
}
