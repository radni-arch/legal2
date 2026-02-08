# Citation Network Analysis UI Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Implement UI components for Citation Network Analysis in GraphViewer to make E2E tests pass

**Architecture:** Add citation analysis functionality to existing GraphViewer Livewire component. Integrate with DecisionCitationService (already implemented) to display citation graphs, authority metrics, patterns, and influence spread. Use Alpine.js for interactions and D3.js for graph visualization.

**Tech Stack:** Laravel Livewire, Alpine.js, Tailwind CSS, D3.js v7, DecisionCitationService

---

## Context

- **Service Layer**: `app/Services/DecisionCitationService.php` ✅ Already implemented
- **E2E Tests**: `tests/Browser/CitationNetworkAnalysisTest.php` ✅ Already written (TDD RED phase)
- **Component**: `app/Http/Livewire/GraphViewer.php` - Need to extend
- **View**: `resources/views/livewire/graph-viewer.blade.php` - Need to extend

## Test-Driven Development

All tests already exist and describe desired behavior. This is TDD **GREEN** phase - implement minimal code to make tests pass.

Run tests:
```bash
php artisan dusk tests/Browser/CitationNetworkAnalysisTest.php
```

---

## Task 1: Add Citation Analysis Button to GraphViewer

**Priority:** HIGH

**Files:**
- Modify: `app/Http/Livewire/GraphViewer.php` (add properties and methods)
- Modify: `resources/views/livewire/graph-viewer.blade.php` (add button UI)

### Step 1: Add properties to GraphViewer.php

Add after line 57 (after `$showMetrics`):

```php
// Citation Analysis state
public $showCitationAnalysis = false;
public $citationAnalysisOperation = 'graph'; // graph, authority, patterns, influence
public $citationAnalysisResults = null;
public $citationAnalysisLoading = false;
```

### Step 2: Add openCitationAnalysis() method

Add after `loadGraphMetrics()` method (around line 300):

```php
/**
 * Open citation analysis panel for selected court decision
 */
public function openCitationAnalysis()
{
    if ($this->selectedNodeType !== 'CourtDecisionDocument' || !$this->selectedNodeId) {
        $this->error = 'Please select a court decision first';
        return;
    }

    $this->showCitationAnalysis = true;
    $this->citationAnalysisResults = null;

    // Auto-run graph analysis by default
    $this->runCitationAnalysis();
}

/**
 * Close citation analysis panel
 */
public function closeCitationAnalysis()
{
    $this->showCitationAnalysis = false;
    $this->citationAnalysisResults = null;
}
```

### Step 3: Add button to blade template

In `graph-viewer.blade.php`, add button after node details section (find the section showing selected node info, around line 200-300):

```blade
{{-- Citation Analysis Button (only for Court Decisions) --}}
@if($selectedNodeType === 'CourtDecisionDocument' && $selectedNodeId)
<div class="mt-4">
    <button
        wire:click="openCitationAnalysis"
        dusk="citation-analysis-button"
        class="w-full px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg transition-colors flex items-center justify-center gap-2"
    >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
        </svg>
        <span>Citation Analysis</span>
    </button>
</div>
@endif
```

### Step 4: Run E2E test to verify button exists

```bash
php artisan dusk --filter=test_can_view_citation_graph_visualization
```

Expected: Test should progress further - button now clickable

### Step 5: Commit

```bash
git add app/Http/Livewire/GraphViewer.php resources/views/livewire/graph-viewer.blade.php
git commit -m "feat: add citation analysis button to GraphViewer

- Add showCitationAnalysis state properties
- Add openCitationAnalysis() and closeCitationAnalysis() methods
- Add citation analysis button (only shows for CourtDecisionDocument)
- Button has @citation-analysis-button dusk selector
- Addresses first requirement from CitationNetworkAnalysisTest"
```

---

## Task 2: Implement Citation Analysis Panel

**Priority:** HIGH

**Files:**
- Modify: `app/Http/Livewire/GraphViewer.php` (add runCitationAnalysis method)
- Modify: `resources/views/livewire/graph-viewer.blade.php` (add panel UI)

### Step 1: Inject DecisionCitationService dependency

In `GraphViewer.php`, add property after `$metricsRepository`:

```php
protected \App\Services\DecisionCitationService $citationService;
```

Update `boot()` method:

```php
public function boot(
    GraphDatabaseService $graphService,
    GraphMetricsRepository $metricsRepository,
    \App\Services\DecisionCitationService $citationService
) {
    $this->graphService = $graphService;
    $this->metricsRepository = $metricsRepository;
    $this->citationService = $citationService;
}
```

### Step 2: Implement runCitationAnalysis() method

Add after `closeCitationAnalysis()`:

```php
/**
 * Run citation analysis operation
 */
public function runCitationAnalysis()
{
    if (!$this->selectedNodeId) {
        $this->error = 'No decision selected';
        return;
    }

    try {
        $this->citationAnalysisLoading = true;
        $this->error = null;

        // Call DecisionCitationService
        $this->citationAnalysisResults = $this->citationService->analyzeCitations(
            $this->selectedNodeId,
            ['operation' => $this->citationAnalysisOperation, 'depth' => 2, 'limit' => 50]
        );

        $this->citationAnalysisLoading = false;
    } catch (\Exception $e) {
        $this->error = 'Citation analysis failed: ' . $e->getMessage();
        $this->citationAnalysisLoading = false;
        Log::error('Citation analysis error', [
            'decision_id' => $this->selectedNodeId,
            'operation' => $this->citationAnalysisOperation,
            'error' => $e->getMessage()
        ]);
    }
}
```

### Step 3: Add citation analysis panel to blade

Add modal/panel before closing `</div>` of main component:

```blade
{{-- Citation Analysis Panel --}}
@if($showCitationAnalysis)
<div class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4"
     dusk="citation-analysis-panel">
    <div class="bg-gray-800 rounded-lg shadow-xl max-w-6xl w-full max-h-[90vh] overflow-hidden flex flex-col">

        {{-- Header --}}
        <div class="px-6 py-4 border-b border-gray-700 flex items-center justify-between">
            <h3 class="text-xl font-semibold text-white">Citation Network Analysis</h3>
            <button wire:click="closeCitationAnalysis" class="text-gray-400 hover:text-white">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        {{-- Operation Selector --}}
        <div class="px-6 py-4 border-b border-gray-700 bg-gray-750">
            <div class="flex items-center gap-4">
                <label class="text-sm font-medium text-gray-300">Analysis Type:</label>
                <select
                    wire:model="citationAnalysisOperation"
                    dusk="analysis-operation"
                    class="bg-gray-700 text-white rounded px-3 py-2 text-sm border border-gray-600 focus:outline-none focus:ring-2 focus:ring-purple-500"
                >
                    <option value="graph">Citation Graph</option>
                    <option value="authority">Authority Metrics</option>
                    <option value="patterns">Citation Patterns</option>
                    <option value="influence">Influence Spread</option>
                </select>

                <button
                    wire:click="runCitationAnalysis"
                    dusk="run-analysis-button"
                    class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded transition-colors text-sm"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove wire:target="runCitationAnalysis">Run Analysis</span>
                    <span wire:loading wire:target="runCitationAnalysis">Analyzing...</span>
                </button>
            </div>
        </div>

        {{-- Results Area --}}
        <div class="flex-1 overflow-y-auto p-6">
            @if($citationAnalysisLoading)
                <div class="flex items-center justify-center h-64">
                    <div class="text-center">
                        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-purple-500 mx-auto"></div>
                        <p class="text-gray-400 mt-4">Analyzing citation network...</p>
                    </div>
                </div>
            @elseif($citationAnalysisResults)
                {{-- Results will be displayed here by subsequent tasks --}}
                <div class="text-gray-400 text-sm">
                    <p>Results for operation: <span class="text-white font-medium">{{ $citationAnalysisOperation }}</span></p>
                    <p class="mt-2">Data loaded. Visualization panels will be implemented next.</p>
                </div>
            @else
                <div class="text-center text-gray-400 py-12">
                    <p>Select an analysis type and click "Run Analysis" to begin.</p>
                </div>
            @endif

            @if($error)
                <div class="bg-red-900 bg-opacity-20 border border-red-700 rounded p-4 mt-4">
                    <p class="text-red-400">{{ $error }}</p>
                </div>
            @endif
        </div>
    </div>
</div>
@endif
```

### Step 4: Test citation panel opens and operation selector works

```bash
php artisan dusk --filter=test_can_switch_between_analysis_operations
```

Expected: Panel opens, operation selector exists, but specific visualization panels not yet implemented

### Step 5: Commit

```bash
git add app/Http/Livewire/GraphViewer.php resources/views/livewire/graph-viewer.blade.php
git commit -m "feat: implement citation analysis panel with operation selector

- Inject DecisionCitationService dependency
- Add runCitationAnalysis() method calling service
- Add modal panel UI with operation selector (graph/authority/patterns/influence)
- Add run analysis button with loading state
- Add error handling and display
- Panel has @citation-analysis-panel dusk selector
- Operation selector has @analysis-operation dusk selector
- Addresses panel requirement from E2E tests"
```

---

## Task 3: Implement Authority Metrics Panel

**Priority:** HIGH

**Files:**
- Modify: `resources/views/livewire/graph-viewer.blade.php` (add authority metrics visualization)

### Step 1: Add authority metrics panel component

Replace the placeholder results area in citation analysis panel with conditional panels. Find the `@elseif($citationAnalysisResults)` section and replace with:

```blade
@elseif($citationAnalysisResults)
    {{-- Authority Metrics Panel --}}
    @if($citationAnalysisOperation === 'authority')
        <div dusk="authority-metrics-panel" class="space-y-6">
            <h4 class="text-lg font-semibold text-white border-b border-gray-700 pb-2">
                Authority Metrics
            </h4>

            {{-- Key Metrics Grid --}}
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                {{-- H-Index --}}
                <div class="bg-gray-750 rounded-lg p-4 border border-gray-700">
                    <div class="text-gray-400 text-sm mb-1">H-Index</div>
                    <div dusk="h-index-value" class="text-2xl font-bold text-purple-400">
                        {{ $citationAnalysisResults['h_index'] ?? 0 }}
                    </div>
                    <div class="text-gray-500 text-xs mt-1">
                        Citation Impact Score
                    </div>
                </div>

                {{-- Influence Rank --}}
                <div class="bg-gray-750 rounded-lg p-4 border border-gray-700">
                    <div class="text-gray-400 text-sm mb-1">Influence Rank</div>
                    <div dusk="influence-rank-value" class="text-lg font-semibold">
                        @php
                            $rank = $citationAnalysisResults['influence_rank'] ?? 'unknown';
                            $rankColors = [
                                'highly_influential' => 'text-green-400',
                                'influential' => 'text-blue-400',
                                'moderately_influential' => 'text-yellow-400',
                                'emerging' => 'text-orange-400',
                                'limited' => 'text-gray-400',
                            ];
                            $color = $rankColors[$rank] ?? 'text-gray-400';
                        @endphp
                        <span class="{{ $color }}">
                            {{ ucwords(str_replace('_', ' ', $rank)) }}
                        </span>
                    </div>
                </div>

                {{-- Citation Count (outgoing) --}}
                <div class="bg-gray-750 rounded-lg p-4 border border-gray-700">
                    <div class="text-gray-400 text-sm mb-1">Citations Made</div>
                    <div dusk="citation-count-value" class="text-2xl font-bold text-blue-400">
                        {{ $citationAnalysisResults['citation_count'] ?? 0 }}
                    </div>
                    <div class="text-gray-500 text-xs mt-1">
                        Outgoing Citations
                    </div>
                </div>

                {{-- Cited-By Count (incoming) --}}
                <div class="bg-gray-750 rounded-lg p-4 border border-gray-700">
                    <div class="text-gray-400 text-sm mb-1">Times Cited</div>
                    <div dusk="cited-by-count-value" class="text-2xl font-bold text-green-400">
                        {{ $citationAnalysisResults['cited_by_count'] ?? 0 }}
                    </div>
                    <div class="text-gray-500 text-xs mt-1">
                        Incoming Citations
                    </div>
                </div>

                {{-- Authority Score --}}
                <div class="bg-gray-750 rounded-lg p-4 border border-gray-700">
                    <div class="text-gray-400 text-sm mb-1">Authority Score</div>
                    <div dusk="authority-score-value" class="text-2xl font-bold text-purple-400">
                        {{ number_format($citationAnalysisResults['authority_score'] ?? 0, 2) }}
                    </div>
                    <div class="text-gray-500 text-xs mt-1">
                        Weighted Metric
                    </div>
                </div>

                {{-- Citation Velocity (if available) --}}
                @if(isset($citationAnalysisResults['citation_velocity']))
                <div class="bg-gray-750 rounded-lg p-4 border border-gray-700">
                    <div class="text-gray-400 text-sm mb-1">Citation Velocity</div>
                    <div class="text-2xl font-bold text-yellow-400">
                        {{ $citationAnalysisResults['citation_velocity'] }}
                    </div>
                    <div class="text-gray-500 text-xs mt-1">
                        Citations per Month
                    </div>
                </div>
                @endif
            </div>

            {{-- Explanation --}}
            <div class="bg-gray-750 rounded-lg p-4 border border-gray-700">
                <h5 class="text-white font-medium mb-2">Metrics Explanation</h5>
                <ul class="text-sm text-gray-400 space-y-1">
                    <li>• <strong class="text-white">H-Index:</strong> A decision with h-index of N has N citations, each cited at least N times</li>
                    <li>• <strong class="text-white">Influence Rank:</strong> Categorical ranking based on citation patterns and authority</li>
                    <li>• <strong class="text-white">Authority Score:</strong> Weighted combination of incoming and outgoing citations</li>
                </ul>
            </div>
        </div>
    @endif

    {{-- Placeholder for other operations --}}
    @if($citationAnalysisOperation !== 'authority')
        <div class="text-gray-400 text-center py-12">
            <p>{{ ucfirst($citationAnalysisOperation) }} visualization will be implemented in next task.</p>
        </div>
    @endif
@endif
```

### Step 2: Test authority metrics display

```bash
php artisan dusk --filter=test_can_view_authority_metrics
```

Expected: Test passes - all metrics displayed with correct dusk selectors

### Step 3: Commit

```bash
git add resources/views/livewire/graph-viewer.blade.php
git commit -m "feat: implement authority metrics panel

- Add authority metrics visualization with 6 key metrics
- Display h-index, influence rank, citation counts, authority score
- Color-coded influence rank (highly_influential, influential, etc.)
- Add metrics explanation section
- All elements have proper dusk selectors (@h-index-value, @influence-rank-value, etc.)
- Addresses authority metrics requirement from E2E tests"
```

---

## Task 4: Implement Citation Graph Visualization with D3.js

**Priority:** MEDIUM

**Files:**
- Create: `resources/js/components/citation-graph.js` (D3.js visualization)
- Modify: `resources/views/livewire/graph-viewer.blade.php` (add graph panel)
- Modify: `resources/js/app.js` (import citation graph component)

### Step 1: Create D3.js citation graph component

Create `resources/js/components/citation-graph.js`:

```javascript
/**
 * Citation Graph Visualization using D3.js
 *
 * Displays citation network as force-directed graph
 */
export function renderCitationGraph(elementId, data) {
    const container = document.getElementById(elementId);
    if (!container) return;

    // Clear previous graph
    container.innerHTML = '';

    // Dimensions
    const width = container.clientWidth;
    const height = 600;

    // Create SVG
    const svg = d3.select(`#${elementId}`)
        .append('svg')
        .attr('width', width)
        .attr('height', height)
        .attr('class', 'bg-gray-900 rounded');

    // Parse data
    const nodes = data.nodes || [];
    const edges = data.edges || [];
    const rootId = data.root_decision || null;

    // Create force simulation
    const simulation = d3.forceSimulation(nodes)
        .force('link', d3.forceLink(edges).id(d => d.id).distance(100))
        .force('charge', d3.forceManyBody().strength(-300))
        .force('center', d3.forceCenter(width / 2, height / 2))
        .force('collision', d3.forceCollide().radius(30));

    // Draw edges
    const link = svg.append('g')
        .selectAll('line')
        .data(edges)
        .join('line')
        .attr('stroke', '#4B5563')
        .attr('stroke-width', 2)
        .attr('stroke-opacity', 0.6)
        .attr('marker-end', 'url(#arrowhead)');

    // Define arrowhead marker
    svg.append('defs').append('marker')
        .attr('id', 'arrowhead')
        .attr('viewBox', '-0 -5 10 10')
        .attr('refX', 25)
        .attr('refY', 0)
        .attr('orient', 'auto')
        .attr('markerWidth', 6)
        .attr('markerHeight', 6)
        .append('svg:path')
        .attr('d', 'M 0,-5 L 10,0 L 0,5')
        .attr('fill', '#4B5563');

    // Draw nodes
    const node = svg.append('g')
        .selectAll('circle')
        .data(nodes)
        .join('circle')
        .attr('r', d => d.id === rootId ? 20 : 15)
        .attr('fill', d => {
            if (d.id === rootId) return '#A855F7'; // Purple for root
            if (d.type === 'citing') return '#3B82F6'; // Blue for citing
            if (d.type === 'cited') return '#10B981'; // Green for cited
            return '#6B7280'; // Gray default
        })
        .attr('stroke', '#fff')
        .attr('stroke-width', 2)
        .call(drag(simulation));

    // Add node labels
    const label = svg.append('g')
        .selectAll('text')
        .data(nodes)
        .join('text')
        .text(d => d.label || d.id)
        .attr('font-size', 10)
        .attr('fill', '#E5E7EB')
        .attr('text-anchor', 'middle')
        .attr('dy', -25);

    // Update positions on simulation tick
    simulation.on('tick', () => {
        link
            .attr('x1', d => d.source.x)
            .attr('y1', d => d.source.y)
            .attr('x2', d => d.target.x)
            .attr('y2', d => d.target.y);

        node
            .attr('cx', d => d.x)
            .attr('cy', d => d.y);

        label
            .attr('x', d => d.x)
            .attr('y', d => d.y);
    });

    // Drag behavior
    function drag(simulation) {
        function dragstarted(event) {
            if (!event.active) simulation.alphaTarget(0.3).restart();
            event.subject.fx = event.subject.x;
            event.subject.fy = event.subject.y;
        }

        function dragged(event) {
            event.subject.fx = event.subject.x;
            event.subject.fy = event.subject.y;
        }

        function dragended(event) {
            if (!event.active) simulation.alphaTarget(0);
            event.subject.fx = null;
            event.subject.fy = null;
        }

        return d3.drag()
            .on('start', dragstarted)
            .on('drag', dragged)
            .on('end', dragended);
    }
}

// Make globally available for Livewire
window.renderCitationGraph = renderCitationGraph;
```

### Step 2: Import in app.js

Add to `resources/js/app.js`:

```javascript
import { renderCitationGraph } from './components/citation-graph';
```

### Step 3: Add citation graph panel to blade

Add after authority metrics panel in `@if` chain:

```blade
{{-- Citation Graph Panel --}}
@if($citationAnalysisOperation === 'graph')
    <div dusk="citation-graph-panel" class="space-y-6">
        <div class="flex items-center justify-between border-b border-gray-700 pb-2">
            <h4 class="text-lg font-semibold text-white">Citation Graph</h4>

            {{-- Legend --}}
            <div dusk="graph-legend" class="flex items-center gap-4 text-xs">
                <div class="flex items-center gap-1">
                    <div class="w-3 h-3 rounded-full bg-purple-500"></div>
                    <span class="text-gray-400">Selected Decision</span>
                </div>
                <div class="flex items-center gap-1">
                    <div class="w-3 h-3 rounded-full bg-blue-500"></div>
                    <span class="text-gray-400">Citing Decisions</span>
                </div>
                <div class="flex items-center gap-1">
                    <div class="w-3 h-3 rounded-full bg-green-500"></div>
                    <span class="text-gray-400">Cited Decisions</span>
                </div>
            </div>
        </div>

        {{-- Graph Statistics --}}
        <div class="grid grid-cols-3 gap-4 text-sm">
            <div class="bg-gray-750 rounded p-3 border border-gray-700">
                <div class="text-gray-400">Nodes</div>
                <div class="text-xl font-bold text-white">
                    {{ count($citationAnalysisResults['nodes'] ?? []) }}
                </div>
            </div>
            <div class="bg-gray-750 rounded p-3 border border-gray-700">
                <div class="text-gray-400">Edges</div>
                <div class="text-xl font-bold text-white">
                    {{ count($citationAnalysisResults['edges'] ?? []) }}
                </div>
            </div>
            <div class="bg-gray-750 rounded p-3 border border-gray-700">
                <div class="text-gray-400">Root</div>
                <div class="text-sm font-medium text-purple-400">
                    {{ $citationAnalysisResults['root_decision'] ?? 'N/A' }}
                </div>
            </div>
        </div>

        {{-- D3.js Graph Canvas --}}
        <div
            id="citation-graph-canvas"
            dusk="citation-graph-canvas"
            class="bg-gray-900 rounded-lg border border-gray-700 min-h-[600px]"
            x-data
            x-init="
                $nextTick(() => {
                    if (window.renderCitationGraph) {
                        window.renderCitationGraph('citation-graph-canvas', @js($citationAnalysisResults));
                    }
                });
            "
        ></div>

        <p class="text-gray-500 text-xs text-center">
            Drag nodes to rearrange. Purple = selected decision, Blue = citing decisions, Green = cited decisions.
        </p>
    </div>
@endif
```

### Step 4: Install D3.js if not already installed

```bash
npm install d3@7
npm run build
```

### Step 5: Test citation graph visualization

```bash
php artisan dusk --filter=test_can_view_citation_graph_visualization
```

Expected: Graph panel displays with D3.js force-directed graph

### Step 6: Commit

```bash
git add resources/js/components/citation-graph.js resources/js/app.js resources/views/livewire/graph-viewer.blade.php
git commit -m "feat: implement citation graph visualization with D3.js

- Create D3.js citation graph component with force-directed layout
- Add graph panel with legend (purple=root, blue=citing, green=cited)
- Display node/edge counts and root decision
- Interactive graph with drag-to-rearrange nodes
- Add arrowheads for directed edges
- All elements have proper dusk selectors
- Addresses citation graph requirement from E2E tests"
```

---

## Task 5: Implement Citation Patterns Panel

**Priority:** LOW

**Files:**
- Modify: `resources/views/livewire/graph-viewer.blade.php` (add patterns panel)

### Step 1: Add citation patterns panel

Add after graph panel in `@if` chain:

```blade
{{-- Citation Patterns Panel --}}
@if($citationAnalysisOperation === 'patterns')
    <div dusk="citation-patterns-panel" class="space-y-6">
        <h4 class="text-lg font-semibold text-white border-b border-gray-700 pb-2">
            Citation Patterns
        </h4>

        {{-- Temporal Distribution --}}
        <div>
            <h5 class="text-white font-medium mb-3">Temporal Distribution</h5>
            <div dusk="temporal-distribution-chart" class="bg-gray-900 rounded-lg p-4 border border-gray-700">
                @php
                    $temporal = $citationAnalysisResults['temporal_distribution'] ?? [];
                @endphp

                @if(!empty($temporal))
                    <div class="space-y-2">
                        @foreach($temporal as $period => $count)
                            <div class="flex items-center gap-3">
                                <div class="text-sm text-gray-400 w-24">{{ $period }}</div>
                                <div class="flex-1 bg-gray-800 rounded-full h-6 overflow-hidden">
                                    <div
                                        class="bg-purple-500 h-full flex items-center justify-end pr-2"
                                        style="width: {{ min(100, ($count / max($temporal)) * 100) }}%"
                                    >
                                        <span class="text-xs text-white font-medium">{{ $count }}</span>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-gray-500 text-center py-4">No temporal data available</p>
                @endif
            </div>
        </div>

        {{-- Citation Types Breakdown --}}
        <div>
            <h5 class="text-white font-medium mb-3">Citation Types</h5>
            <div dusk="citation-types-breakdown" class="grid grid-cols-2 gap-4">
                @php
                    $types = $citationAnalysisResults['citation_types'] ?? [];
                @endphp

                <div class="bg-gray-750 rounded-lg p-4 border border-gray-700">
                    <div class="text-gray-400 text-sm mb-1">Direct Citations</div>
                    <div class="text-2xl font-bold text-blue-400">
                        {{ $types['direct'] ?? 0 }}
                    </div>
                </div>

                <div class="bg-gray-750 rounded-lg p-4 border border-gray-700">
                    <div class="text-gray-400 text-sm mb-1">Indirect Citations</div>
                    <div class="text-2xl font-bold text-green-400">
                        {{ $types['indirect'] ?? 0 }}
                    </div>
                </div>
            </div>
        </div>

        {{-- Detected Patterns --}}
        <div>
            <h5 class="text-white font-medium mb-3">Detected Patterns</h5>
            <div dusk="patterns-list" class="space-y-2">
                @php
                    $patterns = $citationAnalysisResults['patterns'] ?? [];
                @endphp

                @forelse($patterns as $pattern)
                    <div class="bg-gray-750 rounded-lg p-3 border border-gray-700">
                        <div class="flex items-start gap-2">
                            <svg class="w-5 h-5 text-purple-400 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            <span class="text-sm text-gray-300">{{ $pattern }}</span>
                        </div>
                    </div>
                @empty
                    <p class="text-gray-500 text-center py-4">No patterns detected</p>
                @endforelse
            </div>
        </div>
    </div>
@endif
```

### Step 2: Test citation patterns panel

```bash
php artisan dusk --filter=test_can_analyze_citation_patterns
```

Expected: Patterns panel displays with temporal chart, types, and patterns list

### Step 3: Commit

```bash
git add resources/views/livewire/graph-viewer.blade.php
git commit -m "feat: implement citation patterns panel

- Add temporal distribution bar chart showing citations over time
- Add citation types breakdown (direct vs indirect)
- Add detected patterns list with checkmark icons
- All sections have proper dusk selectors
- Addresses citation patterns requirement from E2E tests"
```

---

## Task 6: Implement Influence Spread Panel

**Priority:** LOW

**Files:**
- Modify: `resources/views/livewire/graph-viewer.blade.php` (add influence panel)

### Step 1: Add influence spread panel

Add after patterns panel in `@if` chain:

```blade
{{-- Influence Spread Panel --}}
@if($citationAnalysisOperation === 'influence')
    <div dusk="influence-spread-panel" class="space-y-6">
        <h4 class="text-lg font-semibold text-white border-b border-gray-700 pb-2">
            Influence Spread
        </h4>

        {{-- Key Metrics --}}
        <div class="grid grid-cols-3 gap-4">
            <div class="bg-gray-750 rounded-lg p-4 border border-gray-700">
                <div class="text-gray-400 text-sm mb-1">Direct Influences</div>
                <div dusk="direct-influence-count" class="text-2xl font-bold text-blue-400">
                    {{ count($citationAnalysisResults['direct_influences'] ?? []) }}
                </div>
            </div>

            <div class="bg-gray-750 rounded-lg p-4 border border-gray-700">
                <div class="text-gray-400 text-sm mb-1">Indirect Influences</div>
                <div dusk="indirect-influence-count" class="text-2xl font-bold text-green-400">
                    {{ count($citationAnalysisResults['indirect_influences'] ?? []) }}
                </div>
            </div>

            <div class="bg-gray-750 rounded-lg p-4 border border-gray-700">
                <div class="text-gray-400 text-sm mb-1">Total Reach</div>
                <div dusk="total-reach-value" class="text-2xl font-bold text-purple-400">
                    {{ $citationAnalysisResults['total_reach'] ?? 0 }}
                </div>
            </div>
        </div>

        {{-- Influence Visualization --}}
        <div>
            <h5 class="text-white font-medium mb-3">Influence Spread Chart</h5>
            <div dusk="influence-spread-chart" class="bg-gray-900 rounded-lg p-6 border border-gray-700">
                <div class="flex items-center justify-center gap-8">
                    {{-- Root Decision --}}
                    <div class="text-center">
                        <div class="w-20 h-20 rounded-full bg-purple-600 flex items-center justify-center mb-2">
                            <svg class="w-10 h-10 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/>
                                <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="text-xs text-gray-400">Root Decision</div>
                    </div>

                    {{-- Arrow --}}
                    <svg class="w-8 h-8 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                    </svg>

                    {{-- Direct Influences --}}
                    <div class="text-center">
                        <div class="w-16 h-16 rounded-full bg-blue-600 flex items-center justify-center mb-2">
                            <span class="text-2xl font-bold text-white">
                                {{ count($citationAnalysisResults['direct_influences'] ?? []) }}
                            </span>
                        </div>
                        <div class="text-xs text-gray-400">Direct</div>
                    </div>

                    {{-- Arrow --}}
                    <svg class="w-8 h-8 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                    </svg>

                    {{-- Indirect Influences --}}
                    <div class="text-center">
                        <div class="w-16 h-16 rounded-full bg-green-600 flex items-center justify-center mb-2">
                            <span class="text-2xl font-bold text-white">
                                {{ count($citationAnalysisResults['indirect_influences'] ?? []) }}
                            </span>
                        </div>
                        <div class="text-xs text-gray-400">Indirect</div>
                    </div>
                </div>

                <p class="text-center text-gray-500 text-xs mt-6">
                    Influence spreads from root decision through direct citations to indirect citations
                </p>
            </div>
        </div>

        {{-- Influenced Decisions List --}}
        <div>
            <h5 class="text-white font-medium mb-3">Influenced Decisions</h5>
            <div dusk="influenced-decisions-list" class="space-y-2 max-h-96 overflow-y-auto">
                @php
                    $allInfluenced = array_merge(
                        array_map(fn($d) => ['decision' => $d, 'level' => 'direct'], $citationAnalysisResults['direct_influences'] ?? []),
                        array_map(fn($d) => ['decision' => $d, 'level' => 'indirect'], $citationAnalysisResults['indirect_influences'] ?? [])
                    );
                @endphp

                @forelse($allInfluenced as $influenced)
                    <div class="bg-gray-750 rounded-lg p-3 border border-gray-700 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-2 h-2 rounded-full {{ $influenced['level'] === 'direct' ? 'bg-blue-400' : 'bg-green-400' }}"></div>
                            <span class="text-sm text-gray-300">{{ $influenced['decision'] }}</span>
                        </div>
                        <span class="text-xs text-gray-500 uppercase">{{ $influenced['level'] }}</span>
                    </div>
                @empty
                    <p class="text-gray-500 text-center py-4">No influenced decisions found</p>
                @endforelse
            </div>
        </div>
    </div>
@endif
```

### Step 2: Test influence spread panel

```bash
php artisan dusk --filter=test_can_visualize_influence_spread
```

Expected: Influence panel displays with metrics, chart, and decisions list

### Step 3: Commit

```bash
git add resources/views/livewire/graph-viewer.blade.php
git commit -m "feat: implement influence spread panel

- Add direct/indirect influence counts and total reach metrics
- Add visual influence spread chart (root → direct → indirect)
- Add influenced decisions list with level badges
- All elements have proper dusk selectors
- Addresses influence spread requirement from E2E tests"
```

---

## Task 7: Run Full E2E Test Suite and Fix Issues

**Priority:** HIGH

**Files:**
- May need minor fixes based on test results

### Step 1: Run all citation network E2E tests

```bash
php artisan dusk tests/Browser/CitationNetworkAnalysisTest.php
```

### Step 2: Review test results

Check for any failures and note which tests pass/fail:
- test_can_view_citation_graph_visualization
- test_can_view_authority_metrics
- test_can_analyze_citation_patterns
- test_can_visualize_influence_spread
- test_can_switch_between_analysis_operations
- test_citation_graph_shows_correct_relationships
- test_influence_classification_based_on_citations

### Step 3: Fix any failing tests

Common issues to check:
- Dusk selectors match exactly (@citation-analysis-button, etc.)
- Data structure from DecisionCitationService matches expectations
- Alpine.js initialization for D3.js graph
- Loading states and error handling

### Step 4: Re-run tests until all pass

```bash
php artisan dusk tests/Browser/CitationNetworkAnalysisTest.php --verbose
```

### Step 5: Commit fixes

```bash
git add .
git commit -m "fix: resolve E2E test failures for citation network analysis

- Fix dusk selector mismatches
- Adjust data structure handling
- Improve error handling and loading states
- All 7 E2E tests now passing"
```

---

## Task 8: Final Polish and Documentation

**Priority:** MEDIUM

**Files:**
- Create: `docs/features/CITATION_NETWORK_ANALYSIS.md` (user documentation)
- Update: `README.md` (add feature to list)

### Step 1: Create user documentation

Create `docs/features/CITATION_NETWORK_ANALYSIS.md`:

```markdown
# Citation Network Analysis

## Overview

Analyze citation relationships between court decisions to understand influence, authority, and citation patterns.

## Features

### 1. Authority Metrics
- **H-Index**: Citation impact score
- **Influence Rank**: Categorical ranking (highly influential → limited)
- **Citation Counts**: Incoming and outgoing citations
- **Authority Score**: Weighted metric combining citation factors

### 2. Citation Graph
- Visual network showing citation relationships
- Interactive force-directed graph with D3.js
- Color-coded nodes (purple=selected, blue=citing, green=cited)
- Drag-to-rearrange functionality

### 3. Citation Patterns
- Temporal distribution of citations over time
- Citation types breakdown (direct vs indirect)
- Pattern detection (e.g., "primarily_direct_citations")

### 4. Influence Spread
- Track how influence propagates through citation network
- Direct influences (1-hop citations)
- Indirect influences (2-hop citations)
- Total reach metrics

## How to Use

1. Navigate to Graph Viewer (`/graph`)
2. Search for a court decision
3. Click "Citation Analysis" button
4. Select analysis type from dropdown:
   - Citation Graph
   - Authority Metrics
   - Citation Patterns
   - Influence Spread
5. Click "Run Analysis"
6. View results in the panel

## Technical Details

- **Service**: `DecisionCitationService`
- **Component**: `GraphViewer` Livewire component
- **Visualization**: D3.js v7 for graph rendering
- **Data Source**: `citation_relationships` table in PostgreSQL

## E2E Tests

Tests located in `tests/Browser/CitationNetworkAnalysisTest.php`:
```bash
php artisan dusk --filter=CitationNetworkAnalysisTest
```

## API

The underlying service can be called programmatically:

```php
use App\Services\DecisionCitationService;

$service = app(DecisionCitationService::class);

// Get authority metrics
$metrics = $service->analyzeCitationNetwork('decision-id', 'authority');

// Get citation graph
$graph = $service->analyzeCitationNetwork('decision-id', 'graph', ['depth' => 2]);

// Get patterns
$patterns = $service->analyzeCitationNetwork('decision-id', 'patterns');

// Get influence spread
$influence = $service->analyzeCitationNetwork('decision-id', 'influence');
```
```

### Step 2: Update README.md

Add to feature list in README.md:

```markdown
### Citation Network Analysis
- Authority metrics (h-index, influence rank, citation counts)
- Interactive citation graph visualization with D3.js
- Citation pattern analysis (temporal distribution, types)
- Influence spread tracking (direct and indirect)
- Integration with Neo4j graph database
```

### Step 3: Commit documentation

```bash
git add docs/features/CITATION_NETWORK_ANALYSIS.md README.md
git commit -m "docs: add citation network analysis documentation

- Create comprehensive feature documentation
- Document all 4 analysis types with usage examples
- Add API usage examples
- Update README.md feature list"
```

---

## Summary

**Total Tasks:** 8
**Priority Breakdown:**
- HIGH: Tasks 1, 2, 3, 7 (Button, Panel, Authority Metrics, Testing)
- MEDIUM: Tasks 4, 8 (Graph Visualization, Documentation)
- LOW: Tasks 5, 6 (Patterns, Influence panels)

**Estimated Time:** 3-4 hours for HIGH priority tasks, 5-6 hours total

**Testing Strategy:**
- TDD GREEN phase - tests already written
- Run E2E tests after each task to verify progress
- Final full test suite run in Task 7

**Dependencies:**
- DecisionCitationService (✅ already implemented)
- D3.js v7 (install via npm)
- Alpine.js (already in project)
- Livewire (already in project)

**Key Files:**
- `app/Http/Livewire/GraphViewer.php` - Main component logic
- `resources/views/livewire/graph-viewer.blade.php` - UI template
- `resources/js/components/citation-graph.js` - D3.js visualization
- `tests/Browser/CitationNetworkAnalysisTest.php` - E2E tests (already exist)
