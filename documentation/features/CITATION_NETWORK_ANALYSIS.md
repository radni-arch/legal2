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
# Requires PostgreSQL to be running
sudo service postgresql start

# Run tests
php artisan dusk --filter=CitationNetworkAnalysisTest
```

## API

The underlying service can be called programmatically:

```php
use App\Services\DecisionCitationService;

$service = app(DecisionCitationService::class);

// Get authority metrics
$metrics = $service->analyzeCitations('decision-id', ['operation' => 'authority']);

// Get citation graph
$graph = $service->analyzeCitations('decision-id', ['operation' => 'graph', 'depth' => 2, 'limit' => 50]);

// Get patterns
$patterns = $service->analyzeCitations('decision-id', ['operation' => 'patterns']);

// Get influence spread
$influence = $service->analyzeCitations('decision-id', ['operation' => 'influence']);
```

## Implementation Files

- **Backend**: `app/Services/DecisionCitationService.php`
- **Livewire Component**: `app/Http/Livewire/GraphViewer.php`
- **Blade Template**: `resources/views/livewire/graph-viewer.blade.php`
- **D3.js Component**: `resources/js/components/citation-graph.js`
- **Tests**: `tests/Browser/CitationNetworkAnalysisTest.php`

## Dusk Selectors

All UI elements have browser test selectors for E2E testing:

- `@citation-analysis-button` - Citation analysis button
- `@citation-analysis-panel` - Main analysis panel modal
- `@analysis-operation` - Operation selector dropdown
- `@run-analysis-button` - Run analysis button
- `@authority-metrics-panel` - Authority metrics panel
- `@h-index-value` - H-index metric
- `@influence-rank-value` - Influence rank
- `@citation-count-value` - Citation count (outgoing)
- `@cited-by-count-value` - Cited-by count (incoming)
- `@authority-score-value` - Authority score
- `@citation-graph-panel` - Citation graph panel
- `@graph-legend` - Graph legend
- `@citation-graph-canvas` - D3.js canvas
- `@citation-patterns-panel` - Patterns panel
- `@temporal-distribution-chart` - Temporal chart
- `@citation-types-breakdown` - Types breakdown
- `@patterns-list` - Detected patterns list
- `@influence-spread-panel` - Influence spread panel
- `@direct-influence-count` - Direct influences count
- `@indirect-influence-count` - Indirect influences count
- `@total-reach-value` - Total reach metric
- `@influence-spread-chart` - Influence spread visualization
- `@influenced-decisions-list` - Influenced decisions list
