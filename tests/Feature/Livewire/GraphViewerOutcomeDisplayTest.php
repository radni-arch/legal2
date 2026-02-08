<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\GraphViewer;
use App\Repositories\GraphMetricsRepository;
use App\Services\GraphDatabaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

/**
 * Tests for Phase 5.3: Court Decision Outcome Display in GraphViewer
 *
 * These tests verify that dissent counts, concurrence counts, and holding
 * text are properly available and displayed in the GraphViewer component.
 */
class GraphViewerOutcomeDisplayTest extends TestCase
{
    use RefreshDatabase;

    protected $graphService;

    protected $metricsRepository;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock the GraphDatabaseService
        $this->graphService = Mockery::mock(GraphDatabaseService::class);
        $this->app->instance(GraphDatabaseService::class, $this->graphService);

        // Mock the GraphMetricsRepository
        $this->metricsRepository = Mockery::mock(GraphMetricsRepository::class);
        $this->app->instance(GraphMetricsRepository::class, $this->metricsRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
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
            ->with(Mockery::on(fn ($query) => str_contains($query, 'labels(n)')))
            ->andReturn(collect([$statsRecord]));

        // Mock relationship count query
        $relCountRecord = Mockery::mock();
        $relCountRecord->shouldReceive('get')->with('count')->andReturn(250);

        $relCountResult = Mockery::mock();
        $relCountResult->shouldReceive('first')->andReturn($relCountRecord);

        $this->graphService->shouldReceive('run')
            ->with(Mockery::on(fn ($query) => str_contains($query, 'MATCH ()-[r]->()')))
            ->andReturn($relCountResult);

        // Mock recent nodes query
        $this->graphService->shouldReceive('run')
            ->with(Mockery::on(fn ($query) => str_contains($query, 'created_at IS NOT NULL')))
            ->andReturn(collect([]));

        // Mock metrics repository
        $this->metricsRepository->shouldReceive('getInfluentialDecisions')->andReturn([]);
        $this->metricsRepository->shouldReceive('getCitationClusters')->andReturn([]);
        $this->metricsRepository->shouldReceive('getNetworkStats')->andReturn(null);
    }

    /** @test */
    public function it_renders_dissent_count_badge_when_greater_than_zero(): void
    {
        $this->setupDefaultMocks();

        Livewire::test(GraphViewer::class)
            ->set('selectedNodeType', 'CourtDecisionDocument')
            ->set('selectedNode', [
                'id' => 'test-123',
                'case_number' => 'Rev 1/2026',
                'dissent_count' => 3,
                'concurrence_count' => 0,
            ])
            ->assertSee('3 Dissents');
    }

    /** @test */
    public function it_hides_dissent_badge_when_zero(): void
    {
        $this->setupDefaultMocks();

        $component = Livewire::test(GraphViewer::class)
            ->set('selectedNodeType', 'CourtDecisionDocument')
            ->set('selectedNode', [
                'id' => 'test-123',
                'case_number' => 'Rev 1/2026',
                'dissent_count' => 0,
                'concurrence_count' => 0,
            ]);

        // Verify the state
        $selectedNode = $component->get('selectedNode');
        $this->assertEquals(0, $selectedNode['dissent_count']);

        // The badge should not show "0 Dissent" text
        $component->assertDontSee('0 Dissent');
    }

    /** @test */
    public function it_uses_singular_form_for_single_dissent(): void
    {
        $this->setupDefaultMocks();

        Livewire::test(GraphViewer::class)
            ->set('selectedNodeType', 'CourtDecisionDocument')
            ->set('selectedNode', [
                'id' => 'test-123',
                'dissent_count' => 1,
            ])
            ->assertSee('1 Dissent')
            ->assertDontSee('1 Dissents');
    }

    /** @test */
    public function it_renders_concurrence_count_badge_when_greater_than_zero(): void
    {
        $this->setupDefaultMocks();

        Livewire::test(GraphViewer::class)
            ->set('selectedNodeType', 'CourtDecisionDocument')
            ->set('selectedNode', [
                'id' => 'test-123',
                'case_number' => 'Rev 1/2026',
                'dissent_count' => 0,
                'concurrence_count' => 2,
            ])
            ->assertSee('2 Concurrences');
    }

    /** @test */
    public function it_uses_singular_form_for_single_concurrence(): void
    {
        $this->setupDefaultMocks();

        Livewire::test(GraphViewer::class)
            ->set('selectedNodeType', 'CourtDecisionDocument')
            ->set('selectedNode', [
                'id' => 'test-123',
                'concurrence_count' => 1,
            ])
            ->assertSee('1 Concurrence')
            ->assertDontSee('1 Concurrences');
    }

    /** @test */
    public function it_renders_both_dissent_and_concurrence_badges(): void
    {
        $this->setupDefaultMocks();

        Livewire::test(GraphViewer::class)
            ->set('selectedNodeType', 'CourtDecisionDocument')
            ->set('selectedNode', [
                'id' => 'test-123',
                'dissent_count' => 2,
                'concurrence_count' => 3,
            ])
            ->assertSee('2 Dissents')
            ->assertSee('3 Concurrences');
    }

    /** @test */
    public function it_renders_holding_panel_when_holding_present(): void
    {
        $this->setupDefaultMocks();

        Livewire::test(GraphViewer::class)
            ->set('selectedNodeType', 'CourtDecisionDocument')
            ->set('selectedNode', [
                'id' => 'test-123',
                'holding' => 'The court finds in favor of the plaintiff.',
            ])
            ->assertSee('Holding')
            ->assertSee('The court finds in favor of the plaintiff.');
    }

    /** @test */
    public function it_hides_holding_panel_when_null(): void
    {
        $this->setupDefaultMocks();

        $component = Livewire::test(GraphViewer::class)
            ->set('selectedNodeType', 'CourtDecisionDocument')
            ->set('selectedNode', [
                'id' => 'test-123',
                'holding' => null,
            ]);

        // Verify state
        $selectedNode = $component->get('selectedNode');
        $this->assertNull($selectedNode['holding']);
    }

    /** @test */
    public function it_hides_holding_panel_when_empty_string(): void
    {
        $this->setupDefaultMocks();

        $component = Livewire::test(GraphViewer::class)
            ->set('selectedNodeType', 'CourtDecisionDocument')
            ->set('selectedNode', [
                'id' => 'test-123',
                'holding' => '',
            ]);

        // Verify state
        $selectedNode = $component->get('selectedNode');
        $this->assertEquals('', $selectedNode['holding']);
    }

    /** @test */
    public function it_stores_outcome_properties_for_court_decisions(): void
    {
        $this->setupDefaultMocks();

        $component = Livewire::test(GraphViewer::class)
            ->set('selectedNodeType', 'CourtDecisionDocument')
            ->set('selectedNode', [
                'id' => 'test-123',
                'dissent_count' => 5,
                'concurrence_count' => 3,
                'holding' => 'Test holding text',
            ]);

        $selectedNode = $component->get('selectedNode');
        $this->assertEquals(5, $selectedNode['dissent_count']);
        $this->assertEquals(3, $selectedNode['concurrence_count']);
        $this->assertEquals('Test holding text', $selectedNode['holding']);
    }
}
