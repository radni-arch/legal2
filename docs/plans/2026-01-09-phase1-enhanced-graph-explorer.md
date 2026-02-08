# Phase 1: Enhanced Graph Explorer - Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Transform the existing ForceGraph into an interactive legal research explorer with relationship filtering, node metadata panels, pinning, and visual encoding.

**Architecture:** Extend existing ForceGraphController (Livewire) and ForceGraph.js (Alpine/D3) with new state management for filters, pins, and metadata. Add GraphExplorerService for server-side query optimization.

**Tech Stack:** Laravel 11, Livewire 3, Alpine.js, D3.js, Neo4j, PHPUnit

---

## Summary

| Task | Component | Effort |
|------|-----------|--------|
| 1-5 | Relationship Type Filter UI | S |
| 6-10 | Server-side Filtered Queries | M |
| 11-15 | Node Metadata Panel | M |
| 16-20 | Pin to Workspace | M |
| 21-25 | Visual Encoding (Size/Color) | S |

**Total: 25 bite-sized tasks**

---

## Task 1: Add relationship filter state to ForceGraph.js

**Files:**
- Modify: `resources/js/components/ForceGraph.js:22-26`

**Step 1: Write the failing test**

```javascript
// tests/js/ForceGraph.test.js (if Jest configured) - or manual verification
// For now, we verify manually that the new state exists
```

**Step 2: Add filter state to Alpine component**

In `ForceGraph.js`, add to the `filters` object around line 22:

```javascript
filters: {
    nodeTypes: [],
    activeTypes: [],
    relationshipTypes: ['CITES', 'CONTRADICTS', 'SUPPORTS', 'REFERENCES', 'SUPERSEDES', 'HAS_KEYWORD'],
    activeRelationships: ['CITES', 'CONTRADICTS', 'SUPPORTS', 'REFERENCES', 'SUPERSEDES', 'HAS_KEYWORD'],
},
```

**Step 3: Verify the state is initialized**

Run: Open browser devtools, check Alpine component has new properties.

**Step 4: Commit**

```bash
git add resources/js/components/ForceGraph.js
git commit -m "feat(graph): Add relationship filter state to ForceGraph"
```

---

## Task 2: Add relationship toggle UI in Blade template

**Files:**
- Modify: `resources/views/livewire/graph/force-graph.blade.php`

**Step 1: Find the existing filter section**

Look for nodeTypes filter UI (around line 20-50).

**Step 2: Add relationship toggles after node type filters**

```blade
{{-- Relationship Type Filters --}}
<div class="mb-4">
    <h4 class="text-sm font-medium text-gray-400 mb-2">Relationship Types</h4>
    <div class="flex flex-wrap gap-2">
        <template x-for="relType in filters.relationshipTypes" :key="relType">
            <button
                @click="toggleRelationship(relType)"
                :class="filters.activeRelationships.includes(relType)
                    ? 'bg-blue-600 text-white'
                    : 'bg-gray-700 text-gray-400'"
                class="px-3 py-1 text-xs rounded-full transition-colors"
                x-text="relType.replace('_', ' ')"
            ></button>
        </template>
    </div>
</div>
```

**Step 3: Verify UI renders**

Run: `npm run dev` and open the graph page. See relationship toggles.

**Step 4: Commit**

```bash
git add resources/views/livewire/graph/force-graph.blade.php
git commit -m "feat(graph): Add relationship filter toggle UI"
```

---

## Task 3: Add toggleRelationship method to ForceGraph.js

**Files:**
- Modify: `resources/js/components/ForceGraph.js`

**Step 1: Add the toggle method**

Add after the existing filter methods:

```javascript
toggleRelationship(relType) {
    const index = this.filters.activeRelationships.indexOf(relType);
    if (index > -1) {
        this.filters.activeRelationships.splice(index, 1);
    } else {
        this.filters.activeRelationships.push(relType);
    }
    this.applyFilters();
},
```

**Step 2: Verify toggle works**

Click a relationship toggle, check `filters.activeRelationships` updates in devtools.

**Step 3: Commit**

```bash
git add resources/js/components/ForceGraph.js
git commit -m "feat(graph): Add toggleRelationship method"
```

---

## Task 4: Filter edges based on active relationships

**Files:**
- Modify: `resources/js/components/ForceGraph.js`

**Step 1: Update applyFilters method**

Find `applyFilters()` method and add relationship filtering:

```javascript
applyFilters() {
    // Filter nodes by type
    const filteredNodes = this.nodes.filter(node =>
        this.filters.activeTypes.length === 0 ||
        this.filters.activeTypes.includes(node.type)
    );

    // Filter edges by relationship type
    const nodeIds = new Set(filteredNodes.map(n => n.id));
    const filteredEdges = this.edges.filter(edge =>
        this.filters.activeRelationships.includes(edge.type) &&
        nodeIds.has(edge.source.id || edge.source) &&
        nodeIds.has(edge.target.id || edge.target)
    );

    this.updateVisualization(filteredNodes, filteredEdges);
},
```

**Step 2: Verify filtering works**

Toggle off a relationship type, verify those edges disappear from graph.

**Step 3: Commit**

```bash
git add resources/js/components/ForceGraph.js
git commit -m "feat(graph): Filter edges by relationship type"
```

---

## Task 5: Add "Select All / Clear All" for relationship filters

**Files:**
- Modify: `resources/views/livewire/graph/force-graph.blade.php`
- Modify: `resources/js/components/ForceGraph.js`

**Step 1: Add buttons to Blade**

After the relationship toggles:

```blade
<div class="flex gap-2 mt-2">
    <button @click="selectAllRelationships()" class="text-xs text-blue-400 hover:underline">
        Select All
    </button>
    <button @click="clearAllRelationships()" class="text-xs text-gray-500 hover:underline">
        Clear All
    </button>
</div>
```

**Step 2: Add methods to ForceGraph.js**

```javascript
selectAllRelationships() {
    this.filters.activeRelationships = [...this.filters.relationshipTypes];
    this.applyFilters();
},

clearAllRelationships() {
    this.filters.activeRelationships = [];
    this.applyFilters();
},
```

**Step 3: Verify buttons work**

**Step 4: Commit**

```bash
git add resources/views/livewire/graph/force-graph.blade.php resources/js/components/ForceGraph.js
git commit -m "feat(graph): Add select/clear all for relationship filters"
```

---

## Task 6: Create GraphExplorerService (server-side filtering)

**Files:**
- Create: `app/Services/Graph/GraphExplorerService.php`
- Test: `tests/Unit/Services/Graph/GraphExplorerServiceTest.php`

**Step 1: Write the failing test**

```php
<?php
// tests/Unit/Services/Graph/GraphExplorerServiceTest.php

namespace Tests\Unit\Services\Graph;

use App\Services\Graph\GraphExplorerService;
use App\Services\GraphDatabaseService;
use Mockery;
use Tests\TestCase;

class GraphExplorerServiceTest extends TestCase
{
    /** @test */
    public function it_filters_connections_by_relationship_type()
    {
        $graphMock = Mockery::mock(GraphDatabaseService::class);
        $graphMock->shouldReceive('run')
            ->once()
            ->with(Mockery::on(fn($cypher) => str_contains($cypher, 'type(r) IN')), Mockery::any())
            ->andReturn(collect([
                ['node' => ['id' => 'n1'], 'rel' => ['type' => 'CITES']],
            ]));

        $service = new GraphExplorerService($graphMock);

        $result = $service->getFilteredConnections('node-123', ['CITES', 'CONTRADICTS']);

        $this->assertCount(1, $result);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
```

**Step 2: Run test to verify it fails**

Run: `php artisan test --filter=GraphExplorerServiceTest`
Expected: FAIL - class not found

**Step 3: Create minimal service**

```php
<?php
// app/Services/Graph/GraphExplorerService.php

namespace App\Services\Graph;

use App\Services\GraphDatabaseService;

class GraphExplorerService
{
    public function __construct(
        protected GraphDatabaseService $graph
    ) {}

    /**
     * Get node connections filtered by relationship types
     */
    public function getFilteredConnections(string $nodeId, array $relationshipTypes, int $limit = 50): array
    {
        if (empty($relationshipTypes)) {
            return [];
        }

        $cypher = '
            MATCH (n {id: $nodeId})-[r]-(connected)
            WHERE type(r) IN $relationshipTypes
            RETURN
                {id: connected.id, type: labels(connected)[0], properties: properties(connected)} AS node,
                {type: type(r), properties: properties(r)} AS rel
            LIMIT $limit
        ';

        $result = $this->graph->run($cypher, [
            'nodeId' => $nodeId,
            'relationshipTypes' => $relationshipTypes,
            'limit' => $limit,
        ]);

        return $result->toArray();
    }
}
```

**Step 4: Run test to verify it passes**

Run: `php artisan test --filter=GraphExplorerServiceTest`
Expected: PASS

**Step 5: Commit**

```bash
git add app/Services/Graph/GraphExplorerService.php tests/Unit/Services/Graph/GraphExplorerServiceTest.php
git commit -m "feat(graph): Add GraphExplorerService with relationship filtering"
```

---

## Task 7: Add filtered expand endpoint to ForceGraphController

**Files:**
- Modify: `app/Livewire/Graph/ForceGraphController.php`

**Step 1: Write the failing test**

```php
// tests/Feature/Livewire/Graph/ForceGraphControllerTest.php
<?php

namespace Tests\Feature\Livewire\Graph;

use App\Livewire\Graph\ForceGraphController;
use App\Services\GraphDatabaseService;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class ForceGraphControllerTest extends TestCase
{
    /** @test */
    public function it_expands_node_with_relationship_filter()
    {
        $graphMock = Mockery::mock(GraphDatabaseService::class);
        $graphMock->shouldReceive('getNodeWithConnections')->andReturn(['nodes' => [], 'edges' => []]);
        $graphMock->shouldReceive('run')->andReturn(collect([
            ['node' => ['id' => 'n1', 'type' => 'Decision'], 'rel' => ['type' => 'CITES']],
        ]));
        $this->app->instance(GraphDatabaseService::class, $graphMock);

        Livewire::test(ForceGraphController::class)
            ->call('expandNodeFiltered', 'node-123', ['CITES'])
            ->assertDispatched('filtered-nodes-loaded');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
```

**Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ForceGraphControllerTest`
Expected: FAIL - method not found

**Step 3: Add the method**

In `ForceGraphController.php`, add:

```php
use App\Services\Graph\GraphExplorerService;

#[On('expand-node-filtered')]
public function expandNodeFiltered(string $nodeId, array $relationshipTypes): void
{
    $explorerService = app(GraphExplorerService::class);

    $existingIds = array_column($this->graphData['nodes'], 'id');
    $connections = $explorerService->getFilteredConnections($nodeId, $relationshipTypes);

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
```

**Step 4: Run test to verify it passes**

Run: `php artisan test --filter=ForceGraphControllerTest`
Expected: PASS

**Step 5: Commit**

```bash
git add app/Livewire/Graph/ForceGraphController.php tests/Feature/Livewire/Graph/ForceGraphControllerTest.php
git commit -m "feat(graph): Add expandNodeFiltered method with relationship filtering"
```

---

## Task 8: Update ForceGraph.js to use filtered expand

**Files:**
- Modify: `resources/js/components/ForceGraph.js`

**Step 1: Update the expandNode method**

Find `expandNode` and modify to pass active relationships:

```javascript
async expandNode(nodeId) {
    if (this.expandedNodeIds.has(nodeId)) return;

    this.isLoading = true;
    this.expandedNodeIds.add(nodeId);

    // Use filtered expand with active relationship types
    this.$wire.expandNodeFiltered(nodeId, this.filters.activeRelationships);
},
```

**Step 2: Verify expansion uses filter**

Expand a node with only CITES selected, verify only CITES edges appear.

**Step 3: Commit**

```bash
git add resources/js/components/ForceGraph.js
git commit -m "feat(graph): Use filtered expand in ForceGraph.js"
```

---

## Task 9: Add relationship legend to UI

**Files:**
- Modify: `resources/views/livewire/graph/force-graph.blade.php`

**Step 1: Add legend section**

```blade
{{-- Relationship Legend --}}
<div class="mt-4 p-3 bg-gray-800 rounded-lg">
    <h4 class="text-sm font-medium text-gray-400 mb-2">Legend</h4>
    <div class="grid grid-cols-2 gap-2 text-xs">
        <div class="flex items-center gap-2">
            <span class="w-4 h-1 bg-blue-400"></span>
            <span class="text-gray-300">CITES</span>
        </div>
        <div class="flex items-center gap-2">
            <span class="w-4 h-1 bg-red-400"></span>
            <span class="text-gray-300">CONTRADICTS</span>
        </div>
        <div class="flex items-center gap-2">
            <span class="w-4 h-1 bg-green-400"></span>
            <span class="text-gray-300">SUPPORTS</span>
        </div>
        <div class="flex items-center gap-2">
            <span class="w-4 h-1 bg-purple-400"></span>
            <span class="text-gray-300">REFERENCES</span>
        </div>
    </div>
</div>
```

**Step 2: Verify legend renders**

**Step 3: Commit**

```bash
git add resources/views/livewire/graph/force-graph.blade.php
git commit -m "feat(graph): Add relationship legend to UI"
```

---

## Task 10: Write integration test for relationship filtering

**Files:**
- Create: `tests/Feature/Graph/RelationshipFilteringTest.php`

**Step 1: Write integration test**

```php
<?php

namespace Tests\Feature\Graph;

use App\Services\Graph\GraphExplorerService;
use App\Services\GraphDatabaseService;
use Mockery;
use Tests\TestCase;

class RelationshipFilteringTest extends TestCase
{
    /** @test */
    public function it_returns_empty_when_no_relationships_selected()
    {
        $graphMock = Mockery::mock(GraphDatabaseService::class);
        $service = new GraphExplorerService($graphMock);

        $result = $service->getFilteredConnections('node-123', []);

        $this->assertEmpty($result);
    }

    /** @test */
    public function it_filters_by_multiple_relationship_types()
    {
        $graphMock = Mockery::mock(GraphDatabaseService::class);
        $graphMock->shouldReceive('run')
            ->once()
            ->with(Mockery::on(fn($cypher) => str_contains($cypher, 'type(r) IN')), Mockery::type('array'))
            ->andReturn(collect([
                ['node' => ['id' => 'n1'], 'rel' => ['type' => 'CITES']],
                ['node' => ['id' => 'n2'], 'rel' => ['type' => 'CONTRADICTS']],
            ]));

        $service = new GraphExplorerService($graphMock);

        $result = $service->getFilteredConnections('node-123', ['CITES', 'CONTRADICTS']);

        $this->assertCount(2, $result);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
```

**Step 2: Run test**

Run: `php artisan test --filter=RelationshipFilteringTest`
Expected: PASS

**Step 3: Commit**

```bash
git add tests/Feature/Graph/RelationshipFilteringTest.php
git commit -m "test(graph): Add integration tests for relationship filtering"
```

---

## Task 11: Add node metadata panel state

**Files:**
- Modify: `resources/js/components/ForceGraph.js`

**Step 1: Add panel state**

Add after line 32:

```javascript
// Metadata panel state
metadataPanel: {
    isOpen: false,
    node: null,
    loading: false,
},
```

**Step 2: Commit**

```bash
git add resources/js/components/ForceGraph.js
git commit -m "feat(graph): Add metadata panel state"
```

---

## Task 12: Create metadata panel Blade component

**Files:**
- Create: `resources/views/livewire/graph/partials/node-metadata-panel.blade.php`

**Step 1: Create the panel**

```blade
{{-- resources/views/livewire/graph/partials/node-metadata-panel.blade.php --}}
<div
    x-show="metadataPanel.isOpen"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0 translate-x-4"
    x-transition:enter-end="opacity-100 translate-x-0"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100 translate-x-0"
    x-transition:leave-end="opacity-0 translate-x-4"
    class="absolute right-0 top-0 w-80 h-full bg-gray-900 border-l border-gray-700 overflow-y-auto z-50"
>
    {{-- Header --}}
    <div class="sticky top-0 bg-gray-900 border-b border-gray-700 p-4 flex justify-between items-center">
        <h3 class="text-lg font-semibold text-white" x-text="metadataPanel.node?.type || 'Node Details'"></h3>
        <button @click="closeMetadataPanel()" class="text-gray-400 hover:text-white">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    </div>

    {{-- Loading state --}}
    <div x-show="metadataPanel.loading" class="p-4 flex justify-center">
        <svg class="animate-spin h-8 w-8 text-blue-500" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
        </svg>
    </div>

    {{-- Node properties --}}
    <div x-show="!metadataPanel.loading && metadataPanel.node" class="p-4 space-y-4">
        {{-- ID --}}
        <div>
            <label class="text-xs text-gray-500 uppercase">ID</label>
            <p class="text-sm text-gray-300 font-mono" x-text="metadataPanel.node?.id"></p>
        </div>

        {{-- Type --}}
        <div>
            <label class="text-xs text-gray-500 uppercase">Type</label>
            <p class="text-sm text-gray-300" x-text="metadataPanel.node?.type"></p>
        </div>

        {{-- Properties (dynamic) --}}
        <template x-if="metadataPanel.node?.properties">
            <div class="space-y-3">
                <template x-for="(value, key) in metadataPanel.node.properties" :key="key">
                    <div>
                        <label class="text-xs text-gray-500 uppercase" x-text="key.replace(/_/g, ' ')"></label>
                        <p class="text-sm text-gray-300 break-words" x-text="value"></p>
                    </div>
                </template>
            </div>
        </template>

        {{-- Actions --}}
        <div class="pt-4 border-t border-gray-700 flex gap-2">
            <button
                @click="pinNode(metadataPanel.node)"
                class="flex-1 px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded"
            >
                Pin to Workspace
            </button>
            <button
                @click="expandNode(metadataPanel.node.id)"
                class="flex-1 px-3 py-2 bg-gray-700 hover:bg-gray-600 text-white text-sm rounded"
            >
                Expand Connections
            </button>
        </div>
    </div>
</div>
```

**Step 2: Include in main template**

In `force-graph.blade.php`, add inside the main container:

```blade
@include('livewire.graph.partials.node-metadata-panel')
```

**Step 3: Commit**

```bash
git add resources/views/livewire/graph/partials/node-metadata-panel.blade.php resources/views/livewire/graph/force-graph.blade.php
git commit -m "feat(graph): Add node metadata panel component"
```

---

## Task 13: Add panel open/close methods

**Files:**
- Modify: `resources/js/components/ForceGraph.js`

**Step 1: Add methods**

```javascript
openMetadataPanel(node) {
    this.metadataPanel.node = node;
    this.metadataPanel.isOpen = true;
    this.metadataPanel.loading = false;
},

closeMetadataPanel() {
    this.metadataPanel.isOpen = false;
    this.metadataPanel.node = null;
},
```

**Step 2: Update node click handler**

Find the D3 node click handler and add:

```javascript
// In the node click handler
.on('click', (event, d) => {
    this.openMetadataPanel(d);
    this.$wire.selectNode(d);
})
```

**Step 3: Commit**

```bash
git add resources/js/components/ForceGraph.js
git commit -m "feat(graph): Add metadata panel open/close methods"
```

---

## Task 14: Fetch additional node details on panel open

**Files:**
- Modify: `app/Services/Graph/GraphExplorerService.php`
- Modify: `resources/js/components/ForceGraph.js`

**Step 1: Add method to service**

```php
/**
 * Get full node details including connection counts
 */
public function getNodeDetails(string $nodeId): ?array
{
    $cypher = '
        MATCH (n {id: $nodeId})
        OPTIONAL MATCH (n)-[r]-()
        WITH n, type(r) AS relType, count(*) AS cnt
        WITH n, collect({type: relType, count: cnt}) AS relationships
        RETURN {
            id: n.id,
            type: labels(n)[0],
            properties: properties(n),
            connectionCounts: relationships
        } AS details
    ';

    $result = $this->graph->run($cypher, ['nodeId' => $nodeId])->first();

    return $result ? $result['details'] : null;
}
```

**Step 2: Add Livewire method to fetch details**

In `ForceGraphController.php`:

```php
public function getNodeDetails(string $nodeId): void
{
    $explorerService = app(GraphExplorerService::class);
    $details = $explorerService->getNodeDetails($nodeId);

    $this->dispatch('node-details-loaded', details: $details);
}
```

**Step 3: Update JS to fetch on panel open**

```javascript
async openMetadataPanel(node) {
    this.metadataPanel.node = node;
    this.metadataPanel.isOpen = true;
    this.metadataPanel.loading = true;

    // Fetch full details
    this.$wire.getNodeDetails(node.id);
},

// Add listener for details loaded
init() {
    // ... existing init code ...

    this.$wire.on('node-details-loaded', (details) => {
        this.metadataPanel.node = details;
        this.metadataPanel.loading = false;
    });
}
```

**Step 4: Commit**

```bash
git add app/Services/Graph/GraphExplorerService.php app/Livewire/Graph/ForceGraphController.php resources/js/components/ForceGraph.js
git commit -m "feat(graph): Fetch full node details on panel open"
```

---

## Task 15: Add connection counts to metadata panel

**Files:**
- Modify: `resources/views/livewire/graph/partials/node-metadata-panel.blade.php`

**Step 1: Add connection counts section**

After properties, before actions:

```blade
{{-- Connection counts --}}
<template x-if="metadataPanel.node?.connectionCounts">
    <div class="pt-4 border-t border-gray-700">
        <label class="text-xs text-gray-500 uppercase mb-2 block">Connections</label>
        <div class="grid grid-cols-2 gap-2">
            <template x-for="conn in metadataPanel.node.connectionCounts" :key="conn.type">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-400" x-text="conn.type"></span>
                    <span class="text-white font-medium" x-text="conn.count"></span>
                </div>
            </template>
        </div>
    </div>
</template>
```

**Step 2: Commit**

```bash
git add resources/views/livewire/graph/partials/node-metadata-panel.blade.php
git commit -m "feat(graph): Show connection counts in metadata panel"
```

---

## Task 16: Add pinned nodes state

**Files:**
- Modify: `resources/js/components/ForceGraph.js`

**Step 1: Add state**

```javascript
// After expandedNodeIds
pinnedNodes: [],
```

**Step 2: Commit**

```bash
git add resources/js/components/ForceGraph.js
git commit -m "feat(graph): Add pinned nodes state"
```

---

## Task 17: Add pin/unpin methods

**Files:**
- Modify: `resources/js/components/ForceGraph.js`

**Step 1: Add methods**

```javascript
pinNode(node) {
    if (!this.pinnedNodes.find(n => n.id === node.id)) {
        this.pinnedNodes.push({
            id: node.id,
            type: node.type,
            label: node.properties?.case_number || node.properties?.title || node.id,
        });
    }
},

unpinNode(nodeId) {
    this.pinnedNodes = this.pinnedNodes.filter(n => n.id !== nodeId);
},

isPinned(nodeId) {
    return this.pinnedNodes.some(n => n.id === nodeId);
},
```

**Step 2: Commit**

```bash
git add resources/js/components/ForceGraph.js
git commit -m "feat(graph): Add pin/unpin node methods"
```

---

## Task 18: Create pinned nodes workspace UI

**Files:**
- Create: `resources/views/livewire/graph/partials/pinned-workspace.blade.php`

**Step 1: Create the component**

```blade
{{-- resources/views/livewire/graph/partials/pinned-workspace.blade.php --}}
<div x-show="pinnedNodes.length > 0" class="mb-4">
    <div class="bg-gray-800 rounded-lg p-4">
        <div class="flex justify-between items-center mb-3">
            <h4 class="text-sm font-medium text-gray-300">
                Pinned Nodes (<span x-text="pinnedNodes.length"></span>)
            </h4>
            <button
                @click="pinnedNodes = []"
                class="text-xs text-gray-500 hover:text-red-400"
            >
                Clear All
            </button>
        </div>

        <div class="flex flex-wrap gap-2">
            <template x-for="node in pinnedNodes" :key="node.id">
                <div class="flex items-center gap-1 px-2 py-1 bg-gray-700 rounded-full text-sm">
                    <span
                        class="w-2 h-2 rounded-full"
                        :style="'background-color: ' + (nodeColors[node.type] || '#6B7280')"
                    ></span>
                    <span
                        class="text-gray-300 cursor-pointer hover:text-white max-w-32 truncate"
                        @click="focusOnNode(node.id)"
                        x-text="node.label"
                    ></span>
                    <button
                        @click="unpinNode(node.id)"
                        class="text-gray-500 hover:text-red-400"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </template>
        </div>
    </div>
</div>
```

**Step 2: Include in main template**

**Step 3: Commit**

```bash
git add resources/views/livewire/graph/partials/pinned-workspace.blade.php resources/views/livewire/graph/force-graph.blade.php
git commit -m "feat(graph): Add pinned nodes workspace UI"
```

---

## Task 19: Add focusOnNode method

**Files:**
- Modify: `resources/js/components/ForceGraph.js`

**Step 1: Add method**

```javascript
focusOnNode(nodeId) {
    const node = this.nodes.find(n => n.id === nodeId);
    if (!node || !this.svg) return;

    // Get current transform
    const transform = d3.zoomTransform(this.svg.node());

    // Calculate new transform to center on node
    const scale = 1.5;
    const x = this.width / 2 - node.x * scale;
    const y = this.height / 2 - node.y * scale;

    // Animate to new position
    this.svg.transition()
        .duration(500)
        .call(this.zoom.transform, d3.zoomIdentity.translate(x, y).scale(scale));

    // Highlight the node
    this.highlightedNodeId = nodeId;
    setTimeout(() => this.highlightedNodeId = null, 2000);
},
```

**Step 2: Add visual highlight for focused node**

In the D3 node rendering, add pulse animation for highlighted node.

**Step 3: Commit**

```bash
git add resources/js/components/ForceGraph.js
git commit -m "feat(graph): Add focusOnNode with zoom animation"
```

---

## Task 20: Update pin button state in metadata panel

**Files:**
- Modify: `resources/views/livewire/graph/partials/node-metadata-panel.blade.php`

**Step 1: Update button to show pinned state**

```blade
<button
    @click="isPinned(metadataPanel.node?.id) ? unpinNode(metadataPanel.node?.id) : pinNode(metadataPanel.node)"
    :class="isPinned(metadataPanel.node?.id) ? 'bg-yellow-600 hover:bg-yellow-700' : 'bg-blue-600 hover:bg-blue-700'"
    class="flex-1 px-3 py-2 text-white text-sm rounded"
>
    <span x-text="isPinned(metadataPanel.node?.id) ? 'Unpin' : 'Pin to Workspace'"></span>
</button>
```

**Step 2: Commit**

```bash
git add resources/views/livewire/graph/partials/node-metadata-panel.blade.php
git commit -m "feat(graph): Show pin/unpin state in metadata panel"
```

---

## Task 21: Add node size based on citation count

**Files:**
- Modify: `resources/js/components/ForceGraph.js`

**Step 1: Update node radius calculation**

In the D3 node rendering:

```javascript
getNodeRadius(node) {
    // Base radius
    const baseRadius = 8;

    // Scale by citation count (if available)
    const citationCount = node.properties?.citation_count || 0;
    const scaleFactor = Math.min(Math.log(citationCount + 1) * 2, 10);

    return baseRadius + scaleFactor;
},
```

**Step 2: Apply to node circles**

```javascript
.attr('r', d => this.getNodeRadius(d))
```

**Step 3: Commit**

```bash
git add resources/js/components/ForceGraph.js
git commit -m "feat(graph): Size nodes by citation count"
```

---

## Task 22: Add edge color by relationship type

**Files:**
- Modify: `resources/js/components/ForceGraph.js`

**Step 1: Update edge rendering**

```javascript
getEdgeColor(edge) {
    const colors = {
        CITES: '#60A5FA',        // blue
        CONTRADICTS: '#F87171',  // red
        SUPPORTS: '#4ADE80',     // green
        REFERENCES: '#A78BFA',   // purple
        SUPERSEDES: '#FBBF24',   // yellow
        HAS_KEYWORD: '#94A3B8',  // gray
    };
    return colors[edge.type] || '#6B7280';
},
```

**Step 2: Apply to edges**

```javascript
.attr('stroke', d => this.getEdgeColor(d))
```

**Step 3: Commit**

```bash
git add resources/js/components/ForceGraph.js
git commit -m "feat(graph): Color edges by relationship type"
```

---

## Task 23: Add faded styling for superseded nodes

**Files:**
- Modify: `resources/js/components/ForceGraph.js`

**Step 1: Add opacity calculation**

```javascript
getNodeOpacity(node) {
    // Fade superseded laws
    if (node.properties?.is_superseded === true) {
        return 0.4;
    }
    // Fade old versions
    if (node.properties?.valid_until && new Date(node.properties.valid_until) < new Date()) {
        return 0.4;
    }
    return 1;
},
```

**Step 2: Apply to nodes**

```javascript
.attr('opacity', d => this.getNodeOpacity(d))
```

**Step 3: Commit**

```bash
git add resources/js/components/ForceGraph.js
git commit -m "feat(graph): Fade superseded/outdated nodes"
```

---

## Task 24: Add glow effect for pinned nodes

**Files:**
- Modify: `resources/js/components/ForceGraph.js`

**Step 1: Add SVG filter for glow**

In `initSvg()`:

```javascript
// Add glow filter
const defs = this.svg.append('defs');
const filter = defs.append('filter')
    .attr('id', 'glow')
    .attr('x', '-50%')
    .attr('y', '-50%')
    .attr('width', '200%')
    .attr('height', '200%');

filter.append('feGaussianBlur')
    .attr('in', 'SourceGraphic')
    .attr('stdDeviation', '3')
    .attr('result', 'blur');

filter.append('feMerge')
    .selectAll('feMergeNode')
    .data(['blur', 'SourceGraphic'])
    .enter()
    .append('feMergeNode')
    .attr('in', d => d);
```

**Step 2: Apply filter to pinned nodes**

```javascript
.attr('filter', d => this.isPinned(d.id) ? 'url(#glow)' : null)
```

**Step 3: Commit**

```bash
git add resources/js/components/ForceGraph.js
git commit -m "feat(graph): Add glow effect for pinned nodes"
```

---

## Task 25: Final integration test and cleanup

**Files:**
- Create: `tests/Feature/Graph/EnhancedGraphExplorerTest.php`

**Step 1: Write comprehensive test**

```php
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
            ->with('node-123', 1)
            ->andReturn([
                'nodes' => [['id' => 'node-123', 'type' => 'Decision']],
                'edges' => [],
            ]);
        $this->app->instance(GraphDatabaseService::class, $graphMock);

        Livewire::test(ForceGraphController::class, ['rootNodeId' => 'node-123'])
            ->assertSet('graphData.nodes.0.id', 'node-123');
    }

    /** @test */
    public function it_expands_node_with_filtered_relationships()
    {
        $graphMock = Mockery::mock(GraphDatabaseService::class);
        $graphMock->shouldReceive('getNodeWithConnections')->andReturn(['nodes' => [], 'edges' => []]);
        $graphMock->shouldReceive('run')->andReturn(collect([
            ['node' => ['id' => 'n1', 'type' => 'Law'], 'rel' => ['type' => 'CITES']],
        ]));
        $this->app->instance(GraphDatabaseService::class, $graphMock);

        Livewire::test(ForceGraphController::class)
            ->call('expandNodeFiltered', 'node-123', ['CITES'])
            ->assertDispatched('filtered-nodes-loaded');
    }

    /** @test */
    public function it_fetches_node_details()
    {
        $graphMock = Mockery::mock(GraphDatabaseService::class);
        $graphMock->shouldReceive('getNodeWithConnections')->andReturn(['nodes' => [], 'edges' => []]);
        $graphMock->shouldReceive('run')->andReturn(collect([
            ['details' => ['id' => 'node-123', 'type' => 'Decision', 'properties' => ['case_number' => 'K-1']]],
        ]));
        $this->app->instance(GraphDatabaseService::class, $graphMock);

        Livewire::test(ForceGraphController::class)
            ->call('getNodeDetails', 'node-123')
            ->assertDispatched('node-details-loaded');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
```

**Step 2: Run all tests**

Run: `php artisan test --filter=Graph`
Expected: All PASS

**Step 3: Build assets**

Run: `npm run build`

**Step 4: Final commit**

```bash
git add tests/Feature/Graph/EnhancedGraphExplorerTest.php
git commit -m "test(graph): Add comprehensive Enhanced Graph Explorer tests"
```

**Step 5: Push branch**

```bash
git push -u origin claude/graph-enhancement-data-integrity-XqqqL
```

---

## Summary

**Phase 1 Complete!** The Enhanced Graph Explorer now includes:

- ✅ Relationship type filtering (Tasks 1-5)
- ✅ Server-side filtered queries (Tasks 6-10)
- ✅ Node metadata panel (Tasks 11-15)
- ✅ Pin to workspace (Tasks 16-20)
- ✅ Visual encoding (Tasks 21-25)

**Next Phase:** Research Session Tracking (enables Gap Analysis & Contradiction Radar)
