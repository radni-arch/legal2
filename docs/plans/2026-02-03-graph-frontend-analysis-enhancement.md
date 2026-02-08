# Neo4j Graph DB Frontend — Analysis & Enhancement Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Fix broken node interactions, unify the duplicate graph rendering systems, and enhance the graph explorer into a polished, feature-rich legal knowledge graph tool.

**Architecture:** The frontend uses Livewire components (`GraphViewer`, `GraphDashboard`, `LlmBrainPanel`) with D3.js force-directed graph rendering. There are currently TWO competing graph implementations — an inline script in `graph-viewer.blade.php` and an Alpine.js `ForceGraph` component — which must be unified. The fix path is to replace the inline script with the proper `ForceGraph` Alpine component wired to Livewire.

**Tech Stack:** Laravel Livewire 3, Alpine.js, D3.js v7, Vite, Neo4j (Laudis driver), TailwindCSS

### Agent Skills to Load

```
/mnt/skills/user/tall-specialist.md     → Livewire 3 + Alpine.js patterns
/mnt/skills/user/d3-viz/SKILL.md        → D3.js v7 best practices
```

---

## Part A: Current State Audit

### A1. Architecture Map

```
GraphDashboard (Livewire) ─── graph-dashboard.blade.php
  ├── Explorer tab  ──→ GraphViewer (Livewire) ─── graph-viewer.blade.php
  │                       ├── Inline D3 script (ACTIVE, broken clicks)
  │                       └── dispatches 'graph-data-updated' browser event
  ├── LLM Brain tab ──→ LlmBrainPanel (Livewire) ─── llm-brain-panel.blade.php
  ├── Analytics tab  ──→ AnalyticsPanel (Livewire) ─── (exists, not provided)
  ├── Temporal tab   ──→ TemporalPanel (Livewire) ─── (exists, not provided)
  └── Admin tab      ──→ Placeholder (Coming Soon)

ForceGraph.js (Alpine component) ─── force-graph.blade.php
  ├── Full-featured: click, dblclick, drag, zoom, filter, search, pin, tooltips
  ├── Uses d3-v6-tip for rich tooltips
  ├── Has WebSocket (Echo) listener for live updates
  ├── Has alert ring system (Contradiction Radar)
  └── NOT CONNECTED to GraphViewer Livewire — used in separate blade template

citation-graph.js ─── standalone renderer for citation analysis modal
graph-tooltip.js  ─── tooltip factories (only used by ForceGraph.js)
```

### A2. Critical Bugs Found

| # | Severity | Issue | Root Cause | Location |
|---|----------|-------|------------|----------|
| 1 | 🔴 CRITICAL | **Node click does nothing** | Inline D3 script in `graph-viewer.blade.php` has NO click handler on nodes — only drag handlers | `graph-viewer.blade.php` inline `<script>`, ~line 680 |
| 2 | 🔴 CRITICAL | **Two competing graph systems** | `ForceGraph.js` (full-featured Alpine component) exists but is NOT used in `graph-viewer.blade.php`. Instead, a basic inline script renders the graph without click/dblclick/tooltip/filter support | `ForceGraph.js` vs `graph-viewer.blade.php` inline script |
| 3 | 🟡 MAJOR | **D3 loaded twice** | `bootstrap.js` imports `d3` and sets `window.d3`; blade template also loads `<script src="https://d3js.org/d3.v7.min.js">` via CDN — potential version conflicts and wasted bandwidth | `bootstrap.js:2` and `graph-viewer.blade.php` bottom |
| 4 | 🟡 MAJOR | **Sidebar panels reference undefined variables** | `arguments-panel.blade.php` uses `$arguments`, `evidence-panel.blade.php` uses `$evidence`, `timeline-panel.blade.php` uses `$timeline` — none of these are defined in `GraphViewer.php` | `resources/views/livewire/graph/partials/` |
| 5 | 🟡 MAJOR | **ForceGraph `$wire` calls reference non-existent methods** | `ForceGraph.js` calls `this.$wire.expandNodeFiltered()`, `this.$wire.pinNode()`, `this.$wire.unpinNode()`, `this.$wire.dispatch('update-filter-settings')` — none exist in `GraphViewer.php` | `ForceGraph.js:369,378,384,405` |
| 6 | 🟠 MODERATE | **Judge panel Cypher parameter syntax** | `getJudgeData()` query uses `$judgeId` inside single-quoted Cypher string which doesn't get interpolated as a parameter — should be a parameterized query | `GraphViewer.php:584` |
| 7 | 🟠 MODERATE | **No node expansion from graph** | After rendering, clicking a connected node should load its subgraph via Livewire, but no mechanism exists in the active rendering path | `graph-viewer.blade.php` inline script |
| 8 | 🟢 MINOR | **Citation graph uses global `d3`** | `renderCitationGraph` assumes `d3` is available globally — depends on CDN load order, fragile | `citation-graph.js:21` |
| 9 | 🟢 MINOR | **No enter/exit animations** | Nodes/edges appear/disappear without transition | Inline script |
| 10 | 🟢 MINOR | **`ForceGraph.js` dynamic import + global d3 conflict** | `ForceGraph.js` dynamically imports D3 (`await import('d3')`) but `bootstrap.js` already loads it globally — double loading | `ForceGraph.js:116`, `bootstrap.js:2` |

### A3. Feature Completeness Matrix

| Feature | ForceGraph.js (Alpine) | Inline Script (Active) | Status |
|---------|----------------------|----------------------|--------|
| Force-directed layout | ✅ | ✅ | Both have it |
| Zoom & pan | ✅ | ✅ | Both have it |
| Drag nodes | ✅ | ✅ | Both have it |
| **Click to select node** | ✅ | ❌ **MISSING** | **BROKEN** |
| **Double-click to expand** | ✅ | ❌ **MISSING** | **BROKEN** |
| Node tooltips (rich) | ✅ (d3-tip) | ❌ (basic `<title>`) | Degraded |
| Edge tooltips | ✅ (d3-tip) | ❌ MISSING | Missing |
| Node type filtering | ✅ (checkboxes) | ❌ MISSING | Missing |
| Relationship type filtering | ✅ (toggle buttons) | ❌ MISSING | Missing |
| Search within graph | ✅ | ❌ MISSING | Missing |
| Pin nodes to workspace | ✅ | ❌ MISSING | Missing |
| Performance mode (>200 nodes) | ✅ | ❌ MISSING | Missing |
| Alert rings (Contradiction Radar) | ✅ | ❌ MISSING | Missing |
| WebSocket live updates | ✅ (Echo) | ❌ MISSING | Missing |
| Session save/load | ✅ (blade partial) | ❌ MISSING | Missing |
| Fit-to-view | ✅ | ✅ (setTimeout hack) | Both have it |
| Node color by type | ✅ (rich palette) | ✅ (basic palette) | Both have it |
| Edge color by type | ✅ | ❌ (all gray) | Missing |
| Arrowhead markers | ✅ | ✅ | Both have it |
| Metadata side panel | ✅ (Alpine) | ❌ MISSING | Missing |
| Livewire integration | ❌ BROKEN ($wire refs) | Partial (event listener) | Both broken |

### A4. The Click Bug — Root Cause Analysis

**Why clicking a node does nothing:**

In `graph-viewer.blade.php`, the inline `<script>` renders nodes like this:

```javascript
// Line ~680: Nodes are created as <g> groups
const node = g.append('g')
    .selectAll('g')
    .data(graphData.nodes)
    .enter()
    .append('g')
    .attr('class', 'graph-node')
    .call(d3.drag()           // ← Only drag is attached
        .on('start', dragStarted)
        .on('drag', dragged)
        .on('end', dragEnded));

// Circles are appended to the group
node.append('circle')
    .attr('r', d => d.isCenter ? 20 : 15)
    // ... NO .on('click', ...) ANYWHERE
```

**What SHOULD happen** (from `ForceGraph.js`):

```javascript
// ForceGraph.js line ~297
.on('click', (event, d) => this.onNodeClick(d))        // ← Select node
.on('dblclick', (event, d) => this.onNodeDoubleClick(d)) // ← Expand connections
```

**The fix** is not just adding click handlers to the inline script — it's replacing the inline script entirely with the `ForceGraph.js` Alpine component, which already has all these features built.

---

## Part B: Enhancement Plan

### Overview of Tasks

| Task | Type | Priority | Effort | Description |
|------|------|----------|--------|-------------|
| 1 | 🔴 Bug Fix | P0 | Medium | Unify graph rendering — replace inline script with ForceGraph Alpine component |
| 2 | 🔴 Bug Fix | P0 | Small | Wire ForceGraph.js to Livewire (add missing PHP methods) |
| 3 | 🟡 Fix | P1 | Small | Remove duplicate D3 loading |
| 4 | 🟡 Fix | P1 | Small | Fix Judge panel Cypher parameter binding |
| 5 | 🟢 Enhancement | P2 | Medium | Add node context menu (right-click) |
| 6 | 🟢 Enhancement | P2 | Medium | Add graph minimap for navigation |
| 7 | 🟢 Enhancement | P2 | Large | Add argument/evidence/timeline data pipeline |
| 8 | 🟢 Enhancement | P3 | Medium | Add graph snapshot export (PNG/SVG) |
| 9 | 🟢 Enhancement | P3 | Medium | Add graph layout options (force, radial, hierarchical) |
| 10 | 🟢 Enhancement | P3 | Small | Add keyboard shortcuts for graph navigation |

---

### Task 1: Replace Inline Script with ForceGraph Alpine Component
**Skills**: `d3-viz`, `tall-specialist.md`  

> This is the PRIMARY fix for "clicking a node does nothing"

**Files:**
- Modify: `resources/views/livewire/graph-viewer.blade.php` — replace `#graph-container` div and inline `<script>`
- Modify: `resources/js/components/ForceGraph.js` — fix Livewire wire references
- Modify: `resources/js/app.js` — ensure ForceGraph is registered
- Modify: `resources/views/livewire/graph-viewer.blade.php` — remove CDN D3 script tag
- Test: Manual browser test + Dusk test

**Step 1: Understand the current graph container in `graph-viewer.blade.php`**

Currently the graph container is:
```html
<div id="graph-container" dusk="graph-canvas" class="w-full"
     style="height: 600px; background: #0b1220;" wire:ignore></div>
```

And at the bottom of the blade, there's an inline `<script>` block (~200 lines) that:
- Defines `window.renderNeo4jGraph(graphData)` — a plain D3 renderer with NO click handlers
- Calls it immediately if `$graphData` exists
- Listens for `graph-data-updated` browser event

**Step 2: Replace the graph container div**

Replace the `#graph-container` div in `graph-viewer.blade.php` with the Alpine ForceGraph component:

```html
<!-- Replace this: -->
<div id="graph-container" dusk="graph-canvas" class="w-full"
     style="height: 600px; background: #0b1220;" wire:ignore></div>

<!-- With this: -->
<div
    x-data="ForceGraph()"
    x-init="
        await init();
        @if($graphData)
            loadData(@js($graphData));
        @endif
    "
    @graph-data-updated.window="loadData($event.detail?.graphData ?? $event.detail)"
    @node-selected.window="
        const nodeData = $event.detail?.node;
        if (nodeData) {
            $wire.call('selectNodeFromGraph', nodeData.id, nodeData.type);
        }
    "
    wire:ignore
    dusk="graph-canvas"
    class="w-full relative"
    style="min-height: 600px;"
>
    <!-- ForceGraph renders its own SVG into x-ref="container" -->
    <div x-ref="container" class="w-full" style="height: 600px; background: #0b1220;"></div>

    <!-- Inline toolbar (zoom controls) -->
    <div class="absolute top-4 left-4 z-10 flex gap-2">
        <div class="bg-gray-800/90 rounded-lg p-1 flex gap-1">
            <button @click="zoomIn()" class="p-2 hover:bg-gray-700 rounded" title="Zoom In">
                <svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
            </button>
            <button @click="zoomOut()" class="p-2 hover:bg-gray-700 rounded" title="Zoom Out">
                <svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 12H6"/>
                </svg>
            </button>
            <button @click="fitToView()" class="p-2 hover:bg-gray-700 rounded" title="Fit to View">
                <svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/>
                </svg>
            </button>
        </div>
    </div>

    <!-- Search within graph -->
    <div class="absolute top-4 right-4 z-10">
        <div class="bg-gray-800/90 rounded-lg p-1 flex items-center">
            <input type="text" x-model.debounce.300ms="searchQuery" @input="searchNodes()"
                   placeholder="Search in graph..." class="bg-transparent border-none text-sm text-gray-300 placeholder-gray-500 focus:ring-0 w-48">
        </div>
        <!-- Search results dropdown -->
        <div x-show="searchResults.length > 0"
             class="mt-1 bg-gray-800 rounded-lg shadow-lg max-h-48 overflow-y-auto w-full">
            <template x-for="result in searchResults" :key="result.id">
                <button @click="focusOnNode(result)"
                        class="w-full px-3 py-2 text-left text-sm hover:bg-gray-700 flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full" :style="`background:${nodeColors[result.type]||'#6B7280'}`"></span>
                    <span class="text-gray-300 truncate" x-text="result.label || result.id"></span>
                </button>
            </template>
        </div>
    </div>

    <!-- Loading indicator -->
    <div x-show="isLoading" class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 z-20">
        <div class="bg-gray-800/90 rounded-lg px-4 py-3 flex items-center gap-3">
            <svg class="animate-spin h-5 w-5 text-blue-500" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
            <span class="text-sm text-gray-300">Expanding...</span>
        </div>
    </div>

    <!-- Performance mode indicator -->
    <div x-show="performanceMode" class="absolute top-4 left-1/2 -translate-x-1/2 z-10">
        <div class="bg-yellow-900/80 text-yellow-200 text-xs px-3 py-1 rounded-full">
            Performance mode (<span x-text="nodes.length"></span> nodes)
        </div>
    </div>

    <!-- Node type legend/filter -->
    <div class="absolute bottom-4 left-4 z-10 bg-gray-800/90 rounded-lg p-3 max-w-xs max-h-48 overflow-y-auto">
        <div class="flex justify-between items-center mb-2">
            <span class="text-xs text-gray-400 font-medium">Node Types</span>
            <div class="flex gap-1">
                <button @click="filters.activeTypes = [...filters.nodeTypes]; updateGraph()"
                        class="text-xs text-blue-400 hover:text-blue-300">All</button>
                <span class="text-gray-600">|</span>
                <button @click="filters.activeTypes = []; updateGraph()"
                        class="text-xs text-blue-400 hover:text-blue-300">None</button>
            </div>
        </div>
        <div class="grid grid-cols-2 gap-x-3 gap-y-1 text-xs">
            <template x-for="type in filters.nodeTypes" :key="type">
                <label class="flex items-center gap-1.5 cursor-pointer">
                    <input type="checkbox" :checked="filters.activeTypes.includes(type)"
                           @change="toggleNodeType(type)"
                           class="rounded border-gray-600 bg-gray-700 text-blue-500 focus:ring-blue-500 w-3 h-3">
                    <span class="w-2 h-2 rounded-full" :style="`background:${nodeColors[type]||'#6B7280'}`"></span>
                    <span class="text-gray-300 truncate" x-text="type"></span>
                </label>
            </template>
        </div>
    </div>

    <!-- Instructions -->
    <div class="absolute bottom-4 right-4 z-10 bg-gray-800/90 rounded-lg p-2 text-xs text-gray-500">
        <div><kbd class="bg-gray-700 px-1 rounded text-gray-400">Click</kbd> Select</div>
        <div><kbd class="bg-gray-700 px-1 rounded text-gray-400">Dbl-click</kbd> Expand</div>
        <div><kbd class="bg-gray-700 px-1 rounded text-gray-400">Scroll</kbd> Zoom</div>
    </div>

    <!-- Metadata side panel -->
    <div x-show="metadataPanel.isOpen"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-x-4"
         x-transition:enter-end="opacity-100 translate-x-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-x-0"
         x-transition:leave-end="opacity-0 translate-x-4"
         class="absolute right-0 top-0 w-72 h-full bg-gray-900/95 border-l border-gray-700 overflow-y-auto z-30">
        <div class="sticky top-0 bg-gray-900 border-b border-gray-700 p-3 flex justify-between items-center">
            <h4 class="text-sm font-semibold text-white" x-text="metadataPanel.node?.type || 'Node'"></h4>
            <button @click="closeMetadataPanel()" class="text-gray-400 hover:text-white">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div x-show="metadataPanel.node" class="p-3 space-y-3 text-sm">
            <div>
                <label class="text-xs text-gray-500 uppercase">Label</label>
                <p class="text-gray-200" x-text="metadataPanel.node?.label || metadataPanel.node?.id"></p>
            </div>
            <template x-if="metadataPanel.node?.properties">
                <div class="space-y-2">
                    <template x-for="(value, key) in metadataPanel.node.properties" :key="key">
                        <div x-show="key !== 'id' && value">
                            <label class="text-xs text-gray-500 uppercase" x-text="key.replace(/_/g, ' ')"></label>
                            <p class="text-gray-300 text-xs break-words" x-text="typeof value === 'string' && value.length > 100 ? value.substring(0, 100) + '...' : value"></p>
                        </div>
                    </template>
                </div>
            </template>
            <div class="pt-3 border-t border-gray-700 flex gap-2">
                <button @click="expandNode(metadataPanel.node)"
                        class="flex-1 px-2 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs rounded">
                    Expand
                </button>
                <button @click="$wire.call('loadNodeGraph', metadataPanel.node?.type, metadataPanel.node?.id)"
                        class="flex-1 px-2 py-1.5 bg-gray-700 hover:bg-gray-600 text-white text-xs rounded">
                    Load as Center
                </button>
            </div>
        </div>
    </div>
</div>
```

**Step 3: Remove the entire inline `<script>` block**

Delete from `graph-viewer.blade.php`:
- The `<script src="https://d3js.org/d3.v7.min.js"></script>` CDN tag
- The entire `<script>` block containing `window.renderNeo4jGraph`, `window.neo4jGraphZoomIn`, etc.
- The `@if($graphData) ... @endif` immediate render block

These are ALL replaced by the Alpine `ForceGraph` component above.

**Step 4: Update the zoom control buttons in the header**

Replace the old zoom buttons that called `window.neo4jGraphZoomIn()` etc:
```html
<!-- Old (remove): -->
<button type="button" class="btn-secondary px-3 py-1" onclick="window.neo4jGraphZoomIn()">
<!-- These are now inside the Alpine component's toolbar above -->
```

**Step 5: Verify `ForceGraph` is registered globally**

In `resources/js/app.js`, this line already exists:
```javascript
window.ForceGraph = ForceGraph;
```
This is correct. Alpine's `x-data="ForceGraph()"` will find it via `window.ForceGraph`.

**Step 6: Run verification**

```bash
# Build assets
npm run build

# Start dev server
php artisan serve

# Navigate to /graph-dashboard → Explorer tab
# Click any node in the graph → should open metadata panel
# Double-click any node → should expand connections
```

**Step 7: Commit**

```bash
git add resources/views/livewire/graph-viewer.blade.php
git commit -m "fix: replace broken inline D3 script with ForceGraph Alpine component

- Node clicks now work (select, dblclick expand)
- Rich tooltips on hover
- Node type filtering
- Search within graph
- Metadata side panel
- Remove duplicate CDN D3 load"
```

---

### Task 2: Wire ForceGraph to Livewire — Add Missing PHP Methods
**Skills**: `d3-viz`, `tall-specialist.md`  
**Files:**
- Modify: `app/Http/Livewire/GraphViewer.php` — add `selectNodeFromGraph`, `expandNodeFiltered`, `pinNode`, `unpinNode` methods
- Modify: `resources/js/components/ForceGraph.js` — update `$wire` calls to match new PHP methods, fix D3 import
- Test: `tests/Feature/Livewire/GraphViewerTest.php`

**Step 1: Write failing test for `selectNodeFromGraph`**

```php
// tests/Feature/Livewire/GraphViewerTest.php
/** @test */
public function it_can_select_node_from_graph()
{
    Livewire::test(GraphViewer::class)
        ->call('selectNodeFromGraph', 'test-id-123', 'CourtDecisionDocument')
        ->assertSet('selectedNodeId', 'test-id-123')
        ->assertSet('selectedNodeType', 'CourtDecisionDocument');
}
```

**Step 2: Run test to verify it fails**

```bash
php artisan test --filter=it_can_select_node_from_graph
```

Expected: FAIL — method `selectNodeFromGraph` doesn't exist.

**Step 3: Add `selectNodeFromGraph` to `GraphViewer.php`**

Add after the `selectRecentNode` method (~line 495):

```php
/**
 * Handle node selection from the frontend graph component
 *
 * Called by Alpine ForceGraph when user clicks a node in the D3 visualization.
 */
public function selectNodeFromGraph(string $nodeId, string $nodeType = 'CourtDecisionDocument'): void
{
    if (empty($nodeId)) {
        return;
    }

    // Validate node type
    if (!in_array($nodeType, self::ALLOWED_NODE_TYPES, true)) {
        $nodeType = 'CourtDecisionDocument';
    }

    $this->selectedNodeType = $nodeType;
    $this->selectedNodeId = $nodeId;

    // Load the node details without reloading the entire graph
    // (the graph already shows the node — we just need the properties for the detail panel)
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
}
```

**Step 4: Add `expandNodeFiltered` method**

```php
/**
 * Expand a node's connections filtered by relationship types
 *
 * Called by Alpine ForceGraph when user double-clicks a node.
 * Returns new nodes and edges via browser event for the frontend to merge.
 */
public function expandNodeFiltered(string $nodeId, array $relationshipTypes = []): void
{
    if (empty($nodeId)) {
        return;
    }

    try {
        // Build relationship type filter
        $relFilter = '';
        if (!empty($relationshipTypes)) {
            // Sanitize relationship types to prevent injection
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

        // Dispatch to Alpine frontend
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
```

**Step 5: Add `pinNode` and `unpinNode` stub methods**

```php
/**
 * Track pinned node (for session persistence)
 */
public function pinNode(array $node): void
{
    // Session tracking — store in user's session for later
    Log::info('Node pinned', ['node_id' => $node['id'] ?? 'unknown']);
}

/**
 * Untrack pinned node
 */
public function unpinNode(string $nodeId): void
{
    Log::info('Node unpinned', ['node_id' => $nodeId]);
}
```

**Step 6: Run tests**

```bash
php artisan test --filter=GraphViewer
```

**Step 7: Update ForceGraph.js — fix D3 import to use global**

In `ForceGraph.js`, the `loadD3()` method dynamically imports D3, but `bootstrap.js` already loads it globally. Update to prefer the global:

```javascript
async loadD3() {
    // Prefer globally loaded D3 (from bootstrap.js)
    if (window.d3) {
        d3 = window.d3;
        this.d3Loaded = true;
        return d3;
    }

    if (d3) {
        this.d3Loaded = true;
        return d3;
    }

    // Fallback: dynamic import
    this.isLoading = true;
    try {
        const d3Module = await import('d3');
        d3 = d3Module;
        this.d3Loaded = true;
        return d3;
    } catch (error) {
        console.error('Failed to load D3.js:', error);
        throw error;
    } finally {
        this.isLoading = false;
    }
},
```

**Step 8: Update ForceGraph.js `onNodeClick` to dispatch Livewire call**

The current `onNodeClick` dispatches a custom event. We need it to also call Livewire:

```javascript
onNodeClick(node) {
    this.selectedNode = node;
    this.openMetadataPanel(node);
    this.$dispatch('node-selected', { node });

    // Update Livewire component state
    if (this.$wire) {
        this.$wire.call('selectNodeFromGraph', node.id, node.type);
    }
},
```

**Step 9: Update ForceGraph.js `expandNode` to use correct wire call**

```javascript
async expandNode(node) {
    if (this.expandedNodeIds.has(node.id)) return;

    this.isLoading = true;
    this.expandedNodeIds.add(node.id);

    // Call Livewire to get new nodes
    if (this.$wire) {
        this.$wire.call('expandNodeFiltered', node.id, this.filters.activeRelationships);
    }
},
```

**Step 10: Commit**

```bash
git add app/Http/Livewire/GraphViewer.php resources/js/components/ForceGraph.js
git commit -m "feat: wire ForceGraph Alpine component to Livewire

- Add selectNodeFromGraph() for click-to-select
- Add expandNodeFiltered() for double-click expand
- Add pinNode/unpinNode stubs
- Fix D3 loading to prefer global instance
- ForceGraph now calls $wire methods correctly"
```

---

### Task 3: Remove Duplicate D3 Loading
**Skills**: `d3-viz`, `tall-specialist.md`  
**Files:**
- Modify: `resources/views/livewire/graph-viewer.blade.php` — remove CDN script tag (done in Task 1)
- Modify: `resources/js/components/citation-graph.js` — use `window.d3` or dynamic import instead of assuming global
- Verify: `resources/js/bootstrap.js` — confirm D3 is loaded once

**Step 1: Verify bootstrap.js loads D3**

`bootstrap.js` already has:
```javascript
import * as d3 from 'd3';
window.d3 = d3;
```
This is the single source of truth.

**Step 2: Update `citation-graph.js` to use window.d3 safely**

```javascript
export function renderCitationGraph(elementId, data) {
    const d3 = window.d3;
    if (!d3) {
        console.error('D3.js not loaded — cannot render citation graph');
        return;
    }
    // ... rest of function unchanged, but now uses local d3 reference
}
```

**Step 3: Verify CDN script tag is removed from blade (Task 1)**

The `<script src="https://d3js.org/d3.v7.min.js"></script>` line should already be removed.

**Step 4: Commit**

```bash
git add resources/js/components/citation-graph.js
git commit -m "fix: remove duplicate D3 loading, use single import from bootstrap.js"
```

---

### Task 4: Fix Judge Panel Cypher Parameter Binding
**Skills**: `d3-viz`, `tall-specialist.md`  
**Files:**
- Modify: `app/Http/Livewire/GraphViewer.php` — fix `getJudgeData()` query

**Step 1: Identify the bug**

In `getJudgeData()` (line ~584):
```php
$query = '
    MATCH (j:Judge {id: $judgeId})
    ...
';
```

The `$judgeId` inside single-quoted PHP string is a literal string `$judgeId`, NOT a Cypher parameter. The Laudis Neo4j driver uses `$paramName` syntax in Cypher queries, and the parameters are passed as the second argument to `run()`. The query IS actually correct for Cypher parameterization — but we should verify the parameter is being passed correctly:

```php
$result = $this->graphService->run($query, ['judgeId' => $judgeId]);
```

This is actually correct. The `$judgeId` in the Cypher query IS interpreted as a Cypher parameter by Neo4j, not a PHP variable (since it's in single quotes). The parameter binding `['judgeId' => $judgeId]` passes the PHP variable value. **No fix needed** — this was a false alarm in the initial audit. The Cypher driver correctly handles `$paramName` as parameters.

**Step 1 (revised): Skip this task — no bug exists**

---

### Task 5: Add Node Context Menu (Right-Click)
**Skills**: `d3-viz`, `tall-specialist.md`  
**Files:**
- Create: `resources/js/components/graph-context-menu.js`
- Modify: `resources/js/components/ForceGraph.js` — add right-click handler
- Modify: `resources/views/livewire/graph-viewer.blade.php` — add context menu HTML
- Modify: `app/Http/Livewire/GraphViewer.php` — add `getNodeNeighborCount` action

**Step 1: Create context menu component**

```javascript
// resources/js/components/graph-context-menu.js
export function createContextMenu() {
    return {
        visible: false,
        x: 0,
        y: 0,
        node: null,

        show(event, node) {
            event.preventDefault();
            this.node = node;
            this.x = event.clientX;
            this.y = event.clientY;
            this.visible = true;
        },

        hide() {
            this.visible = false;
            this.node = null;
        },

        actions: [
            { id: 'expand', label: 'Expand Connections', icon: '🔗' },
            { id: 'center', label: 'Load as Center Node', icon: '🎯' },
            { id: 'citation', label: 'Citation Analysis', icon: '📊' },
            { id: 'precedent', label: 'Show Precedent Chain', icon: '⛓️' },
            { id: 'pin', label: 'Pin to Workspace', icon: '📌' },
            { id: 'copy_id', label: 'Copy Node ID', icon: '📋' },
            { id: 'hide', label: 'Hide from Graph', icon: '👁️' },
        ],
    };
}
```

**Step 2: Add right-click handler to ForceGraph.js**

In the node rendering section of `updateGraph()`, add:

```javascript
.on('contextmenu', (event, d) => {
    event.preventDefault();
    this.$dispatch('graph-context-menu', {
        x: event.clientX,
        y: event.clientY,
        node: d,
    });
})
```

**Step 3: Add context menu HTML to the graph container in blade**

```html
<!-- Context Menu (inside the Alpine ForceGraph div) -->
<div x-data="{ ctxMenu: { visible: false, x: 0, y: 0, node: null } }"
     @graph-context-menu.window="
         ctxMenu.visible = true;
         ctxMenu.x = $event.detail.x;
         ctxMenu.y = $event.detail.y;
         ctxMenu.node = $event.detail.node;
     "
     @click.window="ctxMenu.visible = false"
     @keydown.escape.window="ctxMenu.visible = false">

    <div x-show="ctxMenu.visible"
         :style="`position:fixed; left:${ctxMenu.x}px; top:${ctxMenu.y}px; z-index:100;`"
         class="bg-gray-800 border border-gray-600 rounded-lg shadow-2xl py-1 min-w-48"
         x-transition>
        <button @click="expandNode(ctxMenu.node); ctxMenu.visible = false"
                class="w-full px-4 py-2 text-left text-sm text-gray-300 hover:bg-gray-700 flex items-center gap-2">
            🔗 Expand Connections
        </button>
        <button @click="$wire.call('loadNodeGraph', ctxMenu.node?.type, ctxMenu.node?.id); ctxMenu.visible = false"
                class="w-full px-4 py-2 text-left text-sm text-gray-300 hover:bg-gray-700 flex items-center gap-2">
            🎯 Load as Center
        </button>
        <template x-if="ctxMenu.node?.type === 'CourtDecisionDocument'">
            <button @click="$wire.call('openCitationAnalysis'); $wire.set('analysisDecisionId', ctxMenu.node?.id); ctxMenu.visible = false"
                    class="w-full px-4 py-2 text-left text-sm text-gray-300 hover:bg-gray-700 flex items-center gap-2">
                📊 Citation Analysis
            </button>
        </template>
        <template x-if="ctxMenu.node?.type === 'CourtDecisionDocument'">
            <button @click="$wire.call('getPrecedentChain', ctxMenu.node?.id); ctxMenu.visible = false"
                    class="w-full px-4 py-2 text-left text-sm text-gray-300 hover:bg-gray-700 flex items-center gap-2">
                ⛓️ Precedent Chain
            </button>
        </template>
        <div class="border-t border-gray-700 my-1"></div>
        <button @click="navigator.clipboard.writeText(ctxMenu.node?.id); ctxMenu.visible = false"
                class="w-full px-4 py-2 text-left text-sm text-gray-300 hover:bg-gray-700 flex items-center gap-2">
            📋 Copy Node ID
        </button>
    </div>
</div>
```

**Step 4: Commit**

```bash
git add resources/js/components/graph-context-menu.js resources/js/components/ForceGraph.js resources/views/livewire/graph-viewer.blade.php
git commit -m "feat: add right-click context menu for graph nodes

- Expand, Load as Center, Citation Analysis, Precedent Chain
- Copy Node ID
- Context-sensitive options (citation only for CourtDecisionDocument)"
```

---

### Task 6: Add Graph Minimap
**Skills**: `d3-viz`, `tall-specialist.md`  
**Files:**
- Modify: `resources/js/components/ForceGraph.js` — add minimap rendering in `tick()`

**Step 1: Add minimap container to the ForceGraph Alpine component**

In the graph container (blade), add:
```html
<!-- Minimap -->
<canvas x-ref="minimap" width="160" height="100"
        class="absolute bottom-4 left-4 z-10 border border-gray-700 rounded bg-gray-900/80 cursor-pointer"
        style="width: 160px; height: 100px;"
        @click="handleMinimapClick($event)">
</canvas>
```

**Step 2: Add minimap rendering to ForceGraph.js**

```javascript
// In the ForceGraph return object, add:

renderMinimap() {
    const canvas = this.$refs.minimap;
    if (!canvas) return;

    const ctx = canvas.getContext('2d');
    const w = canvas.width;
    const h = canvas.height;

    ctx.clearRect(0, 0, w, h);

    if (this.nodes.length === 0) return;

    // Calculate bounds
    let minX = Infinity, maxX = -Infinity, minY = Infinity, maxY = -Infinity;
    for (const n of this.nodes) {
        if (n.x < minX) minX = n.x;
        if (n.x > maxX) maxX = n.x;
        if (n.y < minY) minY = n.y;
        if (n.y > maxY) maxY = n.y;
    }

    const pad = 20;
    const scaleX = (w - pad * 2) / (maxX - minX || 1);
    const scaleY = (h - pad * 2) / (maxY - minY || 1);
    const scale = Math.min(scaleX, scaleY);

    const offsetX = (w - (maxX - minX) * scale) / 2;
    const offsetY = (h - (maxY - minY) * scale) / 2;

    // Draw edges
    ctx.strokeStyle = '#374151';
    ctx.lineWidth = 0.5;
    for (const e of this.edges) {
        const sx = (e.source.x - minX) * scale + offsetX;
        const sy = (e.source.y - minY) * scale + offsetY;
        const tx = (e.target.x - minX) * scale + offsetX;
        const ty = (e.target.y - minY) * scale + offsetY;
        ctx.beginPath();
        ctx.moveTo(sx, sy);
        ctx.lineTo(tx, ty);
        ctx.stroke();
    }

    // Draw nodes
    for (const n of this.nodes) {
        const x = (n.x - minX) * scale + offsetX;
        const y = (n.y - minY) * scale + offsetY;
        ctx.fillStyle = this.nodeColors[n.type] || '#6B7280';
        ctx.beginPath();
        ctx.arc(x, y, 2, 0, Math.PI * 2);
        ctx.fill();
    }

    // Draw viewport rectangle (approximate)
    // Would need current zoom transform to be accurate
},

handleMinimapClick(event) {
    // Click on minimap to navigate to that area
    // Implementation: translate click coords to graph coords and center view
},
```

**Step 3: Call `renderMinimap()` in `tick()`**

```javascript
tick() {
    // ... existing tick code ...

    // Render minimap every N ticks for performance
    if (!this._minimapCounter) this._minimapCounter = 0;
    this._minimapCounter++;
    if (this._minimapCounter % 5 === 0) {
        this.renderMinimap();
    }
},
```

**Step 4: Commit**

```bash
git add resources/js/components/ForceGraph.js resources/views/livewire/graph-viewer.blade.php
git commit -m "feat: add canvas minimap for graph navigation overview"
```

---

### Task 7: Add Argument/Evidence/Timeline Data Pipeline

> This connects the sidebar panels that currently show "Select a decision node to view"
**Skills**: `d3-viz`, `tall-specialist.md`  
**Files:**
- Modify: `app/Http/Livewire/GraphViewer.php` — add properties and data loading methods
- Modify: `resources/views/livewire/graph-viewer.blade.php` — add sidebar include

**Step 1: Add properties to GraphViewer.php**

```php
// Public Properties - Sidebar Panel Data
public array $arguments = [];
public array $evidence = [];
public array $timeline = [];
public string $activePanel = 'arguments';
```

**Step 2: Add data loading method triggered when a CourtDecisionDocument is selected**

```php
/**
 * Load sidebar data (arguments, evidence, timeline) for a court decision
 */
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
```

**Step 3: Call it from `selectNodeFromGraph`**

```php
public function selectNodeFromGraph(string $nodeId, string $nodeType = 'CourtDecisionDocument'): void
{
    // ... existing code ...

    // Load sidebar data if it's a court decision
    if ($nodeType === 'CourtDecisionDocument') {
        $this->loadDecisionSidebarData($nodeId);
    }
}
```

**Step 4: Commit**

```bash
git add app/Http/Livewire/GraphViewer.php
git commit -m "feat: load arguments, evidence, timeline data for selected decisions"
```

---

### Task 8: Add Graph Snapshot Export (PNG/SVG)
**Skills**: `d3-viz`, `tall-specialist.md`  
**Files:**
- Modify: `resources/js/components/ForceGraph.js` — add `exportPNG()` and `exportSVG()` methods
- Modify: `resources/views/livewire/graph-viewer.blade.php` — add export buttons

**Step 1: Add export methods to ForceGraph.js**

```javascript
exportSVG() {
    if (!this.svg) return;
    const svgElement = this.svg.node();
    const serializer = new XMLSerializer();
    const svgString = serializer.serializeToString(svgElement);
    const blob = new Blob([svgString], { type: 'image/svg+xml;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `graph-${Date.now()}.svg`;
    a.click();
    URL.revokeObjectURL(url);
},

exportPNG() {
    if (!this.svg) return;
    const svgElement = this.svg.node();
    const serializer = new XMLSerializer();
    const svgString = serializer.serializeToString(svgElement);
    const canvas = document.createElement('canvas');
    canvas.width = this.width * 2;  // 2x for retina
    canvas.height = this.height * 2;
    const ctx = canvas.getContext('2d');
    ctx.scale(2, 2);
    const img = new Image();
    const svgBlob = new Blob([svgString], { type: 'image/svg+xml;charset=utf-8' });
    const url = URL.createObjectURL(svgBlob);
    img.onload = () => {
        ctx.fillStyle = '#0b1220';
        ctx.fillRect(0, 0, this.width, this.height);
        ctx.drawImage(img, 0, 0, this.width, this.height);
        URL.revokeObjectURL(url);
        canvas.toBlob((blob) => {
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = `graph-${Date.now()}.png`;
            a.click();
        }, 'image/png');
    };
    img.src = url;
},
```

**Step 2: Add export buttons to toolbar**

```html
<!-- In the toolbar area of the graph -->
<button @click="exportPNG()" class="p-2 hover:bg-gray-700 rounded" title="Export PNG">
    <svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
    </svg>
</button>
<button @click="exportSVG()" class="p-2 hover:bg-gray-700 rounded" title="Export SVG">
    <svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
    </svg>
</button>
```

**Step 3: Commit**

```bash
git add resources/js/components/ForceGraph.js resources/views/livewire/graph-viewer.blade.php
git commit -m "feat: add graph export as PNG and SVG"
```

---

### Task 9: Add Graph Layout Options
**Skills**: `d3-viz`, `tall-specialist.md`  
**Files:**
- Modify: `resources/js/components/ForceGraph.js` — add layout switcher

**Step 1: Add layout property and switcher**

```javascript
// Add to ForceGraph state:
layout: 'force', // 'force', 'radial', 'hierarchical'

// Add layout methods:
setLayout(layout) {
    this.layout = layout;

    switch (layout) {
        case 'radial':
            this.simulation
                .force('center', null)
                .force('r', d3.forceRadial(200, this.width / 2, this.height / 2)
                    .strength(d => d.isCenter ? 0 : 0.5))
                .force('charge', d3.forceManyBody().strength(-100));
            break;

        case 'hierarchical':
            // Assign layers based on distance from center
            const centerNode = this.nodes.find(n => n.isCenter);
            if (centerNode) {
                const layerHeight = this.height / 6;
                this.simulation
                    .force('center', null)
                    .force('y', d3.forceY(d => {
                        // Determine depth from center via edges
                        return d.isCenter ? this.height / 2 : this.height / 2 + layerHeight;
                    }).strength(0.5))
                    .force('x', d3.forceX(this.width / 2).strength(0.1));
            }
            break;

        case 'force':
        default:
            this.simulation
                .force('r', null)
                .force('y', null)
                .force('x', null)
                .force('center', d3.forceCenter(this.width / 2, this.height / 2))
                .force('charge', d3.forceManyBody().strength(-300));
            break;
    }

    this.simulation.alpha(1).restart();
},
```

**Step 2: Add layout selector buttons to the toolbar**

```html
<div class="bg-gray-800/90 rounded-lg p-1 flex gap-1">
    <button @click="setLayout('force')" :class="layout === 'force' ? 'bg-blue-600' : 'hover:bg-gray-700'"
            class="px-2 py-1 text-xs text-gray-300 rounded" title="Force Layout">Force</button>
    <button @click="setLayout('radial')" :class="layout === 'radial' ? 'bg-blue-600' : 'hover:bg-gray-700'"
            class="px-2 py-1 text-xs text-gray-300 rounded" title="Radial Layout">Radial</button>
    <button @click="setLayout('hierarchical')" :class="layout === 'hierarchical' ? 'bg-blue-600' : 'hover:bg-gray-700'"
            class="px-2 py-1 text-xs text-gray-300 rounded" title="Hierarchical Layout">Tree</button>
</div>
```

**Step 3: Commit**

```bash
git add resources/js/components/ForceGraph.js resources/views/livewire/graph-viewer.blade.php
git commit -m "feat: add graph layout options (force, radial, hierarchical)"
```

---

### Task 10: Add Keyboard Shortcuts
**Skills**: `d3-viz`, `tall-specialist.md`  
**Files:**
- Modify: `resources/js/components/ForceGraph.js` — add keyboard event handlers

**Step 1: Add keyboard handler in `init()`**

```javascript
// In init(), add:
this.initKeyboardShortcuts();

// New method:
initKeyboardShortcuts() {
    document.addEventListener('keydown', (e) => {
        // Only handle if graph container is visible/focused
        if (!this.$refs.container || !this.$refs.container.closest(':hover')) return;

        switch (e.key) {
            case '+':
            case '=':
                e.preventDefault();
                this.zoomIn();
                break;
            case '-':
                e.preventDefault();
                this.zoomOut();
                break;
            case '0':
                if (e.ctrlKey || e.metaKey) {
                    e.preventDefault();
                    this.fitToView();
                }
                break;
            case 'f':
                if (e.ctrlKey || e.metaKey) {
                    e.preventDefault();
                    // Focus search input
                    const searchInput = this.$el.querySelector('input[type="text"]');
                    if (searchInput) searchInput.focus();
                }
                break;
            case 'Escape':
                this.closeMetadataPanel();
                this.highlightedNodeId = null;
                this.searchQuery = '';
                this.searchResults = [];
                this.updateGraph();
                break;
            case 'Delete':
            case 'Backspace':
                if (this.selectedNode && !document.activeElement.matches('input, textarea')) {
                    // Hide selected node from graph
                    this.nodes = this.nodes.filter(n => n.id !== this.selectedNode.id);
                    this.selectedNode = null;
                    this.closeMetadataPanel();
                    this.updateGraph();
                }
                break;
        }
    });
},
```

**Step 2: Commit**

```bash
git add resources/js/components/ForceGraph.js
git commit -m "feat: add keyboard shortcuts for graph navigation

- +/- for zoom
- Ctrl+0 for fit-to-view
- Ctrl+F for search
- Escape to close panels
- Delete to hide selected node"
```

---

## Part C: Future Enhancement Ideas (Post-MVP)

These are not planned tasks but ideas for later sprints:

| # | Feature | Description | Complexity |
|---|---------|-------------|------------|
| 1 | **Graph diff view** | Compare two graph snapshots side-by-side (e.g., before/after a law amendment) | High |
| 2 | **AI-powered graph query** | Connect LLM Brain results to the graph view — click a query result to visualize it | Medium |
| 3 | **Collaborative annotations** | Allow users to annotate nodes/edges with notes, shared across team | High |
| 4 | **Graph bookmarks** | Save specific graph views (selected node + settings) as named bookmarks | Low |
| 5 | **Time slider** | Filter graph nodes by date range — animate how citations evolved over time | High |
| 6 | **Community detection visualization** | Color nodes by detected Louvain communities, show cluster boundaries | Medium |
| 7 | **Path finder** | Find shortest/all paths between two selected nodes | Medium |
| 8 | **Batch node expansion** | Select multiple nodes and expand all at once | Low |
| 9 | **Graph statistics overlay** | Show degree distribution, clustering coefficient as overlay charts | Medium |
| 10 | **WebSocket real-time updates** | When new decisions are ingested, show live graph updates | Medium |
| 11 | **Force graph 3D** | Optional 3D force graph using three.js for immersive exploration | High |
| 12 | **Edge bundling** | Bundle parallel edges between same node pairs for cleaner visualization | Medium |

---

## Execution Order

```
Task 1 (P0) → Task 2 (P0) → Task 3 (P1) → BUILD & TEST
    ↓
Task 5 (P2) → Task 6 (P2) → Task 7 (P2) → BUILD & TEST
    ↓
Task 8 (P3) → Task 9 (P3) → Task 10 (P3) → BUILD & TEST
```

Tasks 1-3 are the critical path — they fix the broken click behavior and unify the codebase. Everything else is enhancement on a working foundation.
