# Citation Network Analysis - UI Implementation Requirements

## Overview

This document describes the UI components that need to be implemented to support citation network analysis in the Graph Viewer. These requirements are based on E2E browser tests in `tests/Browser/CitationNetworkAnalysisTest.php`.

## Status

- **Service Layer**: ✅ Implemented (`app/Services/DecisionCitationService.php`)
- **Service Tests**: ✅ Passing (`tests/Unit/Services/DecisionCitationServiceTest.php`)
- **UI Components**: ✅ Implemented (`app/Http/Livewire/GraphViewer.php`)
- **E2E Tests**: ✅ Written (describe desired behavior)

## Service Layer Capabilities

The `DecisionCitationService` already implements:

```php
public function analyzeCitations(string $decisionId, array $options = []): array
{
    $operation = $options['operation'] ?? 'graph';

    return match ($operation) {
        'graph' => $this->buildCitationGraph($decisionId, $options),
        'authority' => $this->calculateAuthorityMetrics($decisionId),
        'patterns' => $this->analyzeCitationPatterns($decisionId),
        'influence' => $this->analyzeInfluenceSpread($decisionId),
        default => ['error' => 'Unknown operation'],
    };
}
```

## Required UI Components

### 1. Citation Analysis Button

**Location**: Graph Viewer (`resources/views/livewire/graph-viewer.blade.php`)

**Selector**: `@citation-analysis-button`

**Behavior**:
- Appears when a court decision is selected
- Opens citation analysis panel
- Should be visible in the node details or graph controls area

**Example HTML**:
```html
@if($selectedNode && $selectedNodeType === 'CourtDecisionDocument')
<button
    dusk="citation-analysis-button"
    wire:click="openCitationAnalysis"
    class="btn-primary w-full">
    <svg>...</svg>
    Analyze Citations
</button>
@endif
```

### 2. Citation Analysis Panel

**Location**: Graph Viewer

**Selector**: `@citation-analysis-panel`

**Components**:
- Operation selector dropdown
- Run analysis button
- Results display area

**Example Structure**:
```html
<div dusk="citation-analysis-panel" class="card">
    <div class="card-header">
        <h2>Citation Network Analysis</h2>
    </div>
    <div class="card-body">
        <!-- Operation Selector -->
        <div class="form-group">
            <label>Analysis Type</label>
            <select dusk="analysis-operation" wire:model="citationOperation">
                <option value="graph">Citation Graph</option>
                <option value="authority">Authority Metrics</option>
                <option value="patterns">Citation Patterns</option>
                <option value="influence">Influence Spread</option>
            </select>
        </div>

        <!-- Run Button -->
        <button dusk="run-analysis-button" wire:click="runCitationAnalysis">
            Run Analysis
        </button>

        <!-- Results Area -->
        <div id="citation-results">
            @if($citationResults)
                {!! $this->renderCitationResults() !!}
            @endif
        </div>
    </div>
</div>
```

### 3. Citation Graph Visualization

**Selector**: `@citation-graph-panel`

**Display Elements**:
- Citation graph canvas (`@citation-graph-canvas`)
- Graph legend showing node types (`@graph-legend`)
- Graph statistics

**Data Structure** (from service):
```json
{
    "root_decision": "dec-123",
    "depth": 2,
    "nodes": [
        {"id": "dec-123"},
        {"id": "dec-456"}
    ],
    "edges": [
        {
            "from": "dec-123",
            "to": "dec-456",
            "type": "direct"
        }
    ]
}
```

**Visual Requirements**:
- Root decision: Center node (highlighted)
- Citing decisions: Incoming edges (nodes that cite this decision)
- Cited decisions: Outgoing edges (nodes this decision cites)
- Edge direction indicators (arrows)

### 4. Authority Metrics Panel

**Selector**: `@authority-metrics-panel`

**Display Elements**:
- H-index value (`@h-index-value`)
- Influence rank (`@influence-rank-value`)
- Citation count - outgoing (`@citation-count-value`)
- Cited-by count - incoming (`@cited-by-count-value`)
- Authority score (`@authority-score-value`)

**Data Structure** (from service):
```json
{
    "decision_id": "dec-123",
    "authority_score": 42.5,
    "citation_count": 15,
    "cited_by_count": 30,
    "h_index": 15,
    "influence_rank": "influential",
    "citation_velocity": 0.0
}
```

**Influence Rank Classifications**:
- `highly_influential`: 50+ citations
- `influential`: 20-49 citations
- `moderately_influential`: 10-19 citations
- `somewhat_influential`: 5-9 citations
- `minimally_influential`: 1-4 citations
- `not_influential`: 0 citations

**Example HTML**:
```html
<div dusk="authority-metrics-panel" class="card">
    <h3>Authority Metrics</h3>
    <div class="metrics-grid">
        <div class="metric">
            <label>H-Index</label>
            <span dusk="h-index-value">15</span>
        </div>
        <div class="metric">
            <label>Influence Rank</label>
            <span dusk="influence-rank-value" class="badge-influential">
                influential
            </span>
        </div>
        <div class="metric">
            <label>Citations (Out)</label>
            <span dusk="citation-count-value">15</span>
        </div>
        <div class="metric">
            <label>Cited By (In)</label>
            <span dusk="cited-by-count-value">30</span>
        </div>
        <div class="metric">
            <label>Authority Score</label>
            <span dusk="authority-score-value">42.5</span>
        </div>
    </div>
</div>
```

### 5. Citation Patterns Panel

**Selector**: `@citation-patterns-panel`

**Display Elements**:
- Temporal distribution chart (`@temporal-distribution-chart`)
- Citation types breakdown (`@citation-types-breakdown`)
- Detected patterns list (`@patterns-list`)

**Data Structure** (from service):
```json
{
    "decision_id": "dec-123",
    "patterns": [
        "primarily_direct_citations",
        "citations_over_time"
    ],
    "temporal_distribution": {
        "2025-01": 5,
        "2025-02": 8,
        "2025-03": 12
    },
    "citation_types": {
        "direct": 20,
        "indirect": 5
    }
}
```

**Visual Requirements**:
- Line/bar chart showing citations over time
- Pie/bar chart showing citation type distribution
- List of detected patterns with descriptions

### 6. Influence Spread Panel

**Selector**: `@influence-spread-panel`

**Display Elements**:
- Influence spread chart (`@influence-spread-chart`)
- Direct influences count (`@direct-influence-count`)
- Indirect influences count (`@indirect-influence-count`)
- Total reach value (`@total-reach-value`)
- Influenced decisions list (`@influenced-decisions-list`)

**Data Structure** (from service):
```json
{
    "decision_id": "dec-123",
    "influenced_decisions": [
        {"id": "dec-456", "level": "direct"},
        {"id": "dec-789", "level": "indirect"}
    ],
    "influence_spread": {
        "direct": 10,
        "indirect": 15,
        "total_reach": 25
    },
    "key_concepts_propagated": []
}
```

**Visual Requirements**:
- Tree/network diagram showing influence spread
- Metrics showing reach statistics
- List of influenced decisions with direct/indirect indicator

## Livewire Component Methods to Implement

Add to `app/Http/Livewire/GraphViewer.php`:

```php
// Properties
public $citationOperation = 'graph';
public $citationResults = null;
public $showCitationPanel = false;

// Methods
public function openCitationAnalysis()
{
    $this->showCitationPanel = true;
}

public function runCitationAnalysis()
{
    if (!$this->selectedNodeId || $this->selectedNodeType !== 'CourtDecisionDocument') {
        $this->error = 'Please select a court decision first';
        return;
    }

    $citationService = app(\App\Services\DecisionCitationService::class);

    $this->citationResults = $citationService->analyzeCitations(
        $this->selectedNodeId,
        ['operation' => $this->citationOperation]
    );
}

protected function renderCitationResults()
{
    return match($this->citationOperation) {
        'graph' => view('livewire.partials.citation-graph', ['data' => $this->citationResults]),
        'authority' => view('livewire.partials.authority-metrics', ['data' => $this->citationResults]),
        'patterns' => view('livewire.partials.citation-patterns', ['data' => $this->citationResults]),
        'influence' => view('livewire.partials.influence-spread', ['data' => $this->citationResults]),
        default => null,
    };
}
```

## Partial Views to Create

1. `resources/views/livewire/partials/citation-graph.blade.php`
2. `resources/views/livewire/partials/authority-metrics.blade.php`
3. `resources/views/livewire/partials/citation-patterns.blade.php`
4. `resources/views/livewire/partials/influence-spread.blade.php`

## Testing Strategy

### Unit Tests
- ✅ Service layer tests already passing
- Test each `analyzeCitations()` operation independently

### Browser Tests
- ❌ Currently fail (UI not implemented)
- Run: `./scripts/run-tests.sh --filter=CitationNetworkAnalysisTest`
- Requires PostgreSQL database

### Manual Testing
1. Navigate to `/graph`
2. Search for a court decision (e.g., "Pp-74/2025")
3. Click "Analyze Citations" button
4. Select analysis type
5. Click "Run Analysis"
6. Verify results display correctly

## Implementation Priority

1. **High Priority** (Core functionality):
   - Citation Analysis button
   - Citation Analysis panel
   - Authority Metrics panel (most valuable for users)

2. **Medium Priority**:
   - Citation Graph visualization
   - Influence Spread panel

3. **Low Priority** (Nice to have):
   - Citation Patterns panel
   - Advanced visualizations

## Database Requirements

The service relies on the `citation_relationships` table:

```sql
CREATE TABLE citation_relationships (
    id SERIAL PRIMARY KEY,
    citing_decision_id VARCHAR(255) NOT NULL,
    cited_decision_id VARCHAR(255) NOT NULL,
    citation_type VARCHAR(50),
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

Ensure this table is populated with citation data before testing.

## Notes

- All E2E tests are written following TDD principles (Red-Green-Refactor)
- Tests describe desired behavior before implementation
- Service layer is complete and tested
- UI implementation is the only remaining work
- Tests serve as executable specification for UI requirements
