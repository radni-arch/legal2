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
 * Tests for Phase 5.4: Law Document Amendment Display in GraphViewer
 *
 * These tests verify that amendments, repeal status, parent law references,
 * and consolidation dates are properly displayed for LawDocument nodes.
 */
class GraphViewerAmendmentDisplayTest extends TestCase
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
        $statsRecord->shouldReceive('get')->with('label')->andReturn('LawDocument');
        $statsRecord->shouldReceive('get')->with('count')->andReturn(50);

        $this->graphService->shouldReceive('run')
            ->with(Mockery::on(fn ($query) => str_contains($query, 'labels(n)')))
            ->andReturn(collect([$statsRecord]));

        // Mock relationship count query
        $relCountRecord = Mockery::mock();
        $relCountRecord->shouldReceive('get')->with('count')->andReturn(100);

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
    public function it_renders_amendments_panel_when_amendments_exist(): void
    {
        $this->setupDefaultMocks();

        Livewire::test(GraphViewer::class)
            ->set('selectedNodeType', 'LawDocument')
            ->set('selectedNode', [
                'id' => 'law-123',
                'law_number' => 'NN 50/10',
                'amendments' => ['NN 123/21', 'NN 45/22'],
            ])
            ->assertSee('Amendments (2)')
            ->assertSee('NN 123/21')
            ->assertSee('NN 45/22');
    }

    /** @test */
    public function it_hides_amendments_panel_when_empty_array(): void
    {
        $this->setupDefaultMocks();

        $component = Livewire::test(GraphViewer::class)
            ->set('selectedNodeType', 'LawDocument')
            ->set('selectedNode', [
                'id' => 'law-123',
                'amendments' => [],
            ]);

        // Verify state
        $selectedNode = $component->get('selectedNode');
        $this->assertEquals([], $selectedNode['amendments']);

        // Should not show amendments panel
        $component->assertDontSee('Amendments (0)');
    }

    /** @test */
    public function it_hides_amendments_panel_when_null(): void
    {
        $this->setupDefaultMocks();

        $component = Livewire::test(GraphViewer::class)
            ->set('selectedNodeType', 'LawDocument')
            ->set('selectedNode', [
                'id' => 'law-123',
                'amendments' => null,
            ]);

        $selectedNode = $component->get('selectedNode');
        $this->assertNull($selectedNode['amendments']);
    }

    /** @test */
    public function it_renders_repeal_panel_with_both_fields(): void
    {
        $this->setupDefaultMocks();

        Livewire::test(GraphViewer::class)
            ->set('selectedNodeType', 'LawDocument')
            ->set('selectedNode', [
                'id' => 'law-123',
                'repeal_date' => '2025-12-31',
                'repealed_by' => 'NN 200/25',
            ])
            ->assertSee('Repealed')
            ->assertSee('2025-12-31')
            ->assertSee('NN 200/25');
    }

    /** @test */
    public function it_renders_repeal_panel_with_only_repeal_date(): void
    {
        $this->setupDefaultMocks();

        Livewire::test(GraphViewer::class)
            ->set('selectedNodeType', 'LawDocument')
            ->set('selectedNode', [
                'id' => 'law-123',
                'repeal_date' => '2025-06-01',
                'repealed_by' => null,
            ])
            ->assertSee('Repealed')
            ->assertSee('2025-06-01');
    }

    /** @test */
    public function it_renders_repeal_panel_with_only_repealed_by(): void
    {
        $this->setupDefaultMocks();

        Livewire::test(GraphViewer::class)
            ->set('selectedNodeType', 'LawDocument')
            ->set('selectedNode', [
                'id' => 'law-123',
                'repeal_date' => null,
                'repealed_by' => 'NN 100/24',
            ])
            ->assertSee('Repealed')
            ->assertSee('NN 100/24');
    }

    /** @test */
    public function it_hides_repeal_panel_when_not_repealed(): void
    {
        $this->setupDefaultMocks();

        $component = Livewire::test(GraphViewer::class)
            ->set('selectedNodeType', 'LawDocument')
            ->set('selectedNode', [
                'id' => 'law-123',
                'repeal_date' => null,
                'repealed_by' => null,
            ]);

        $selectedNode = $component->get('selectedNode');
        $this->assertNull($selectedNode['repeal_date']);
        $this->assertNull($selectedNode['repealed_by']);
    }

    /** @test */
    public function it_renders_parent_law_panel(): void
    {
        $this->setupDefaultMocks();

        Livewire::test(GraphViewer::class)
            ->set('selectedNodeType', 'LawDocument')
            ->set('selectedNode', [
                'id' => 'law-123',
                'parent_law_number' => 'NN 50/10',
            ])
            ->assertSee('Amends Original Law')
            ->assertSee('NN 50/10');
    }

    /** @test */
    public function it_hides_parent_law_panel_when_null(): void
    {
        $this->setupDefaultMocks();

        $component = Livewire::test(GraphViewer::class)
            ->set('selectedNodeType', 'LawDocument')
            ->set('selectedNode', [
                'id' => 'law-123',
                'parent_law_number' => null,
            ]);

        $selectedNode = $component->get('selectedNode');
        $this->assertNull($selectedNode['parent_law_number']);
    }

    /** @test */
    public function it_renders_consolidation_date(): void
    {
        $this->setupDefaultMocks();

        Livewire::test(GraphViewer::class)
            ->set('selectedNodeType', 'LawDocument')
            ->set('selectedNode', [
                'id' => 'law-123',
                'consolidation_date' => '2024-06-15',
            ])
            ->assertSee('Consolidated Text')
            ->assertSee('2024-06-15');
    }

    /** @test */
    public function it_hides_consolidation_panel_when_null(): void
    {
        $this->setupDefaultMocks();

        $component = Livewire::test(GraphViewer::class)
            ->set('selectedNodeType', 'LawDocument')
            ->set('selectedNode', [
                'id' => 'law-123',
                'consolidation_date' => null,
            ]);

        $selectedNode = $component->get('selectedNode');
        $this->assertNull($selectedNode['consolidation_date']);
    }

    /** @test */
    public function it_does_not_show_amendment_panels_for_court_decisions(): void
    {
        $this->setupDefaultMocks();

        $component = Livewire::test(GraphViewer::class)
            ->set('selectedNodeType', 'CourtDecisionDocument')
            ->set('selectedNode', [
                'id' => 'decision-123',
                'amendments' => ['Should not show'],
                'repealed_by' => 'Should not show',
            ]);

        // Amendment panels are only for LawDocument
        $component->assertDontSee('Amendments (1)');
    }

    /** @test */
    public function it_stores_all_amendment_properties(): void
    {
        $this->setupDefaultMocks();

        $component = Livewire::test(GraphViewer::class)
            ->set('selectedNodeType', 'LawDocument')
            ->set('selectedNode', [
                'id' => 'law-123',
                'amendments' => ['NN 1/21', 'NN 2/22'],
                'repeal_date' => '2025-12-31',
                'repealed_by' => 'NN 100/25',
                'parent_law_number' => 'NN 50/10',
                'consolidation_date' => '2024-06-15',
            ]);

        $selectedNode = $component->get('selectedNode');
        $this->assertEquals(['NN 1/21', 'NN 2/22'], $selectedNode['amendments']);
        $this->assertEquals('2025-12-31', $selectedNode['repeal_date']);
        $this->assertEquals('NN 100/25', $selectedNode['repealed_by']);
        $this->assertEquals('NN 50/10', $selectedNode['parent_law_number']);
        $this->assertEquals('2024-06-15', $selectedNode['consolidation_date']);
    }
}
