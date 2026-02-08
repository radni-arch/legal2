<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\GraphViewer;
use App\Repositories\GraphMetricsRepository;
use App\Services\DecisionCitationService;
use App\Services\GraphDatabaseService;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class GraphViewerInputValidationTest extends TestCase
{
    protected $graphService;

    protected $metricsRepository;

    protected $citationService;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock the GraphDatabaseService
        $this->graphService = Mockery::mock(GraphDatabaseService::class);
        $this->app->instance(GraphDatabaseService::class, $this->graphService);

        // Mock the GraphMetricsRepository
        $this->metricsRepository = Mockery::mock(GraphMetricsRepository::class);
        $this->app->instance(GraphMetricsRepository::class, $this->metricsRepository);

        // Mock the DecisionCitationService
        $this->citationService = Mockery::mock(DecisionCitationService::class);
        $this->app->instance(DecisionCitationService::class, $this->citationService);
    }

    /**
     * Setup default mocks for component mount
     */
    protected function setupDefaultMocks(): void
    {
        // Mock statistics query (node counts)
        $statsRecord = Mockery::mock();
        $statsRecord->shouldReceive('get')->with('label')->andReturn('CourtDecisionDocument');
        $statsRecord->shouldReceive('get')->with('count')->andReturn(100);

        $this->graphService->shouldReceive('run')
            ->with(Mockery::on(function ($query) {
                return str_contains($query, 'labels(n)');
            }))
            ->andReturn(collect([$statsRecord]));

        // Mock relationship count query
        $relCountRecord = Mockery::mock();
        $relCountRecord->shouldReceive('get')->with('count')->andReturn(250);

        $relCountResult = Mockery::mock();
        $relCountResult->shouldReceive('first')->andReturn($relCountRecord);

        $this->graphService->shouldReceive('run')
            ->with(Mockery::on(function ($query) {
                return str_contains($query, 'MATCH ()-[r]->()');
            }))
            ->andReturn($relCountResult);

        // Mock recent nodes query
        $this->graphService->shouldReceive('run')
            ->with(Mockery::on(function ($query) {
                return str_contains($query, 'created_at IS NOT NULL');
            }))
            ->andReturn(collect([]));

        // Mock metrics repository
        $this->metricsRepository->shouldReceive('getInfluentialDecisions')->andReturn([]);
        $this->metricsRepository->shouldReceive('getCitationClusters')->andReturn([]);
        $this->metricsRepository->shouldReceive('getNetworkStats')->andReturn(null);
    }

    /**
     * Test 1: Accepts special characters without throwing exceptions
     *
     * @test
     */
    public function it_accepts_special_characters_without_throwing()
    {
        $this->setupDefaultMocks();

        // SQL injection attempt with special characters
        $maliciousQuery = "'; DROP TABLE users; --";

        Livewire::test(GraphViewer::class)
            ->set('searchQuery', $maliciousQuery)
            ->assertSet('searchQuery', $maliciousQuery) // Should not throw an exception
            ->assertStatus(200);
    }

    /**
     * Test 2: Limits search query length to max
     *
     * @test
     */
    public function it_limits_search_query_length()
    {
        $this->setupDefaultMocks();

        // Create a string over 1000 characters
        $longQuery = str_repeat('a', 1500);

        Livewire::test(GraphViewer::class)
            ->set('searchQuery', $longQuery)
            ->assertSet('searchQuery', substr($longQuery, 0, 1000));
    }

    /**
     * Test 3: Validates depth parameter range - clamps to maximum
     *
     * @test
     */
    public function it_validates_depth_parameter_range()
    {
        $this->setupDefaultMocks();

        Livewire::test(GraphViewer::class)
            ->set('depth', 100)
            ->assertSet('depth', 5); // Should be clamped to MAX_DEPTH (5)
    }

    /**
     * Test 4: Validates depth parameter minimum - clamps to minimum
     *
     * @test
     */
    public function it_validates_depth_parameter_minimum()
    {
        $this->setupDefaultMocks();

        Livewire::test(GraphViewer::class)
            ->set('depth', -5)
            ->assertSet('depth', 1); // Should be clamped to MIN_DEPTH (1)
    }

    /**
     * Test 5: Validates node type filter - filters unknown types
     *
     * @test
     */
    public function it_validates_node_type_filter()
    {
        $this->setupDefaultMocks();

        // Mix of valid and invalid node types
        $nodeTypes = [
            'LawDocument',          // Valid
            'CourtDecisionDocument', // Valid
            'InvalidType',          // Invalid
            'HackerNode',          // Invalid
            'Keyword',             // Valid
        ];

        $expectedValidTypes = [
            'LawDocument',
            'CourtDecisionDocument',
            'Keyword',
        ];

        Livewire::test(GraphViewer::class)
            ->set('selectedNodeTypes', $nodeTypes)
            ->assertSet('selectedNodeTypes', $expectedValidTypes);
    }
}
