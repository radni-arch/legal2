# Hybrid Search Implementation - Sprint E1.2

## Overview

This document describes the implementation of the Hybrid Search feature (Sprint E1.2) for the AI Legal War Machine. The hybrid search combines three different search strategies to provide superior search results compared to pure vector search:

1. **Vector Similarity Search** (60% weight) - Semantic search using pgvector
2. **Full-Text Search** (30% weight) - Keyword-based search using PostgreSQL tsvector
3. **Citation-Based Search** (10% weight) - Legal citation detection and matching

Results from all three methods are merged using **Reciprocal Rank Fusion (RRF)** to produce a unified, ranked result set.

## Architecture

### High-Level Flow

```
User Query
    ↓
┌───────────────────────────────────────────────┐
│         UnifiedSearchService                  │
│                                               │
│  hybridSearch(query, options)                │
└───────────────────────────────────────────────┘
    ↓
┌───────────────────────────────────────────────┐
│     Three Parallel Search Strategies          │
│                                               │
│  ┌─────────────────────────────────────┐    │
│  │  1. Vector Search (60%)             │    │
│  │     - Generate embeddings           │    │
│  │     - Query pgvector                │    │
│  │     - Cosine similarity scoring     │    │
│  └─────────────────────────────────────┘    │
│                                               │
│  ┌─────────────────────────────────────┐    │
│  │  2. Full-Text Search (30%)          │    │
│  │     - Create tsquery                │    │
│  │     - Search tsvector columns       │    │
│  │     - ts_rank scoring               │    │
│  └─────────────────────────────────────┘    │
│                                               │
│  ┌─────────────────────────────────────┐    │
│  │  3. Citation Search (10%)           │    │
│  │     - Detect citations in query     │    │
│  │     - Search for citations in docs  │    │
│  │     - Match-based scoring           │    │
│  └─────────────────────────────────────┘    │
└───────────────────────────────────────────────┘
    ↓
┌───────────────────────────────────────────────┐
│  Reciprocal Rank Fusion (RRF)                │
│                                               │
│  score(doc) = Σ weight / (k + rank)          │
│  where k = 60                                 │
└───────────────────────────────────────────────┘
    ↓
┌───────────────────────────────────────────────┐
│  Post-Processing                              │
│  - Deduplication                              │
│  - Final ranking by RRF score                 │
│  - Pagination                                 │
└───────────────────────────────────────────────┘
    ↓
Ranked Results
```

## Database Changes

### Migration: `2025_10_26_160047_add_fulltext_search_columns.php`

This migration adds full-text search capabilities to the following tables:

#### Tables Modified
- `laws`
- `court_decision_documents`
- `cases_documents`

#### Changes per Table

1. **New Column: `content_tsv`**
   - Type: `tsvector`
   - Purpose: Stores preprocessed text for full-text search
   - Populated from: `title + content`
   - Text search configuration: `simple` (can be changed to Croatian-specific config)

2. **GIN Index: `{table}_content_tsv_idx`**
   - Type: GIN (Generalized Inverted Index)
   - Purpose: Fast full-text search queries
   - Indexed column: `content_tsv`

3. **Automatic Trigger: `tsvectorupdate`**
   - Fires on: `INSERT` or `UPDATE`
   - Function: `{table}_tsvector_update_trigger()`
   - Purpose: Automatically updates `content_tsv` when title or content changes
   - Ensures tsvector column stays synchronized

#### Migration Safety
- PostgreSQL-only (gracefully skips on other databases)
- Handles existing data with initial population
- Includes rollback support in `down()` method
- Error handling for testing environments

## Code Changes

### Modified: `app/Services/UnifiedSearchService.php`

#### New Dependencies
```php
public function __construct(
    protected OpenAIService $openai,
    protected HrLegalCitationsDetector $citationDetector  // NEW
) {}
```

#### New Public Method

##### `hybridSearch(string $query, array $options): array`

Main entry point for hybrid search functionality.

**Parameters:**
- `$query` (string): The search query text
- `$options` (array): Same options as `search()` method
  - `corpora`: Array of corpus types ['laws', 'decisions', 'cases']
  - `filters`: Filters for jurisdiction, date range, etc.
  - `limit`: Max results per corpus (default: 10)
  - `threshold`: Minimum similarity score (default: 0.7)
  - `page`, `per_page`: Pagination options
  - `deduplicate`: Enable deduplication (default: true)

**Returns:**
```php
[
    'query' => string,
    'search_type' => 'hybrid',
    'total_results' => int,
    'returned_results' => int,
    'deduplicated_count' => int,
    'corpora' => array,
    'results' => array,
    'filters' => array,
    'pagination' => [
        'page' => int,
        'per_page' => int,
        'offset' => int,
        'total_pages' => int,
    ],
    'performance' => [
        'total_time' => float,
        'vector_time' => float,
        'fulltext_time' => float,
        'citation_time' => float,
    ],
    'result_counts' => [
        'vector' => int,
        'fulltext' => int,
        'citation' => int,
    ],
]
```

#### New Protected Methods

##### Vector Search

**`getVectorSearchResults(string $query, array $options, int $limit): array`**
- Wrapper around existing `search()` method
- Returns vector search results for RRF merging

##### Full-Text Search

**`getFullTextSearchResults(string $query, array $corpora, array $filters, int $limit): array`**
- Orchestrates full-text search across all corpora
- Returns combined full-text results

**`fullTextSearchLaws(string $query, int $limit, array $filters): array`**
- Full-text search on `laws` table
- Uses PostgreSQL `ts_rank()` for relevance scoring
- Error handling with logging
- Returns empty array if query is invalid or error occurs

**`fullTextSearchDecisions(string $query, int $limit, array $filters): array`**
- Full-text search on `court_decision_documents` table
- Joins with `court_decisions` for metadata
- Same error handling as laws search

**`fullTextSearchCases(string $query, int $limit, array $filters): array`**
- Full-text search on `cases_documents` table
- Same error handling as laws search

##### Citation-Based Search

**`getCitationSearchResults(string $query, array $corpora, array $filters, int $limit): array`**
- Detects citations in query using `HrLegalCitationsDetector`
- Orchestrates citation search across all corpora
- Returns empty if no citations detected

**`citationSearchLaws(array $citations, int $limit, array $filters): array`**
- Searches laws for detected citations
- Uses ILIKE pattern matching with escaped patterns
- Scores results by citation match count
- Error handling with logging

**`citationSearchDecisions(array $citations, int $limit, array $filters): array`**
- Searches court decisions for detected citations
- Same approach as laws citation search

**`citationSearchCases(array $citations, int $limit, array $filters): array`**
- Searches case documents for detected citations
- Same approach as laws citation search

##### RRF and Utilities

**`applyRRF(array $vectorResults, array $fulltextResults, array $citationResults, array $weights): array`**
- Implements Reciprocal Rank Fusion algorithm
- Formula: `score(doc) = Σ weight / (k + rank)` where k=60
- Merges results from all three search methods
- Tracks which methods contributed to each result
- Returns merged results with RRF scores

**`getUniqueDocumentId(array $result): string`**
- Creates unique identifier: `{type}_{id}`
- Used for deduplication in RRF

**`createTsQuery(string $query): string`**
- Converts user query to PostgreSQL tsquery format
- Filters out special characters
- Removes words shorter than 2 characters
- Joins words with OR operator (`|`) for broader matching
- Returns empty string if no valid words

**`buildCitationSearchPatterns(array $citations): array`**
- Extracts citation text from detection results
- Handles both array and string formats
- Returns unique patterns for searching

**`calculateCitationScore(string $content, array $citations): float`**
- Calculates score based on citation matches in content
- Normalizes to 0-1 range
- Formula: `matchCount / totalPatterns`

**`applyFiltersToQuery($queryBuilder, array $filters, array $filterMappings): void`**
- Applies filters consistently across all search types
- Handles LIKE, >=, <=, and = operators
- Escapes LIKE special characters for security

## Search Strategy Details

### 1. Vector Similarity Search (60%)

**How it works:**
1. Convert query to embedding using OpenAI embeddings API
2. Query pgvector with cosine similarity (`<=>` operator)
3. Apply threshold filter (default: 0.7)
4. Apply user filters (jurisdiction, date, etc.)
5. Return top N results ordered by similarity

**Strengths:**
- Understands semantic meaning
- Finds conceptually related documents
- Language-agnostic (with multilingual models)
- Good for complex legal concepts

**Weaknesses:**
- May miss exact keyword matches
- Embedding generation has latency
- Requires pre-computed embeddings

### 2. Full-Text Search (30%)

**How it works:**
1. Create tsquery from user query (OR of all words)
2. Match against tsvector column using `@@` operator
3. Rank results using `ts_rank()`
4. Apply user filters
5. Return top N results ordered by rank

**Strengths:**
- Fast keyword matching
- Good for exact term searches
- No external API calls needed
- Works well for citations, case numbers

**Weaknesses:**
- No semantic understanding
- Sensitive to exact word forms
- May miss synonyms or related concepts

**PostgreSQL Operations:**
```sql
-- Create tsquery (done in createTsQuery())
'zakon obveznim odnosima' -> 'zakon | obveznim | odnosima'

-- Search and rank
WHERE content_tsv @@ to_tsquery('simple', 'zakon | obveznim | odnosima')
ORDER BY ts_rank(content_tsv, to_tsquery('simple', 'zakon | obveznim | odnosima')) DESC
```

### 3. Citation-Based Search (10%)

**How it works:**
1. Detect citations in query using `HrLegalCitationsDetector`
   - Statute citations (e.g., "čl. 350")
   - Narodne Novine references (e.g., "NN 123/45")
   - Case numbers
   - ECLI identifiers
   - Dates
2. Search documents for detected citation patterns
3. Score by number of matching citations
4. Apply user filters
5. Return top N results

**Strengths:**
- Highly accurate for citation-based queries
- Leverages existing citation detection
- Important for legal research workflow
- Finds citing/cited documents

**Weaknesses:**
- Only works when citations are present
- Limited to known citation formats
- Lower weight (10%) means less impact

### 4. Reciprocal Rank Fusion (RRF)

**Algorithm:**
```php
// For each document appearing in any result set:
$rrf_score = 0;

// Add contribution from vector search
if (document in vectorResults at rank R) {
    $rrf_score += 0.60 / (60 + R + 1);
}

// Add contribution from fulltext search
if (document in fulltextResults at rank R) {
    $rrf_score += 0.30 / (60 + R + 1);
}

// Add contribution from citation search
if (document in citationResults at rank R) {
    $rrf_score += 0.10 / (60 + R + 1);
}

// Final score is sum of all contributions
```

**Why RRF?**
- Doesn't require normalizing scores across methods
- Rank-based (order matters, not absolute scores)
- Proven effective for multi-strategy search
- k=60 reduces impact of high-ranking outliers

**Why these weights?**
- Vector (60%): Primary search strategy, best semantic understanding
- Full-text (30%): Important for keyword precision
- Citation (10%): Specialized, only works when citations present

## Performance Optimizations

### Database Level
1. **GIN Indexes** on tsvector columns for O(log n) full-text search
2. **IVFFlat Indexes** on embedding columns for fast vector search
3. **Automatic Triggers** prevent manual tsvector updates
4. **Batch Operations** fetch all corpora results in parallel

### Application Level
1. **Parallel Execution** - All three search methods run concurrently
2. **Early Returns** - Invalid queries return immediately
3. **Result Limits** - Fetch 3x limit for better RRF ranking
4. **Deduplication** - O(n) using hash map instead of array filtering
5. **Error Handling** - Failed searches don't block other methods

### Monitoring
- Performance logging for queries >2 seconds
- Detailed timing breakdown per search method
- Result count tracking
- Error logging with context

## Error Handling

### Graceful Degradation
- If vector search fails → continue with full-text and citation
- If full-text search fails → continue with vector and citation
- If citation search fails → continue with vector and full-text
- Empty results from one method don't prevent others

### Error Logging
All errors are logged with:
- Error message
- Search query or patterns
- Table name
- Stack trace (automatic)

### Database Errors
- PostgreSQL errors (syntax, connection) caught and logged
- Non-PostgreSQL databases skip full-text search gracefully
- Migration handles missing extensions

## Usage Examples

### Basic Hybrid Search

```php
use App\Services\UnifiedSearchService;

$searchService = app(UnifiedSearchService::class);

// Simple search across all corpora
$results = $searchService->hybridSearch(
    query: 'zakon o obveznim odnosima čl. 350',
    options: []
);

echo "Found {$results['total_results']} results in {$results['performance']['total_time']}s\n";
foreach ($results['results'] as $result) {
    echo "- {$result['title']} (score: {$result['score']})\n";
    echo "  Sources: " . implode(', ', $result['rrf_sources']) . "\n";
}
```

### Filtered Search

```php
// Search only court decisions from Croatian jurisdiction
$results = $searchService->hybridSearch(
    query: 'povreda ugovora',
    options: [
        'corpora' => ['decisions'],
        'filters' => [
            'jurisdiction' => 'HR',
            'date_from' => '2020-01-01',
            'date_to' => '2023-12-31',
        ],
        'limit' => 20,
    ]
);
```

### Pagination

```php
// Get page 2 with 15 results per page
$results = $searchService->hybridSearch(
    query: 'odgovornost poslodavca',
    options: [
        'page' => 2,
        'per_page' => 15,
    ]
);

echo "Page {$results['pagination']['page']} of {$results['pagination']['total_pages']}\n";
```

### Performance Analysis

```php
$results = $searchService->hybridSearch(
    query: 'test query',
    options: ['limit' => 10]
);

// Analyze which search methods contributed
$performance = $results['performance'];
echo "Vector search: {$performance['vector_time']}s ({$results['result_counts']['vector']} results)\n";
echo "Fulltext search: {$performance['fulltext_time']}s ({$results['result_counts']['fulltext']} results)\n";
echo "Citation search: {$performance['citation_time']}s ({$results['result_counts']['citation']} results)\n";

// See which methods matched each result
foreach ($results['results'] as $result) {
    echo "{$result['title']}: " . implode(' + ', $result['rrf_sources']) . "\n";
}
```

## Testing Recommendations

### Migration Testing

```bash
# Run migration
php artisan migrate

# Verify columns exist
psql -d your_database -c "\d laws"
# Should show content_tsv column

# Verify indexes exist
psql -d your_database -c "\di"
# Should show laws_content_tsv_idx, etc.

# Verify triggers exist
psql -d your_database -c "\dy"
# Should show tsvectorupdate triggers

# Test trigger functionality
psql -d your_database -c "
UPDATE laws SET title = 'Test Update' WHERE id = (SELECT id FROM laws LIMIT 1);
SELECT title, content_tsv FROM laws WHERE title = 'Test Update';
"
# content_tsv should be updated automatically
```

### Functional Testing

#### Test 1: Vector-only Match
```php
// Query with no exact keywords but semantic similarity
$results = $searchService->hybridSearch(
    query: 'employee obligations', // English, semantic
    options: ['corpora' => ['laws']]
);
// Expect: Results primarily from vector search
// rrf_sources should include 'vector'
```

#### Test 2: Full-text-only Match
```php
// Query with exact keywords
$results = $searchService->hybridSearch(
    query: 'NN 53/91', // Exact Narodne Novine reference
    options: ['corpora' => ['laws']]
);
// Expect: Results primarily from full-text search
// rrf_sources should include 'fulltext'
```

#### Test 3: Citation-only Match
```php
// Query with legal citation
$results = $searchService->hybridSearch(
    query: 'članak 350 ZOO', // Article 350 reference
    options: ['corpora' => ['decisions']]
);
// Expect: Results primarily from citation search
// rrf_sources should include 'citation'
```

#### Test 4: Multi-method Match
```php
// Query that should match all methods
$results = $searchService->hybridSearch(
    query: 'zakon o obveznim odnosima članak 350',
    options: ['corpora' => ['laws', 'decisions']]
);
// Expect: Results from multiple methods
// rrf_sources should include multiple values
// Top results should have contributions from multiple methods
```

#### Test 5: Empty/Invalid Query
```php
$results = $searchService->hybridSearch(
    query: '!!!', // Only special characters
    options: []
);
// Expect: Empty results or graceful handling
// Should not throw exception
```

### Performance Testing

#### Measure vs Pure Vector Search
```php
// Pure vector search
$start = microtime(true);
$vectorResults = $searchService->search($query, $options);
$vectorTime = microtime(true) - $start;

// Hybrid search
$start = microtime(true);
$hybridResults = $searchService->hybridSearch($query, $options);
$hybridTime = microtime(true) - $start;

echo "Vector: {$vectorTime}s\n";
echo "Hybrid: {$hybridTime}s\n";
echo "Overhead: " . ($hybridTime - $vectorTime) . "s\n";
```

#### Measure Precision/Recall

Create a test set of queries with known relevant documents:

```php
$testQueries = [
    [
        'query' => 'zakon o radu',
        'relevant_docs' => ['law_123', 'law_456', 'decision_789'],
    ],
    // ... more test cases
];

foreach ($testQueries as $test) {
    $vectorResults = $searchService->search($test['query']);
    $hybridResults = $searchService->hybridSearch($test['query']);

    $vectorPrecision = calculatePrecision($vectorResults, $test['relevant_docs']);
    $hybridPrecision = calculatePrecision($hybridResults, $test['relevant_docs']);

    echo "Vector Precision: {$vectorPrecision}\n";
    echo "Hybrid Precision: {$hybridPrecision}\n";
    echo "Improvement: " . (($hybridPrecision - $vectorPrecision) / $vectorPrecision * 100) . "%\n";
}

// Target: 15% improvement in precision/recall
```

## Troubleshooting

### Issue: Migration fails with "extension does not exist"

**Cause:** PostgreSQL `vector` extension not installed

**Solution:**
```sql
-- As superuser
CREATE EXTENSION IF NOT EXISTS vector;
```

### Issue: Full-text search returns no results

**Possible Causes:**
1. tsvector column not populated
2. Wrong text search configuration
3. Query words too short (< 2 chars)

**Debug:**
```sql
-- Check if tsvector is populated
SELECT id, title, content_tsv FROM laws LIMIT 5;

-- Test tsquery creation
SELECT to_tsquery('simple', 'zakon | obveznim');

-- Test direct match
SELECT id, title, ts_rank(content_tsv, to_tsquery('simple', 'zakon')) as rank
FROM laws
WHERE content_tsv @@ to_tsquery('simple', 'zakon')
ORDER BY rank DESC
LIMIT 10;
```

### Issue: Slow hybrid search performance

**Possible Causes:**
1. Missing indexes
2. Large result sets
3. Slow embedding generation

**Solutions:**
```sql
-- Verify indexes exist
\di laws_content_tsv_idx
\di laws_embedding_idx

-- Check index usage
EXPLAIN ANALYZE
SELECT * FROM laws
WHERE content_tsv @@ to_tsquery('simple', 'test');

-- Reduce limit if too many results
$options['limit'] = 5; // Instead of 10
```

**Application-level:**
```php
// Cache embeddings for common queries
$cachedEmbedding = Cache::remember("embed_{$query}", 3600, function() use ($query) {
    return $this->embedQuery($query);
});
```

### Issue: Citation search not finding results

**Possible Causes:**
1. Citations not in recognized format
2. HrLegalCitationsDetector not detecting citations
3. Documents don't contain the citations

**Debug:**
```php
// Check what citations are detected
$citations = $citationDetector->detectAll($query);
var_dump($citations);

// Check if patterns are built correctly
$patterns = $this->buildCitationSearchPatterns($citations);
var_dump($patterns);

// Test direct SQL search
$pattern = '%čl. 350%';
$results = DB::table('laws')
    ->where('content', 'ILIKE', $pattern)
    ->limit(10)
    ->get();
```

## Future Enhancements

### Short-term (Next Sprint)
1. **Croatian Text Search Configuration**
   - Replace 'simple' with Croatian stemming
   - Better handling of Croatian language specifics

2. **Query Highlighting**
   - Use `ts_headline()` for result snippets
   - Highlight matched terms in full-text results
   - Highlight detected citations

3. **Configurable Weights**
   - Allow users to adjust RRF weights
   - A/B testing different weight configurations
   - Per-query weight optimization

### Medium-term
1. **Caching Layer**
   - Cache embeddings for common queries
   - Cache full-text results
   - Redis-based distributed cache

2. **Result Diversity**
   - Ensure results from different corpora
   - Prevent one corpus from dominating
   - Maximal Marginal Relevance (MMR)

3. **Advanced Citation Graph**
   - Integration with GraphRagService
   - Citation network traversal
   - "Cited by" and "Cites" relationships

### Long-term
1. **Machine Learning Re-ranking**
   - Learn optimal weights from user interactions
   - Click-through rate tracking
   - Personalized search ranking

2. **Multi-language Support**
   - Language detection in query
   - Language-specific text search configs
   - Cross-language search

3. **Faceted Search**
   - Category-based filtering
   - Date range facets
   - Court/jurisdiction facets

## Success Metrics

### Target (from Sprint E1.2)
- **15% improvement** in precision/recall over pure vector search

### How to Measure
1. Create test set of 50-100 legal queries
2. For each query, identify relevant documents (gold standard)
3. Run both pure vector search and hybrid search
4. Calculate precision and recall for both
5. Compare averages

### Additional Metrics
- **Query Performance**: Should be <2 seconds for 90% of queries
- **Error Rate**: <1% of searches should fail
- **Coverage**: All three methods should contribute to results
- **User Satisfaction**: Track user engagement with results

## Maintenance

### Regular Tasks
1. **Monitor Performance Logs**
   - Review slow query logs weekly
   - Identify problematic queries
   - Optimize as needed

2. **Index Maintenance**
   - PostgreSQL VACUUM on tables with tsvector
   - Rebuild vector indexes if needed
   - Monitor index bloat

3. **Update Dependencies**
   - Keep OpenAI embeddings model current
   - Update HrLegalCitationsDetector patterns
   - Review PostgreSQL version compatibility

### When to Reindex
- After bulk data imports
- After significant schema changes
- If search quality degrades
- After PostgreSQL version upgrade

```sql
-- Reindex tsvector columns
REINDEX INDEX laws_content_tsv_idx;
REINDEX INDEX court_decision_documents_content_tsv_idx;
REINDEX INDEX cases_documents_content_tsv_idx;

-- Repopulate tsvector (if needed)
UPDATE laws SET content_tsv = to_tsvector('simple', COALESCE(title, '') || ' ' || COALESCE(content, ''));
```

## References

### Internal Documentation
- Sprint E1.1: Unified Search Service
- HrLegalCitationsDetector Implementation
- Database Schema Documentation

### External Resources
- [PostgreSQL Full-Text Search](https://www.postgresql.org/docs/current/textsearch.html)
- [pgvector Documentation](https://github.com/pgvector/pgvector)
- [Reciprocal Rank Fusion Paper](https://plg.uwaterloo.ca/~gvcormac/cormacksigir09-rrf.pdf)
- [OpenAI Embeddings](https://platform.openai.com/docs/guides/embeddings)

## Changelog

### Version 1.0 (2025-10-26)
- Initial implementation of hybrid search
- Database migration for tsvector columns
- RRF implementation with 60/30/10 weights
- Full error handling and logging
- Comprehensive documentation

### Bug Fixes (2025-10-26 - Iteration 2)
- Fixed SQL binding issues in full-text search methods
- Added empty query validation
- Improved error handling with try-catch blocks
- Added LIKE pattern escaping for citation search
- Enhanced createTsQuery with minimum word length filter
- Added detailed error logging for all search methods

## Support

For questions or issues:
1. Check this documentation first
2. Review error logs in `storage/logs/laravel.log`
3. Run database diagnostics (see Troubleshooting)
4. Contact the development team with:
   - Query that caused the issue
   - Error messages
   - Expected vs actual behavior
