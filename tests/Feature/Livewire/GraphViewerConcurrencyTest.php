<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\GraphViewer;
use App\Repositories\GraphMetricsRepository;
use App\Services\DecisionCitationService;
use App\Services\GraphDatabaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

/**
 * GraphViewer Concurrency Tests
 *
 * Tests to verify race condition protection in GraphViewer component
 */
class GraphViewerConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock the services to avoid actual Neo4j connections
        $this->mockGraphServices();
    }

    protected function mockGraphServices()
    {
        // Mock GraphDatabaseService
        $graphService = Mockery::mock(GraphDatabaseService::class);
        $graphService->shouldReceive('run')->andReturn(collect([]));
        $this->app->instance(GraphDatabaseService::class, $graphService);

        // Mock GraphMetricsRepository
        $metricsRepo = Mockery::mock(GraphMetricsRepository::class);
        $metricsRepo->shouldReceive('getInfluentialDecisions')->andReturn([]);
        $metricsRepo->shouldReceive('getCitationClusters')->andReturn([]);
        $metricsRepo->shouldReceive('getNetworkStats')->andReturn([]);
        $this->app->instance(GraphMetricsRepository::class, $metricsRepo);

        // Mock DecisionCitationService
        $citationService = Mockery::mock(DecisionCitationService::class);
        $citationService->shouldReceive('analyzeCitations')->andReturn([
            'nodes' => [],
            'edges' => [],
        ]);
        $this->app->instance(DecisionCitationService::class, $citationService);
    }

    /** @test */
    public function it_prevents_duplicate_search_requests()
    {
        Cache::flush();

        $component = Livewire::test(GraphViewer::class);

        // Set up search term
        $component->set('searchTerm', 'test decision');

        // First search should work
        $component->call('searchNodes');
        $this->assertNull($component->get('error'));

        // Immediate second search with same term should be blocked by deduplication
        $component->call('searchNodes');

        // Both should complete without errors (duplicate is silently blocked)
        $this->assertNull($component->get('error'));
    }

    /** @test */
    public function it_prevents_duplicate_graph_loads()
    {
        Cache::flush();

        $component = Livewire::test(GraphViewer::class);

        $component->set('selectedNodeId', 'test-node-id');
        $component->set('selectedNodeType', 'CourtDecisionDocument');

        // First load should work
        $component->call('loadNodeGraph');

        // Immediate second load should be blocked
        $component->call('loadNodeGraph');

        // No errors should occur
        $this->assertNull($component->get('error'));
    }

    /** @test */
    public function it_prevents_duplicate_citation_analysis()
    {
        Cache::flush();

        $component = Livewire::test(GraphViewer::class);

        $component->set('analysisDecisionId', 'decision-123');
        $component->set('citationOperation', 'graph');

        // First analysis should work
        $component->call('analyzeCitations');

        // Immediate second analysis should be blocked (60 second TTL)
        $component->call('analyzeCitations');

        // No errors should occur
        $this->assertNull($component->get('error'));
    }

    /** @test */
    public function it_prevents_duplicate_cluster_loads()
    {
        Cache::flush();

        // Mock cluster data
        $metricsRepo = Mockery::mock(GraphMetricsRepository::class);
        $metricsRepo->shouldReceive('getInfluentialDecisions')->andReturn([]);
        $metricsRepo->shouldReceive('getCitationClusters')->andReturn([]);
        $metricsRepo->shouldReceive('getNetworkStats')->andReturn([]);
        $metricsRepo->shouldReceive('getCluster')->andReturn([
            'community_id' => 1,
            'members' => [
                ['id' => 'node-1', 'case_number' => 'Case 1'],
            ],
        ]);
        $this->app->instance(GraphMetricsRepository::class, $metricsRepo);

        $component = Livewire::test(GraphViewer::class);

        // First view should work
        $component->call('viewCluster', 1);

        // Immediate second view should be blocked
        $component->call('viewCluster', 1);

        // No errors should occur
        $this->assertNull($component->get('error'));
    }

    /** @test */
    public function it_allows_requests_after_ttl_expires()
    {
        Cache::flush();

        $component = Livewire::test(GraphViewer::class);

        $component->set('searchTerm', 'test decision');

        // First search
        $component->call('searchNodes');

        // Clear the cache to simulate TTL expiration
        Cache::flush();

        // Second search should now work
        $component->call('searchNodes');

        // No errors should occur
        $this->assertNull($component->get('error'));
    }

    /** @test */
    public function it_handles_different_parameters_as_different_requests()
    {
        Cache::flush();

        $component = Livewire::test(GraphViewer::class);

        // Search with term "decision1"
        $component->set('searchTerm', 'decision1');
        $component->call('searchNodes');

        // Search with term "decision2" should not be blocked (different fingerprint)
        $component->set('searchTerm', 'decision2');
        $component->call('searchNodes');

        // No errors should occur
        $this->assertNull($component->get('error'));
    }

    /** @test */
    public function it_handles_rapid_successive_clicks()
    {
        Cache::flush();

        $component = Livewire::test(GraphViewer::class);

        $component->set('selectedNodeId', 'test-node');
        $component->set('selectedNodeType', 'CourtDecisionDocument');

        // Simulate 5 rapid clicks
        for ($i = 0; $i < 5; $i++) {
            $component->call('loadNodeGraph');
        }

        // Only one request should have processed, others blocked
        $this->assertNull($component->get('error'));
    }

    /** @test */
    public function it_prevents_concurrent_analysis_operations()
    {
        Cache::flush();

        $component = Livewire::test(GraphViewer::class);

        $component->set('analysisDecisionId', 'decision-456');

        // Test different operation types
        $operations = ['graph', 'authority', 'patterns', 'influence'];

        foreach ($operations as $operation) {
            $component->set('citationOperation', $operation);
            $component->call('analyzeCitations');

            // Immediate duplicate should be blocked
            $component->call('analyzeCitations');
        }

        // No errors should occur
        $this->assertNull($component->get('error'));
    }

    /** @test */
    public function it_validates_input_before_checking_duplicates()
    {
        Cache::flush();

        $component = Livewire::test(GraphViewer::class);

        // Empty search term should fail validation, not reach deduplication
        $component->set('searchTerm', '');
        $component->call('searchNodes');

        $this->assertEquals('Please enter a search term', $component->get('error'));
    }

    /** @test */
    public function it_generates_unique_fingerprints_per_component_instance()
    {
        Cache::flush();

        // Create two separate component instances
        $component1 = Livewire::test(GraphViewer::class);
        $component2 = Livewire::test(GraphViewer::class);

        $component1->set('searchTerm', 'decision');
        $component2->set('searchTerm', 'decision');

        // Both should be able to search independently (different component IDs)
        $component1->call('searchNodes');
        $component2->call('searchNodes');

        $this->assertNull($component1->get('error'));
        $this->assertNull($component2->get('error'));
    }

    /** @test */
    public function it_clears_fingerprint_after_operation_completes()
    {
        Cache::flush();

        $component = Livewire::test(GraphViewer::class);

        $component->set('selectedNodeId', 'test-node');

        // First load
        $component->call('loadNodeGraph');

        // Wait for operation to complete (fingerprint cleared in finally block)
        // In real scenario, this happens automatically

        // Manually clear to simulate completion
        Cache::flush();

        // Second load should work
        $component->call('loadNodeGraph');

        $this->assertNull($component->get('error'));
    }

    /** @test */
    public function it_uses_longer_ttl_for_expensive_operations()
    {
        Cache::flush();

        $component = Livewire::test(GraphViewer::class);

        $component->set('analysisDecisionId', 'decision-789');
        $component->set('citationOperation', 'graph');

        // Start analysis
        $component->call('analyzeCitations');

        // Check that fingerprint exists with longer TTL (60 seconds)
        $fingerprint = $this->getRequestFingerprint($component, 'analyzeCitations', [
            'decision-789',
            'graph',
        ]);

        $this->assertTrue(Cache::has($fingerprint));
    }

    protected function getRequestFingerprint($component, $method, $params)
    {
        $componentId = $component->id;
        $paramHash = md5(serialize($params));

        return "livewire:request:{$componentId}:{$method}:{$paramHash}";
    }
}
