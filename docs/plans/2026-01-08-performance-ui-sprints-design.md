# Graph Enhancement Phase 3-4: Performance & UI Sprints Design

**Created**: 2026-01-08
**Status**: Approved
**Author**: Claude (Brainstorming Session)

---

## Executive Summary

Two sequential sprints to improve graph sync performance (10x target) and add rich UI visualization for legal research exploration and analysis.

| Sprint | Focus | Effort | Target |
|--------|-------|--------|--------|
| Sprint 3 | Performance Tuning | 27h | 10x sync speed improvement |
| Sprint 4 | UI Polish | 34h | D3.js exploration + enhanced analysis panels |

**Deferred**: ML Enhancement (NER, text classification, semantic similarity) → Future sprint

---

## Design Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Priority | Performance first, then UI | Performance informs UI decisions (lazy loading, etc.) |
| Performance target | 10x improvement | Achievable, meaningful impact, sets foundation |
| UI use case | Exploration + Analysis | Covers discovery and task-focused workflows |
| Real-time updates | Background notify (toast/badge) | Simpler than full real-time, upgradeable later |
| Sprint structure | Sequential | Cleaner focus, easier measurement |

---

## Sprint 3: Performance Tuning

### Goal

Reduce graph sync time from ~60s/10 nodes to ~6s/10 nodes (10x improvement)

### Success Metrics

- Benchmark: 10 nodes in <6s
- Benchmark: 100 nodes in <60s
- No regression in extraction accuracy

### Three Optimization Tracks

#### Track A: Batch Cypher Operations (~5x improvement)

**Problem**: Current implementation executes one Cypher query per node/relationship operation.

**Solution**: Use UNWIND for bulk operations.

```cypher
-- Before: N queries
MERGE (n:Decision {id: $id}) SET n.title = $title

-- After: 1 query for N nodes
UNWIND $nodes AS node
MERGE (n:Decision {id: node.id})
SET n += node.props
```

| Task | Description | Effort |
|------|-------------|--------|
| A.1 | Create `BatchCypherBuilder` service for UNWIND queries | 3h |
| A.2 | Refactor `GraphDatabaseService::upsertNode()` to batch mode | 2h |
| A.3 | Refactor `GraphDatabaseService::createRelationship()` to batch mode | 2h |
| A.4 | Add batch flush threshold config (default: 100 operations) | 1h |
| A.5 | Update all 9 sync services to use batch mode | 4h |
| A.6 | Write integration tests for batch operations | 2h |

**Files to Create/Modify:**
```
app/Services/Graph/BatchCypherBuilder.php          # A.1 (new)
app/Services/Graph/GraphDatabaseService.php        # A.2, A.3
config/graph.php                                   # A.4
app/Services/Graph/*GraphSyncService.php           # A.5 (9 files)
tests/Integration/Graph/BatchOperationsTest.php   # A.6 (new)
```

#### Track B: Parallel Extraction (~2x improvement)

**Problem**: 9 extractors run sequentially, wasting time on independent operations.

**Solution**: Run independent extractors in parallel using Laravel Concurrency.

**Dependency Graph:**
```
Independent (can run parallel):
├── LegalTopicExtractor
├── ArticleExtractor
├── LegalConceptExtractor
├── LawyerExtractor
├── VerdictExtractor
├── LegalArgumentExtractor
├── EvidenceExtractor
├── DateEventExtractor
└── LegalDefinitionExtractor

All extractors are text-based and independent.
Sync services run after extraction (can also be parallelized).
```

| Task | Description | Effort |
|------|-------------|--------|
| B.1 | Create `ParallelExtractionService` using Laravel Concurrency | 2h |
| B.2 | Define extractor dependency graph (which can run parallel) | 1h |
| B.3 | Refactor `EnhancedGraphSyncJob` to use parallel extraction | 2h |
| B.4 | Add concurrency config (max workers, timeout) | 1h |
| B.5 | Write tests for parallel extraction ordering | 2h |

**Files to Create/Modify:**
```
app/Services/Graph/ParallelExtractionService.php   # B.1 (new)
config/graph.php                                   # B.2, B.4
app/Jobs/EnhancedGraphSyncJob.php                  # B.3
tests/Unit/Services/Graph/ParallelExtractionTest.php # B.5 (new)
```

#### Track C: Selective Extraction (~1.5x improvement)

**Problem**: All 9 extractors run on every document, even when not applicable.

**Solution**: Detect document type and run only relevant extractors.

**Extractor-to-Document-Type Mapping:**
```
CourtDecision:
  - ALL extractors (Judge, Party, Verdict, Arguments, Evidence, etc.)

LawDocument:
  - ArticleExtractor
  - LegalDefinitionExtractor
  - LegalConceptExtractor
  - LegalTopicExtractor

Generic:
  - DateEventExtractor
  - LegalConceptExtractor
  - LegalTopicExtractor
```

| Task | Description | Effort |
|------|-------------|--------|
| C.1 | Create `DocumentTypeDetector` service | 2h |
| C.2 | Create extractor-to-document-type mapping config | 1h |
| C.3 | Update sync job to filter extractors by document type | 1h |
| C.4 | Write tests for selective extraction | 1h |

**Files to Create/Modify:**
```
app/Services/Graph/DocumentTypeDetector.php        # C.1 (new)
config/graph.php                                   # C.2
app/Jobs/EnhancedGraphSyncJob.php                  # C.3
tests/Unit/Services/Graph/DocumentTypeDetectorTest.php # C.4 (new)
```

### Execution Order

```
Wave 1: Foundation (7h)
├── A.1 BatchCypherBuilder service
├── B.2 Extractor dependency graph
└── C.1 DocumentTypeDetector service

Wave 2: Core Refactoring (9h) - depends on Wave 1
├── A.2 Batch upsertNode
├── A.3 Batch createRelationship
├── A.4 Batch flush config
├── B.1 ParallelExtractionService
└── C.2 Extractor-to-doctype mapping

Wave 3: Integration (8h) - depends on Wave 2
├── A.5 Update 9 sync services
├── B.3 Refactor EnhancedGraphSyncJob
├── B.4 Concurrency config
└── C.3 Update sync job for selective extraction

Wave 4: Testing & Validation (6h)
├── A.6 Batch integration tests
├── B.5 Parallel extraction tests
├── C.4 Selective extraction tests
└── Run benchmarks, verify 10x improvement
```

---

## Sprint 4: UI Polish

### Goal

Rich graph visualization for exploration and analysis with background change notifications.

### Success Metrics

- Graph renders 500+ nodes smoothly (60fps)
- Panel load time <200ms
- Notification delivery <1s after data change

### Tech Stack

- **D3.js v7**: Force-directed graph visualization
- **Alpine.js**: Panel interactivity (already in use)
- **Laravel Echo**: WebSocket client
- **Laravel Reverb**: WebSocket server (already configured)
- **Tailwind CSS**: Styling consistency

### Three Component Tracks

#### Track A: D3.js Force Graph (Exploration)

**Purpose**: Interactive force-directed graph for relationship discovery.

**Features:**
- Zoom, pan, drag nodes
- Click-to-expand (load connected nodes on demand)
- Filter by entity type
- Search/highlight node by name
- Color coding by node type
- Edge styling by relationship type

| Task | Description | Effort |
|------|-------------|--------|
| A.1 | Install D3.js v7, create base `ForceGraph` Alpine component | 2h |
| A.2 | Implement node rendering with type-based colors/icons | 2h |
| A.3 | Implement edge rendering with relationship-type styling | 2h |
| A.4 | Add zoom, pan, drag interactions | 2h |
| A.5 | Add click-to-expand (load connected nodes on demand) | 3h |
| A.6 | Add entity type filter controls | 2h |
| A.7 | Add search/highlight node by name | 2h |
| A.8 | Performance optimization for 500+ nodes | 2h |
| A.9 | Write Dusk browser tests | 2h |

**Files to Create:**
```
resources/js/components/ForceGraph.js              # A.1-A.8
resources/views/livewire/graph/force-graph.blade.php
app/Livewire/Graph/ForceGraphController.php
tests/Browser/ForceGraphTest.php                   # A.9
```

**Node Color Scheme:**
```javascript
const nodeColors = {
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
};
```

#### Track B: Enhanced Analysis Panels (Analysis)

**Purpose**: Structured panels for task-focused legal research.

**New Panels:**
- Arguments panel (plaintiff/defendant/court positions)
- Evidence panel (documentary, testimonial, expert, physical)
- Timeline panel (filing, hearing, judgment dates)

| Task | Description | Effort |
|------|-------------|--------|
| B.1 | Create collapsible sidebar with tabbed navigation | 2h |
| B.2 | Add Arguments panel (from LegalArgument nodes) | 2h |
| B.3 | Add Evidence panel (from Evidence nodes) | 2h |
| B.4 | Add Timeline panel (from DateEvent nodes) | 2h |
| B.5 | Add deep-link routing to specific panels | 1h |
| B.6 | Write Livewire feature tests | 2h |

**Files to Create/Modify:**
```
resources/views/livewire/graph/partials/sidebar.blade.php      # B.1
resources/views/livewire/graph/partials/arguments-panel.blade.php # B.2
resources/views/livewire/graph/partials/evidence-panel.blade.php  # B.3
resources/views/livewire/graph/partials/timeline-panel.blade.php  # B.4
app/Livewire/Graph/GraphViewer.php                             # B.2-B.5
routes/web.php                                                  # B.5
tests/Feature/Livewire/GraphViewerPanelsTest.php               # B.6
```

#### Track C: Background Notifications

**Purpose**: Awareness of data changes without full real-time complexity.

**Flow:**
1. Graph data changes (new decision synced)
2. Server broadcasts `GraphDataUpdated` event
3. Client receives via Laravel Echo
4. Toast appears: "Graph data updated. Click to refresh."
5. Badge counter increments
6. User clicks "Refresh" to pull latest

| Task | Description | Effort |
|------|-------------|--------|
| C.1 | Configure Laravel Echo with Reverb channel | 1h |
| C.2 | Create `GraphDataUpdated` broadcast event | 1h |
| C.3 | Implement toast notification component | 1h |
| C.4 | Add badge counter for pending updates | 1h |
| C.5 | Add "Refresh" button to pull latest data | 1h |
| C.6 | Write tests for notification flow | 1h |

**Files to Create/Modify:**
```
app/Events/Graph/GraphDataUpdated.php              # C.2
resources/js/echo.js                               # C.1
resources/views/components/toast-notification.blade.php # C.3
resources/views/livewire/graph/graph-viewer.blade.php  # C.4, C.5
tests/Feature/GraphNotificationTest.php            # C.6
```

### Execution Order

```
Wave 1: Foundation (5h)
├── A.1 D3.js + ForceGraph component
├── B.1 Collapsible sidebar
└── C.1 Laravel Echo + Reverb

Wave 2: Core Features (13h) - depends on Wave 1
├── A.2-A.4 Node/edge rendering + interactions
├── B.2-B.4 Arguments/Evidence/Timeline panels
└── C.2-C.3 Broadcast event + toast

Wave 3: Polish (10h) - depends on Wave 2
├── A.5-A.7 Click-expand, filters, search
├── B.5 Deep-link routing
└── C.4-C.5 Badge counter + refresh button

Wave 4: Testing (6h)
├── A.8-A.9 Performance + Dusk tests
├── B.6 Livewire tests
└── C.6 Notification tests
```

---

## Deferred: ML Enhancement (Future Sprint)

The following ML enhancements are deferred to a future sprint after Performance and UI are complete:

1. **Named Entity Recognition for Lawyers**
   - Train NER model on Croatian legal text
   - Replace regex-based LawyerExtractor
   - Requires: Annotated training data, ML infrastructure

2. **Text Classification for Legal Topics**
   - Train classifier on Croatian case law
   - Replace keyword-based LegalTopicExtractor
   - Requires: Labeled topic dataset, evaluation metrics

3. **Semantic Similarity for Concept Matching**
   - Embeddings for legal concepts
   - Vector similarity for concept extraction
   - Requires: Croatian legal embeddings model

**Prerequisites for ML Sprint:**
- Annotated Croatian legal corpus
- ML infrastructure (training, serving)
- Evaluation framework for accuracy metrics

---

## Risk Assessment

| Risk | Probability | Impact | Mitigation |
|------|-------------|--------|------------|
| Batch operations break edge cases | Medium | High | Comprehensive integration tests |
| Parallel extraction race conditions | Low | Medium | Proper isolation, no shared state |
| D3.js performance with large graphs | Medium | Medium | Virtual rendering, node limits |
| WebSocket connection reliability | Low | Low | Graceful fallback to polling |

---

## Definition of Done

### Sprint 3: Performance
- [ ] Benchmark: 10 nodes syncs in <6s
- [ ] Benchmark: 100 nodes syncs in <60s
- [ ] All existing tests pass (no regression)
- [ ] New integration tests for batch operations
- [ ] Concurrency config documented

### Sprint 4: UI
- [ ] Force graph renders 500+ nodes at 60fps
- [ ] All analysis panels load in <200ms
- [ ] Notifications delivered in <1s
- [ ] Dusk browser tests pass
- [ ] Deep-links work for all panels

---

## Files Index

### Sprint 3: Performance
```
app/Services/Graph/
├── BatchCypherBuilder.php           # NEW
├── ParallelExtractionService.php    # NEW
├── DocumentTypeDetector.php         # NEW
├── GraphDatabaseService.php         # MODIFY
└── *GraphSyncService.php            # MODIFY (9 files)

app/Jobs/EnhancedGraphSyncJob.php    # MODIFY

config/graph.php                     # MODIFY

tests/Integration/Graph/BatchOperationsTest.php        # NEW
tests/Unit/Services/Graph/ParallelExtractionTest.php   # NEW
tests/Unit/Services/Graph/DocumentTypeDetectorTest.php # NEW
```

### Sprint 4: UI
```
resources/js/
├── components/ForceGraph.js         # NEW
└── echo.js                          # MODIFY

resources/views/livewire/graph/
├── force-graph.blade.php            # NEW
├── graph-viewer.blade.php           # MODIFY
└── partials/
    ├── sidebar.blade.php            # NEW
    ├── arguments-panel.blade.php    # NEW
    ├── evidence-panel.blade.php     # NEW
    └── timeline-panel.blade.php     # NEW

app/Livewire/Graph/
├── ForceGraphController.php         # NEW
└── GraphViewer.php                  # MODIFY

app/Events/Graph/GraphDataUpdated.php # NEW

tests/Browser/ForceGraphTest.php             # NEW
tests/Feature/Livewire/GraphViewerPanelsTest.php # NEW
tests/Feature/GraphNotificationTest.php      # NEW
```
