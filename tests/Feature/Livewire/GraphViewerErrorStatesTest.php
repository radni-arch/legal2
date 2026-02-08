<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\GraphViewer;
use App\Services\GraphDatabaseService;
use App\Services\DecisionCitationService;
use App\Repositories\GraphMetricsRepository;
use App\Exceptions\Graph\GraphConnectionException;
use Livewire\Livewire;
use Tests\TestCase;
use Mockery;

class GraphViewerErrorStatesTest extends TestCase
{
    /** @test */
    public function it_displays_connection_error_when_neo4j_unavailable(): void
    {
        $graphService = Mockery::mock(GraphDatabaseService::class);
        $graphService->shouldReceive('run')
            ->andThrow(GraphConnectionException::forHost('localhost:7687'));

        $metricsRepository = Mockery::mock(GraphMetricsRepository::class);
        $metricsRepository->shouldReceive('getInfluentialDecisions')->andReturn([]);
        $metricsRepository->shouldReceive('getCitationClusters')->andReturn([]);
        $metricsRepository->shouldReceive('getNetworkStats')->andReturn(null);

        $citationService = Mockery::mock(DecisionCitationService::class);

        $this->app->instance(GraphDatabaseService::class, $graphService);
        $this->app->instance(GraphMetricsRepository::class, $metricsRepository);
        $this->app->instance(DecisionCitationService::class, $citationService);

        $component = Livewire::test(GraphViewer::class);

        $component->assertSee('Graph database unavailable');
        $component->assertSet('hasConnectionError', true);
    }

    /** @test */
    public function it_has_loading_state_property(): void
    {
        // Mock the services to prevent real connections
        $graphService = Mockery::mock(GraphDatabaseService::class);
        $graphService->shouldReceive('run')->andReturn(collect([]));

        $metricsRepository = Mockery::mock(GraphMetricsRepository::class);
        $metricsRepository->shouldReceive('getInfluentialDecisions')->andReturn([]);
        $metricsRepository->shouldReceive('getCitationClusters')->andReturn([]);
        $metricsRepository->shouldReceive('getNetworkStats')->andReturn(null);

        $citationService = Mockery::mock(DecisionCitationService::class);

        $this->app->instance(GraphDatabaseService::class, $graphService);
        $this->app->instance(GraphMetricsRepository::class, $metricsRepository);
        $this->app->instance(DecisionCitationService::class, $citationService);

        $component = Livewire::test(GraphViewer::class);

        // Component should have loading property
        $this->assertNotNull($component->get('loading'));
    }

    /** @test */
    public function it_displays_empty_state_when_no_search_results(): void
    {
        $graphService = Mockery::mock(GraphDatabaseService::class);
        $graphService->shouldReceive('run')->andReturn(collect([]));

        $metricsRepository = Mockery::mock(GraphMetricsRepository::class);
        $metricsRepository->shouldReceive('getInfluentialDecisions')->andReturn([]);
        $metricsRepository->shouldReceive('getCitationClusters')->andReturn([]);
        $metricsRepository->shouldReceive('getNetworkStats')->andReturn(null);

        $citationService = Mockery::mock(DecisionCitationService::class);

        $this->app->instance(GraphDatabaseService::class, $graphService);
        $this->app->instance(GraphMetricsRepository::class, $metricsRepository);
        $this->app->instance(DecisionCitationService::class, $citationService);

        $component = Livewire::test(GraphViewer::class)
            ->set('searchTerm', 'nonexistent-xyz-12345')
            ->call('search');

        $component->assertSee('No results found');
    }
}
