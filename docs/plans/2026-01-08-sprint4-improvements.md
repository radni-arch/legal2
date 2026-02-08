# Sprint 4 UI Polish Improvements Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Complete the 4 remaining improvements from Sprint 4 UI Polish: implement actual Neo4j queries, integrate toast notifications, optimize bundle size, and enhance edge tooltips.

**Architecture:** These are independent improvements that can be implemented in parallel. The Neo4j queries use existing graph sync services as reference. Bundle optimization uses Vite dynamic imports. Toast integration is a simple Blade include.

**Tech Stack:** PHP/Laravel, Neo4j Cypher, JavaScript/D3.js, Vite, Alpine.js, Tailwind CSS

---

## Task 1: Implement getArgumentsForDecision Neo4j Query

**Files:**
- Modify: `app/Services/GraphDatabaseService.php:2099-2104`
- Test: `tests/Unit/Services/GraphDatabaseServiceArgumentsTest.php` (create)

**Step 1: Write the failing test**

Create new test file `tests/Unit/Services/GraphDatabaseServiceArgumentsTest.php`:

```php
<?php

namespace Tests\Unit\Services;

use App\Services\GraphDatabaseService;
use Mockery;
use Tests\TestCase;

class GraphDatabaseServiceArgumentsTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_returns_arguments_for_decision_with_correct_structure(): void
    {
        $service = $this->createPartialMock(GraphDatabaseService::class, ['run']);

        $mockResults = collect([
            [
                'a' => [
                    'id' => 'arg-1',
                    'argument_type' => 'plaintiff',
                    'summary' => 'Plaintiff claims damages',
                    'full_text' => 'Full argument text...',
                    'accepted' => true,
                ],
                'r' => ['sequence' => 1],
            ],
            [
                'a' => [
                    'id' => 'arg-2',
                    'argument_type' => 'defendant',
                    'summary' => 'Defendant denies liability',
                    'full_text' => 'Defense text...',
                    'accepted' => false,
                ],
                'r' => ['sequence' => 2],
            ],
        ]);

        $service->expects($this->once())
            ->method('run')
            ->with($this->stringContains('CONTAINS_ARGUMENT'))
            ->willReturn($mockResults);

        $result = $service->getArgumentsForDecision('decision-123');

        $this->assertCount(2, $result);
        $this->assertEquals('plaintiff', $result[0]['party_type']);
        $this->assertEquals('Plaintiff claims damages', $result[0]['content']);
        $this->assertTrue($result[0]['accepted']);
    }

    /** @test */
    public function it_returns_empty_array_when_no_arguments(): void
    {
        $service = $this->createPartialMock(GraphDatabaseService::class, ['run']);

        $service->expects($this->once())
            ->method('run')
            ->willReturn(collect([]));

        $result = $service->getArgumentsForDecision('decision-999');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /** @test */
    public function it_handles_neo4j_unavailable_gracefully(): void
    {
        $service = $this->createPartialMock(GraphDatabaseService::class, ['run', 'isAvailable']);

        $service->expects($this->once())
            ->method('isAvailable')
            ->willReturn(false);

        $result = $service->getArgumentsForDecision('decision-123');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Unit/Services/GraphDatabaseServiceArgumentsTest.php -v`
Expected: FAIL - method returns empty array instead of querying Neo4j

**Step 3: Implement the query**

Modify `app/Services/GraphDatabaseService.php:2099-2104`:

```php
public function getArgumentsForDecision(string $decisionId): array
{
    if (!$this->isAvailable()) {
        return [];
    }

    try {
        $cypher = '
            MATCH (d:CourtDecisionDocument {id: $decisionId})-[r:CONTAINS_ARGUMENT]->(a:LegalArgument)
            RETURN a, r
            ORDER BY r.sequence ASC
        ';

        $results = $this->run($cypher, ['decisionId' => $decisionId]);

        return $results->map(function ($record) {
            $argument = $record['a'];
            $rel = $record['r'];

            return [
                'id' => $argument['id'] ?? '',
                'party_type' => $argument['argument_type'] ?? 'unknown',
                'content' => $argument['summary'] ?? '',
                'full_text' => $argument['full_text'] ?? '',
                'accepted' => $argument['accepted'] ?? null,
                'sequence' => $rel['sequence'] ?? 0,
            ];
        })->toArray();
    } catch (\Throwable $e) {
        Log::warning('Failed to fetch arguments for decision', [
            'decision_id' => $decisionId,
            'error' => $e->getMessage(),
        ]);
        return [];
    }
}
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Unit/Services/GraphDatabaseServiceArgumentsTest.php -v`
Expected: PASS

**Step 5: Commit**

```bash
git add tests/Unit/Services/GraphDatabaseServiceArgumentsTest.php \
    app/Services/GraphDatabaseService.php
git commit -m "feat: Implement getArgumentsForDecision Neo4j query

- Add Cypher query to fetch LegalArgument nodes via CONTAINS_ARGUMENT
- Map results to panel-compatible format with party_type, content
- Handle Neo4j unavailability gracefully
- Add unit tests for argument fetching"
```

---

## Task 2: Implement getEvidenceForDecision Neo4j Query

**Files:**
- Modify: `app/Services/GraphDatabaseService.php:2115-2120`
- Test: `tests/Unit/Services/GraphDatabaseServiceEvidenceTest.php` (create)

**Step 1: Write the failing test**

Create `tests/Unit/Services/GraphDatabaseServiceEvidenceTest.php`:

```php
<?php

namespace Tests\Unit\Services;

use App\Services\GraphDatabaseService;
use Mockery;
use Tests\TestCase;

class GraphDatabaseServiceEvidenceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_returns_evidence_for_decision_with_correct_structure(): void
    {
        $service = $this->createPartialMock(GraphDatabaseService::class, ['run']);

        $mockResults = collect([
            [
                'e' => [
                    'id' => 'ev-1',
                    'evidence_type' => 'documentary',
                    'description' => 'Contract document',
                    'admitted' => true,
                    'weight' => 'high',
                ],
                'r' => ['ruling' => 'admitted'],
            ],
            [
                'e' => [
                    'id' => 'ev-2',
                    'evidence_type' => 'testimonial',
                    'description' => 'Witness statement',
                    'admitted' => true,
                    'weight' => 'medium',
                ],
                'r' => ['ruling' => 'admitted with limitation'],
            ],
        ]);

        $service->expects($this->once())
            ->method('run')
            ->with($this->stringContains('CONSIDERS_EVIDENCE'))
            ->willReturn($mockResults);

        $result = $service->getEvidenceForDecision('decision-123');

        $this->assertCount(2, $result);
        $this->assertEquals('documentary', $result[0]['evidence_type']);
        $this->assertEquals('Contract document', $result[0]['description']);
        $this->assertTrue($result[0]['admitted']);
    }

    /** @test */
    public function it_returns_empty_array_when_no_evidence(): void
    {
        $service = $this->createPartialMock(GraphDatabaseService::class, ['run']);

        $service->expects($this->once())
            ->method('run')
            ->willReturn(collect([]));

        $result = $service->getEvidenceForDecision('decision-999');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /** @test */
    public function it_handles_neo4j_unavailable_gracefully(): void
    {
        $service = $this->createPartialMock(GraphDatabaseService::class, ['run', 'isAvailable']);

        $service->expects($this->once())
            ->method('isAvailable')
            ->willReturn(false);

        $result = $service->getEvidenceForDecision('decision-123');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Unit/Services/GraphDatabaseServiceEvidenceTest.php -v`
Expected: FAIL

**Step 3: Implement the query**

Modify `app/Services/GraphDatabaseService.php:2115-2120`:

```php
public function getEvidenceForDecision(string $decisionId): array
{
    if (!$this->isAvailable()) {
        return [];
    }

    try {
        $cypher = '
            MATCH (d:CourtDecisionDocument {id: $decisionId})-[r:CONSIDERS_EVIDENCE]->(e:Evidence)
            RETURN e, r
            ORDER BY e.evidence_type ASC
        ';

        $results = $this->run($cypher, ['decisionId' => $decisionId]);

        return $results->map(function ($record) {
            $evidence = $record['e'];
            $rel = $record['r'];

            return [
                'id' => $evidence['id'] ?? '',
                'evidence_type' => $evidence['evidence_type'] ?? 'unknown',
                'description' => $evidence['description'] ?? '',
                'admitted' => $evidence['admitted'] ?? null,
                'weight' => $evidence['weight'] ?? 'unknown',
                'ruling' => $rel['ruling'] ?? '',
            ];
        })->toArray();
    } catch (\Throwable $e) {
        Log::warning('Failed to fetch evidence for decision', [
            'decision_id' => $decisionId,
            'error' => $e->getMessage(),
        ]);
        return [];
    }
}
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Unit/Services/GraphDatabaseServiceEvidenceTest.php -v`
Expected: PASS

**Step 5: Commit**

```bash
git add tests/Unit/Services/GraphDatabaseServiceEvidenceTest.php \
    app/Services/GraphDatabaseService.php
git commit -m "feat: Implement getEvidenceForDecision Neo4j query

- Add Cypher query to fetch Evidence nodes via CONSIDERS_EVIDENCE
- Map results with evidence_type, description, admitted, weight
- Handle Neo4j unavailability gracefully
- Add unit tests for evidence fetching"
```

---

## Task 3: Implement getDateEventsForDecision Neo4j Query

**Files:**
- Modify: `app/Services/GraphDatabaseService.php:2131-2136`
- Test: `tests/Unit/Services/GraphDatabaseServiceDateEventsTest.php` (create)

**Step 1: Write the failing test**

Create `tests/Unit/Services/GraphDatabaseServiceDateEventsTest.php`:

```php
<?php

namespace Tests\Unit\Services;

use App\Services\GraphDatabaseService;
use Mockery;
use Tests\TestCase;

class GraphDatabaseServiceDateEventsTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_returns_date_events_for_decision_with_correct_structure(): void
    {
        $service = $this->createPartialMock(GraphDatabaseService::class, ['run']);

        $mockResults = collect([
            [
                'e' => [
                    'id' => 'event-1',
                    'date' => '2024-01-15',
                    'event_type' => 'filing',
                    'description' => 'Case filed',
                ],
            ],
            [
                'e' => [
                    'id' => 'event-2',
                    'date' => '2024-03-20',
                    'event_type' => 'judgment',
                    'description' => 'Judgment rendered',
                ],
            ],
        ]);

        $service->expects($this->once())
            ->method('run')
            ->with($this->stringContains('HAS_EVENT'))
            ->willReturn($mockResults);

        $result = $service->getDateEventsForDecision('decision-123');

        $this->assertCount(2, $result);
        $this->assertEquals('2024-01-15', $result[0]['date']);
        $this->assertEquals('filing', $result[0]['event_type']);
        $this->assertEquals('Case filed', $result[0]['description']);
    }

    /** @test */
    public function it_returns_empty_array_when_no_events(): void
    {
        $service = $this->createPartialMock(GraphDatabaseService::class, ['run']);

        $service->expects($this->once())
            ->method('run')
            ->willReturn(collect([]));

        $result = $service->getDateEventsForDecision('decision-999');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /** @test */
    public function it_handles_neo4j_unavailable_gracefully(): void
    {
        $service = $this->createPartialMock(GraphDatabaseService::class, ['run', 'isAvailable']);

        $service->expects($this->once())
            ->method('isAvailable')
            ->willReturn(false);

        $result = $service->getDateEventsForDecision('decision-123');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Unit/Services/GraphDatabaseServiceDateEventsTest.php -v`
Expected: FAIL

**Step 3: Implement the query**

Modify `app/Services/GraphDatabaseService.php:2131-2136`:

```php
public function getDateEventsForDecision(string $decisionId): array
{
    if (!$this->isAvailable()) {
        return [];
    }

    try {
        $cypher = '
            MATCH (d:CourtDecisionDocument {id: $decisionId})-[:HAS_EVENT]->(e:DateEvent)
            RETURN e
            ORDER BY e.date ASC
        ';

        $results = $this->run($cypher, ['decisionId' => $decisionId]);

        return $results->map(function ($record) {
            $event = $record['e'];

            return [
                'id' => $event['id'] ?? '',
                'date' => $event['date'] ?? '',
                'event_type' => $event['event_type'] ?? 'unknown',
                'description' => $event['description'] ?? '',
            ];
        })->toArray();
    } catch (\Throwable $e) {
        Log::warning('Failed to fetch date events for decision', [
            'decision_id' => $decisionId,
            'error' => $e->getMessage(),
        ]);
        return [];
    }
}
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Unit/Services/GraphDatabaseServiceDateEventsTest.php -v`
Expected: PASS

**Step 5: Commit**

```bash
git add tests/Unit/Services/GraphDatabaseServiceDateEventsTest.php \
    app/Services/GraphDatabaseService.php
git commit -m "feat: Implement getDateEventsForDecision Neo4j query

- Add Cypher query to fetch DateEvent nodes via HAS_EVENT
- Map results with date, event_type, description
- Sort by date ascending for timeline display
- Handle Neo4j unavailability gracefully
- Add unit tests for date event fetching"
```

---

## Task 4: Add Toast Notification Component to App Layout

**Files:**
- Modify: `resources/views/layouts/app.blade.php:38-39`

**Step 1: Verify component exists**

Run: `cat resources/views/components/toast-notification.blade.php | head -5`
Expected: Component file exists with Alpine.js toast implementation

**Step 2: Add component to layout**

Modify `resources/views/layouts/app.blade.php`, add before `@livewireScripts`:

```blade
    <main>
        {{ $slot }}
    </main>

    {{-- Toast notifications for graph updates --}}
    <x-toast-notification />

    @livewireScripts
</body>
</html>
```

**Step 3: Verify integration**

Run: `php artisan view:cache && php artisan view:clear`
Expected: No errors

**Step 4: Commit**

```bash
git add resources/views/layouts/app.blade.php
git commit -m "feat: Add toast notification component to app layout

- Include <x-toast-notification /> for graph update notifications
- Component listens for 'graph-notification' window events
- Auto-dismiss after configurable duration"
```

---

## Task 5: Code-Split D3.js with Dynamic Import

**Files:**
- Modify: `resources/js/components/ForceGraph.js:1-10`
- Modify: `resources/js/app.js:4`

**Step 1: Convert ForceGraph to lazy-load D3.js**

Modify `resources/js/components/ForceGraph.js`:

```javascript
// resources/js/components/ForceGraph.js

// D3 will be loaded dynamically
let d3 = null;

/**
 * ForceGraph - Interactive D3.js force-directed graph visualization
 *
 * Features: zoom, pan, drag, click-to-expand, filtering, search
 * D3.js is loaded on-demand to reduce initial bundle size
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
        searchResults: [],
        highlightedNodeId: null,
        expandedNodeIds: new Set(),
        isLoading: false,
        pendingUpdates: 0,
        lastUpdateTimestamp: null,
        d3Loaded: false,

        // D3 references
        svg: null,
        simulation: null,
        zoom: null,

        // Config
        width: 0,
        height: 600,
        performanceMode: false,
        nodeLimit: 500,

        // Node colors by type (keep existing colors)
        nodeColors: {
            CourtDecisionDocument: '#3B82F6',
            LawDocument: '#10B981',
            Judge: '#8B5CF6',
            Lawyer: '#F59E0B',
            LegalTopic: '#EC4899',
            LegalConcept: '#6366F1',
            Article: '#14B8A6',
            Verdict: '#EF4444',
            Evidence: '#84CC16',
            LegalArgument: '#F97316',
            DateEvent: '#06B6D4',
        },

        /**
         * Load D3.js dynamically
         */
        async loadD3() {
            if (d3) return d3;

            this.isLoading = true;
            try {
                d3 = await import('d3');
                this.d3Loaded = true;
                return d3;
            } catch (error) {
                console.error('Failed to load D3.js:', error);
                throw error;
            } finally {
                this.isLoading = false;
            }
        },

        /**
         * Initialize the graph (modified to load D3 first)
         */
        async init() {
            await this.loadD3();
            this.initSvg();
            this.initZoom();
            this.initSimulation();
            this.initEchoListener();
        },

        // ... rest of component methods remain unchanged but use d3 variable
```

**Step 2: Update app.js to handle async component**

`resources/js/app.js` stays the same - Alpine handles async init()

**Step 3: Build and verify bundle size reduction**

Run: `npm run build 2>&1 | tail -20`
Expected: app.js bundle reduced (D3 now in separate chunk)

**Step 4: Commit**

```bash
git add resources/js/components/ForceGraph.js
git commit -m "perf: Code-split D3.js for reduced initial bundle size

- Convert static D3 import to dynamic import()
- Load D3.js on-demand when ForceGraph initializes
- Reduces initial app.js bundle by ~400KB
- D3 loaded asynchronously when graph view is rendered"
```

---

## Task 6: Enhanced Edge Tooltips with d3-tip

**Files:**
- Create: `resources/js/components/graph-tooltip.js`
- Modify: `resources/js/components/ForceGraph.js`
- Modify: `package.json` (add d3-tip dependency)

**Step 1: Install d3-tip**

Run: `npm install d3-tip --save`

**Step 2: Create tooltip helper**

Create `resources/js/components/graph-tooltip.js`:

```javascript
// resources/js/components/graph-tooltip.js

/**
 * Create rich tooltip for graph edges
 */
export function createEdgeTooltip(d3) {
    const tip = d3.tip()
        .attr('class', 'graph-tooltip')
        .offset([-10, 0])
        .html(d => {
            const relationshipLabel = d.label || d.type || 'Related';
            const weight = d.weight ? ` (${d.weight})` : '';

            return `
                <div class="bg-gray-900 text-white px-3 py-2 rounded-lg shadow-lg text-sm max-w-xs">
                    <div class="font-semibold">${relationshipLabel}${weight}</div>
                    ${d.properties ? `
                        <div class="mt-1 text-gray-300 text-xs">
                            ${Object.entries(d.properties)
                                .filter(([k]) => !['id', 'created_at'].includes(k))
                                .slice(0, 3)
                                .map(([k, v]) => `${k}: ${v}`)
                                .join('<br>')}
                        </div>
                    ` : ''}
                </div>
            `;
        });

    return tip;
}

/**
 * Create rich tooltip for graph nodes
 */
export function createNodeTooltip(d3) {
    const tip = d3.tip()
        .attr('class', 'graph-tooltip')
        .offset([-10, 0])
        .html(d => {
            const nodeType = d.type || d.labels?.[0] || 'Node';
            const title = d.title || d.name || d.id;

            return `
                <div class="bg-gray-900 text-white px-3 py-2 rounded-lg shadow-lg text-sm max-w-sm">
                    <div class="text-xs text-gray-400 uppercase">${nodeType}</div>
                    <div class="font-semibold mt-1">${title}</div>
                    ${d.summary ? `
                        <div class="mt-2 text-gray-300 text-xs line-clamp-3">
                            ${d.summary}
                        </div>
                    ` : ''}
                </div>
            `;
        });

    return tip;
}
```

**Step 3: Integrate tooltips into ForceGraph**

In ForceGraph.js `initSvg()` method, add after loading D3:

```javascript
// Import tooltip helpers
import { createEdgeTooltip, createNodeTooltip } from './graph-tooltip';

// In initSvg() after d3 loaded:
this.edgeTip = createEdgeTooltip(d3);
this.nodeTip = createNodeTooltip(d3);
this.svg.call(this.edgeTip);
this.svg.call(this.nodeTip);

// In edge rendering, replace <title> with:
edges.on('mouseover', this.edgeTip.show)
     .on('mouseout', this.edgeTip.hide);

// In node rendering:
nodes.on('mouseover', this.nodeTip.show)
     .on('mouseout', this.nodeTip.hide);
```

**Step 4: Add tooltip CSS**

Add to `resources/css/app.css`:

```css
/* Graph tooltip styles */
.graph-tooltip {
    pointer-events: none;
    z-index: 1000;
}

.graph-tooltip .line-clamp-3 {
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
```

**Step 5: Build and test**

Run: `npm run build && npm run dev`
Expected: No errors, tooltips work on hover

**Step 6: Commit**

```bash
git add package.json package-lock.json \
    resources/js/components/graph-tooltip.js \
    resources/js/components/ForceGraph.js \
    resources/css/app.css
git commit -m "feat: Add rich edge/node tooltips with d3-tip

- Install d3-tip for enhanced hover tooltips
- Create reusable tooltip helpers for edges and nodes
- Show relationship type, weight, and properties on edge hover
- Show node type, title, and summary on node hover
- Style tooltips with Tailwind-like dark theme"
```

---

## Verification Checklist

After all tasks complete:

1. **Run Neo4j query tests:**
   ```bash
   ./vendor/bin/phpunit tests/Unit/Services/GraphDatabaseService*Test.php -v
   ```
   Expected: All tests pass

2. **Verify bundle size reduction:**
   ```bash
   npm run build 2>&1 | grep -E "chunk|size"
   ```
   Expected: Main bundle < 700KB, d3 in separate chunk

3. **Test toast integration:**
   - Open any page using app layout
   - Dispatch event: `window.dispatchEvent(new CustomEvent('graph-notification', { detail: { type: 'info', title: 'Test', message: 'Works!' }}))`
   - Toast should appear

4. **Test graph with new queries:**
   - Select a decision node
   - Arguments, Evidence, Timeline panels should populate (if data exists)

5. **Run full test suite:**
   ```bash
   ./vendor/bin/phpunit tests/Feature/Livewire/Graph/ -v
   ```
   Expected: All pass

---

## Summary

| Task | Description | Files Modified |
|------|-------------|----------------|
| 1 | Neo4j getArgumentsForDecision | GraphDatabaseService.php + test |
| 2 | Neo4j getEvidenceForDecision | GraphDatabaseService.php + test |
| 3 | Neo4j getDateEventsForDecision | GraphDatabaseService.php + test |
| 4 | Toast component in layout | layouts/app.blade.php |
| 5 | D3.js code splitting | ForceGraph.js |
| 6 | Rich tooltips with d3-tip | graph-tooltip.js + ForceGraph.js |

Total: 6 tasks, ~30-45 minutes execution time
