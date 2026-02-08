<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Graph\ForceGraphController;
use App\Services\GraphDatabaseService;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class GraphViewerPanelsTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_renders_force_graph_controller_component(): void
    {
        $this->mock(GraphDatabaseService::class, function ($mock) {
            $mock->shouldReceive('getNodeWithConnections')
                ->andReturn(['nodes' => [], 'edges' => []]);
        });

        Livewire::test(ForceGraphController::class)
            ->assertStatus(200)
            ->assertViewIs('livewire.graph.force-graph');
    }

    /** @test */
    public function it_loads_arguments_when_decision_node_selected(): void
    {
        $mockArgs = [
            ['content' => 'Plaintiff argument', 'party_type' => 'plaintiff'],
            ['content' => 'Defendant argument', 'party_type' => 'defendant'],
        ];

        $this->mock(GraphDatabaseService::class, function ($mock) use ($mockArgs) {
            $mock->shouldReceive('getNodeWithConnections')
                ->andReturn(['nodes' => [], 'edges' => []]);
            $mock->shouldReceive('getArgumentsForDecision')
                ->with('decision-123')
                ->andReturn($mockArgs);
            $mock->shouldReceive('getEvidenceForDecision')
                ->andReturn([]);
            $mock->shouldReceive('getDateEventsForDecision')
                ->andReturn([]);
        });

        Livewire::test(ForceGraphController::class)
            ->call('selectNode', ['id' => 'decision-123', 'type' => 'CourtDecisionDocument'])
            ->assertSet('arguments.plaintiff', [['content' => 'Plaintiff argument', 'party_type' => 'plaintiff']])
            ->assertSet('arguments.defendant', [['content' => 'Defendant argument', 'party_type' => 'defendant']]);
    }

    /** @test */
    public function it_loads_evidence_when_decision_node_selected(): void
    {
        $mockEvidence = [
            ['description' => 'Document X', 'evidence_type' => 'documentary'],
            ['description' => 'Witness Y', 'evidence_type' => 'testimonial'],
        ];

        $this->mock(GraphDatabaseService::class, function ($mock) use ($mockEvidence) {
            $mock->shouldReceive('getNodeWithConnections')
                ->andReturn(['nodes' => [], 'edges' => []]);
            $mock->shouldReceive('getArgumentsForDecision')
                ->andReturn([]);
            $mock->shouldReceive('getEvidenceForDecision')
                ->with('decision-123')
                ->andReturn($mockEvidence);
            $mock->shouldReceive('getDateEventsForDecision')
                ->andReturn([]);
        });

        Livewire::test(ForceGraphController::class)
            ->call('selectNode', ['id' => 'decision-123', 'type' => 'CourtDecisionDocument'])
            ->assertSet('evidence.documentary', [['description' => 'Document X', 'evidence_type' => 'documentary']])
            ->assertSet('evidence.testimonial', [['description' => 'Witness Y', 'evidence_type' => 'testimonial']]);
    }

    /** @test */
    public function it_loads_timeline_when_decision_node_selected(): void
    {
        $mockEvents = [
            ['date' => '2024-01-15', 'event_type' => 'filing', 'description' => 'Case filed'],
            ['date' => '2024-03-20', 'event_type' => 'judgment', 'description' => 'Judgment rendered'],
        ];

        $this->mock(GraphDatabaseService::class, function ($mock) use ($mockEvents) {
            $mock->shouldReceive('getNodeWithConnections')
                ->andReturn(['nodes' => [], 'edges' => []]);
            $mock->shouldReceive('getArgumentsForDecision')
                ->andReturn([]);
            $mock->shouldReceive('getEvidenceForDecision')
                ->andReturn([]);
            $mock->shouldReceive('getDateEventsForDecision')
                ->with('decision-123')
                ->andReturn($mockEvents);
        });

        Livewire::test(ForceGraphController::class)
            ->call('selectNode', ['id' => 'decision-123', 'type' => 'CourtDecisionDocument'])
            ->assertSet('timeline', $mockEvents);
    }

    /** @test */
    public function it_clears_panels_when_non_decision_node_selected(): void
    {
        $this->mock(GraphDatabaseService::class, function ($mock) {
            $mock->shouldReceive('getNodeWithConnections')
                ->andReturn(['nodes' => [], 'edges' => []]);
        });

        Livewire::test(ForceGraphController::class)
            ->call('selectNode', ['id' => 'lawyer-456', 'type' => 'Lawyer'])
            ->assertSet('arguments', [])
            ->assertSet('evidence', [])
            ->assertSet('timeline', []);
    }
}
