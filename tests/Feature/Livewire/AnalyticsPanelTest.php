<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\AnalyticsPanel;
use App\Repositories\GraphMetricsRepository;
use App\Services\Graph\ContradictionDetectionService;
use App\Services\Graph\OutlierDetectionService;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class AnalyticsPanelTest extends TestCase
{
    protected $metricsRepository;

    protected $contradictionService;

    protected $outlierService;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock the repositories and services
        $this->metricsRepository = Mockery::mock(GraphMetricsRepository::class);
        $this->contradictionService = Mockery::mock(ContradictionDetectionService::class);
        $this->outlierService = Mockery::mock(OutlierDetectionService::class);

        $this->app->instance(GraphMetricsRepository::class, $this->metricsRepository);
        $this->app->instance(ContradictionDetectionService::class, $this->contradictionService);
        $this->app->instance(OutlierDetectionService::class, $this->outlierService);
    }

    /**
     * Test 1: Component renders correctly with initial state
     *
     * @test
     */
    public function test_component_renders_correctly()
    {
        $this->setupDefaultMocks();

        Livewire::test(AnalyticsPanel::class)
            ->assertStatus(200)
            ->assertViewIs('livewire.analytics-panel')
            ->assertSet('selectedView', 'influential')
            ->assertSet('loading', false);
    }

    /**
     * Test 2: View switching works
     *
     * @test
     */
    public function test_view_switching_works()
    {
        $this->setupDefaultMocks();

        Livewire::test(AnalyticsPanel::class)
            ->assertSet('selectedView', 'influential')
            ->call('switchView', 'contradictions')
            ->assertSet('selectedView', 'contradictions')
            ->call('switchView', 'outliers')
            ->assertSet('selectedView', 'outliers')
            ->call('switchView', 'clusters')
            ->assertSet('selectedView', 'clusters');
    }

    /**
     * Test 3: Loads influential decisions on mount
     *
     * @test
     */
    public function test_loads_influential_decisions_on_mount()
    {
        $mockDecisions = [
            ['case_number' => 'K-123/2024', 'rank' => 0.85, 'court' => 'Vrhovni sud RH'],
            ['case_number' => 'K-456/2024', 'rank' => 0.72, 'court' => 'Županijski sud'],
        ];

        $this->metricsRepository
            ->shouldReceive('getInfluentialDecisions')
            ->with(20)
            ->andReturn($mockDecisions);

        $this->metricsRepository
            ->shouldReceive('getCitationClusters')
            ->andReturn([]);

        $component = Livewire::test(AnalyticsPanel::class);

        $this->assertCount(2, $component->get('influentialDecisions'));
    }

    /**
     * Test 4: Loads citation clusters
     *
     * @test
     */
    public function test_loads_citation_clusters()
    {
        $mockClusters = [
            ['community_id' => 1, 'size' => 25, 'modularity' => 0.45],
            ['community_id' => 2, 'size' => 18, 'modularity' => 0.38],
        ];

        $this->metricsRepository
            ->shouldReceive('getCitationClusters')
            ->with(10)
            ->andReturn($mockClusters);

        $this->metricsRepository
            ->shouldReceive('getInfluentialDecisions')
            ->andReturn([]);

        $component = Livewire::test(AnalyticsPanel::class);

        $this->assertCount(2, $component->get('citationClusters'));
    }

    /**
     * Test 5: Refresh data reloads metrics
     *
     * @test
     */
    public function test_refresh_data_reloads_metrics()
    {
        // Setup mocks for mount() + refreshData() calls (2 calls each)
        $this->metricsRepository
            ->shouldReceive('getInfluentialDecisions')
            ->with(20)
            ->twice()
            ->andReturn([]);

        $this->metricsRepository
            ->shouldReceive('getCitationClusters')
            ->with(10)
            ->twice()
            ->andReturn([]);

        Livewire::test(AnalyticsPanel::class)
            ->call('refreshData')
            ->assertSet('loading', false);
    }

    /**
     * Test 6: Component has view options
     *
     * @test
     */
    public function test_component_has_view_options()
    {
        $this->setupDefaultMocks();

        $component = Livewire::test(AnalyticsPanel::class);

        $views = $component->get('views');

        $this->assertIsArray($views);
        $this->assertArrayHasKey('influential', $views);
        $this->assertArrayHasKey('contradictions', $views);
        $this->assertArrayHasKey('outliers', $views);
        $this->assertArrayHasKey('clusters', $views);
    }

    /**
     * Test 7: Invalid view defaults to influential
     *
     * @test
     */
    public function test_invalid_view_defaults_to_influential()
    {
        $this->setupDefaultMocks();

        Livewire::test(AnalyticsPanel::class)
            ->call('switchView', 'invalid_view')
            ->assertSet('selectedView', 'influential');
    }

    /**
     * Test 8: Loading state management
     *
     * @test
     */
    public function test_loading_state_initially_false()
    {
        $this->setupDefaultMocks();

        Livewire::test(AnalyticsPanel::class)
            ->assertSet('loading', false);
    }

    protected function setupDefaultMocks(): void
    {
        $this->metricsRepository
            ->shouldReceive('getInfluentialDecisions')
            ->andReturn([]);

        $this->metricsRepository
            ->shouldReceive('getCitationClusters')
            ->andReturn([]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
