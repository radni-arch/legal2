# Citation Extraction and Similarity Detection for Court Decision Search

This document describes the citation extraction and similarity detection features integrated into the Court Decision Search system.

## Overview

The system provides comprehensive citation extraction, fact extraction, and multi-method similarity detection for court decisions:

1. **Citation Extraction**: Automatically extracts legal citations from court decisions (statutes, case numbers, ECLI identifiers)
2. **Fact Extraction**: Extracts structured legal facts including parties, issues, holdings, arguments, and evidence (see [Fact Extraction Documentation](./FACT_EXTRACTION.md))
3. **Similarity Detection**: Finds similar decisions using content similarity, citation patterns, and graph relationships
4. **Citation-Aware Search**: Enhances search results with citation analysis and ranking

> **Note**: For detailed information about fact extraction, see the dedicated [Fact Extraction Documentation](./FACT_EXTRACTION.md).

## Features

### 1. Citation Extraction

Extract all legal citations from a court decision document:

- **Statute Citations**: Croatian legal statutes with article-level precision (e.g., "ZPP članak 110")
- **Narodne Novine References**: Official Gazette references (e.g., "NN 53/91")
- **Case Numbers**: Court case identifiers (e.g., "Rev 123/2020")
- **ECLI Identifiers**: European Case Law Identifier (e.g., "ECLI:HR:VSRH:2020:123")
- **Dates**: Legal dates mentioned in decisions

#### MCP Tool: `decision.extract_citations`

```json
{
  "decision_id": "01HF123ABC..."
}
```

**Response:**
```json
{
  "decision_id": "01HF123ABC...",
  "citations": {
    "statutes": [...],
    "narodne_novine": [...],
    "case_numbers": [...],
    "ecli": [...],
    "dates": [...]
  },
  "statistics": {
    "total_citations": 15,
    "statute_citations": 8,
    "case_citations": 3,
    "ecli_citations": 2
  },
  "canonical_citations": ["ZPP čl. 110", "Rev 123/2020", ...],
  "law_numbers": ["53/91", "123/20"],
  "case_identifiers": ["Rev 123/2020", ...]
}
```

#### PHP API:

```php
use App\Services\DecisionSearchService;

$searchService = app(DecisionSearchService::class);
$citations = $searchService->extractCitations($decisionId);
```

### 2. Similarity Detection

Find similar court decisions using multiple similarity methods:

#### **Multi-Method Similarity**

The system uses three complementary methods:

1. **Content Similarity (40% weight)**
   - Semantic similarity using vector embeddings (pgvector)
   - Compares the actual content and legal reasoning

2. **Citation Pattern Similarity (20% weight)**
   - Jaccard similarity of cited laws and cases
   - Identifies decisions citing similar legal foundations

3. **Graph Relationship Similarity (40% weight)**
   - Analyzes relationships via Neo4j graph database
   - Finds decisions through:
     - Shared law citations
     - Shared keywords
     - Direct precedent references
     - Existing SIMILAR_TO relationships

#### MCP Tool: `decision.find_similar`

```json
{
  "decision_id": "01HF123ABC...",
  "limit": 20,
  "threshold": 0.75,
  "filters": {
    "court": "Vrhovni sud",
    "jurisdiction": "HR",
    "date_from": "2020-01-01"
  }
}
```

**Response:**
```json
{
  "source_decision": {
    "id": "01HF123ABC...",
    "case_number": "Rev 123/2020",
    "title": "..."
  },
  "total_results": 15,
  "results": [
    {
      "decision": {
        "id": "01HF456DEF...",
        "case_number": "Rev 456/2021",
        "court": "Vrhovni sud",
        "jurisdiction": "HR"
      },
      "composite_similarity": 0.8750,
      "graph_similarity": 0.9000,
      "vector_similarity": 0.8500,
      "citation_similarity": 0.8750,
      "relationship_types": ["cites_same_law", "shared_keywords"],
      "shared_citations": {
        "statutes": ["ZPP čl. 110", "ZPP čl. 350"],
        "case_numbers": ["Rev 789/2019"]
      }
    }
  ],
  "similarity_methods": {
    "graph": "Relationship-based similarity using Neo4j graph",
    "vector": "Semantic content similarity using embeddings",
    "citation": "Citation pattern similarity",
    "composite": "Weighted combination of all methods"
  }
}
```

#### PHP API:

```php
$options = [
    'limit' => 20,
    'threshold' => 0.75,
    'filters' => [
        'court' => 'Vrhovni sud',
        'date_from' => '2020-01-01',
    ],
];

$similarDecisions = $searchService->findSimilar($decisionId, $options);
```

### 3. Search by Cited Law

Find all court decisions that cite a specific law and article:

#### MCP Tool: `decision.search_by_cited_law`

```json
{
  "law_identifier": "ZPP",
  "article_number": "110",
  "filters": {
    "jurisdiction": "HR",
    "date_from": "2020-01-01"
  },
  "limit": 50
}
```

**Response:**
```json
{
  "query": {
    "law": "ZPP",
    "article": "110"
  },
  "total_results": 42,
  "results": [
    {
      "id": "01HF123ABC...",
      "decision_id": "01HE789XYZ...",
      "case_number": "Rev 123/2020",
      "title": "...",
      "court": "Vrhovni sud",
      "decision_date": "2020-05-15",
      "citation": {
        "cited_law": {
          "id": "01HD456UVW...",
          "title": "Zakon o parničnom postupku",
          "law_number": "NN 53/91"
        },
        "article": "110",
        "paragraph": "2",
        "item": null
      }
    }
  ]
}
```

#### PHP API:

```php
$options = [
    'jurisdiction' => 'HR',
    'date_from' => '2020-01-01',
    'limit' => 50,
];

$decisions = $searchService->searchByCitedLaw('ZPP', '110', $options);
```

### 4. Comprehensive Citation Analysis

Get complete citation analysis for a decision:

#### MCP Tool: `decision.analyze_citations`

```json
{
  "decision_id": "01HF123ABC..."
}
```

**Response:**
```json
{
  "decision_id": "01HF123ABC...",
  "citations": {
    "statutes": [...],
    "case_numbers": [...],
    "ecli": [...]
  },
  "statistics": {
    "total_citations": 15,
    "statute_citations": 8
  },
  "canonical_citations": [...],
  "graph_context": {
    "cited_laws": [
      {
        "law": {
          "id": "01HD456...",
          "title": "Zakon o parničnom postupku"
        },
        "citation_type": "statute",
        "article": "110"
      }
    ],
    "related_decisions": [
      {
        "decision": {...},
        "relationship_types": ["cites_same_law"],
        "strength": 0.85
      }
    ]
  },
  "similar_citation_patterns": [
    {
      "decision": {...},
      "citation_similarity": 0.75,
      "shared_citations": {
        "statutes": ["ZPP čl. 110"]
      }
    }
  ]
}
```

#### PHP API:

```php
$analysis = $searchService->analyzeCitations($decisionId);
```

### 5. Citation-Aware Search

Search with automatic citation detection and boosting:

#### Via UnifiedSearchService:

```php
use App\Services\UnifiedSearchService;

$searchService = app(UnifiedSearchService::class);

$results = $searchService->searchWithCitations('ZPP članak 110 pravomočnost', [
    'corpora' => ['decisions'],
    'limit' => 10,
]);
```

**Features:**
- Automatically detects citations in the query
- Extracts citations from each result
- Boosts results that cite the same laws
- Provides citation matching information

#### Via DecisionSearchService:

```php
$results = $decisionSearchService->searchWithCitations('ZPP članak 110', [
    'limit' => 10,
    'filters' => [
        'court' => 'Vrhovni sud',
    ],
]);
```

## Architecture

### Services

1. **DecisionCitationService**
   - Core service for citation extraction and similarity detection
   - Location: `app/Services/DecisionCitationService.php`
   - Integrates with:
     - `HrLegalCitationsDetector`: Croatian legal citation extraction
     - `GraphRagService`: Graph-based relationship analysis
     - `DecisionSearchService`: Search functionality

2. **UnifiedSearchService**
   - Enhanced with `searchWithCitations()` method
   - Provides cross-corpus citation-aware search
   - Location: `app/Services/UnifiedSearchService.php`

3. **DecisionSearchService**
   - Enhanced with citation and similarity methods
   - Convenience methods for common operations
   - Location: `app/Services/DecisionSearchService.php`

4. **GraphRagService**
   - Graph database integration for citation relationships
   - Methods for finding related decisions
   - Location: `app/Services/GraphRagService.php`

5. **HrLegalCitationsDetector**
   - Croatian legal citation detection
   - Supports multiple citation types
   - Location: `app/Services/LegalCitations/HrLegalCitationsDetector.php`

### MCP Tools

All tools are registered in `app/Mcp/Servers/OdlukeServer.php`:

1. `decision.extract_citations` - Extract citations from a decision
2. `decision.find_similar` - Find similar decisions
3. `decision.search_by_cited_law` - Search by cited law/article
4. `decision.analyze_citations` - Comprehensive citation analysis

### Database Integration

#### PostgreSQL (pgvector)
- Vector embeddings for semantic similarity
- Content-based similarity search
- Fast cosine similarity calculations

#### Neo4j Graph Database
- Citation relationships (CITES, REFERENCES)
- Keyword and tag relationships (HAS_KEYWORD, HAS_TAG)
- Similarity relationships (SIMILAR_TO)
- Court and jurisdiction relationships

## API Endpoints

### HTTP REST API

The features are exposed via the existing `/api/search/with-citations` endpoint:

```http
POST /api/search/with-citations
Content-Type: application/json
X-API-Token: your-api-token

{
  "query": "ZPP članak 110 pravomočnost",
  "corpora": ["decisions"],
  "limit": 10,
  "filters": {
    "court": "Vrhovni sud",
    "date_from": "2020-01-01"
  }
}
```

### MCP HTTP API

```http
POST /api/mcp/decision.extract_citations
Content-Type: application/json
X-MCP-Token: your-mcp-token

{
  "decision_id": "01HF123ABC..."
}
```

## Usage Examples

### Example 1: Find Decisions Citing ZPP Article 110

```php
use App\Services\DecisionSearchService;

$searchService = app(DecisionSearchService::class);

$decisions = $searchService->searchByCitedLaw('ZPP', '110', [
    'jurisdiction' => 'HR',
    'date_from' => '2020-01-01',
    'limit' => 50,
]);

foreach ($decisions['results'] as $decision) {
    echo "{$decision['case_number']}: {$decision['title']}\n";
    echo "  Cites: {$decision['citation']['cited_law']['title']} ";
    echo "Article {$decision['citation']['article']}\n";
}
```

### Example 2: Find Similar Decisions

```php
$similar = $searchService->findSimilar($decisionId, [
    'limit' => 10,
    'threshold' => 0.75,
    'filters' => ['court' => 'Vrhovni sud'],
]);

foreach ($similar['results'] as $result) {
    echo "{$result['decision']['case_number']}\n";
    echo "  Composite Similarity: {$result['composite_similarity']}\n";
    echo "  Content: {$result['vector_similarity']}\n";
    echo "  Citations: {$result['citation_similarity']}\n";
    echo "  Graph: {$result['graph_similarity']}\n";
}
```

### Example 3: Analyze Citation Patterns

```php
$analysis = $searchService->analyzeCitations($decisionId);

echo "Total Citations: {$analysis['statistics']['total_citations']}\n";
echo "Statute Citations: {$analysis['statistics']['statute_citations']}\n";

echo "\nCited Laws:\n";
foreach ($analysis['graph_context']['cited_laws'] as $citedLaw) {
    echo "  - {$citedLaw['law']['title']} ";
    echo "Article {$citedLaw['article']}\n";
}

echo "\nSimilar Citation Patterns:\n";
foreach ($analysis['similar_citation_patterns'] as $similar) {
    echo "  - {$similar['decision']['case_number']} ";
    echo "(similarity: {$similar['citation_similarity']})\n";
}
```

## Performance Considerations

### Caching

- Graph queries are cached for 5-30 minutes depending on volatility
- Search results are cached for 5 minutes
- Cache keys are generated from query parameters

### Optimization

- Vector similarity uses PostgreSQL pgvector for fast searches
- Graph queries use indexed Neo4j Cypher queries
- Citation extraction is performed on-demand with caching
- Composite similarity calculations are parallelizable

### Scalability

- Limit results to reasonable numbers (max 100)
- Use filters to narrow search scope
- Consider pagination for large result sets
- Graph traversal depth is limited (default: 2)

## Testing

Run tests for citation and similarity features:

```bash
# All tests
php artisan test

# Specific feature tests
php artisan test --filter Citation
php artisan test --filter Similarity
```

## Future Enhancements

- Machine learning for citation importance scoring
- Temporal citation analysis (citation trends over time)
- Cross-jurisdictional citation analysis
- Citation network visualization
- Automatic precedent detection
- Citation quality assessment

## Related Documentation

- [Court Decision Refactoring](./COURT_DECISION_REFACTORING.md)
- [Court Decisions Graph Flow](./COURT_DECISIONS_GRAPH_FLOW.md)
- [MCP Tools Documentation](./MCP_TOOLS.md)
- [Search API Documentation](./SEARCH_API.md)
