# Sprint 4: UI Polish Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Rich graph visualization for exploration and analysis with background change notifications.

**Architecture:** Extend existing D3.js citation-graph.js into a full ForceGraph Alpine component with entity type filtering, search, and click-to-expand. Add Livewire analysis panels for Arguments, Evidence, and Timeline. Implement background notifications via Laravel Echo/Reverb for graph data updates.

**Tech Stack:** D3.js v7 (already installed), Alpine.js, Livewire 3, Laravel Echo, Laravel Reverb, Tailwind CSS

---

## Prerequisites

- Sprint 3 Performance complete (batch operations, parallel extraction)
- D3.js v7 installed (confirmed in package.json)
- Laravel Reverb configured
- Neo4j graph database running

---

## Track A: D3.js Force Graph (Exploration)

### Task 1: Create ForceGraph Alpine Component Base

**Files:**
- Create: `resources/js/components/ForceGraph.js`
- Modify: `resources/js/app.js`

**Step 1: Write the ForceGraph component skeleton**

```javascript
// resources/js/components/ForceGraph.js
/**
 * ForceGraph - Interactive D3.js force-directed graph visualization
 *
 * Features: zoom, pan, drag, click-to-expand, filtering, search
 */
export default function ForceGraph() {
    return {
        // State
        nodes: [],
        edges: [],
        selectedNode: null,
        filters: {
            nodeTypes: [],
            activeTypes: [],
        },
        searchQuery: '',
        highlightedNodeId: null,

        // D3 references
        svg: null,
        simulation: null,
        zoom: null,

        // Config
        width: 0,
        height: 600,

        // Node colors by type
        nodeColors: {
            CourtDecisionDocument: '#3B82F6', // blue
            LawDocument: '#10B981',           // green
            Judge: '#8B5CF6',                 // purple
            Lawyer: '#F59E0B',                // amber
            LegalTopic: '#EC4899',            // pink
            LegalConcept: '#6366F1',          // indigo
            Article: '#14B8A6',               // teal
            Verdict: '#EF4444',               // red
            Evidence: '#84CC16',              // lime
            LegalArgument: '#F97316',         // orange
            DateEvent: '#06B6D4',             // cyan
            LegalDefinition: '#D946EF',       // fuchsia
        },

        init() {
            this.width = this.$refs.container.clientWidth;
            this.initSvg();
            this.initZoom();
            this.initSimulation();

            // Watch for data updates
            this.$watch('nodes', () => this.updateGraph());
            this.$watch('edges', () => this.updateGraph());
        },

        initSvg() {
            // Will implement in Task 2
        },

        initZoom() {
            // Will implement in Task 4
        },

        initSimulation() {
            // Will implement in Task 2
        },

        updateGraph() {
            // Will implement in Task 2
        },

        loadData(graphData) {
            this.nodes = graphData.nodes || [];
            this.edges = graphData.edges || [];
            this.extractNodeTypes();
        },

        extractNodeTypes() {
            const types = [...new Set(this.nodes.map(n => n.type))];
            this.filters.nodeTypes = types;
            this.filters.activeTypes = [...types]; // All active by default
        },
    };
}

// Register globally
window.ForceGraph = ForceGraph;
```

**Step 2: Update app.js to import ForceGraph**

```javascript
// resources/js/app.js
import './bootstrap';
import { renderCitationGraph } from './components/citation-graph';
import './components/pdf-viewer';
import ForceGraph from './components/ForceGraph';

// Make ForceGraph available globally for Alpine
window.ForceGraph = ForceGraph;
```

**Step 3: Verify build succeeds**

Run: `npm run build`
Expected: Build completes without errors

**Step 4: Commit**

```bash
git add resources/js/components/ForceGraph.js resources/js/app.js
git commit -m "feat: [Sprint 4 - A.1] Add ForceGraph Alpine component skeleton"
```

---

### Task 2: Implement Node Rendering with Type-Based Colors

**Files:**
- Modify: `resources/js/components/ForceGraph.js`

**Step 1: Implement initSvg and node rendering**

Add to ForceGraph.js `initSvg()` method:

```javascript
initSvg() {
    const container = this.$refs.container;
    container.innerHTML = '';

    this.svg = d3.select(container)
        .append('svg')
        .attr('width', this.width)
        .attr('height', this.height)
        .attr('class', 'bg-gray-900 rounded-lg');

    // Container group for zoom transforms
    this.graphGroup = this.svg.append('g').attr('class', 'graph-group');

    // Separate groups for edges and nodes (edges behind nodes)
    this.edgeGroup = this.graphGroup.append('g').attr('class', 'edges');
    this.nodeGroup = this.graphGroup.append('g').attr('class', 'nodes');
    this.labelGroup = this.graphGroup.append('g').attr('class', 'labels');

    // Define arrowhead marker
    this.svg.append('defs').append('marker')
        .attr('id', 'arrowhead')
        .attr('viewBox', '-0 -5 10 10')
        .attr('refX', 20)
        .attr('refY', 0)
        .attr('orient', 'auto')
        .attr('markerWidth', 6)
        .attr('markerHeight', 6)
        .append('path')
        .attr('d', 'M 0,-5 L 10,0 L 0,5')
        .attr('fill', '#4B5563');
},

initSimulation() {
    this.simulation = d3.forceSimulation()
        .force('link', d3.forceLink().id(d => d.id).distance(100))
        .force('charge', d3.forceManyBody().strength(-300))
        .force('center', d3.forceCenter(this.width / 2, this.height / 2))
        .force('collision', d3.forceCollide().radius(35))
        .on('tick', () => this.tick());
},

updateGraph() {
    // Filter nodes by active types
    const visibleNodes = this.nodes.filter(n =>
        this.filters.activeTypes.includes(n.type)
    );
    const visibleNodeIds = new Set(visibleNodes.map(n => n.id));
    const visibleEdges = this.edges.filter(e =>
        visibleNodeIds.has(e.source.id || e.source) &&
        visibleNodeIds.has(e.target.id || e.target)
    );

    // Update simulation
    this.simulation.nodes(visibleNodes);
    this.simulation.force('link').links(visibleEdges);

    // Render edges
    this.edgeGroup.selectAll('line')
        .data(visibleEdges, d => `${d.source.id || d.source}-${d.target.id || d.target}`)
        .join('line')
        .attr('stroke', '#4B5563')
        .attr('stroke-width', 2)
        .attr('stroke-opacity', 0.6)
        .attr('marker-end', 'url(#arrowhead)');

    // Render nodes
    this.nodeGroup.selectAll('circle')
        .data(visibleNodes, d => d.id)
        .join('circle')
        .attr('r', d => d.id === this.highlightedNodeId ? 20 : 15)
        .attr('fill', d => this.nodeColors[d.type] || '#6B7280')
        .attr('stroke', d => d.id === this.highlightedNodeId ? '#FBBF24' : '#fff')
        .attr('stroke-width', d => d.id === this.highlightedNodeId ? 3 : 2)
        .attr('cursor', 'pointer')
        .on('click', (event, d) => this.onNodeClick(d))
        .call(this.drag());

    // Render labels
    this.labelGroup.selectAll('text')
        .data(visibleNodes, d => d.id)
        .join('text')
        .text(d => d.label || d.name || d.id)
        .attr('font-size', 10)
        .attr('fill', '#E5E7EB')
        .attr('text-anchor', 'middle')
        .attr('dy', -20)
        .attr('pointer-events', 'none');

    this.simulation.alpha(0.3).restart();
},

tick() {
    this.edgeGroup.selectAll('line')
        .attr('x1', d => d.source.x)
        .attr('y1', d => d.source.y)
        .attr('x2', d => d.target.x)
        .attr('y2', d => d.target.y);

    this.nodeGroup.selectAll('circle')
        .attr('cx', d => d.x)
        .attr('cy', d => d.y);

    this.labelGroup.selectAll('text')
        .attr('x', d => d.x)
        .attr('y', d => d.y);
},

onNodeClick(node) {
    this.selectedNode = node;
    this.$dispatch('node-selected', { node });
},

drag() {
    const simulation = this.simulation;

    function dragstarted(event) {
        if (!event.active) simulation.alphaTarget(0.3).restart();
        event.subject.fx = event.subject.x;
        event.subject.fy = event.subject.y;
    }

    function dragged(event) {
        event.subject.fx = event.x;
        event.subject.fy = event.y;
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
},
```

**Step 2: Verify build succeeds**

Run: `npm run build`
Expected: Build completes without errors

**Step 3: Commit**

```bash
git add resources/js/components/ForceGraph.js
git commit -m "feat: [Sprint 4 - A.2] Implement node rendering with type-based colors"
```

---

### Task 3: Implement Edge Rendering with Relationship Styling

**Files:**
- Modify: `resources/js/components/ForceGraph.js`

**Step 1: Add edge styling configuration and rendering**

Add to ForceGraph component:

```javascript
// Edge colors by relationship type
edgeColors: {
    CITES: '#60A5FA',           // blue
    CITED_BY: '#60A5FA',        // blue
    INVOLVES: '#A78BFA',        // purple
    ARGUED_BY: '#FBBF24',       // amber
    DECIDED_BY: '#8B5CF6',      // purple
    REFERENCES: '#34D399',      // green
    CONTAINS: '#F472B6',        // pink
    SUPPORTS: '#4ADE80',        // green
    OPPOSES: '#F87171',         // red
    OCCURRED_ON: '#22D3EE',     // cyan
},

// Update edge rendering in updateGraph()
// Replace the edge rendering section with:
this.edgeGroup.selectAll('line')
    .data(visibleEdges, d => `${d.source.id || d.source}-${d.target.id || d.target}`)
    .join(
        enter => enter.append('line')
            .attr('stroke', d => this.edgeColors[d.type] || '#4B5563')
            .attr('stroke-width', 2)
            .attr('stroke-opacity', 0.6)
            .attr('marker-end', 'url(#arrowhead)'),
        update => update
            .attr('stroke', d => this.edgeColors[d.type] || '#4B5563'),
        exit => exit.remove()
    );
```

**Step 2: Add edge tooltips**

```javascript
// Add to updateGraph() after edge rendering
this.edgeGroup.selectAll('line')
    .append('title')
    .text(d => d.type || 'Related');
```

**Step 3: Verify build succeeds**

Run: `npm run build`
Expected: Build completes without errors

**Step 4: Commit**

```bash
git add resources/js/components/ForceGraph.js
git commit -m "feat: [Sprint 4 - A.3] Implement edge rendering with relationship styling"
```

---

### Task 4: Add Zoom, Pan, Drag Interactions

**Files:**
- Modify: `resources/js/components/ForceGraph.js`

**Step 1: Implement zoom behavior**

Add/update in ForceGraph component:

```javascript
initZoom() {
    this.zoom = d3.zoom()
        .scaleExtent([0.1, 4])
        .on('zoom', (event) => {
            this.graphGroup.attr('transform', event.transform);
        });

    this.svg.call(this.zoom);
},

// Add zoom control methods
zoomIn() {
    this.svg.transition().duration(300).call(
        this.zoom.scaleBy, 1.3
    );
},

zoomOut() {
    this.svg.transition().duration(300).call(
        this.zoom.scaleBy, 0.7
    );
},

resetZoom() {
    this.svg.transition().duration(300).call(
        this.zoom.transform,
        d3.zoomIdentity.translate(this.width / 2, this.height / 2).scale(1)
    );
},

fitToView() {
    if (this.nodes.length === 0) return;

    const bounds = this.graphGroup.node().getBBox();
    const fullWidth = this.width;
    const fullHeight = this.height;
    const width = bounds.width;
    const height = bounds.height;
    const midX = bounds.x + width / 2;
    const midY = bounds.y + height / 2;

    const scale = 0.8 / Math.max(width / fullWidth, height / fullHeight);
    const translate = [fullWidth / 2 - scale * midX, fullHeight / 2 - scale * midY];

    this.svg.transition().duration(500).call(
        this.zoom.transform,
        d3.zoomIdentity.translate(translate[0], translate[1]).scale(scale)
    );
},
```

**Step 2: Verify build succeeds**

Run: `npm run build`
Expected: Build completes without errors

**Step 3: Commit**

```bash
git add resources/js/components/ForceGraph.js
git commit -m "feat: [Sprint 4 - A.4] Add zoom, pan, drag interactions"
```

---

### Task 5: Add Click-to-Expand (Load Connected Nodes)

**Files:**
- Modify: `resources/js/components/ForceGraph.js`
- Create: `app/Livewire/Graph/ForceGraphController.php`
- Create: `resources/views/livewire/graph/force-graph.blade.php`

**Step 1: Add expand functionality to ForceGraph.js**

```javascript
// Add to ForceGraph component
expandedNodeIds: new Set(),
isLoading: false,

async expandNode(node) {
    if (this.expandedNodeIds.has(node.id)) return;

    this.isLoading = true;
    this.$dispatch('expand-node', { nodeId: node.id });
},

addConnectedNodes(newNodes, newEdges) {
    // Merge new nodes (avoid duplicates)
    const existingIds = new Set(this.nodes.map(n => n.id));
    const uniqueNewNodes = newNodes.filter(n => !existingIds.has(n.id));

    this.nodes = [...this.nodes, ...uniqueNewNodes];
    this.edges = [...this.edges, ...newEdges];

    this.expandedNodeIds.add(this.selectedNode?.id);
    this.extractNodeTypes();
    this.isLoading = false;
},

// Update onNodeClick to support double-click expand
onNodeClick(node) {
    this.selectedNode = node;
    this.$dispatch('node-selected', { node });
},

onNodeDoubleClick(node) {
    this.expandNode(node);
},
```

Update node rendering to add double-click:

```javascript
// In updateGraph(), update node rendering
this.nodeGroup.selectAll('circle')
    .data(visibleNodes, d => d.id)
    .join('circle')
    // ... existing attributes ...
    .on('click', (event, d) => this.onNodeClick(d))
    .on('dblclick', (event, d) => this.onNodeDoubleClick(d))
    .call(this.drag());
```

**Step 2: Create Livewire controller**

```php
<?php
// app/Livewire/Graph/ForceGraphController.php

namespace App\Livewire\Graph;

use App\Services\GraphDatabaseService;
use Livewire\Component;
use Livewire\Attributes\On;

class ForceGraphController extends Component
{
    public array $graphData = ['nodes' => [], 'edges' => []];
    public ?string $selectedNodeId = null;
    public ?string $rootNodeId = null;

    public function mount(?string $rootNodeId = null): void
    {
        $this->rootNodeId = $rootNodeId;

        if ($rootNodeId) {
            $this->loadInitialGraph($rootNodeId);
        }
    }

    public function loadInitialGraph(string $nodeId): void
    {
        $graphService = app(GraphDatabaseService::class);

        // Load node and immediate connections
        $this->graphData = $graphService->getNodeWithConnections($nodeId, 1);
        $this->dispatch('graph-data-loaded', graphData: $this->graphData);
    }

    #[On('expand-node')]
    public function expandNode(string $nodeId): void
    {
        $graphService = app(GraphDatabaseService::class);

        // Get connected nodes not already loaded
        $existingIds = array_column($this->graphData['nodes'], 'id');
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

        $this->dispatch('connected-nodes-loaded',
            nodes: $newData['nodes'],
            edges: $newData['edges']
        );
    }

    #[On('node-selected')]
    public function selectNode(array $node): void
    {
        $this->selectedNodeId = $node['id'] ?? null;
    }

    public function render()
    {
        return view('livewire.graph.force-graph');
    }
}
```

**Step 3: Create Blade view**

```blade
{{-- resources/views/livewire/graph/force-graph.blade.php --}}
<div
    x-data="ForceGraph()"
    x-init="loadData(@js($graphData))"
    @graph-data-loaded.window="loadData($event.detail.graphData)"
    @connected-nodes-loaded.window="addConnectedNodes($event.detail.nodes, $event.detail.edges)"
    class="relative"
>
    {{-- Toolbar --}}
    <div class="absolute top-4 left-4 z-10 flex gap-2">
        {{-- Zoom controls --}}
        <div class="bg-gray-800 rounded-lg p-1 flex gap-1">
            <button @click="zoomIn()" class="p-2 hover:bg-gray-700 rounded" title="Zoom In">
                <svg class="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m6-6H6"/>
                </svg>
            </button>
            <button @click="zoomOut()" class="p-2 hover:bg-gray-700 rounded" title="Zoom Out">
                <svg class="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 12H6"/>
                </svg>
            </button>
            <button @click="fitToView()" class="p-2 hover:bg-gray-700 rounded" title="Fit to View">
                <svg class="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/>
                </svg>
            </button>
        </div>
    </div>

    {{-- Loading indicator --}}
    <div x-show="isLoading" class="absolute top-4 right-4 z-10">
        <div class="bg-gray-800 rounded-lg px-3 py-2 flex items-center gap-2">
            <svg class="animate-spin h-4 w-4 text-blue-500" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
            <span class="text-sm text-gray-300">Loading...</span>
        </div>
    </div>

    {{-- Graph container --}}
    <div x-ref="container" class="w-full h-[600px] bg-gray-900 rounded-lg"></div>

    {{-- Legend --}}
    <div class="absolute bottom-4 left-4 z-10 bg-gray-800/90 rounded-lg p-3">
        <div class="text-xs text-gray-400 mb-2 font-medium">Node Types</div>
        <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-xs">
            <template x-for="type in filters.nodeTypes" :key="type">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input
                        type="checkbox"
                        :checked="filters.activeTypes.includes(type)"
                        @change="toggleNodeType(type)"
                        class="rounded border-gray-600 bg-gray-700 text-blue-500 focus:ring-blue-500"
                    >
                    <span
                        class="w-3 h-3 rounded-full"
                        :style="`background-color: ${nodeColors[type] || '#6B7280'}`"
                    ></span>
                    <span class="text-gray-300" x-text="type"></span>
                </label>
            </template>
        </div>
    </div>

    {{-- Instructions --}}
    <div class="absolute bottom-4 right-4 z-10 bg-gray-800/90 rounded-lg p-3 text-xs text-gray-400">
        <div><kbd class="bg-gray-700 px-1 rounded">Click</kbd> Select node</div>
        <div><kbd class="bg-gray-700 px-1 rounded">Double-click</kbd> Expand connections</div>
        <div><kbd class="bg-gray-700 px-1 rounded">Drag</kbd> Move node</div>
        <div><kbd class="bg-gray-700 px-1 rounded">Scroll</kbd> Zoom</div>
    </div>
</div>
```

**Step 4: Add toggleNodeType method to ForceGraph.js**

```javascript
toggleNodeType(type) {
    const index = this.filters.activeTypes.indexOf(type);
    if (index > -1) {
        this.filters.activeTypes.splice(index, 1);
    } else {
        this.filters.activeTypes.push(type);
    }
    this.updateGraph();
},
```

**Step 5: Verify build and files**

Run: `npm run build`
Expected: Build completes without errors

**Step 6: Commit**

```bash
git add resources/js/components/ForceGraph.js \
    app/Livewire/Graph/ForceGraphController.php \
    resources/views/livewire/graph/force-graph.blade.php
git commit -m "feat: [Sprint 4 - A.5] Add click-to-expand with Livewire controller"
```

---

### Task 6: Add Entity Type Filter Controls

**Files:**
- Modify: `resources/views/livewire/graph/force-graph.blade.php`
- Modify: `resources/js/components/ForceGraph.js`

**Step 1: Add filter panel to blade view**

Already included in Task 5 blade view (Legend section with checkboxes).

**Step 2: Add filter all/none buttons**

Update the legend section in force-graph.blade.php:

```blade
{{-- Legend with filters --}}
<div class="absolute bottom-4 left-4 z-10 bg-gray-800/90 rounded-lg p-3 max-w-xs">
    <div class="flex justify-between items-center mb-2">
        <span class="text-xs text-gray-400 font-medium">Node Types</span>
        <div class="flex gap-1">
            <button
                @click="filters.activeTypes = [...filters.nodeTypes]; updateGraph()"
                class="text-xs text-blue-400 hover:text-blue-300"
            >All</button>
            <span class="text-gray-600">|</span>
            <button
                @click="filters.activeTypes = []; updateGraph()"
                class="text-xs text-blue-400 hover:text-blue-300"
            >None</button>
        </div>
    </div>
    <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-xs max-h-48 overflow-y-auto">
        {{-- Filter checkboxes from Task 5 --}}
    </div>
</div>
```

**Step 3: Commit**

```bash
git add resources/views/livewire/graph/force-graph.blade.php
git commit -m "feat: [Sprint 4 - A.6] Add entity type filter controls"
```

---

### Task 7: Add Search/Highlight Node by Name

**Files:**
- Modify: `resources/views/livewire/graph/force-graph.blade.php`
- Modify: `resources/js/components/ForceGraph.js`

**Step 1: Add search input to toolbar**

Update force-graph.blade.php toolbar:

```blade
{{-- Search --}}
<div class="bg-gray-800 rounded-lg p-1 flex items-center">
    <input
        type="text"
        x-model.debounce.300ms="searchQuery"
        @input="searchNodes()"
        placeholder="Search nodes..."
        class="bg-transparent border-none text-sm text-gray-300 placeholder-gray-500 focus:ring-0 w-48"
    >
    <button
        x-show="searchQuery"
        @click="searchQuery = ''; clearSearch()"
        class="p-1 hover:bg-gray-700 rounded"
    >
        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
    </button>
</div>

{{-- Search results --}}
<div x-show="searchResults.length > 0" class="absolute top-16 left-4 z-20 bg-gray-800 rounded-lg shadow-lg max-h-64 overflow-y-auto w-64">
    <template x-for="result in searchResults" :key="result.id">
        <button
            @click="focusOnNode(result)"
            class="w-full px-3 py-2 text-left text-sm hover:bg-gray-700 flex items-center gap-2"
        >
            <span
                class="w-3 h-3 rounded-full flex-shrink-0"
                :style="`background-color: ${nodeColors[result.type] || '#6B7280'}`"
            ></span>
            <span class="text-gray-300 truncate" x-text="result.label || result.name || result.id"></span>
            <span class="text-xs text-gray-500" x-text="result.type"></span>
        </button>
    </template>
</div>
```

**Step 2: Add search methods to ForceGraph.js**

```javascript
searchResults: [],

searchNodes() {
    if (!this.searchQuery.trim()) {
        this.searchResults = [];
        this.highlightedNodeId = null;
        this.updateGraph();
        return;
    }

    const query = this.searchQuery.toLowerCase();
    this.searchResults = this.nodes.filter(node => {
        const label = (node.label || node.name || node.id || '').toLowerCase();
        return label.includes(query);
    }).slice(0, 10); // Limit results
},

clearSearch() {
    this.searchResults = [];
    this.highlightedNodeId = null;
    this.updateGraph();
},

focusOnNode(node) {
    this.highlightedNodeId = node.id;
    this.selectedNode = node;
    this.searchResults = [];

    // Center view on node
    const scale = 1.5;
    this.svg.transition().duration(500).call(
        this.zoom.transform,
        d3.zoomIdentity.translate(
            this.width / 2 - scale * node.x,
            this.height / 2 - scale * node.y
        ).scale(scale)
    );

    this.updateGraph();
    this.$dispatch('node-selected', { node });
},
```

**Step 3: Verify build succeeds**

Run: `npm run build`
Expected: Build completes without errors

**Step 4: Commit**

```bash
git add resources/views/livewire/graph/force-graph.blade.php resources/js/components/ForceGraph.js
git commit -m "feat: [Sprint 4 - A.7] Add search and highlight node by name"
```

---

### Task 8: Performance Optimization for 500+ Nodes

**Files:**
- Modify: `resources/js/components/ForceGraph.js`

**Step 1: Add performance optimizations**

```javascript
// Add to ForceGraph component
performanceMode: false, // Auto-enable for large graphs
nodeLimit: 500,

updateGraph() {
    // Enable performance mode for large graphs
    this.performanceMode = this.nodes.length > 200;

    // Filter nodes by active types
    let visibleNodes = this.nodes.filter(n =>
        this.filters.activeTypes.includes(n.type)
    );

    // Limit nodes in performance mode
    if (visibleNodes.length > this.nodeLimit) {
        console.warn(`Graph has ${visibleNodes.length} nodes, limiting to ${this.nodeLimit}`);
        visibleNodes = visibleNodes.slice(0, this.nodeLimit);
    }

    const visibleNodeIds = new Set(visibleNodes.map(n => n.id));
    const visibleEdges = this.edges.filter(e =>
        visibleNodeIds.has(e.source.id || e.source) &&
        visibleNodeIds.has(e.target.id || e.target)
    );

    // Update simulation with performance tweaks
    this.simulation
        .nodes(visibleNodes)
        .alpha(this.performanceMode ? 0.1 : 0.3);

    if (this.performanceMode) {
        this.simulation
            .force('charge', d3.forceManyBody().strength(-100)) // Weaker charge
            .alphaDecay(0.05); // Faster settling
    }

    this.simulation.force('link').links(visibleEdges);

    // Render edges (simplified in performance mode)
    this.edgeGroup.selectAll('line')
        .data(visibleEdges, d => `${d.source.id || d.source}-${d.target.id || d.target}`)
        .join('line')
        .attr('stroke', d => this.performanceMode ? '#4B5563' : (this.edgeColors[d.type] || '#4B5563'))
        .attr('stroke-width', this.performanceMode ? 1 : 2)
        .attr('stroke-opacity', 0.6)
        .attr('marker-end', this.performanceMode ? null : 'url(#arrowhead)');

    // Render nodes
    this.nodeGroup.selectAll('circle')
        .data(visibleNodes, d => d.id)
        .join('circle')
        .attr('r', d => {
            if (d.id === this.highlightedNodeId) return 20;
            return this.performanceMode ? 8 : 15;
        })
        .attr('fill', d => this.nodeColors[d.type] || '#6B7280')
        .attr('stroke', d => d.id === this.highlightedNodeId ? '#FBBF24' : '#fff')
        .attr('stroke-width', d => d.id === this.highlightedNodeId ? 3 : (this.performanceMode ? 1 : 2))
        .attr('cursor', 'pointer')
        .on('click', (event, d) => this.onNodeClick(d))
        .on('dblclick', (event, d) => this.onNodeDoubleClick(d))
        .call(this.drag());

    // Skip labels in performance mode
    if (this.performanceMode) {
        this.labelGroup.selectAll('text').remove();
    } else {
        this.labelGroup.selectAll('text')
            .data(visibleNodes, d => d.id)
            .join('text')
            .text(d => d.label || d.name || d.id)
            .attr('font-size', 10)
            .attr('fill', '#E5E7EB')
            .attr('text-anchor', 'middle')
            .attr('dy', -20)
            .attr('pointer-events', 'none');
    }

    this.simulation.restart();
},

// Add performance indicator to view
```

**Step 2: Add performance indicator to blade view**

```blade
{{-- Performance mode indicator --}}
<div x-show="performanceMode" class="absolute top-4 left-1/2 -translate-x-1/2 z-10">
    <div class="bg-yellow-900/80 text-yellow-200 text-xs px-3 py-1 rounded-full">
        Performance mode (labels hidden, <span x-text="nodes.length"></span> nodes)
    </div>
</div>
```

**Step 3: Verify build succeeds**

Run: `npm run build`
Expected: Build completes without errors

**Step 4: Commit**

```bash
git add resources/js/components/ForceGraph.js resources/views/livewire/graph/force-graph.blade.php
git commit -m "feat: [Sprint 4 - A.8] Performance optimization for 500+ nodes"
```

---

### Task 9: Write Dusk Browser Tests for ForceGraph

**Files:**
- Create: `tests/Browser/ForceGraphTest.php`

**Step 1: Create Dusk test**

```php
<?php
// tests/Browser/ForceGraphTest.php

namespace Tests\Browser;

use App\Models\CourtDecisionDocument;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class ForceGraphTest extends DuskTestCase
{
    /** @test */
    public function it_renders_force_graph_component(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/graph/explore')
                ->waitFor('[x-data*="ForceGraph"]', 5)
                ->assertPresent('svg')
                ->assertPresent('.graph-group');
        });
    }

    /** @test */
    public function it_displays_zoom_controls(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/graph/explore')
                ->waitFor('[x-data*="ForceGraph"]', 5)
                ->assertPresent('button[title="Zoom In"]')
                ->assertPresent('button[title="Zoom Out"]')
                ->assertPresent('button[title="Fit to View"]');
        });
    }

    /** @test */
    public function it_shows_node_type_filters(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/graph/explore')
                ->waitFor('[x-data*="ForceGraph"]', 5)
                ->assertSee('Node Types')
                ->assertPresent('input[type="checkbox"]');
        });
    }

    /** @test */
    public function it_has_search_input(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/graph/explore')
                ->waitFor('[x-data*="ForceGraph"]', 5)
                ->assertPresent('input[placeholder="Search nodes..."]');
        });
    }

    /** @test */
    public function it_shows_instructions(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/graph/explore')
                ->waitFor('[x-data*="ForceGraph"]', 5)
                ->assertSee('Click')
                ->assertSee('Double-click')
                ->assertSee('Drag')
                ->assertSee('Scroll');
        });
    }
}
```

**Step 2: Add route for graph explore page**

```php
// routes/web.php (add this route)
Route::get('/graph/explore/{nodeId?}', function (?string $nodeId = null) {
    return view('graph.explore', ['nodeId' => $nodeId]);
})->name('graph.explore');
```

**Step 3: Create explore view**

```blade
{{-- resources/views/graph/explore.blade.php --}}
<x-app-layout>
    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h1 class="text-2xl font-semibold text-gray-100 mb-4">Graph Explorer</h1>
            <livewire:graph.force-graph-controller :root-node-id="$nodeId" />
        </div>
    </div>
</x-app-layout>
```

**Step 4: Run Dusk tests**

Run: `php artisan dusk tests/Browser/ForceGraphTest.php`
Expected: Tests may fail initially (routes/views need full setup)

**Step 5: Commit**

```bash
git add tests/Browser/ForceGraphTest.php routes/web.php resources/views/graph/explore.blade.php
git commit -m "test: [Sprint 4 - A.9] Add Dusk browser tests for ForceGraph"
```

---

## Track B: Enhanced Analysis Panels

### Task 10: Create Collapsible Sidebar with Tabbed Navigation

**Files:**
- Create: `resources/views/livewire/graph/partials/sidebar.blade.php`
- Modify: `resources/views/livewire/graph/force-graph.blade.php`

**Step 1: Create sidebar partial**

```blade
{{-- resources/views/livewire/graph/partials/sidebar.blade.php --}}
<div
    x-data="{
        open: true,
        activeTab: 'arguments',
        tabs: [
            { id: 'arguments', label: 'Arguments', icon: 'chat-bubble-left-right' },
            { id: 'evidence', label: 'Evidence', icon: 'document-text' },
            { id: 'timeline', label: 'Timeline', icon: 'clock' },
        ]
    }"
    class="fixed right-0 top-0 h-full z-30 flex"
>
    {{-- Toggle button --}}
    <button
        @click="open = !open"
        class="self-center -ml-10 bg-gray-800 hover:bg-gray-700 rounded-l-lg p-2 shadow-lg"
    >
        <svg
            class="w-5 h-5 text-gray-300 transition-transform duration-200"
            :class="{ 'rotate-180': open }"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
        >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
    </button>

    {{-- Sidebar panel --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
        class="w-96 bg-gray-900 border-l border-gray-700 shadow-xl h-full overflow-hidden flex flex-col"
    >
        {{-- Tabs --}}
        <div class="flex border-b border-gray-700">
            <template x-for="tab in tabs" :key="tab.id">
                <button
                    @click="activeTab = tab.id"
                    :class="activeTab === tab.id ? 'bg-gray-800 text-blue-400 border-b-2 border-blue-400' : 'text-gray-400 hover:text-gray-200'"
                    class="flex-1 px-4 py-3 text-sm font-medium transition-colors"
                >
                    <span x-text="tab.label"></span>
                </button>
            </template>
        </div>

        {{-- Tab content --}}
        <div class="flex-1 overflow-y-auto">
            <div x-show="activeTab === 'arguments'">
                @include('livewire.graph.partials.arguments-panel')
            </div>
            <div x-show="activeTab === 'evidence'">
                @include('livewire.graph.partials.evidence-panel')
            </div>
            <div x-show="activeTab === 'timeline'">
                @include('livewire.graph.partials.timeline-panel')
            </div>
        </div>
    </div>
</div>
```

**Step 2: Update force-graph.blade.php to include sidebar**

```blade
{{-- At end of force-graph.blade.php, before closing </div> --}}
@include('livewire.graph.partials.sidebar')
```

**Step 3: Commit**

```bash
git add resources/views/livewire/graph/partials/sidebar.blade.php \
    resources/views/livewire/graph/force-graph.blade.php
git commit -m "feat: [Sprint 4 - B.1] Create collapsible sidebar with tabbed navigation"
```

---

### Task 11: Add Arguments Panel

**Files:**
- Create: `resources/views/livewire/graph/partials/arguments-panel.blade.php`
- Modify: `app/Livewire/Graph/ForceGraphController.php`

**Step 1: Create arguments panel**

```blade
{{-- resources/views/livewire/graph/partials/arguments-panel.blade.php --}}
<div class="p-4 space-y-4">
    <h3 class="text-lg font-medium text-gray-200">Legal Arguments</h3>

    @if(empty($arguments))
        <div class="text-center py-8 text-gray-500">
            <svg class="w-12 h-12 mx-auto mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
            </svg>
            <p>Select a decision node to view arguments</p>
        </div>
    @else
        {{-- Plaintiff Arguments --}}
        @if(!empty($arguments['plaintiff']))
            <div class="space-y-2">
                <h4 class="text-sm font-medium text-blue-400 flex items-center gap-2">
                    <span class="w-2 h-2 bg-blue-400 rounded-full"></span>
                    Plaintiff Arguments ({{ count($arguments['plaintiff']) }})
                </h4>
                @foreach($arguments['plaintiff'] as $arg)
                    <div class="bg-gray-800 rounded-lg p-3 text-sm text-gray-300 border-l-2 border-blue-400">
                        {{ $arg['content'] }}
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Defendant Arguments --}}
        @if(!empty($arguments['defendant']))
            <div class="space-y-2">
                <h4 class="text-sm font-medium text-red-400 flex items-center gap-2">
                    <span class="w-2 h-2 bg-red-400 rounded-full"></span>
                    Defendant Arguments ({{ count($arguments['defendant']) }})
                </h4>
                @foreach($arguments['defendant'] as $arg)
                    <div class="bg-gray-800 rounded-lg p-3 text-sm text-gray-300 border-l-2 border-red-400">
                        {{ $arg['content'] }}
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Court Reasoning --}}
        @if(!empty($arguments['court']))
            <div class="space-y-2">
                <h4 class="text-sm font-medium text-purple-400 flex items-center gap-2">
                    <span class="w-2 h-2 bg-purple-400 rounded-full"></span>
                    Court Reasoning ({{ count($arguments['court']) }})
                </h4>
                @foreach($arguments['court'] as $arg)
                    <div class="bg-gray-800 rounded-lg p-3 text-sm text-gray-300 border-l-2 border-purple-400">
                        {{ $arg['content'] }}
                    </div>
                @endforeach
            </div>
        @endif
    @endif
</div>
```

**Step 2: Add arguments loading to controller**

```php
// Add to ForceGraphController.php
public array $arguments = [];

#[On('node-selected')]
public function selectNode(array $node): void
{
    $this->selectedNodeId = $node['id'] ?? null;

    if ($node['type'] === 'CourtDecisionDocument') {
        $this->loadArgumentsForDecision($node['id']);
    } else {
        $this->arguments = [];
    }
}

protected function loadArgumentsForDecision(string $decisionId): void
{
    $graphService = app(GraphDatabaseService::class);

    $args = $graphService->getArgumentsForDecision($decisionId);

    $this->arguments = [
        'plaintiff' => array_filter($args, fn($a) => ($a['party_type'] ?? '') === 'plaintiff'),
        'defendant' => array_filter($args, fn($a) => ($a['party_type'] ?? '') === 'defendant'),
        'court' => array_filter($args, fn($a) => ($a['party_type'] ?? '') === 'court'),
    ];
}
```

**Step 3: Commit**

```bash
git add resources/views/livewire/graph/partials/arguments-panel.blade.php \
    app/Livewire/Graph/ForceGraphController.php
git commit -m "feat: [Sprint 4 - B.2] Add Arguments panel with party grouping"
```

---

### Task 12: Add Evidence Panel

**Files:**
- Create: `resources/views/livewire/graph/partials/evidence-panel.blade.php`
- Modify: `app/Livewire/Graph/ForceGraphController.php`

**Step 1: Create evidence panel**

```blade
{{-- resources/views/livewire/graph/partials/evidence-panel.blade.php --}}
<div class="p-4 space-y-4">
    <h3 class="text-lg font-medium text-gray-200">Evidence</h3>

    @if(empty($evidence))
        <div class="text-center py-8 text-gray-500">
            <svg class="w-12 h-12 mx-auto mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <p>Select a decision node to view evidence</p>
        </div>
    @else
        {{-- Evidence type icons --}}
        @php
            $evidenceIcons = [
                'documentary' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>',
                'testimonial' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>',
                'expert' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>',
                'physical' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>',
            ];
            $evidenceColors = [
                'documentary' => 'lime',
                'testimonial' => 'cyan',
                'expert' => 'amber',
                'physical' => 'rose',
            ];
        @endphp

        @foreach(['documentary', 'testimonial', 'expert', 'physical'] as $type)
            @if(!empty($evidence[$type]))
                <div class="space-y-2">
                    <h4 class="text-sm font-medium text-{{ $evidenceColors[$type] }}-400 flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            {!! $evidenceIcons[$type] !!}
                        </svg>
                        {{ ucfirst($type) }} Evidence ({{ count($evidence[$type]) }})
                    </h4>
                    @foreach($evidence[$type] as $item)
                        <div class="bg-gray-800 rounded-lg p-3 text-sm border-l-2 border-{{ $evidenceColors[$type] }}-400">
                            <div class="text-gray-300">{{ $item['description'] }}</div>
                            @if(!empty($item['source']))
                                <div class="text-xs text-gray-500 mt-1">Source: {{ $item['source'] }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        @endforeach
    @endif
</div>
```

**Step 2: Add evidence loading to controller**

```php
// Add to ForceGraphController.php
public array $evidence = [];

// Update selectNode method
#[On('node-selected')]
public function selectNode(array $node): void
{
    $this->selectedNodeId = $node['id'] ?? null;

    if ($node['type'] === 'CourtDecisionDocument') {
        $this->loadArgumentsForDecision($node['id']);
        $this->loadEvidenceForDecision($node['id']);
    } else {
        $this->arguments = [];
        $this->evidence = [];
    }
}

protected function loadEvidenceForDecision(string $decisionId): void
{
    $graphService = app(GraphDatabaseService::class);

    $items = $graphService->getEvidenceForDecision($decisionId);

    $this->evidence = [
        'documentary' => array_filter($items, fn($e) => ($e['evidence_type'] ?? '') === 'documentary'),
        'testimonial' => array_filter($items, fn($e) => ($e['evidence_type'] ?? '') === 'testimonial'),
        'expert' => array_filter($items, fn($e) => ($e['evidence_type'] ?? '') === 'expert'),
        'physical' => array_filter($items, fn($e) => ($e['evidence_type'] ?? '') === 'physical'),
    ];
}
```

**Step 3: Commit**

```bash
git add resources/views/livewire/graph/partials/evidence-panel.blade.php \
    app/Livewire/Graph/ForceGraphController.php
git commit -m "feat: [Sprint 4 - B.3] Add Evidence panel with type categorization"
```

---

### Task 13: Add Timeline Panel

**Files:**
- Create: `resources/views/livewire/graph/partials/timeline-panel.blade.php`
- Modify: `app/Livewire/Graph/ForceGraphController.php`

**Step 1: Create timeline panel**

```blade
{{-- resources/views/livewire/graph/partials/timeline-panel.blade.php --}}
<div class="p-4 space-y-4">
    <h3 class="text-lg font-medium text-gray-200">Timeline</h3>

    @if(empty($timeline))
        <div class="text-center py-8 text-gray-500">
            <svg class="w-12 h-12 mx-auto mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p>Select a decision node to view timeline</p>
        </div>
    @else
        <div class="relative">
            {{-- Vertical line --}}
            <div class="absolute left-4 top-0 bottom-0 w-0.5 bg-gray-700"></div>

            <div class="space-y-4">
                @foreach($timeline as $event)
                    <div class="relative pl-10">
                        {{-- Dot --}}
                        <div class="absolute left-2.5 w-3 h-3 rounded-full
                            @switch($event['event_type'] ?? 'other')
                                @case('filing') bg-blue-500 @break
                                @case('hearing') bg-amber-500 @break
                                @case('judgment') bg-green-500 @break
                                @case('appeal') bg-purple-500 @break
                                @default bg-gray-500
                            @endswitch
                        "></div>

                        {{-- Event card --}}
                        <div class="bg-gray-800 rounded-lg p-3">
                            <div class="flex justify-between items-start">
                                <div>
                                    <span class="text-xs font-medium uppercase tracking-wider
                                        @switch($event['event_type'] ?? 'other')
                                            @case('filing') text-blue-400 @break
                                            @case('hearing') text-amber-400 @break
                                            @case('judgment') text-green-400 @break
                                            @case('appeal') text-purple-400 @break
                                            @default text-gray-400
                                        @endswitch
                                    ">
                                        {{ $event['event_type'] ?? 'Event' }}
                                    </span>
                                    <div class="text-sm text-gray-300 mt-1">
                                        {{ $event['description'] }}
                                    </div>
                                </div>
                                <span class="text-xs text-gray-500 whitespace-nowrap ml-2">
                                    {{ \Carbon\Carbon::parse($event['date'])->format('M j, Y') }}
                                </span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
```

**Step 2: Add timeline loading to controller**

```php
// Add to ForceGraphController.php
public array $timeline = [];

// Update selectNode method
#[On('node-selected')]
public function selectNode(array $node): void
{
    $this->selectedNodeId = $node['id'] ?? null;

    if ($node['type'] === 'CourtDecisionDocument') {
        $this->loadArgumentsForDecision($node['id']);
        $this->loadEvidenceForDecision($node['id']);
        $this->loadTimelineForDecision($node['id']);
    } else {
        $this->arguments = [];
        $this->evidence = [];
        $this->timeline = [];
    }
}

protected function loadTimelineForDecision(string $decisionId): void
{
    $graphService = app(GraphDatabaseService::class);

    $events = $graphService->getDateEventsForDecision($decisionId);

    // Sort by date
    usort($events, fn($a, $b) => strtotime($a['date']) <=> strtotime($b['date']));

    $this->timeline = $events;
}
```

**Step 3: Commit**

```bash
git add resources/views/livewire/graph/partials/timeline-panel.blade.php \
    app/Livewire/Graph/ForceGraphController.php
git commit -m "feat: [Sprint 4 - B.4] Add Timeline panel with date events"
```

---

### Task 14: Add Deep-Link Routing to Panels

**Files:**
- Modify: `routes/web.php`
- Modify: `app/Livewire/Graph/ForceGraphController.php`
- Modify: `resources/views/livewire/graph/partials/sidebar.blade.php`

**Step 1: Update routes**

```php
// routes/web.php
Route::get('/graph/explore/{nodeId?}', function (?string $nodeId = null) {
    return view('graph.explore', [
        'nodeId' => $nodeId,
        'panel' => request()->query('panel', 'arguments'),
    ]);
})->name('graph.explore');
```

**Step 2: Update controller to accept panel param**

```php
// ForceGraphController.php
public string $activePanel = 'arguments';

public function mount(?string $rootNodeId = null, string $panel = 'arguments'): void
{
    $this->rootNodeId = $rootNodeId;
    $this->activePanel = $panel;

    if ($rootNodeId) {
        $this->loadInitialGraph($rootNodeId);
    }
}
```

**Step 3: Update sidebar to use activePanel**

```blade
{{-- In sidebar.blade.php, update x-data --}}
<div
    x-data="{
        open: true,
        activeTab: @js($activePanel ?? 'arguments'),
        // ... rest
    }"
>
```

**Step 4: Update explore view**

```blade
{{-- resources/views/graph/explore.blade.php --}}
<x-app-layout>
    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h1 class="text-2xl font-semibold text-gray-100 mb-4">Graph Explorer</h1>
            <livewire:graph.force-graph-controller
                :root-node-id="$nodeId"
                :panel="$panel ?? 'arguments'"
            />
        </div>
    </div>
</x-app-layout>
```

**Step 5: Commit**

```bash
git add routes/web.php app/Livewire/Graph/ForceGraphController.php \
    resources/views/livewire/graph/partials/sidebar.blade.php \
    resources/views/graph/explore.blade.php
git commit -m "feat: [Sprint 4 - B.5] Add deep-link routing to panels"
```

---

### Task 15: Write Livewire Feature Tests for Panels

**Files:**
- Create: `tests/Feature/Livewire/GraphViewerPanelsTest.php`

**Step 1: Create test file**

```php
<?php
// tests/Feature/Livewire/GraphViewerPanelsTest.php

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
```

**Step 2: Run tests**

Run: `./vendor/bin/phpunit tests/Feature/Livewire/GraphViewerPanelsTest.php`
Expected: Tests pass (or indicate missing GraphDatabaseService methods)

**Step 3: Commit**

```bash
git add tests/Feature/Livewire/GraphViewerPanelsTest.php
git commit -m "test: [Sprint 4 - B.6] Add Livewire feature tests for panels"
```

---

## Track C: Background Notifications

### Task 16: Configure Laravel Echo with Reverb

**Files:**
- Modify: `resources/js/bootstrap.js`
- Verify: `.env` has Reverb config

**Step 1: Add Echo configuration to bootstrap.js**

```javascript
// resources/js/bootstrap.js
import axios from 'axios';
import {Timeline} from "vis-timeline/peer";
import {DataSet, DataView, Queue} from "vis-data";
import "vis-timeline/styles/vis-timeline-graph2d.css";
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.axios = axios;
window.dataset = DataSet;
window.timeline = Timeline;
window.Pusher = Pusher;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Laravel Echo configuration for Reverb
window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
});
```

**Step 2: Install Echo and Pusher if needed**

Run: `npm install laravel-echo pusher-js --save`

**Step 3: Verify .env has Reverb config**

```bash
# Check .env for these values (should already exist)
REVERB_APP_ID=...
REVERB_APP_KEY=...
REVERB_APP_SECRET=...
REVERB_HOST="localhost"
REVERB_PORT=8080
REVERB_SCHEME=http

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

**Step 4: Verify build succeeds**

Run: `npm run build`
Expected: Build completes without errors

**Step 5: Commit**

```bash
git add resources/js/bootstrap.js package.json package-lock.json
git commit -m "feat: [Sprint 4 - C.1] Configure Laravel Echo with Reverb"
```

---

### Task 17: Create GraphDataUpdated Broadcast Event

**Files:**
- Create: `app/Events/Graph/GraphDataUpdated.php`

**Step 1: Create event**

```php
<?php
// app/Events/Graph/GraphDataUpdated.php

namespace App\Events\Graph;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GraphDataUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $updateType, // 'sync_complete', 'new_decision', 'new_relationships'
        public int $nodesAffected,
        public int $relationshipsAffected,
        public ?string $decisionId = null,
        public ?string $message = null
    ) {
        $this->message = $message ?? $this->generateMessage();
    }

    protected function generateMessage(): string
    {
        return match ($this->updateType) {
            'sync_complete' => "Graph sync complete: {$this->nodesAffected} nodes, {$this->relationshipsAffected} relationships",
            'new_decision' => "New decision added to graph",
            'new_relationships' => "{$this->relationshipsAffected} new relationships discovered",
            default => "Graph data updated",
        };
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('graph-updates'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'graph.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'type' => $this->updateType,
            'nodes' => $this->nodesAffected,
            'relationships' => $this->relationshipsAffected,
            'decisionId' => $this->decisionId,
            'message' => $this->message,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
```

**Step 2: Dispatch event from EnhancedGraphSyncJob (update existing)**

```php
// In EnhancedGraphSyncJob, after successful sync
use App\Events\Graph\GraphDataUpdated;

// At end of handle() method, after sync completes:
event(new GraphDataUpdated(
    updateType: 'sync_complete',
    nodesAffected: $totalNodesCreated,
    relationshipsAffected: $totalRelationshipsCreated
));
```

**Step 3: Commit**

```bash
git add app/Events/Graph/GraphDataUpdated.php
git commit -m "feat: [Sprint 4 - C.2] Create GraphDataUpdated broadcast event"
```

---

### Task 18: Implement Toast Notification Component

**Files:**
- Create: `resources/views/components/toast-notification.blade.php`

**Step 1: Create toast component**

```blade
{{-- resources/views/components/toast-notification.blade.php --}}
<div
    x-data="{
        notifications: [],
        add(notification) {
            const id = Date.now();
            this.notifications.push({ id, ...notification });
            setTimeout(() => this.remove(id), notification.duration || 5000);
        },
        remove(id) {
            this.notifications = this.notifications.filter(n => n.id !== id);
        }
    }"
    @graph-notification.window="add($event.detail)"
    class="fixed top-4 right-4 z-50 space-y-2"
>
    <template x-for="notification in notifications" :key="notification.id">
        <div
            x-show="true"
            x-transition:enter="transform ease-out duration-300 transition"
            x-transition:enter-start="translate-x-full opacity-0"
            x-transition:enter-end="translate-x-0 opacity-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="max-w-sm w-full bg-gray-800 shadow-lg rounded-lg pointer-events-auto ring-1 ring-black ring-opacity-5 overflow-hidden"
        >
            <div class="p-4">
                <div class="flex items-start">
                    {{-- Icon --}}
                    <div class="flex-shrink-0">
                        <svg
                            x-show="notification.type === 'success'"
                            class="h-6 w-6 text-green-400"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <svg
                            x-show="notification.type === 'info'"
                            class="h-6 w-6 text-blue-400"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>

                    {{-- Content --}}
                    <div class="ml-3 w-0 flex-1">
                        <p class="text-sm font-medium text-gray-100" x-text="notification.title"></p>
                        <p class="mt-1 text-sm text-gray-400" x-text="notification.message"></p>

                        {{-- Action button --}}
                        <div x-show="notification.action" class="mt-3">
                            <button
                                @click="notification.action?.callback(); remove(notification.id)"
                                class="text-sm font-medium text-blue-400 hover:text-blue-300"
                                x-text="notification.action?.label || 'Refresh'"
                            ></button>
                        </div>
                    </div>

                    {{-- Close button --}}
                    <div class="ml-4 flex-shrink-0 flex">
                        <button
                            @click="remove(notification.id)"
                            class="rounded-md inline-flex text-gray-400 hover:text-gray-300 focus:outline-none"
                        >
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>
```

**Step 2: Include toast in layout**

```blade
{{-- In resources/views/layouts/app.blade.php, before </body> --}}
<x-toast-notification />
```

**Step 3: Commit**

```bash
git add resources/views/components/toast-notification.blade.php
git commit -m "feat: [Sprint 4 - C.3] Implement toast notification component"
```

---

### Task 19: Add Badge Counter for Pending Updates

**Files:**
- Modify: `resources/views/livewire/graph/force-graph.blade.php`
- Modify: `resources/js/components/ForceGraph.js`

**Step 1: Add badge counter to ForceGraph.js**

```javascript
// Add to ForceGraph component
pendingUpdates: 0,
lastUpdateTimestamp: null,

initEchoListener() {
    if (typeof window.Echo === 'undefined') {
        console.warn('Laravel Echo not available');
        return;
    }

    window.Echo.channel('graph-updates')
        .listen('.graph.updated', (event) => {
            this.pendingUpdates++;
            this.lastUpdateTimestamp = event.timestamp;

            // Dispatch notification
            this.$dispatch('graph-notification', {
                type: 'info',
                title: 'Graph Updated',
                message: event.message,
                duration: 8000,
                action: {
                    label: 'Refresh Now',
                    callback: () => this.refreshGraph()
                }
            });
        });
},

refreshGraph() {
    this.pendingUpdates = 0;
    this.$dispatch('refresh-graph');
},
```

**Step 2: Call initEchoListener in init()**

```javascript
init() {
    this.width = this.$refs.container.clientWidth;
    this.initSvg();
    this.initZoom();
    this.initSimulation();
    this.initEchoListener();

    // ... rest
},
```

**Step 3: Add badge to blade view**

```blade
{{-- Add to toolbar in force-graph.blade.php --}}
<div x-show="pendingUpdates > 0" class="bg-gray-800 rounded-lg p-1 flex items-center gap-2">
    <span class="relative flex h-3 w-3">
        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-75"></span>
        <span class="relative inline-flex rounded-full h-3 w-3 bg-blue-500"></span>
    </span>
    <span class="text-sm text-gray-300">
        <span x-text="pendingUpdates"></span> update<span x-show="pendingUpdates > 1">s</span>
    </span>
    <button
        @click="refreshGraph()"
        class="text-blue-400 hover:text-blue-300 text-sm font-medium"
    >
        Refresh
    </button>
</div>
```

**Step 4: Commit**

```bash
git add resources/views/livewire/graph/force-graph.blade.php resources/js/components/ForceGraph.js
git commit -m "feat: [Sprint 4 - C.4] Add badge counter for pending updates"
```

---

### Task 20: Add Refresh Button and Handler

**Files:**
- Modify: `app/Livewire/Graph/ForceGraphController.php`

**Step 1: Add refresh handler to controller**

```php
// Add to ForceGraphController.php
use Livewire\Attributes\On;

#[On('refresh-graph')]
public function refreshGraph(): void
{
    if ($this->rootNodeId) {
        $this->loadInitialGraph($this->rootNodeId);
    }
}
```

**Step 2: Commit**

```bash
git add app/Livewire/Graph/ForceGraphController.php
git commit -m "feat: [Sprint 4 - C.5] Add refresh button handler"
```

---

### Task 21: Write Tests for Notification Flow

**Files:**
- Create: `tests/Feature/GraphNotificationTest.php`

**Step 1: Create test file**

```php
<?php
// tests/Feature/GraphNotificationTest.php

namespace Tests\Feature;

use App\Events\Graph\GraphDataUpdated;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class GraphNotificationTest extends TestCase
{
    /** @test */
    public function it_broadcasts_graph_updated_event(): void
    {
        Event::fake();

        event(new GraphDataUpdated(
            updateType: 'sync_complete',
            nodesAffected: 10,
            relationshipsAffected: 25
        ));

        Event::assertDispatched(GraphDataUpdated::class, function ($event) {
            return $event->updateType === 'sync_complete'
                && $event->nodesAffected === 10
                && $event->relationshipsAffected === 25;
        });
    }

    /** @test */
    public function it_generates_appropriate_message_for_sync_complete(): void
    {
        $event = new GraphDataUpdated(
            updateType: 'sync_complete',
            nodesAffected: 10,
            relationshipsAffected: 25
        );

        $this->assertStringContainsString('10 nodes', $event->message);
        $this->assertStringContainsString('25 relationships', $event->message);
    }

    /** @test */
    public function it_broadcasts_on_graph_updates_channel(): void
    {
        $event = new GraphDataUpdated(
            updateType: 'new_decision',
            nodesAffected: 1,
            relationshipsAffected: 5
        );

        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertEquals('graph-updates', $channels[0]->name);
    }

    /** @test */
    public function it_uses_correct_broadcast_name(): void
    {
        $event = new GraphDataUpdated(
            updateType: 'new_decision',
            nodesAffected: 1,
            relationshipsAffected: 5
        );

        $this->assertEquals('graph.updated', $event->broadcastAs());
    }

    /** @test */
    public function it_includes_timestamp_in_broadcast_data(): void
    {
        $event = new GraphDataUpdated(
            updateType: 'sync_complete',
            nodesAffected: 10,
            relationshipsAffected: 25
        );

        $data = $event->broadcastWith();

        $this->assertArrayHasKey('timestamp', $data);
        $this->assertArrayHasKey('type', $data);
        $this->assertArrayHasKey('message', $data);
    }
}
```

**Step 2: Run tests**

Run: `./vendor/bin/phpunit tests/Feature/GraphNotificationTest.php`
Expected: All tests pass

**Step 3: Commit**

```bash
git add tests/Feature/GraphNotificationTest.php
git commit -m "test: [Sprint 4 - C.6] Add tests for notification flow"
```

---

## Final Tasks

### Task 22: Final Verification and Integration Test

**Files:**
- None (verification only)

**Step 1: Run all Sprint 4 tests**

```bash
# Run all new tests
./vendor/bin/phpunit tests/Feature/Livewire/GraphViewerPanelsTest.php
./vendor/bin/phpunit tests/Feature/GraphNotificationTest.php
php artisan dusk tests/Browser/ForceGraphTest.php
```

**Step 2: Build assets and verify**

```bash
npm run build
```

**Step 3: Manual verification checklist**

- [ ] ForceGraph renders with nodes and edges
- [ ] Zoom controls work
- [ ] Node filtering works
- [ ] Search highlights nodes
- [ ] Double-click expands nodes
- [ ] Sidebar panels show data
- [ ] Deep links work (/graph/explore/123?panel=evidence)
- [ ] Toast notifications appear

**Step 4: Commit final state**

```bash
git add -A
git commit -m "feat: [Sprint 4] Complete UI Polish implementation"
git push -u origin claude/graph-enhancement-data-integrity-XqqqL
```

---

## Summary

| Track | Tasks | Effort |
|-------|-------|--------|
| A: D3.js Force Graph | 1-9 | 19h |
| B: Analysis Panels | 10-15 | 11h |
| C: Notifications | 16-21 | 6h |
| Final | 22 | 2h |
| **Total** | **22 tasks** | **38h** |

### Definition of Done

- [ ] Force graph renders 500+ nodes at 60fps
- [ ] All analysis panels load in <200ms
- [ ] Notifications delivered in <1s
- [ ] Dusk browser tests pass
- [ ] Deep-links work for all panels
- [ ] All existing tests still pass
