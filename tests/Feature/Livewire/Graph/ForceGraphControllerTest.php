<?php

namespace Tests\Feature\Livewire\Graph;

use App\Livewire\Graph\ForceGraphController;
use App\Services\GraphDatabaseService;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class ForceGraphControllerTest extends TestCase
{
    protected $graphService;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock the GraphDatabaseService
        $this->graphService = Mockery::mock(GraphDatabaseService::class);
        $this->app->instance(GraphDatabaseService::class, $this->graphService);
    }

    /** @test */
    public function it_has_arguments_property(): void
    {
        Livewire::test(ForceGraphController::class)
            ->assertSet('arguments', []);
    }

    /** @test */
    public function it_has_evidence_property(): void
    {
        Livewire::test(ForceGraphController::class)
            ->assertSet('evidence', []);
    }

    /** @test */
    public function it_has_timeline_property(): void
    {
        Livewire::test(ForceGraphController::class)
            ->assertSet('timeline', []);
    }

    /** @test */
    public function it_loads_arguments_when_court_decision_node_selected(): void
    {
        $mockArguments = [
            ['content' => 'Plaintiff argument 1', 'party_type' => 'plaintiff'],
            ['content' => 'Defendant argument 1', 'party_type' => 'defendant'],
            ['content' => 'Court reasoning 1', 'party_type' => 'court'],
        ];

        $this->graphService->shouldReceive('getArgumentsForDecision')
            ->once()
            ->with('decision-123')
            ->andReturn($mockArguments);

        $this->graphService->shouldReceive('getEvidenceForDecision')
            ->once()
            ->with('decision-123')
            ->andReturn([]);

        $this->graphService->shouldReceive('getDateEventsForDecision')
            ->once()
            ->with('decision-123')
            ->andReturn([]);

        Livewire::test(ForceGraphController::class)
            ->call('selectNode', [
                'id' => 'decision-123',
                'type' => 'CourtDecisionDocument',
            ])
            ->assertSet('selectedNodeId', 'decision-123')
            ->assertSet('arguments', [
                'plaintiff' => [
                    ['content' => 'Plaintiff argument 1', 'party_type' => 'plaintiff'],
                ],
                'defendant' => [
                    ['content' => 'Defendant argument 1', 'party_type' => 'defendant'],
                ],
                'court' => [
                    ['content' => 'Court reasoning 1', 'party_type' => 'court'],
                ],
            ]);
    }

    /** @test */
    public function it_clears_arguments_when_non_decision_node_selected(): void
    {
        // First, select a decision node to populate arguments
        $this->graphService->shouldReceive('getArgumentsForDecision')
            ->once()
            ->with('decision-123')
            ->andReturn([
                ['content' => 'Some argument', 'party_type' => 'plaintiff'],
            ]);

        $this->graphService->shouldReceive('getEvidenceForDecision')
            ->once()
            ->with('decision-123')
            ->andReturn([]);

        $this->graphService->shouldReceive('getDateEventsForDecision')
            ->once()
            ->with('decision-123')
            ->andReturn([]);

        $component = Livewire::test(ForceGraphController::class)
            ->call('selectNode', [
                'id' => 'decision-123',
                'type' => 'CourtDecisionDocument',
            ])
            ->assertSet('arguments.plaintiff.0.content', 'Some argument');

        // Then select a non-decision node
        $component->call('selectNode', [
            'id' => 'law-456',
            'type' => 'LawDocument',
        ])
            ->assertSet('selectedNodeId', 'law-456')
            ->assertSet('arguments', []);
    }

    /** @test */
    public function it_handles_empty_arguments_response(): void
    {
        $this->graphService->shouldReceive('getArgumentsForDecision')
            ->once()
            ->with('decision-789')
            ->andReturn([]);

        $this->graphService->shouldReceive('getEvidenceForDecision')
            ->once()
            ->with('decision-789')
            ->andReturn([]);

        $this->graphService->shouldReceive('getDateEventsForDecision')
            ->once()
            ->with('decision-789')
            ->andReturn([]);

        Livewire::test(ForceGraphController::class)
            ->call('selectNode', [
                'id' => 'decision-789',
                'type' => 'CourtDecisionDocument',
            ])
            ->assertSet('selectedNodeId', 'decision-789')
            ->assertSet('arguments', [
                'plaintiff' => [],
                'defendant' => [],
                'court' => [],
            ]);
    }

    /** @test */
    public function it_groups_multiple_arguments_by_party_type(): void
    {
        $mockArguments = [
            ['content' => 'Plaintiff argument 1', 'party_type' => 'plaintiff'],
            ['content' => 'Plaintiff argument 2', 'party_type' => 'plaintiff'],
            ['content' => 'Defendant argument 1', 'party_type' => 'defendant'],
            ['content' => 'Court reasoning 1', 'party_type' => 'court'],
            ['content' => 'Court reasoning 2', 'party_type' => 'court'],
        ];

        $this->graphService->shouldReceive('getArgumentsForDecision')
            ->once()
            ->with('decision-999')
            ->andReturn($mockArguments);

        $this->graphService->shouldReceive('getEvidenceForDecision')
            ->once()
            ->with('decision-999')
            ->andReturn([]);

        $this->graphService->shouldReceive('getDateEventsForDecision')
            ->once()
            ->with('decision-999')
            ->andReturn([]);

        Livewire::test(ForceGraphController::class)
            ->call('selectNode', [
                'id' => 'decision-999',
                'type' => 'CourtDecisionDocument',
            ])
            ->assertSet('arguments.plaintiff', [
                ['content' => 'Plaintiff argument 1', 'party_type' => 'plaintiff'],
                ['content' => 'Plaintiff argument 2', 'party_type' => 'plaintiff'],
            ])
            ->assertSet('arguments.defendant', [
                ['content' => 'Defendant argument 1', 'party_type' => 'defendant'],
            ])
            ->assertSet('arguments.court', [
                ['content' => 'Court reasoning 1', 'party_type' => 'court'],
                ['content' => 'Court reasoning 2', 'party_type' => 'court'],
            ]);
    }

    /** @test */
    public function it_loads_evidence_when_court_decision_node_selected(): void
    {
        $mockEvidence = [
            ['description' => 'Contract dated 2023-01-15', 'evidence_type' => 'documentary', 'source' => 'Exhibit A'],
            ['description' => 'Witness testimony from John Doe', 'evidence_type' => 'testimonial', 'source' => 'Transcript p.42'],
            ['description' => 'Expert opinion on damages', 'evidence_type' => 'expert', 'source' => 'Dr. Smith Report'],
            ['description' => 'Physical evidence item', 'evidence_type' => 'physical', 'source' => 'Evidence locker #123'],
        ];

        $this->graphService->shouldReceive('getArgumentsForDecision')
            ->once()
            ->with('decision-123')
            ->andReturn([]);

        $this->graphService->shouldReceive('getEvidenceForDecision')
            ->once()
            ->with('decision-123')
            ->andReturn($mockEvidence);

        $this->graphService->shouldReceive('getDateEventsForDecision')
            ->once()
            ->with('decision-123')
            ->andReturn([]);

        Livewire::test(ForceGraphController::class)
            ->call('selectNode', [
                'id' => 'decision-123',
                'type' => 'CourtDecisionDocument',
            ])
            ->assertSet('selectedNodeId', 'decision-123')
            ->assertSet('evidence', [
                'documentary' => [
                    ['description' => 'Contract dated 2023-01-15', 'evidence_type' => 'documentary', 'source' => 'Exhibit A'],
                ],
                'testimonial' => [
                    ['description' => 'Witness testimony from John Doe', 'evidence_type' => 'testimonial', 'source' => 'Transcript p.42'],
                ],
                'expert' => [
                    ['description' => 'Expert opinion on damages', 'evidence_type' => 'expert', 'source' => 'Dr. Smith Report'],
                ],
                'physical' => [
                    ['description' => 'Physical evidence item', 'evidence_type' => 'physical', 'source' => 'Evidence locker #123'],
                ],
            ]);
    }

    /** @test */
    public function it_clears_evidence_when_non_decision_node_selected(): void
    {
        // First, select a decision node to populate evidence
        $this->graphService->shouldReceive('getArgumentsForDecision')
            ->once()
            ->with('decision-123')
            ->andReturn([]);

        $this->graphService->shouldReceive('getEvidenceForDecision')
            ->once()
            ->with('decision-123')
            ->andReturn([
                ['description' => 'Some evidence', 'evidence_type' => 'documentary'],
            ]);

        $this->graphService->shouldReceive('getDateEventsForDecision')
            ->once()
            ->with('decision-123')
            ->andReturn([]);

        $component = Livewire::test(ForceGraphController::class)
            ->call('selectNode', [
                'id' => 'decision-123',
                'type' => 'CourtDecisionDocument',
            ])
            ->assertSet('evidence.documentary.0.description', 'Some evidence');

        // Then select a non-decision node
        $component->call('selectNode', [
            'id' => 'law-456',
            'type' => 'LawDocument',
        ])
            ->assertSet('selectedNodeId', 'law-456')
            ->assertSet('evidence', []);
    }

    /** @test */
    public function it_handles_empty_evidence_response(): void
    {
        $this->graphService->shouldReceive('getArgumentsForDecision')
            ->once()
            ->with('decision-789')
            ->andReturn([]);

        $this->graphService->shouldReceive('getEvidenceForDecision')
            ->once()
            ->with('decision-789')
            ->andReturn([]);

        $this->graphService->shouldReceive('getDateEventsForDecision')
            ->once()
            ->with('decision-789')
            ->andReturn([]);

        Livewire::test(ForceGraphController::class)
            ->call('selectNode', [
                'id' => 'decision-789',
                'type' => 'CourtDecisionDocument',
            ])
            ->assertSet('selectedNodeId', 'decision-789')
            ->assertSet('evidence', [
                'documentary' => [],
                'testimonial' => [],
                'expert' => [],
                'physical' => [],
            ]);
    }

    /** @test */
    public function it_groups_multiple_evidence_items_by_type(): void
    {
        $mockEvidence = [
            ['description' => 'Contract 1', 'evidence_type' => 'documentary'],
            ['description' => 'Contract 2', 'evidence_type' => 'documentary'],
            ['description' => 'Witness 1', 'evidence_type' => 'testimonial'],
            ['description' => 'Expert report 1', 'evidence_type' => 'expert'],
            ['description' => 'Expert report 2', 'evidence_type' => 'expert'],
        ];

        $this->graphService->shouldReceive('getArgumentsForDecision')
            ->once()
            ->with('decision-999')
            ->andReturn([]);

        $this->graphService->shouldReceive('getEvidenceForDecision')
            ->once()
            ->with('decision-999')
            ->andReturn($mockEvidence);

        $this->graphService->shouldReceive('getDateEventsForDecision')
            ->once()
            ->with('decision-999')
            ->andReturn([]);

        Livewire::test(ForceGraphController::class)
            ->call('selectNode', [
                'id' => 'decision-999',
                'type' => 'CourtDecisionDocument',
            ])
            ->assertSet('evidence.documentary', [
                ['description' => 'Contract 1', 'evidence_type' => 'documentary'],
                ['description' => 'Contract 2', 'evidence_type' => 'documentary'],
            ])
            ->assertSet('evidence.testimonial', [
                ['description' => 'Witness 1', 'evidence_type' => 'testimonial'],
            ])
            ->assertSet('evidence.expert', [
                ['description' => 'Expert report 1', 'evidence_type' => 'expert'],
                ['description' => 'Expert report 2', 'evidence_type' => 'expert'],
            ])
            ->assertSet('evidence.physical', []);
    }

    /** @test */
    public function it_loads_timeline_when_court_decision_node_selected(): void
    {
        $mockTimeline = [
            ['date' => '2023-03-15', 'event_type' => 'hearing', 'description' => 'Court hearing'],
            ['date' => '2023-01-10', 'event_type' => 'filing', 'description' => 'Case filed'],
            ['date' => '2023-05-20', 'event_type' => 'judgment', 'description' => 'Judgment issued'],
        ];

        $this->graphService->shouldReceive('getArgumentsForDecision')
            ->once()
            ->with('decision-123')
            ->andReturn([]);

        $this->graphService->shouldReceive('getEvidenceForDecision')
            ->once()
            ->with('decision-123')
            ->andReturn([]);

        $this->graphService->shouldReceive('getDateEventsForDecision')
            ->once()
            ->with('decision-123')
            ->andReturn($mockTimeline);

        Livewire::test(ForceGraphController::class)
            ->call('selectNode', [
                'id' => 'decision-123',
                'type' => 'CourtDecisionDocument',
            ])
            ->assertSet('selectedNodeId', 'decision-123')
            ->assertSet('timeline', [
                ['date' => '2023-01-10', 'event_type' => 'filing', 'description' => 'Case filed'],
                ['date' => '2023-03-15', 'event_type' => 'hearing', 'description' => 'Court hearing'],
                ['date' => '2023-05-20', 'event_type' => 'judgment', 'description' => 'Judgment issued'],
            ]);
    }

    /** @test */
    public function it_clears_timeline_when_non_decision_node_selected(): void
    {
        // First, select a decision node to populate timeline
        $this->graphService->shouldReceive('getArgumentsForDecision')
            ->once()
            ->with('decision-123')
            ->andReturn([]);

        $this->graphService->shouldReceive('getEvidenceForDecision')
            ->once()
            ->with('decision-123')
            ->andReturn([]);

        $this->graphService->shouldReceive('getDateEventsForDecision')
            ->once()
            ->with('decision-123')
            ->andReturn([
                ['date' => '2023-01-10', 'event_type' => 'filing', 'description' => 'Case filed'],
            ]);

        $component = Livewire::test(ForceGraphController::class)
            ->call('selectNode', [
                'id' => 'decision-123',
                'type' => 'CourtDecisionDocument',
            ])
            ->assertSet('timeline.0.description', 'Case filed');

        // Then select a non-decision node
        $component->call('selectNode', [
            'id' => 'law-456',
            'type' => 'LawDocument',
        ])
            ->assertSet('selectedNodeId', 'law-456')
            ->assertSet('timeline', []);
    }

    /** @test */
    public function it_handles_empty_timeline_response(): void
    {
        $this->graphService->shouldReceive('getArgumentsForDecision')
            ->once()
            ->with('decision-789')
            ->andReturn([]);

        $this->graphService->shouldReceive('getEvidenceForDecision')
            ->once()
            ->with('decision-789')
            ->andReturn([]);

        $this->graphService->shouldReceive('getDateEventsForDecision')
            ->once()
            ->with('decision-789')
            ->andReturn([]);

        Livewire::test(ForceGraphController::class)
            ->call('selectNode', [
                'id' => 'decision-789',
                'type' => 'CourtDecisionDocument',
            ])
            ->assertSet('selectedNodeId', 'decision-789')
            ->assertSet('timeline', []);
    }

    /** @test */
    public function it_sorts_timeline_events_by_date_ascending(): void
    {
        $mockTimeline = [
            ['date' => '2023-05-20', 'event_type' => 'judgment', 'description' => 'Final judgment'],
            ['date' => '2023-01-10', 'event_type' => 'filing', 'description' => 'Initial filing'],
            ['date' => '2023-03-15', 'event_type' => 'hearing', 'description' => 'First hearing'],
            ['date' => '2023-02-20', 'event_type' => 'hearing', 'description' => 'Preliminary hearing'],
            ['date' => '2023-06-01', 'event_type' => 'appeal', 'description' => 'Appeal filed'],
        ];

        $this->graphService->shouldReceive('getArgumentsForDecision')
            ->once()
            ->with('decision-999')
            ->andReturn([]);

        $this->graphService->shouldReceive('getEvidenceForDecision')
            ->once()
            ->with('decision-999')
            ->andReturn([]);

        $this->graphService->shouldReceive('getDateEventsForDecision')
            ->once()
            ->with('decision-999')
            ->andReturn($mockTimeline);

        Livewire::test(ForceGraphController::class)
            ->call('selectNode', [
                'id' => 'decision-999',
                'type' => 'CourtDecisionDocument',
            ])
            ->assertSet('timeline.0.date', '2023-01-10')
            ->assertSet('timeline.1.date', '2023-02-20')
            ->assertSet('timeline.2.date', '2023-03-15')
            ->assertSet('timeline.3.date', '2023-05-20')
            ->assertSet('timeline.4.date', '2023-06-01');
    }

    /** @test */
    public function it_initializes_with_default_panel_arguments(): void
    {
        Livewire::test(ForceGraphController::class)
            ->assertSet('activePanel', 'arguments');
    }

    /** @test */
    public function it_accepts_panel_parameter_in_mount(): void
    {
        Livewire::test(ForceGraphController::class, ['panel' => 'evidence'])
            ->assertSet('activePanel', 'evidence');
    }

    /** @test */
    public function it_accepts_timeline_panel_parameter(): void
    {
        Livewire::test(ForceGraphController::class, ['panel' => 'timeline'])
            ->assertSet('activePanel', 'timeline');
    }

    /** @test */
    public function it_accepts_arguments_panel_parameter(): void
    {
        Livewire::test(ForceGraphController::class, ['panel' => 'arguments'])
            ->assertSet('activePanel', 'arguments');
    }

    /** @test */
    public function it_refreshes_graph_when_root_node_is_set(): void
    {
        $mockGraphData = [
            'nodes' => [
                ['id' => 'node-1', 'label' => 'Test Node'],
            ],
            'edges' => [
                ['from' => 'node-1', 'to' => 'node-2'],
            ],
        ];

        $this->graphService->shouldReceive('getNodeWithConnections')
            ->with('root-123', 1)
            ->twice() // Once for mount, once for refresh
            ->andReturn($mockGraphData);

        $component = Livewire::test(ForceGraphController::class, ['rootNodeId' => 'root-123'])
            ->assertSet('rootNodeId', 'root-123')
            ->assertSet('graphData', $mockGraphData);

        // Call refreshGraph
        $component->call('refreshGraph')
            ->assertSet('graphData', $mockGraphData)
            ->assertDispatched('graph-data-loaded');
    }

    /** @test */
    public function it_does_not_error_when_refresh_called_without_root_node(): void
    {
        // Should not call getNodeWithConnections when no rootNodeId
        $this->graphService->shouldNotReceive('getNodeWithConnections');

        Livewire::test(ForceGraphController::class)
            ->assertSet('rootNodeId', null)
            ->call('refreshGraph')
            ->assertSet('graphData', ['nodes' => [], 'edges' => []]);
    }

    /** @test */
    public function it_can_dispatch_refresh_graph_event(): void
    {
        $mockGraphData = [
            'nodes' => [
                ['id' => 'node-1', 'label' => 'Test Node'],
            ],
            'edges' => [],
        ];

        $this->graphService->shouldReceive('getNodeWithConnections')
            ->with('root-456', 1)
            ->twice()
            ->andReturn($mockGraphData);

        // Test that the event listener is properly registered
        Livewire::test(ForceGraphController::class, ['rootNodeId' => 'root-456'])
            ->dispatch('refresh-graph')
            ->assertSet('graphData', $mockGraphData);
    }

    /** @test */
    public function it_expands_node_with_relationship_filter(): void
    {
        $explorerService = Mockery::mock(\App\Services\Graph\GraphExplorerService::class);

        $explorerService->shouldReceive('getFilteredConnections')
            ->once()
            ->with('node-123', ['CITES'], 50)
            ->andReturn([
                [
                    'node' => ['id' => 'n1', 'type' => 'Decision', 'label' => 'Related Decision'],
                    'rel' => ['type' => 'CITES', 'properties' => []],
                ],
                [
                    'node' => ['id' => 'n2', 'type' => 'Law', 'label' => 'Related Law'],
                    'rel' => ['type' => 'CITES', 'properties' => []],
                ],
            ]);

        $this->app->instance(\App\Services\Graph\GraphExplorerService::class, $explorerService);

        // Initialize with some existing nodes
        $this->graphService->shouldReceive('getNodeWithConnections')
            ->with('root-node', 1)
            ->andReturn([
                'nodes' => [['id' => 'root-node', 'type' => 'Decision', 'label' => 'Root']],
                'edges' => [],
            ]);

        Livewire::test(ForceGraphController::class, ['rootNodeId' => 'root-node'])
            ->call('expandNodeFiltered', 'node-123', ['CITES'])
            ->assertDispatched('filtered-nodes-loaded', function ($event, $data) {
                return count($data['nodes']) === 2
                    && count($data['edges']) === 2
                    && $data['nodes'][0]['id'] === 'n1'
                    && $data['nodes'][1]['id'] === 'n2'
                    && $data['edges'][0]['source'] === 'node-123'
                    && $data['edges'][0]['target'] === 'n1'
                    && $data['edges'][0]['type'] === 'CITES';
            });
    }

    /** @test */
    public function it_tracks_pinned_node_for_authenticated_user(): void
    {
        $sessionService = Mockery::mock(\App\Services\Graph\ResearchSessionService::class);
        $this->app->instance(\App\Services\Graph\ResearchSessionService::class, $sessionService);

        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        $nodeData = [
            'id' => 'node-456',
            'type' => 'CourtDecisionDocument',
            'properties' => ['title' => 'Important Case'],
        ];

        $sessionService->shouldReceive('trackPinnedNode')
            ->once()
            ->with($nodeData);

        Livewire::test(ForceGraphController::class)
            ->dispatch('pin-node', node: $nodeData);
    }

    /** @test */
    public function it_does_not_error_when_pinning_node_as_guest(): void
    {
        $sessionService = Mockery::mock(\App\Services\Graph\ResearchSessionService::class);
        $this->app->instance(\App\Services\Graph\ResearchSessionService::class, $sessionService);

        // No user authenticated
        $sessionService->shouldNotReceive('trackPinnedNode');

        $nodeData = [
            'id' => 'node-789',
            'type' => 'LawDocument',
        ];

        Livewire::test(ForceGraphController::class)
            ->dispatch('pin-node', node: $nodeData);
    }

    /** @test */
    public function it_unpins_node_for_authenticated_user(): void
    {
        $sessionService = Mockery::mock(\App\Services\Graph\ResearchSessionService::class);
        $this->app->instance(\App\Services\Graph\ResearchSessionService::class, $sessionService);

        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        $sessionService->shouldReceive('unpinNode')
            ->once()
            ->with('node-123');

        Livewire::test(ForceGraphController::class)
            ->dispatch('unpin-node', nodeId: 'node-123');
    }

    /** @test */
    public function it_does_not_error_when_unpinning_node_as_guest(): void
    {
        $sessionService = Mockery::mock(\App\Services\Graph\ResearchSessionService::class);
        $this->app->instance(\App\Services\Graph\ResearchSessionService::class, $sessionService);

        // No user authenticated
        $sessionService->shouldNotReceive('unpinNode');

        Livewire::test(ForceGraphController::class)
            ->dispatch('unpin-node', nodeId: 'node-456');
    }

    /** @test */
    public function it_saves_session_for_authenticated_user(): void
    {
        $sessionService = Mockery::mock(\App\Services\Graph\ResearchSessionService::class);
        $this->app->instance(\App\Services\Graph\ResearchSessionService::class, $sessionService);

        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        $mockSession = Mockery::mock(\App\Models\ResearchSession::class);
        $mockSession->shouldReceive('toArray')
            ->once()
            ->andReturn([
                'id' => 1,
                'name' => 'My Research',
                'description' => 'Test description',
                'user_id' => $user->id,
            ]);

        $sessionService->shouldReceive('saveSession')
            ->once()
            ->with('My Research', 'Test description')
            ->andReturn($mockSession);

        Livewire::test(ForceGraphController::class)
            ->call('saveSession', 'My Research', 'Test description')
            ->assertDispatched('session-saved', function ($event, $data) {
                return $data['session']['name'] === 'My Research';
            });
    }

    /** @test */
    public function it_does_not_error_when_saving_session_as_guest(): void
    {
        $sessionService = Mockery::mock(\App\Services\Graph\ResearchSessionService::class);
        $this->app->instance(\App\Services\Graph\ResearchSessionService::class, $sessionService);

        // No user authenticated
        $sessionService->shouldNotReceive('saveSession');

        Livewire::test(ForceGraphController::class)
            ->call('saveSession', 'My Research', 'Test description')
            ->assertNotDispatched('session-saved');
    }

    /** @test */
    public function it_loads_session_for_authenticated_user(): void
    {
        $sessionService = Mockery::mock(\App\Services\Graph\ResearchSessionService::class);
        $this->app->instance(\App\Services\Graph\ResearchSessionService::class, $sessionService);

        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        $session = \App\Models\ResearchSession::factory()->create([
            'user_id' => $user->id,
            'name' => 'My Research',
            'root_node_id' => 'node-123',
        ]);

        $sessionService->shouldReceive('setCurrentSession')
            ->once()
            ->with($session->id);

        $this->graphService->shouldReceive('getNodeWithConnections')
            ->once()
            ->with('node-123', 1)
            ->andReturn([
                'nodes' => [['id' => 'node-123', 'label' => 'Root Node']],
                'edges' => [],
            ]);

        Livewire::test(ForceGraphController::class)
            ->call('loadSession', $session->id)
            ->assertSet('sessionId', $session->id)
            ->assertDispatched('session-loaded', function ($event, $data) use ($session) {
                return $data['session']['id'] === $session->id;
            })
            ->assertDispatched('graph-data-loaded');
    }

    /** @test */
    public function it_does_not_load_session_for_different_user(): void
    {
        $sessionService = Mockery::mock(\App\Services\Graph\ResearchSessionService::class);
        $this->app->instance(\App\Services\Graph\ResearchSessionService::class, $sessionService);

        $user1 = \App\Models\User::factory()->create();
        $user2 = \App\Models\User::factory()->create();

        $this->actingAs($user1);

        // Session belongs to user2
        $session = \App\Models\ResearchSession::factory()->create([
            'user_id' => $user2->id,
            'name' => 'Other User Research',
        ]);

        $sessionService->shouldNotReceive('setCurrentSession');
        $this->graphService->shouldNotReceive('getNodeWithConnections');

        Livewire::test(ForceGraphController::class)
            ->call('loadSession', $session->id)
            ->assertSet('sessionId', null)
            ->assertNotDispatched('session-loaded');
    }

    /** @test */
    public function it_loads_session_without_root_node(): void
    {
        $sessionService = Mockery::mock(\App\Services\Graph\ResearchSessionService::class);
        $this->app->instance(\App\Services\Graph\ResearchSessionService::class, $sessionService);

        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        // Session without root_node_id
        $session = \App\Models\ResearchSession::factory()->create([
            'user_id' => $user->id,
            'name' => 'My Research',
            'root_node_id' => null,
        ]);

        $sessionService->shouldReceive('setCurrentSession')
            ->once()
            ->with($session->id);

        // Should not call loadInitialGraph when no root_node_id
        $this->graphService->shouldNotReceive('getNodeWithConnections');

        Livewire::test(ForceGraphController::class)
            ->call('loadSession', $session->id)
            ->assertSet('sessionId', $session->id)
            ->assertDispatched('session-loaded');
    }

    /** @test */
    public function it_returns_saved_sessions_for_authenticated_user(): void
    {
        $sessionService = Mockery::mock(\App\Services\Graph\ResearchSessionService::class);
        $this->app->instance(\App\Services\Graph\ResearchSessionService::class, $sessionService);

        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        $mockSessions = [
            ['id' => 1, 'name' => 'Research 1'],
            ['id' => 2, 'name' => 'Research 2'],
        ];

        $sessionService->shouldReceive('getSavedSessions')
            ->once()
            ->andReturn($mockSessions);

        $component = new ForceGraphController();
        $result = $component->getSavedSessions();

        $this->assertEquals($mockSessions, $result);
    }

    /** @test */
    public function it_returns_empty_array_when_getting_sessions_as_guest(): void
    {
        $sessionService = Mockery::mock(\App\Services\Graph\ResearchSessionService::class);
        $this->app->instance(\App\Services\Graph\ResearchSessionService::class, $sessionService);

        // No user authenticated
        $sessionService->shouldNotReceive('getSavedSessions');

        $component = new ForceGraphController();
        $result = $component->getSavedSessions();

        $this->assertEquals([], $result);
    }
}
