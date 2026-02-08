# Unified Search Service - Sprint E1.1 Implementation

## Overview

The Unified Search Service provides a single, powerful interface for searching across all legal corpora (Laws, Court Decisions, and Case Documents) with advanced features including deduplication, pagination, sorting, and performance monitoring.

## Key Features

### 1. **Multi-Corpus Search**
Search across all legal document types simultaneously with a single API call.

### 2. **Common Vector Search Logic**
Consolidated search implementation using `performVectorSearch()` method that eliminates code duplication across corpus-specific searches.

### 3. **Result Deduplication**
Automatically removes duplicate results based on `content_hash`, keeping only the highest-scoring instance of each unique document.

### 4. **Advanced Pagination**
Supports both page-based and offset-based pagination:
- `page` + `per_page`: Traditional page-based pagination
- `offset`: Manual offset control for custom pagination logic

### 5. **Flexible Sorting**
Sort results by:
- **Score** (default): Similarity score (weighted by corpus weight)
- **Date**: Document date (promulgation_date for laws, decision_date for decisions)

### 6. **Performance Monitoring**
- Tracks embedding generation time
- Tracks per-corpus search time
- Logs slow queries (>2s) automatically
- Returns performance metrics in response

### 7. **Weighted Corpus Scoring**
Apply different weights to different corpora to prioritize certain document types in results.

## API Endpoints

### Unified Search
`POST /api/search`

Search across all corpora or selected corpora.

**Request Parameters:**
```json
{
  "query": "contract law obligations",
  "corpora": ["laws", "decisions", "cases"],
  "weights": {
    "laws": 1.5,
    "decisions": 1.0,
    "cases": 0.8
  },
  "filters": {
    "jurisdiction": "EU",
    "date_from": "2020-01-01",
    "date_to": "2024-12-31"
  },
  "page": 1,
  "per_page": 20,
  "threshold": 0.7,
  "sort_by": "score",
  "sort_order": "desc",
  "deduplicate": true
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "query": "contract law obligations",
    "total_results": 45,
    "returned_results": 20,
    "deduplicated_count": 5,
    "corpora": ["laws", "decisions", "cases"],
    "results": [
      {
        "type": "law",
        "id": "01HXXX...",
        "title": "Contract Law Act 2020",
        "snippet": "Obligations under contract law...",
        "score": 0.9234,
        "raw_score": 0.6156,
        "corpus_weight": 1.5,
        "metadata": {
          "doc_id": "contract-law-2020",
          "law_number": "2020-001",
          "jurisdiction": "EU",
          "country": "DE",
          "content_hash": "abc123...",
          "promulgation_date": "2020-03-15"
        }
      }
    ],
    "filters": {...},
    "pagination": {
      "page": 1,
      "per_page": 20,
      "offset": 0,
      "total_pages": 3
    },
    "performance": {
      "total_time": 1.234,
      "embedding_time": 0.156,
      "corpus_timing": {
        "laws": 0.423,
        "decisions": 0.512,
        "cases": 0.143
      }
    }
  }
}
```

### Corpus-Specific Searches

#### Search Laws Only
`POST /api/search/laws`

#### Search Court Decisions Only
`POST /api/search/decisions`

#### Search Case Documents Only
`POST /api/search/cases`

All corpus-specific endpoints support the same parameters as the unified search.

### Hybrid Search (Future Enhancement)
`POST /api/search/hybrid`

Combines vector similarity with keyword matching (currently returns vector results only).

### Search with Citations (Future Enhancement)
`POST /api/search/with-citations`

Enhances results with citation graph information (placeholder for future GraphRAG integration).

## Request Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `query` | string | **required** | Search query text (2-1000 chars) |
| `corpora` | array | `['laws','decisions','cases']` | Corpora to search |
| `weights` | object | `{laws:1.0, decisions:1.0, cases:1.0}` | Per-corpus score weights |
| `filters` | object | `{}` | Corpus-specific filters |
| `limit` | integer | `10` | Results per corpus (1-100) |
| `page` | integer | `1` | Page number (1-based) |
| `per_page` | integer | `limit` | Results per page (1-100) |
| `offset` | integer | calculated | Manual offset (overrides page) |
| `threshold` | float | `0.7` | Minimum similarity score (0-1) |
| `model` | string | config value | Embedding model to use |
| `sort_by` | string | `'score'` | Sort field: `'score'` or `'date'` |
| `sort_order` | string | `'desc'` | Sort order: `'asc'` or `'desc'` |
| `deduplicate` | boolean | `true` | Enable result deduplication |

## Filters

### Laws
- `jurisdiction`: Filter by jurisdiction
- `country`: Filter by country code
- `language`: Filter by language code
- `date_from`: Promulgation date >= (ISO 8601)
- `date_to`: Promulgation date <= (ISO 8601)

### Court Decisions
- `court`: Court name (partial match)
- `jurisdiction`: Filter by jurisdiction
- `decision_type`: Type of decision
- `date_from`: Decision date >= (ISO 8601)
- `date_to`: Decision date <= (ISO 8601)

### Case Documents
- `category`: Document category
- `language`: Language code
- `source`: Document source

## Implementation Details

### Core Components

#### 1. UnifiedSearchService (`app/Services/UnifiedSearchService.php`)

**Key Methods:**

- `search(string $query, array $options)`: Main search orchestrator
- `performVectorSearch(...)`: Common vector search logic for all corpora
- `deduplicateResults(array $results)`: Remove duplicates by content_hash
- `sortResults(array $results, string $sortBy, string $sortOrder)`: Sort results
- `searchLaws()`, `searchDecisions()`, `searchCases()`: Corpus-specific searches

**Architecture:**

The service uses a configuration-based approach to eliminate code duplication:

```php
performVectorSearch(
    table: 'laws',
    type: 'law',
    queryEmbedding: $queryEmbedding,
    limit: $limit,
    threshold: $threshold,
    filters: $filters,
    config: [
        'select' => [...],      // Fields to select
        'joins' => [...],       // Join clauses
        'vector_column' => '...', // Column with embeddings
        'filters' => [...],     // Filter mappings
        'formatter' => fn(...) // Result formatter
    ]
)
```

This allows each corpus to define its schema while sharing the same search logic.

#### 2. SearchController (`app/Http/Controllers/SearchController.php`)

Handles HTTP requests, validation, and response formatting. All endpoints support the full feature set.

### Performance Optimizations

1. **Parallel Corpus Search**: Each corpus is searched independently (potential for parallel execution)
2. **Early Threshold Filtering**: PostgreSQL vector similarity filtering happens at the database level
3. **Efficient Deduplication**: Single-pass algorithm using hash map
4. **Performance Logging**: Automatic logging of slow queries for optimization

### PostgreSQL Vector Search

The service uses pgvector's cosine distance operator (`<=>`) for similarity search:

```sql
SELECT *, 1 - (embedding <=> '[query_vector]') as similarity
FROM table
WHERE 1 - (embedding <=> '[query_vector]') >= threshold
ORDER BY similarity DESC
LIMIT n
```

## Usage Examples

### Basic Search
```bash
curl -X POST http://localhost/api/search \
  -H "Content-Type: application/json" \
  -d '{
    "query": "data protection GDPR",
    "limit": 10
  }'
```

### Filtered Search with Pagination
```bash
curl -X POST http://localhost/api/search \
  -H "Content-Type: application/json" \
  -d '{
    "query": "employment contract termination",
    "filters": {
      "jurisdiction": "EU",
      "date_from": "2018-01-01"
    },
    "page": 2,
    "per_page": 25,
    "sort_by": "date",
    "sort_order": "desc"
  }'
```

### Weighted Multi-Corpus Search
```bash
curl -X POST http://localhost/api/search \
  -H "Content-Type: application/json" \
  -d '{
    "query": "copyright infringement",
    "corpora": ["laws", "decisions"],
    "weights": {
      "laws": 2.0,
      "decisions": 1.0
    },
    "threshold": 0.75
  }'
```

### Search Specific Corpus
```bash
curl -X POST http://localhost/api/search/decisions \
  -H "Content-Type: application/json" \
  -d '{
    "query": "constitutional rights",
    "filters": {
      "court": "Supreme Court",
      "date_from": "2020-01-01"
    }
  }'
```

## Success Criteria (Sprint E1.1)

- ✅ Single API searches all corpora
- ✅ Results ranked by relevance with configurable weights
- ✅ Result deduplication based on content_hash
- ✅ Common search logic extracted to `performVectorSearch()`
- ✅ Filter mapping for jurisdiction, date_range, corpus_type
- ✅ Pagination and sorting support
- ✅ Performance monitoring with <2s response time target
- ✅ Comprehensive API with validation

## Future Enhancements

### Hybrid Search (E1.2)
- Combine vector similarity with full-text keyword search
- Configurable fusion algorithms (RRF, weighted sum)

### Citation-Enhanced Search (E1.3)
- Integrate with GraphRAG for citation context
- Include citing/cited documents in results
- Citation network visualization support

### Advanced Features
- Faceted search with aggregations
- Query expansion and synonyms
- ML-based result ranking
- Caching for common queries
- Async/background search for large result sets

## Testing

### Manual Testing
```bash
# Test basic search
php artisan tinker
>>> $service = app(\App\Services\UnifiedSearchService::class);
>>> $results = $service->search('contract law', ['limit' => 5]);
>>> print_r($results);
```

### Performance Testing
Monitor the `performance` section in API responses. Queries taking >2s are automatically logged.

### Integration Tests
TODO: Add PHPUnit tests for:
- Multi-corpus search
- Deduplication logic
- Pagination
- Sorting
- Filter application
- Performance metrics

## Troubleshooting

### Slow Queries
Check logs for queries >2s. Common causes:
- Missing indexes on filter columns
- Large result sets without proper limits
- Embedding generation bottleneck
- Network latency to OpenAI API

### No Results
- Check threshold (default 0.7 might be too strict)
- Verify filters aren't too restrictive
- Ensure embeddings exist in database
- Check query length and quality

### Duplicate Results
- Ensure `deduplicate: true` is set
- Verify `content_hash` is populated in database
- Check if same content exists across multiple corpora

## Related Documentation

- [Vector Store Services](./vector-store-services.md)
- [GraphRAG Integration](./graph-rag-integration.md)
- [API Reference](./api-reference.md)
- [Performance Tuning](./performance-tuning.md)

## Sprint Information

- **Sprint**: E1.1 - UnifiedSearchService Implementation
- **Milestone**: E - Advanced RAG & Search
- **Duration**: 3-4 days
- **Status**: ✅ Completed
- **Branch**: `claude/unified-search-service-011CUW49WZdnSfimYZTAso75`
