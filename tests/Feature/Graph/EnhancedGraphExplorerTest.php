<?php

namespace Tests\Feature\Graph;

use App\Livewire\Graph\ForceGraphController;
use App\Services\Graph\GraphExplorerService;
use App\Services\GraphDatabaseService;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class EnhancedGraphExplorerTest extends TestCase
{
    /** @test */
    public function it_loads_graph_with_initial_node()
    {
        $graphMock = Mockery::mock(GraphDatabaseService::class);
        $graphMock->shouldReceive('getNodeWithConnections')
            ->once()
            ->with('node-123', 1)
            ->andReturn([
                'nodes' => [['id' => 'node-123', 'type' => 'Decision', 'properties' => []]],
                'edges' => [],
            ]);
        $this->app->instance(GraphDatabaseService::class, $graphMock);

        Livewire::test(ForceGraphController::class, ['rootNodeId' => 'node-123'])
            ->assertSet('graphData.nodes.0.id', 'node-123')
            ->assertSet('rootNodeId', 'node-123');
    }

    /** @test */
    public function it_loads_empty_graph_when_no_root_node_provided()
    {
        Livewire::test(ForceGraphController::class)
            ->assertSet('graphData', ['nodes' => [], 'edges' => []])
            ->assertSet('rootNodeId', null);
    }

    /** @test */
    public function it_expands_node_with_all_connections()
    {
        $graphMock = Mockery::mock(GraphDatabaseService::class);
        $graphMock->shouldReceive('getNodeWithConnections')
            ->once()
            ->andReturn(['nodes' => [['id' => 'root-1']], 'edges' => []]);
        $graphMock->shouldReceive('getConnectedNodes')
            ->once()
            ->with('node-123', ['root-1'])
            ->andReturn([
                'nodes' => [['id' => 'connected-1', 'type' => 'Law']],
                'edges' => [['source' => 'node-123', 'target' => 'connected-1', 'type' => 'CITES']],
            ]);
        $this->app->instance(GraphDatabaseService::class, $graphMock);

        Livewire::test(ForceGraphController::class, ['rootNodeId' => 'root-1'])
            ->dispatch('expand-node', nodeId: 'node-123')
            ->assertDispatched('connected-nodes-loaded');
    }

    /** @test */
    public function it_expands_node_with_filtered_relationships()
    {
        $graphMock = Mockery::mock(GraphDatabaseService::class);
        $graphMock->shouldReceive('getNodeWithConnections')
            ->once()
            ->andReturn(['nodes' => [['id' => 'root-1']], 'edges' => []]);
        $this->app->instance(GraphDatabaseService::class, $graphMock);

        $explorerMock = Mockery::mock(GraphExplorerService::class);
        $explorerMock->shouldReceive('getFilteredConnections')
            ->once()
            ->with('node-123', ['CITES'], 50)
            ->andReturn([
                ['node' => ['id' => 'n1', 'type' => 'Law'], 'rel' => ['type' => 'CITES']],
            ]);
        $this->app->instance(GraphExplorerService::class, $explorerMock);

        Livewire::test(ForceGraphController::class, ['rootNodeId' => 'root-1'])
            ->dispatch('expand-node-filtered', nodeId: 'node-123', relationshipTypes: ['CITES'])
            ->assertDispatched('filtered-nodes-loaded');
    }

    /** @test */
    public function it_handles_empty_filtered_relationships()
    {
        $graphMock = Mockery::mock(GraphDatabaseService::class);
        $graphMock->shouldReceive('getNodeWithConnections')
            ->once()
            ->andReturn(['nodes' => [['id' => 'root-1']], 'edges' => []]);
        $this->app->instance(GraphDatabaseService::class, $graphMock);

        $explorerMock = Mockery::mock(GraphExplorerService::class);
        $explorerMock->shouldReceive('getFilteredConnections')
            ->once()
            ->with('node-123', [], 50)
            ->andReturn([]);
        $this->app->instance(GraphExplorerService::class, $explorerMock);

        Livewire::test(ForceGraphController::class, ['rootNodeId' => 'root-1'])
            ->dispatch('expand-node-filtered', nodeId: 'node-123', relationshipTypes: [])
            ->assertSet('graphData.nodes', [['id' => 'root-1']]);
    }

    /** @test */
    public function it_selects_node_and_loads_decision_data()
    {
        $graphMock = Mockery::mock(GraphDatabaseService::class);
        $graphMock->shouldReceive('getNodeWithConnections')
            ->once()
            ->andReturn(['nodes' => [['id' => 'root-1']], 'edges' => []]);
        $graphMock->shouldReceive('getArgumentsForDecision')
            ->once()
            ->with('decision-123')
            ->andReturn([
                ['id' => 'arg1', 'party_type' => 'plaintiff', 'content' => 'Plaintiff argument'],
                ['id' => 'arg2', 'party_type' => 'defendant', 'content' => 'Defendant argument'],
                ['id' => 'arg3', 'party_type' => 'court', 'content' => 'Court reasoning'],
            ]);
        $graphMock->shouldReceive('getEvidenceForDecision')
            ->once()
            ->with('decision-123')
            ->andReturn([
                ['id' => 'ev1', 'evidence_type' => 'documentary', 'description' => 'Contract'],
                ['id' => 'ev2', 'evidence_type' => 'testimonial', 'description' => 'Witness statement'],
            ]);
        $graphMock->shouldReceive('getDateEventsForDecision')
            ->once()
            ->with('decision-123')
            ->andReturn([
                ['id' => 'evt1', 'date' => '2024-01-15', 'description' => 'Filing'],
                ['id' => 'evt2', 'date' => '2024-02-10', 'description' => 'Hearing'],
            ]);
        $this->app->instance(GraphDatabaseService::class, $graphMock);

        Livewire::test(ForceGraphController::class, ['rootNodeId' => 'root-1'])
            ->dispatch('node-selected', node: ['id' => 'decision-123', 'type' => 'CourtDecisionDocument'])
            ->assertSet('selectedNodeId', 'decision-123')
            ->assertSet('arguments.plaintiff.0.id', 'arg1')
            ->assertSet('arguments.defendant.0.id', 'arg2')
            ->assertSet('arguments.court.0.id', 'arg3')
            ->assertSet('evidence.documentary.0.id', 'ev1')
            ->assertSet('evidence.testimonial.0.id', 'ev2')
            ->assertCount('timeline', 2);
    }

    /** @test */
    public function it_clears_side_panel_data_for_non_decision_nodes()
    {
        $graphMock = Mockery::mock(GraphDatabaseService::class);
        $graphMock->shouldReceive('getNodeWithConnections')
            ->once()
            ->andReturn(['nodes' => [['id' => 'root-1']], 'edges' => []]);
        $this->app->instance(GraphDatabaseService::class, $graphMock);

        Livewire::test(ForceGraphController::class, ['rootNodeId' => 'root-1'])
            ->dispatch('node-selected', node: ['id' => 'law-456', 'type' => 'Law'])
            ->assertSet('selectedNodeId', 'law-456')
            ->assertSet('arguments', [])
            ->assertSet('evidence', [])
            ->assertSet('timeline', []);
    }

    /** @test */
    public function it_refreshes_graph_when_root_node_exists()
    {
        $graphMock = Mockery::mock(GraphDatabaseService::class);
        $graphMock->shouldReceive('getNodeWithConnections')
            ->twice()
            ->with('node-123', 1)
            ->andReturn([
                'nodes' => [['id' => 'node-123', 'type' => 'Decision']],
                'edges' => [],
            ]);
        $this->app->instance(GraphDatabaseService::class, $graphMock);

        Livewire::test(ForceGraphController::class, ['rootNodeId' => 'node-123'])
            ->dispatch('refresh-graph')
            ->assertSet('rootNodeId', 'node-123');
    }

    /** @test */
    public function it_sorts_timeline_events_chronologically()
    {
        $graphMock = Mockery::mock(GraphDatabaseService::class);
        $graphMock->shouldReceive('getNodeWithConnections')
            ->once()
            ->andReturn(['nodes' => [['id' => 'root-1']], 'edges' => []]);
        $graphMock->shouldReceive('getArgumentsForDecision')->andReturn([]);
        $graphMock->shouldReceive('getEvidenceForDecision')->andReturn([]);
        $graphMock->shouldReceive('getDateEventsForDecision')
            ->once()
            ->with('decision-123')
            ->andReturn([
                ['id' => 'evt1', 'date' => '2024-03-20', 'description' => 'Last event'],
                ['id' => 'evt2', 'date' => '2024-01-10', 'description' => 'First event'],
                ['id' => 'evt3', 'date' => '2024-02-15', 'description' => 'Middle event'],
            ]);
        $this->app->instance(GraphDatabaseService::class, $graphMock);

        $component = Livewire::test(ForceGraphController::class, ['rootNodeId' => 'root-1'])
            ->dispatch('node-selected', node: ['id' => 'decision-123', 'type' => 'CourtDecisionDocument']);

        // Verify events are sorted chronologically
        $timeline = $component->get('timeline');
        $this->assertEquals('2024-01-10', $timeline[0]['date']);
        $this->assertEquals('2024-02-15', $timeline[1]['date']);
        $this->assertEquals('2024-03-20', $timeline[2]['date']);
    }

    /** @test */
    public function it_prevents_duplicate_nodes_when_expanding_filtered()
    {
        $graphMock = Mockery::mock(GraphDatabaseService::class);
        $graphMock->shouldReceive('getNodeWithConnections')
            ->once()
            ->andReturn([
                'nodes' => [['id' => 'root-1'], ['id' => 'existing-1']],
                'edges' => [],
            ]);
        $this->app->instance(GraphDatabaseService::class, $graphMock);

        $explorerMock = Mockery::mock(GraphExplorerService::class);
        $explorerMock->shouldReceive('getFilteredConnections')
            ->once()
            ->andReturn([
                ['node' => ['id' => 'existing-1', 'type' => 'Law'], 'rel' => ['type' => 'CITES']],
                ['node' => ['id' => 'new-1', 'type' => 'Law'], 'rel' => ['type' => 'CITES']],
            ]);
        $this->app->instance(GraphExplorerService::class, $explorerMock);

        $component = Livewire::test(ForceGraphController::class, ['rootNodeId' => 'root-1'])
            ->dispatch('expand-node-filtered', nodeId: 'node-123', relationshipTypes: ['CITES']);

        // Should only have 3 nodes total: root-1, existing-1, new-1 (existing-1 not duplicated)
        $nodes = $component->get('graphData.nodes');
        $this->assertCount(3, $nodes);
        $nodeIds = array_column($nodes, 'id');
        $this->assertContains('root-1', $nodeIds);
        $this->assertContains('existing-1', $nodeIds);
        $this->assertContains('new-1', $nodeIds);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
