<?php

namespace App\Livewire\Graph;

use App\Models\ResearchSession;
use App\Services\Graph\ContradictionRadarService;
use App\Services\Graph\GraphExplorerService;
use App\Services\Graph\ResearchSessionService;
use App\Services\GraphDatabaseService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class ForceGraphController extends Component
{
    public array $graphData = ['nodes' => [], 'edges' => []];

    public ?string $selectedNodeId = null;

    public ?string $rootNodeId = null;

    public string $activePanel = 'arguments';

    public array $arguments = [];

    public array $evidence = [];

    public array $timeline = [];

    public ?int $sessionId = null;

    public function mount(?string $rootNodeId = null, string $panel = 'arguments'): void
    {
        $this->rootNodeId = $rootNodeId;
        $this->activePanel = $panel;

        if ($rootNodeId) {
            $this->loadInitialGraph($rootNodeId);
        } else {
            $this->loadOverviewGraph();
        }
    }

    public function loadOverviewGraph(): void
    {
        try {
            $explorerService = app(GraphExplorerService::class);
            $this->graphData = $explorerService->getOverviewGraph(30);
            $this->dispatch('graph-data-loaded', graphData: $this->graphData);
        } catch (\Exception $e) {
            // Graph may be empty or Neo4j unavailable - leave empty state
            $this->graphData = ['nodes' => [], 'edges' => []];
        }
    }

    public function loadInitialGraph(string $nodeId): void
    {
        $graphService = app(GraphDatabaseService::class);

        // Load node and immediate connections
        $this->graphData = $graphService->getNodeWithConnections($nodeId, 1);
        $this->dispatch('graph-data-loaded', graphData: $this->graphData);
    }

    public function searchNodes(string $query): array
    {
        if (strlen(trim($query)) < 2) {
            return [];
        }

        try {
            $explorerService = app(GraphExplorerService::class);

            return $explorerService->searchNodes($query, 20);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function loadNodeIntoGraph(string $nodeId): void
    {
        $graphService = app(GraphDatabaseService::class);

        $newData = $graphService->getNodeWithConnections($nodeId, 1);

        // Merge into existing data
        $existingIds = array_column($this->graphData['nodes'], 'id');
        foreach ($newData['nodes'] as $node) {
            if (! in_array($node['id'], $existingIds)) {
                $this->graphData['nodes'][] = $node;
                $existingIds[] = $node['id'];
            }
        }
        $this->graphData['edges'] = array_merge(
            $this->graphData['edges'],
            $newData['edges']
        );

        $this->rootNodeId = $nodeId;

        $this->dispatch('graph-data-loaded', graphData: $this->graphData);
    }

    #[On('expand-node')]
    public function expandNode(string $nodeId): void
    {
        $graphService = app(GraphDatabaseService::class);

        // Get connected nodes not already loaded
        $existingIds = array_column($this->graphData['nodes'], 'id');

        // Track expanded node in research session
        if (Auth::check()) {
            $sessionService = app(ResearchSessionService::class);
            $sessionService->trackExpandedNode($nodeId);
        }

        $newData = $graphService->getConnectedNodes($nodeId, $existingIds);

        // Merge into existing data
        $this->graphData['nodes'] = array_merge(
            $this->graphData['nodes'],
            $newData['nodes']
        );
        $this->graphData['edges'] = array_merge(
            $this->graphData['edges'],
            $newData['edges']
        );

        $this->dispatch(
            'connected-nodes-loaded',
            nodes: $newData['nodes'],
            edges: $newData['edges']
        );
    }

    #[On('expand-node-filtered')]
    public function expandNodeFiltered(string $nodeId, array $relationshipTypes): void
    {
        $explorerService = app(GraphExplorerService::class);

        $existingIds = array_column($this->graphData['nodes'], 'id');
        $connections = $explorerService->getFilteredConnections($nodeId, $relationshipTypes, 50);

        // Track expanded node in research session
        if (Auth::check()) {
            $sessionService = app(ResearchSessionService::class);
            $sessionService->trackExpandedNode($nodeId);
        }

        $newNodes = [];
        $newEdges = [];

        foreach ($connections as $conn) {
            if (!in_array($conn['node']['id'], $existingIds)) {
                $newNodes[] = $conn['node'];
            }
            $newEdges[] = [
                'source' => $nodeId,
                'target' => $conn['node']['id'],
                'type' => $conn['rel']['type'],
            ];
        }

        $this->graphData['nodes'] = array_merge($this->graphData['nodes'], $newNodes);
        $this->graphData['edges'] = array_merge($this->graphData['edges'], $newEdges);

        $this->dispatch('filtered-nodes-loaded', nodes: $newNodes, edges: $newEdges);
    }

    #[On('pin-node')]
    public function pinNode(array $node): void
    {
        if (Auth::check()) {
            $sessionService = app(ResearchSessionService::class);
            $sessionService->trackPinnedNode($node);

            // Scan for alerts on pin (Phase 3: Contradiction Radar)
            $radarService = app(ContradictionRadarService::class);
            $session = $sessionService->getCurrentSession();
            $alerts = $radarService->scanNode($node['id'], $node['type'] ?? 'Unknown');

            if (! empty($alerts)) {
                $radarService->addAlertsToSession($session, $alerts);
                $this->dispatch('alerts-updated', alerts: $session->fresh()->alerts);
            }
        }
    }

    #[On('unpin-node')]
    public function unpinNode(string $nodeId): void
    {
        if (Auth::check()) {
            $sessionService = app(ResearchSessionService::class);
            $sessionService->unpinNode($nodeId);
        }
    }

    #[On('update-filter-settings')]
    public function updateFilterSettings(array $settings): void
    {
        if (Auth::check()) {
            $sessionService = app(ResearchSessionService::class);
            $sessionService->updateFilterSettings($settings);
        }
    }

    public function saveSession(string $name, ?string $description = null): void
    {
        if (!Auth::check()) {
            return;
        }

        $sessionService = app(ResearchSessionService::class);
        $session = $sessionService->saveSession($name, $description);

        $this->dispatch('session-saved', session: $session->toArray());
    }

    public function loadSession(int $sessionId): void
    {
        $session = ResearchSession::find($sessionId);

        if (!$session || $session->user_id !== Auth::id()) {
            return;
        }

        $sessionService = app(ResearchSessionService::class);
        $sessionService->setCurrentSession($sessionId);

        $this->sessionId = $sessionId;

        // Load the root node if set
        if ($session->root_node_id) {
            $this->loadInitialGraph($session->root_node_id);
        }

        // Dispatch session data to frontend
        $this->dispatch('session-loaded', session: $session->toArray());
    }

    public function getSavedSessions(): array
    {
        if (!Auth::check()) {
            return [];
        }

        $sessionService = app(ResearchSessionService::class);
        return $sessionService->getSavedSessions();
    }

    #[On('node-selected')]
    public function selectNode(array $node): void
    {
        $this->selectedNodeId = $node['id'] ?? null;

        // Track viewed node in research session
        if (Auth::check()) {
            $sessionService = app(ResearchSessionService::class);
            $sessionService->trackViewedNode($node);
            $this->sessionId = $sessionService->getCurrentSession()->id;
        }

        if (($node['type'] ?? '') === 'CourtDecisionDocument') {
            $this->loadArgumentsForDecision($node['id']);
            $this->loadEvidenceForDecision($node['id']);
            $this->loadTimelineForDecision($node['id']);
        } else {
            $this->arguments = [];
            $this->evidence = [];
            $this->timeline = [];
        }
    }

    protected function loadArgumentsForDecision(string $decisionId): void
    {
        $graphService = app(GraphDatabaseService::class);

        $args = $graphService->getArgumentsForDecision($decisionId);

        $this->arguments = [
            'plaintiff' => array_values(array_filter($args, fn($a) => ($a['party_type'] ?? '') === 'plaintiff')),
            'defendant' => array_values(array_filter($args, fn($a) => ($a['party_type'] ?? '') === 'defendant')),
            'court' => array_values(array_filter($args, fn($a) => ($a['party_type'] ?? '') === 'court')),
        ];
    }

    protected function loadEvidenceForDecision(string $decisionId): void
    {
        $graphService = app(GraphDatabaseService::class);

        $items = $graphService->getEvidenceForDecision($decisionId);

        $this->evidence = [
            'documentary' => array_values(array_filter($items, fn($e) => ($e['evidence_type'] ?? '') === 'documentary')),
            'testimonial' => array_values(array_filter($items, fn($e) => ($e['evidence_type'] ?? '') === 'testimonial')),
            'expert' => array_values(array_filter($items, fn($e) => ($e['evidence_type'] ?? '') === 'expert')),
            'physical' => array_values(array_filter($items, fn($e) => ($e['evidence_type'] ?? '') === 'physical')),
        ];
    }

    protected function loadTimelineForDecision(string $decisionId): void
    {
        $graphService = app(GraphDatabaseService::class);

        $events = $graphService->getDateEventsForDecision($decisionId);

        // Sort by date ascending
        usort($events, fn($a, $b) => strtotime($a['date'] ?? '1970-01-01') <=> strtotime($b['date'] ?? '1970-01-01'));

        $this->timeline = $events;
    }

    public function searchGraph(string $query): void
    {
        if (empty(trim($query))) {
            return;
        }

        try {
            $graphService = app(GraphDatabaseService::class);
            $results = $graphService->searchNodesByText($query, 20);

            $this->dispatch('server-search-results', results: $results);
        } catch (\Exception $e) {
            // Silently fail - client-side search still works
        }
    }

    public function loadNodeGraph(string $nodeId): void
    {
        $this->rootNodeId = $nodeId;
        $this->loadInitialGraph($nodeId);
    }

    #[On('refresh-graph')]
    public function refreshGraph(): void
    {
        if ($this->rootNodeId) {
            $this->loadInitialGraph($this->rootNodeId);
        }
    }

    public function render()
    {
        return view('livewire.graph.force-graph');
    }
}
