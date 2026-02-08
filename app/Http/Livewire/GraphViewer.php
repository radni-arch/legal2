<?php

namespace App\Http\Livewire;

use App\Http\Livewire\Concerns\PreventsDuplicateRequests;
use App\Repositories\GraphMetricsRepository;
use App\Services\DecisionCitationService;
use App\Services\GraphDatabaseService;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class GraphViewer extends Component
{
    use PreventsDuplicateRequests;

    // =========================================================================
    // Validation Constants
    // =========================================================================

    private const MAX_SEARCH_LENGTH = 1000;

    private const MAX_DEPTH = 5;

    private const MIN_DEPTH = 1;

    private const ALLOWED_NODE_TYPES = [
        'LawDocument',
        'CourtDecisionDocument',
        'CaseDocument',
        'Keyword',
        'Topic',
        'Tag',
        'Jurisdiction',
        'Court',
        'Judge',
        'Party',
        'LegalConcept',
        'LegalPrinciple',
        'LegalDefinition',
        'LegalArgument',
        'Verdict',
        'Lawyer',
        'DateEvent',
        'Evidence',
        'Article',
    ];

    // =========================================================================
    // Public Properties - Search and Selection
    // =========================================================================

    public string $searchTerm = '';

    public string $searchQuery = '';

    public string $selectedNodeType = 'CourtDecisionDocument';

    public string $selectedNodeId = '';

    public ?array $selectedNode = null;

    public array $selectedNodeTypes = [];

    // =========================================================================
    // Public Properties - Graph Configuration
    // =========================================================================

    public int $depth = 2;

    public string $relationshipType = '';

    public int $limit = 50;

    public bool $includeProperties = true;

    // =========================================================================
    // Public Properties - Graph Data
    // =========================================================================

    public ?array $graphData = null;

    public ?array $statistics = null;

    public array $recentNodes = [];

    // =========================================================================
    // Public Properties - Sidebar Panel Data
    // =========================================================================

    public array $arguments = [];

    public array $evidence = [];

    public array $timeline = [];

    public string $activePanel = 'arguments';

    // =========================================================================
    // Public Properties - Graph Metrics
    // =========================================================================

    public array $influentialDecisions = [];

    public array $citationClusters = [];

    public ?array $networkStats = null;

    public bool $metricsLoaded = false;

    // =========================================================================
    // Public Properties - UI State
    // =========================================================================

    public bool $loading = false;

    public bool $hasConnectionError = false;

    public ?string $error = null;

    public string $viewMode = 'graph'; // 'graph', 'table', 'json'

    public bool $showMetrics = true;

    // =========================================================================
    // Public Properties - Citation Analysis
    // =========================================================================

    public bool $showCitationAnalysis = false;

    public string $citationOperation = 'graph'; // 'graph', 'authority', 'patterns', 'influence'

    public ?array $citationAnalysisResults = null;

    public string $analysisDecisionId = '';

    // =========================================================================
    // Public Properties - Available Options
    // =========================================================================

    public array $nodeTypes = [
        'CourtDecisionDocument' => 'Court Decisions',
        'LawDocument' => 'Laws',
        'CaseDocument' => 'Cases',
        'Court' => 'Courts',
        'Jurisdiction' => 'Jurisdictions',
        'Keyword' => 'Keywords',
        'Tag' => 'Tags',
        'Topic' => 'Topics',
        'LegalConcept' => 'Legal Concepts',
    ];

    public array $relationshipTypes = [
        '' => 'All Relationships',
        'CITES' => 'Citations',
        'REFERENCES' => 'References',
        'OVERRULES' => 'Overrules',
        'CONFIRMS' => 'Confirms',
        'MODIFIES' => 'Modifies',
        'FOLLOWS' => 'Follows Precedent',
        'DISTINGUISHES' => 'Distinguishes',
        'DECIDED_BY' => 'Decided By',
        'BELONGS_TO_JURISDICTION' => 'Belongs To Jurisdiction',
        'HAS_KEYWORD' => 'Has Keyword',
        'HAS_TAG' => 'Has Tag',
        'RELATES_TO' => 'Relates To',
        'MENTIONS' => 'Mentions',
        'SIMILAR_TO' => 'Similar To',
    ];

    // =========================================================================
    // Protected Dependencies
    // =========================================================================

    protected GraphDatabaseService $graphService;

    protected GraphMetricsRepository $metricsRepository;

    protected DecisionCitationService $citationService;

    // =========================================================================
    // Lifecycle Methods
    // =========================================================================

    public function boot(
        GraphDatabaseService $graphService,
        GraphMetricsRepository $metricsRepository,
        DecisionCitationService $citationService
    ) {
        $this->graphService = $graphService;
        $this->metricsRepository = $metricsRepository;
        $this->citationService = $citationService;
    }

    public function mount()
    {
        try {
            $this->loadStatistics();
            $this->loadRecentNodes();
            $this->loadGraphMetrics();
        } catch (\App\Exceptions\Graph\GraphConnectionException $e) {
            $this->hasConnectionError = true;
            $this->error = 'Graph database unavailable';
            Log::error('Graph database connection failed during mount', [
                'error' => $e->getMessage(),
            ]);
        } catch (\Exception $e) {
            $this->hasConnectionError = true;
            $this->error = 'Failed to initialize graph viewer';
            Log::error('Failed to initialize graph viewer', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    // =========================================================================
    // Input Validation Hooks
    // =========================================================================

    /**
     * Validate and sanitize search query on update
     */
    public function updatedSearchQuery($value): void
    {
        if (mb_strlen($value) > self::MAX_SEARCH_LENGTH) {
            $this->searchQuery = mb_substr($value, 0, self::MAX_SEARCH_LENGTH);
        }
    }

    /**
     * Validate and sanitize search term on update
     */
    public function updatedSearchTerm($value): void
    {
        if (mb_strlen($value) > self::MAX_SEARCH_LENGTH) {
            $this->searchTerm = mb_substr($value, 0, self::MAX_SEARCH_LENGTH);
        }
    }

    /**
     * Validate selected node type on update
     */
    public function updatedSelectedNodeType($value): void
    {
        if (!in_array($value, self::ALLOWED_NODE_TYPES, true)) {
            $this->selectedNodeType = 'CourtDecisionDocument'; // safe default
        }
    }

    /**
     * Validate and clamp depth parameter on update
     */
    public function updatedDepth($value): void
    {
        $this->depth = max(self::MIN_DEPTH, min(self::MAX_DEPTH, (int) $value));
    }

    /**
     * Validate and filter node types on update
     */
    public function updatedSelectedNodeTypes($value): void
    {
        if (is_array($value)) {
            $this->selectedNodeTypes = array_values(array_intersect($value, self::ALLOWED_NODE_TYPES));
        }
    }

    // =========================================================================
    // Utility
    // =========================================================================

    /**
     * Normalize Laudis\Neo4j property map or any traversable/object to a plain PHP array.
     */
    protected function normalizeProperties($props): array
    {
        if (is_array($props)) {
            return $props;
        }

        if (is_object($props)) {
            // If it's a Node/Relationship, get its properties first
            if (method_exists($props, 'getProperties')) {
                $props = $props->getProperties();
                // fall-through to convert the value of getProperties
                if (is_array($props)) {
                    return $props;
                }
            }

            if (method_exists($props, 'toArray')) {
                return (array) $props->toArray();
            }

            if ($props instanceof \Traversable) {
                return iterator_to_array($props);
            }
        }

        // Best-effort cast
        return (array) $props;
    }

    // =========================================================================
    // Search and Selection
    // =========================================================================

    public function searchNodes()
    {
        // Validate input
        if (empty(trim($this->searchTerm))) {
            $this->error = 'Please enter a search term';

            return;
        }

        // Sanitize search term
        $this->searchTerm = trim($this->searchTerm);

        // Prevent duplicate concurrent searches
        return $this->preventDuplicate('searchNodes', [$this->searchTerm, $this->selectedNodeType], function () {
            $this->loading = true;
            $this->error = null;

            try {
                $searchResults = $this->performSearch();

                if (empty($searchResults)) {
                    $this->error = 'No nodes found matching your search';
                    $this->graphData = null;
                    Log::info('Graph search returned no results', [
                        'search_term' => $this->searchTerm,
                        'node_type' => $this->selectedNodeType,
                    ]);
                } else {
                    // Select first result and load its graph
                    $firstNode = $searchResults[0];
                    $nodeId = $firstNode['id'] ?? null;

                    if (empty($nodeId)) {
                        $this->error = 'Found nodes have no ID property';
                        Log::warning('Search result missing ID', [
                            'search_term' => $this->searchTerm,
                            'node' => $firstNode,
                        ]);

                        return;
                    }

                    $this->selectedNodeId = $nodeId;
                    $this->loadNodeGraph($this->selectedNodeType, $this->selectedNodeId);
                }
            } catch (\Exception $e) {
                Log::error('Graph search failed', [
                    'search_term' => $this->searchTerm,
                    'node_type' => $this->selectedNodeType,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $this->error = 'Search failed: '.$e->getMessage();
                $this->graphData = null;
            } finally {
                $this->loading = false;
            }
        });
    }

    /**
     * Alias for searchNodes() method
     */
    public function search()
    {
        return $this->searchNodes();
    }

    protected function performSearch(): array
    {
        $label = $this->selectedNodeType;
        $searchTerm = $this->searchTerm;

        // Build search query based on node type
        $query = match ($label) {
            'CourtDecisionDocument' => "
                MATCH (n:$label)
                WHERE n.case_number CONTAINS \$term
                   OR n.ecli CONTAINS \$term
                   OR n.title CONTAINS \$term
                   OR n.court CONTAINS \$term
                RETURN n
                LIMIT 20
            ",
            'LawDocument' => "
                MATCH (n:$label)
                WHERE n.law_number CONTAINS \$term
                   OR n.title CONTAINS \$term
                RETURN n
                LIMIT 20
            ",
            'Court', 'Jurisdiction', 'Keyword', 'Tag', 'Topic', 'LegalConcept' => "
                MATCH (n:$label)
                WHERE n.name CONTAINS \$term
                RETURN n
                LIMIT 20
            ",
            default => "
                MATCH (n:$label)
                WHERE n.title CONTAINS \$term
                   OR n.id CONTAINS \$term
                RETURN n
                LIMIT 20
            ",
        };

        $result = $this->graphService->run($query, ['term' => $searchTerm]);

        return $result->map(fn ($record) => $this->normalizeProperties($record->get('n')->getProperties()))->toArray();
    }

    public function loadNodeGraph(?string $label = null, ?string $id = null)
    {
        $label = $label ?? $this->selectedNodeType;
        $id = $id ?? $this->selectedNodeId;

        if (empty($id)) {
            $this->error = 'Please select a node';

            return;
        }

        // Prevent duplicate concurrent graph loads
        return $this->preventDuplicate('loadNodeGraph', [$label, $id], function () use ($label, $id) {
            $this->loading = true;
            $this->error = null;

            try {
                // Get node with all its relationships
                $graphData = $this->fetchGraphData($label, $id);

                if ($graphData) {
                    $this->graphData = $graphData;
                    $this->selectedNode = $graphData['center'];
                    // Inform front-end to (re)render graph after Livewire DOM update
                    try {
                        // Livewire v3
                        $this->dispatch('graph-data-updated', graphData: $this->graphData);
                    } catch (\Throwable $e) {
                        // Livewire v2 fallback
                        if (method_exists($this, 'dispatchBrowserEvent')) {
                            $this->dispatchBrowserEvent('graph-data-updated', ['graphData' => $this->graphData]);
                        }
                    }

                    $this->addToRecentNodes($label, $id, $graphData['center']);
                } else {
                    $this->error = 'Node not found';
                    $this->graphData = null;
                }
            } catch (\Exception $e) {
                Log::error('Failed to load graph', [
                    'label' => $label,
                    'id' => $id,
                    'error' => $e->getMessage(),
                ]);
                $this->error = 'Failed to load graph: '.$e->getMessage();
                $this->graphData = null;
            } finally {
                $this->loading = false;
            }
        });
    }

    protected function fetchGraphData(string $label, string $id): ?array
    {
        // Validate parameters
        if (empty($label) || empty($id)) {
            Log::warning('fetchGraphData called with empty parameters', [
                'label' => $label,
                'id' => $id,
            ]);

            return null;
        }

        // Validate depth and limit to prevent performance issues
        $depth = max(1, min(3, (int) $this->depth));
        $limit = max(10, min(200, (int) $this->limit));

        // Build relationship pattern based on selected relationship type
        $relPattern = $this->relationshipType
            ? "-[r:$this->relationshipType*1..$depth]-"
            : "-[r*1..$depth]-";

        // Query to get center node and all connected nodes
        // Filter out nodes without 'id' property to prevent processing errors
        $query = "
            MATCH (center:$label {id: \$id})
            OPTIONAL MATCH (center)$relPattern(connected)
            WHERE connected.id IS NOT NULL
            WITH center, collect(DISTINCT connected) as connectedNodes
            OPTIONAL MATCH (center)-[rel]->(target)
            WHERE target.id IS NOT NULL
            WITH center, connectedNodes, collect(DISTINCT {
                type: type(rel),
                target: target,
                properties: properties(rel)
            }) as outgoing
            OPTIONAL MATCH (source)-[rel]->(center)
            WHERE source.id IS NOT NULL
            WITH center, connectedNodes, outgoing, collect(DISTINCT {
                type: type(rel),
                source: source,
                properties: properties(rel)
            }) as incoming
            RETURN center, connectedNodes, outgoing, incoming
            LIMIT 1
        ";

        try {
            $result = $this->graphService->run($query, [
                'id' => $id,
            ]);

            if ($result->count() === 0) {
                Log::info('No graph data found for node', [
                    'label' => $label,
                    'id' => $id,
                ]);

                return null;
            }

            $record = $result->first();
            $center = $record->get('center');
            $connectedNodes = $record->get('connectedNodes') ?? [];
            $outgoing = $record->get('outgoing') ?? [];
            $incoming = $record->get('incoming') ?? [];

            if (! $center) {
                Log::warning('Center node is null', [
                    'label' => $label,
                    'id' => $id,
                ]);

                return null;
            }
        } catch (\Exception $e) {
            Log::error('Graph query execution failed', [
                'label' => $label,
                'id' => $id,
                'query' => $query,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        // Build nodes array
        $nodes = [];
        $nodeIds = []; // Track IDs to prevent duplicates

        try {
            // Add center node
            $centerId = $center->getProperty('id');
            if ($centerId) {
                $nodes[] = [
                    'id' => $centerId,
                    'label' => $this->getNodeLabel($center),
                    'type' => $label,
                    'properties' => $this->normalizeProperties($center->getProperties()),
                    'isCenter' => true,
                ];
                $nodeIds[$centerId] = true;
            }

            // Add connected nodes
            foreach ($connectedNodes as $node) {
                if ($node === null) {
                    continue;
                }

                try {
                    // Use getProperties() for safe property access - avoids exception if 'id' missing
                    $props = iterator_to_array($node->getProperties());
                    $nodeId = $props['id'] ?? null;
                    if (! $nodeId || isset($nodeIds[$nodeId])) {
                        continue; // Skip nodes without ID or duplicates
                    }

                    $labels = $node->getLabels();
                    $nodeType = $labels[0] ?? 'Unknown';

                    $nodes[] = [
                        'id' => $nodeId,
                        'label' => $this->getNodeLabel($node),
                        'type' => $nodeType,
                        'properties' => $this->normalizeProperties($props),
                        'isCenter' => false,
                    ];
                    $nodeIds[$nodeId] = true;

                    if (count($nodes) >= $limit) {
                        break;
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to process connected node', [
                        'error' => $e->getMessage(),
                    ]);

                    continue; // Skip problematic nodes
                }
            }

            // Build edges array
            $edges = [];

            foreach ($outgoing as $rel) {
                if (empty($rel['target'])) {
                    continue;
                }

                try {
                    // Use getProperties() for safe property access
                    $targetProps = iterator_to_array($rel['target']->getProperties());
                    $targetId = $targetProps['id'] ?? null;
                    if (! $targetId) {
                        continue;
                    }

                    // Only add edge if both nodes are in the graph
                    if (isset($nodeIds[$centerId]) && isset($nodeIds[$targetId])) {
                        $edges[] = [
                            'source' => $centerId,
                            'target' => $targetId,
                            'type' => $rel['type'] ?? 'RELATED',
                            'properties' => isset($rel['properties']) ? $this->normalizeProperties($rel['properties']) : [],
                        ];
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to process outgoing relationship', [
                        'error' => $e->getMessage(),
                    ]);

                    continue;
                }
            }

            foreach ($incoming as $rel) {
                if (empty($rel['source'])) {
                    continue;
                }

                try {
                    // Use getProperties() for safe property access
                    $sourceProps = iterator_to_array($rel['source']->getProperties());
                    $sourceId = $sourceProps['id'] ?? null;
                    if (! $sourceId) {
                        continue;
                    }

                    // Only add edge if both nodes are in the graph
                    if (isset($nodeIds[$sourceId]) && isset($nodeIds[$centerId])) {
                        $edges[] = [
                            'source' => $sourceId,
                            'target' => $centerId,
                            'type' => $rel['type'] ?? 'RELATED',
                            'properties' => isset($rel['properties']) ? $this->normalizeProperties($rel['properties']) : [],
                        ];
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to process incoming relationship', [
                        'error' => $e->getMessage(),
                    ]);

                    continue;
                }
            }

            return [
                'center' => $this->normalizeProperties($center->getProperties()),
                'nodes' => $nodes,
                'edges' => $edges,
                'nodeCount' => count($nodes),
                'edgeCount' => count($edges),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to build graph data structure', [
                'label' => $label,
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    protected function getNodeLabel($node): string
    {
        // Normalize properties to an array
        if (is_object($node) && method_exists($node, 'getProperties')) {
            $props = iterator_to_array($node->getProperties());
        } elseif (is_array($node)) {
            $props = $node;
        } else {
            $props = [];
        }

        // Try short identifying properties first
        foreach (['case_number', 'law_number', 'ecli'] as $key) {
            if (array_key_exists($key, $props) && $props[$key] !== null && $props[$key] !== '') {
                return (string) $props[$key];
            }
        }

        // Try human-readable name/value properties
        foreach (['name', 'value', 'keyword', 'tag'] as $key) {
            if (array_key_exists($key, $props) && $props[$key] !== null && $props[$key] !== '') {
                $val = (string) $props[$key];
                return strlen($val) > 40 ? substr($val, 0, 40).'...' : $val;
            }
        }

        if (array_key_exists('title', $props) && $props['title'] !== null && $props['title'] !== '') {
            $title = (string) $props['title'];

            return strlen($title) > 40 ? substr($title, 0, 40).'...' : $title;
        }

        // Try case_id with a prefix for context
        if (array_key_exists('case_id', $props) && $props['case_id'] !== null && $props['case_id'] !== '') {
            return 'Case #' . (string) $props['case_id'];
        }

        // Try doc_id truncated
        if (array_key_exists('doc_id', $props) && $props['doc_id'] !== null && $props['doc_id'] !== '') {
            $docId = (string) $props['doc_id'];
            return strlen($docId) > 20 ? substr($docId, 0, 20).'...' : $docId;
        }

        if (is_object($node) && method_exists($node, 'getProperty')) {
            try {
                $idVal = (string) $node->getProperty('id');
                // If it's a ULID/UUID (26+ alphanumeric chars), truncate it
                if (strlen($idVal) > 20 && preg_match('/^[0-9A-Z]{20,}$/i', $idVal)) {
                    return substr($idVal, 0, 8) . '...';
                }
                return $idVal;
            } catch (\Throwable $e) {
                // ignore
            }
        }

        // Last resort: truncate long IDs
        $id = (string) ($props['id'] ?? 'unknown');
        if (strlen($id) > 20 && preg_match('/^[0-9A-Z]{20,}$/i', $id)) {
            return substr($id, 0, 8) . '...';
        }
        return $id;
    }

    // =========================================================================
    // Sidebar Data Loading
    // =========================================================================

    protected function loadDecisionSidebarData(string $decisionId): void
    {
        try {
            // Load arguments
            $argQuery = "
                MATCH (d:CourtDecisionDocument {id: \$id})-[:HAS_ARGUMENT]->(a:LegalArgument)
                RETURN a, labels(a) as argLabels
                ORDER BY a.position ASC
            ";
            $argResult = $this->graphService->run($argQuery, ['id' => $decisionId]);
            $this->arguments = [
                'plaintiff' => [],
                'defendant' => [],
                'court' => [],
            ];
            foreach ($argResult as $record) {
                $arg = $this->normalizeProperties($record->get('a')->getProperties());
                $role = $arg['party_role'] ?? 'court';
                if (isset($this->arguments[$role])) {
                    $this->arguments[$role][] = $arg;
                }
            }

            // Load evidence
            $evQuery = "
                MATCH (d:CourtDecisionDocument {id: \$id})-[:HAS_EVIDENCE]->(e:Evidence)
                RETURN e
                ORDER BY e.type, e.position
            ";
            $evResult = $this->graphService->run($evQuery, ['id' => $decisionId]);
            $this->evidence = [
                'documentary' => [],
                'testimonial' => [],
                'expert' => [],
                'physical' => [],
            ];
            foreach ($evResult as $record) {
                $ev = $this->normalizeProperties($record->get('e')->getProperties());
                $type = $ev['type'] ?? 'documentary';
                if (isset($this->evidence[$type])) {
                    $this->evidence[$type][] = $ev;
                }
            }

            // Load timeline events
            $timeQuery = "
                MATCH (d:CourtDecisionDocument {id: \$id})-[:HAS_EVENT]->(e:DateEvent)
                RETURN e
                ORDER BY e.date ASC
            ";
            $timeResult = $this->graphService->run($timeQuery, ['id' => $decisionId]);
            $this->timeline = [];
            foreach ($timeResult as $record) {
                $this->timeline[] = $this->normalizeProperties($record->get('e')->getProperties());
            }
        } catch (\Exception $e) {
            Log::warning('Failed to load sidebar data', [
                'decision_id' => $decisionId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // =========================================================================
    // Statistics and Recent Nodes
    // =========================================================================

    protected function loadStatistics()
    {
        try {
            $query = '
                MATCH (n)
                WITH labels(n)[0] as label, count(n) as count
                RETURN label, count
                ORDER BY count DESC
            ';

            $result = $this->graphService->run($query);

            $stats = [];
            foreach ($result as $record) {
                $label = $record->get('label');
                $count = $record->get('count');
                $stats[$label] = $count;
            }

            // Get relationship count
            $relQuery = 'MATCH ()-[r]->() RETURN count(r) as count';
            $relResult = $this->graphService->run($relQuery);
            $relCount = 0;
            if ($relResult && $relResult->count() > 0) {
                $firstRecord = $relResult->first();
                if ($firstRecord) {
                    $relCount = $firstRecord->get('count');
                }
            }

            $this->statistics = [
                'nodes' => $stats,
                'totalNodes' => array_sum($stats),
                'totalRelationships' => $relCount,
            ];
        } catch (\App\Exceptions\Graph\GraphConnectionException $e) {
            // Re-throw connection exceptions so mount() can handle them
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to load graph statistics', ['error' => $e->getMessage()]);
            $this->statistics = null;
        }
    }

    protected function loadRecentNodes()
    {
        try {
            $query = '
                MATCH (n)
                WHERE n.created_at IS NOT NULL
                WITH n, labels(n)[0] as label
                RETURN n, label
                ORDER BY n.created_at DESC
                LIMIT 10
            ';

            $result = $this->graphService->run($query);

            $this->recentNodes = $result->map(function ($record) {
                $node = $record->get('n');
                $label = $record->get('label');

                return [
                    'id' => $node->getProperty('id'),
                    'label' => $label,
                    'display' => $this->getNodeLabel($node),
                    'created_at' => $node->getProperty('created_at'),
                ];
            })->toArray();
        } catch (\App\Exceptions\Graph\GraphConnectionException $e) {
            // Re-throw connection exceptions so mount() can handle them
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to load recent nodes', ['error' => $e->getMessage()]);
            $this->recentNodes = [];
        }
    }

    protected function addToRecentNodes(string $label, string $id, $properties)
    {
        // Normalize to array to avoid type issues (e.g., CypherMap)
        $props = $this->normalizeProperties($properties);

        $display = '';
        foreach (['case_number', 'law_number', 'name', 'title'] as $k) {
            if (! empty($props[$k])) {
                $display = (string) $props[$k];
                break;
            }
        }
        if ($display === '' && ! empty($props['id'])) {
            $display = (string) $props['id'];
        }

        // Prepend and keep unique by id+label, max 10
        $new = [
            'id' => $id,
            'label' => $label,
            'display' => $display,
            'created_at' => $props['created_at'] ?? now()->toIso8601String(),
        ];

        // Remove existing duplicate
        $this->recentNodes = array_values(array_filter($this->recentNodes, function ($n) use ($new) {
            return ! ($n['id'] === $new['id'] && $n['label'] === $new['label']);
        }));

        array_unshift($this->recentNodes, $new);
        if (count($this->recentNodes) > 10) {
            $this->recentNodes = array_slice($this->recentNodes, 0, 10);
        }
    }

    // =========================================================================
    // UI Actions
    // =========================================================================

    public function selectRecentNode(string $label, string $id)
    {
        $this->selectedNodeType = $label;
        $this->selectedNodeId = $id;
        $this->loadNodeGraph($label, $id);
    }

    public function selectNode(array $node): void
    {
        $nodeId = $node['id'] ?? '';
        $nodeType = $node['type'] ?? 'CourtDecisionDocument';

        if (empty($nodeId)) {
            return;
        }

        $this->selectNodeFromGraph($nodeId, $nodeType);
    }

    public function selectNodeFromGraph(string $nodeId, string $nodeType = 'CourtDecisionDocument'): void
    {
        if (empty($nodeId)) {
            return;
        }

        if (!in_array($nodeType, self::ALLOWED_NODE_TYPES, true)) {
            $nodeType = 'CourtDecisionDocument';
        }

        $this->selectedNodeType = $nodeType;
        $this->selectedNodeId = $nodeId;

        try {
            $query = "MATCH (n:{$nodeType} {id: \$id}) RETURN n LIMIT 1";
            $result = $this->graphService->run($query, ['id' => $nodeId]);

            if ($result->count() > 0) {
                $node = $result->first()->get('n');
                $this->selectedNode = $this->normalizeProperties($node->getProperties());
            }
        } catch (\Exception $e) {
            Log::warning('Failed to load selected node details', [
                'node_id' => $nodeId,
                'node_type' => $nodeType,
                'error' => $e->getMessage(),
            ]);
        }

        // Load sidebar data if it's a court decision
        if ($nodeType === 'CourtDecisionDocument') {
            $this->loadDecisionSidebarData($nodeId);
        }
    }

    public function expandNodeFiltered(string $nodeId, array $relationshipTypes = []): void
    {
        if (empty($nodeId)) {
            return;
        }

        try {
            $relFilter = '';
            if (!empty($relationshipTypes)) {
                $safeTypes = array_filter($relationshipTypes, fn($t) => preg_match('/^[A-Z_]+$/', $t));
                if (!empty($safeTypes)) {
                    $relFilter = ':' . implode('|', $safeTypes);
                }
            }

            $query = "
                MATCH (center {id: \$nodeId})-[r{$relFilter}]-(connected)
                WHERE connected.id IS NOT NULL
                WITH connected, r, labels(connected)[0] as connectedType
                RETURN
                    connected,
                    connectedType,
                    type(r) as relType,
                    startNode(r).id as sourceId,
                    endNode(r).id as targetId,
                    properties(r) as relProps
                LIMIT 50
            ";

            $result = $this->graphService->run($query, ['nodeId' => $nodeId]);

            $nodes = [];
            $edges = [];
            $seenNodeIds = [];

            foreach ($result as $record) {
                $connected = $record->get('connected');
                $connectedType = $record->get('connectedType');
                $props = $this->normalizeProperties($connected->getProperties());
                $connectedId = $props['id'] ?? null;

                if (!$connectedId || isset($seenNodeIds[$connectedId])) {
                    continue;
                }
                $seenNodeIds[$connectedId] = true;

                $nodes[] = [
                    'id' => $connectedId,
                    'label' => $this->getNodeLabel($connected),
                    'type' => $connectedType,
                    'properties' => $props,
                    'isCenter' => false,
                ];

                $edges[] = [
                    'source' => $record->get('sourceId'),
                    'target' => $record->get('targetId'),
                    'type' => $record->get('relType') ?? 'RELATED',
                    'properties' => $this->normalizeProperties($record->get('relProps') ?? []),
                ];
            }

            $this->dispatch('filtered-nodes-loaded', nodes: $nodes, edges: $edges);

            Log::info('Node expanded', [
                'node_id' => $nodeId,
                'new_nodes' => count($nodes),
                'new_edges' => count($edges),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to expand node', [
                'node_id' => $nodeId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function pinNode(array $node): void
    {
        Log::info('Node pinned', ['node_id' => $node['id'] ?? 'unknown']);
    }

    public function unpinNode(string $nodeId): void
    {
        Log::info('Node unpinned', ['node_id' => $nodeId]);
    }

    public function resetGraph()
    {
        $this->selectedNodeId = '';
        $this->selectedNode = null;
        $this->graphData = null;
        $this->error = null;
        $this->searchTerm = '';
    }

    public function refreshStatistics()
    {
        $this->loadStatistics();
        $this->loadRecentNodes();
        $this->loadGraphMetrics();
    }

    // =========================================================================
    // Graph Metrics
    // =========================================================================

    /**
     * Load graph metrics from repository
     */
    protected function loadGraphMetrics()
    {
        try {
            // Load influential decisions (PageRank results)
            $this->influentialDecisions = $this->metricsRepository->getInfluentialDecisions(10);

            // Load citation clusters (Louvain communities)
            $this->citationClusters = $this->metricsRepository->getCitationClusters(5);

            // Load network stats for overview
            $this->networkStats = $this->metricsRepository->getNetworkStats();

            $this->metricsLoaded = true;

            Log::info('Graph metrics loaded successfully', [
                'influential_count' => count($this->influentialDecisions),
                'cluster_count' => count($this->citationClusters),
                'has_network_stats' => ! is_null($this->networkStats),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load graph metrics', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->metricsLoaded = false;
            // Don't fail the whole component if metrics fail to load
        }
    }

    /**
     * Toggle metrics panel visibility
     */
    public function toggleMetrics()
    {
        $this->showMetrics = ! $this->showMetrics;
    }

    /**
     * Load a decision from metrics by ID
     */
    public function loadDecisionFromMetrics(string $decisionId)
    {
        $this->selectedNodeType = 'CourtDecisionDocument';
        $this->selectedNodeId = $decisionId;
        $this->loadNodeGraph('CourtDecisionDocument', $decisionId);
    }

    /**
     * View cluster details
     */
    public function viewCluster(int $clusterId)
    {
        // Prevent duplicate cluster loads
        return $this->preventDuplicate('viewCluster', [$clusterId], function () use ($clusterId) {
            try {
                $cluster = $this->metricsRepository->getCluster($clusterId);

                if (! $cluster) {
                    $this->error = 'Cluster not found';

                    return;
                }

                // Load the first node in the cluster
                $members = $cluster['members'] ?? [];
                if (! empty($members)) {
                    $firstMember = $members[0];
                    $nodeId = $firstMember['id'] ?? null;

                    if ($nodeId) {
                        $this->selectedNodeType = 'CourtDecisionDocument';
                        $this->selectedNodeId = $nodeId;
                        $this->loadNodeGraph('CourtDecisionDocument', $nodeId);
                    }
                }
            } catch (\Exception $e) {
                Log::error('Failed to view cluster', [
                    'cluster_id' => $clusterId,
                    'error' => $e->getMessage(),
                ]);
                $this->error = 'Failed to load cluster: '.$e->getMessage();
            }
        });
    }

    // =========================================================================
    // Precedent Chain Visualization
    // =========================================================================

    public ?array $precedentChain = null;

    /**
     * Get precedent chain for a court decision
     *
     * Fetches all FOLLOWS and OVERRULES relationships up to depth 5
     *
     * @param  string  $decisionId  The decision ID to fetch precedents for
     * @return self For method chaining in tests
     */
    public function getPrecedentChain(string $decisionId): self
    {
        try {
            Log::info('Fetching precedent chain', ['decision_id' => $decisionId]);

            // Cypher query to fetch precedent chains
            // Returns paths showing FOLLOWS and OVERRULES relationships
            $query = "
                MATCH path = (d:CourtDecisionDocument)-[r:FOLLOWS|OVERRULES*1..5]->(p:CourtDecisionDocument)
                WHERE d.id = \$decisionId
                RETURN path
            ";

            $result = $this->graphService->run($query, ['decisionId' => $decisionId]);

            $chains = [];

            // Process each path
            foreach ($result as $record) {
                $path = $record->get('path');

                if (! $path) {
                    continue;
                }

                // Extract nodes and relationships from path
                $nodes = [];
                foreach ($path->nodes() as $node) {
                    $nodes[] = $this->normalizeProperties($node->getProperties());
                }

                $relationships = [];
                foreach ($path->relationships() as $rel) {
                    $relationships[] = [
                        'type' => $rel->type(),
                        'properties' => $this->normalizeProperties($rel->getProperties()),
                    ];
                }

                // Determine the primary relationship type (first one in path)
                $chainType = ! empty($relationships) ? $relationships[0]['type'] : 'UNKNOWN';

                $chains[] = [
                    'type' => $chainType,
                    'nodes' => $nodes,
                    'relationships' => $relationships,
                    'depth' => count($relationships),
                ];
            }

            $this->precedentChain = $chains;

            Log::info('Precedent chain fetched', [
                'decision_id' => $decisionId,
                'chain_count' => count($chains),
            ]);

            return $this;
        } catch (\Exception $e) {
            Log::error('Failed to fetch precedent chain', [
                'decision_id' => $decisionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->precedentChain = [];
            $this->error = 'Failed to fetch precedent chain: '.$e->getMessage();

            return $this;
        }
    }

    // =========================================================================
    // Party Data
    // =========================================================================

    /**
     * Get party data from the selected node
     *
     * Returns plaintiff, defendant, and outcome information for court decisions
     *
     * @return array{plaintiff: ?string, defendant: ?string, outcome: ?string}
     */
    public function getPartyData(): array
    {
        // Return empty data if no node is selected
        if (! $this->selectedNode) {
            return [
                'plaintiff' => null,
                'defendant' => null,
                'outcome' => null,
            ];
        }

        // Extract party information from selected node
        return [
            'plaintiff' => $this->selectedNode['plaintiff'] ?? null,
            'defendant' => $this->selectedNode['defendant'] ?? null,
            'outcome' => $this->selectedNode['outcome'] ?? null,
        ];
    }

    // =========================================================================
    // Judge Panel
    // =========================================================================

    public ?array $judgeData = null;

    /**
     * Get judge data with statistics
     *
     * @param  string  $judgeId  The judge node ID
     * @return array|null Judge statistics or null if not found
     */
    public function getJudgeData(string $judgeId): ?array
    {
        if (empty($judgeId)) {
            $this->error = 'Judge ID is required';
            $this->judgeData = null;

            return null;
        }

        $this->loading = true;
        $this->error = null;

        try {
            // Query to get judge statistics
            $query = '
                MATCH (j:Judge {id: $judgeId})
                OPTIONAL MATCH (d:CourtDecisionDocument)-[:PRESIDED_BY]->(j)
                WITH j,
                     count(d) as caseCount,
                     avg(duration.inDays(date(d.decision_date), date()).days) as avgCaseDuration,
                     collect(d.outcome) as rulingDistribution
                RETURN
                    caseCount,
                    avgCaseDuration,
                    rulingDistribution
            ';

            $result = $this->graphService->run($query, ['judgeId' => $judgeId]);

            if ($result->count() === 0) {
                Log::info('No judge data found', ['judge_id' => $judgeId]);
                $this->judgeData = null;

                return null;
            }

            $record = $result->first();

            $this->judgeData = [
                'caseCount' => $record->get('caseCount') ?? 0,
                'avgCaseDuration' => $record->get('avgCaseDuration') ?? 0,
                'rulingDistribution' => $record->get('rulingDistribution') ?? [],
            ];

            Log::info('Judge data loaded successfully', [
                'judge_id' => $judgeId,
                'case_count' => $this->judgeData['caseCount'],
            ]);

            return $this->judgeData;
        } catch (\Exception $e) {
            Log::error('Failed to load judge data', [
                'judge_id' => $judgeId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->error = 'Failed to load judge data: '.$e->getMessage();
            $this->judgeData = null;

            return null;
        } finally {
            $this->loading = false;
        }
    }

    // =========================================================================
    // Citation Analysis Mode
    // =========================================================================

    /**
     * Open Citation Analysis modal/panel
     */
    public function openCitationAnalysis(): void
    {
        $this->showCitationAnalysis = true;

        // Pre-fill decision ID if a CourtDecisionDocument is currently selected
        if ($this->selectedNodeType === 'CourtDecisionDocument' && $this->selectedNodeId) {
            $this->analysisDecisionId = $this->selectedNodeId;
        }

        $this->error = null;
    }

    /**
     * Close Citation Analysis modal/panel
     */
    public function closeCitationAnalysis(): void
    {
        $this->showCitationAnalysis = false;
        $this->citationAnalysisResults = null;
        $this->analysisDecisionId = '';
        $this->error = null;
    }

    /**
     * Alias for closeCitationAnalysis (backwards compatibility)
     */
    public function clearCitationAnalysis(): void
    {
        $this->closeCitationAnalysis();
    }

    /**
     * Alias for analyzeCitations to match blade template calls
     */
    public function runCitationAnalysis(): void
    {
        $this->analyzeCitations();
    }

    /**
     * Analyze citations for a decision
     */
    public function analyzeCitations()
    {
        // Validate decision ID
        if (empty($this->analysisDecisionId)) {
            $this->error = 'Please enter a decision ID';

            return;
        }

        // Prevent duplicate concurrent analysis (with longer TTL for expensive operation)
        return $this->preventDuplicate(
            'analyzeCitations',
            [$this->analysisDecisionId, $this->citationOperation],
            function () {
                $this->loading = true;
                $this->error = null;

                try {
                    Log::info('Citation analysis initiated', [
                        'decision_id' => $this->analysisDecisionId,
                        'operation' => $this->citationOperation,
                    ]);

                    // Call the citation service
                    $this->citationAnalysisResults = $this->citationService->analyzeCitations(
                        $this->analysisDecisionId,
                        ['operation' => $this->citationOperation]
                    );

                    Log::info('Citation analysis completed', [
                        'decision_id' => $this->analysisDecisionId,
                        'operation' => $this->citationOperation,
                        'has_results' => ! empty($this->citationAnalysisResults),
                    ]);
                } catch (\Exception $e) {
                    Log::error('Citation analysis failed', [
                        'decision_id' => $this->analysisDecisionId,
                        'operation' => $this->citationOperation,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    $this->error = 'Citation analysis failed: '.$e->getMessage();
                    $this->citationAnalysisResults = null;
                } finally {
                    $this->loading = false;
                }
            },
            60 // 60 second TTL for expensive operation
        );
    }

    // =========================================================================
    // Render
    // =========================================================================

    public function render()
    {
        return view('livewire.graph-viewer');
    }
}
