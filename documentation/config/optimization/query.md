# Query Optimization for Legal Search

## Overview

The AI Legal War Machine uses intelligent query optimization to improve search quality and relevance through two complementary systems:

1. **Query Rewriting**: Transforms user queries into optimized search variants
2. **Context Compression**: Compresses retrieved documents to fit more context into LLM prompts

## Query Rewriting

### How It Works

User queries are rewritten into 3 optimized variants using GPT-4o-mini:

1. **Specific**: Exact legal terms, law numbers, article references
2. **Broad**: Related concepts, synonyms, adjacent topics
3. **Structured**: Formal Croatian legal terminology

All 3 variants are searched, results are merged, deduplicated, and sorted by relevance score.

### Example Transformations

**Example 1: Employment Termination**

**User Query:**
```
Can employer fire me without notice?
```

**Rewritten Variants:**
```
1. Specific:    "nezakonit otkaz bez otkaznog roka Zakon o radu članak 93"
2. Broad:       "prestanak ugovora o radu otkazni rok zaštita radnika otkaz"
3. Structured:  "raskid ugovora o radu otkazni rok zaposlenika Zakon o radu"
```

**Example 2: Contract Breach**

**User Query:**
```
I signed contract but company didn't deliver
```

**Rewritten Variants:**
```
1. Specific:    "neispunjenje ugovora povreda ugovorne obveze Zakon o obveznim odnosima"
2. Broad:       "ugovor obveza isporuka naknada štete ugovorna odgovornost"
3. Structured:  "povreda ugovorne obveze neispunjenje obveze ugovornih strana"
```

### Usage

#### Via Search Services

All three search services support query rewriting via `searchWithRewriting()` method:

```php
use App\Services\LawSearchService;
use App\Services\DecisionSearchService;
use App\Services\CaseSearchService;

// Law search with rewriting
$lawSearch = app(LawSearchService::class);
$results = $lawSearch->searchWithRewriting('Can employer fire me?', [
    'search_type' => 'hybrid',  // vector, keyword, or hybrid
    'limit' => 10,
    'language' => 'hr',         // hr or en
]);

// Decision search with rewriting
$decisionSearch = app(DecisionSearchService::class);
$results = $decisionSearch->searchWithRewriting('ugovorna odgovornost', [
    'search_type' => 'hybrid',
    'limit' => 20,
]);

// Case search with rewriting
$caseSearch = app(CaseSearchService::class);
$results = $caseSearch->searchWithRewriting('potraživanje naknade', [
    'search_type' => 'vector',
    'limit' => 15,
]);
```

**Response Format:**
```php
[
    'success' => true,
    'data' => [...],                    // Deduplicated results
    'search_type' => 'rewritten',
    'count' => 10,
    'variants_used' => [                // All 3 query variants
        'specific variant',
        'broad variant',
        'structured variant'
    ],
]
```

#### Direct Rewriter Usage

For advanced use cases, use `QueryRewriter` directly:

```php
use App\Services\QueryRewriter;

$rewriter = app(QueryRewriter::class);

// Get all 3 variants
$variants = $rewriter->rewrite('employment termination', 'hr');
// Returns: [
//   'nezakonit otkaz članak 93 Zakon o radu',
//   'prestanak radnog odnosa otkaz zaštita radnika',
//   'raskid ugovora o radu otkazni rok'
// ]

// Get best variant only (specific)
$best = $rewriter->rewriteBest('employment termination');
// Returns: 'nezakonit otkaz članak 93 Zakon o radu'

// Analyze query intent
$intent = $rewriter->analyzeIntent('Can my boss fire me?');
// Returns: {
//   'domain' => 'employment',
//   'law_references' => ['Zakon o radu'],
//   'query_type' => 'advisory',
//   'entities' => ['employer', 'employee']
// }
```

### Configuration

**Caching:**
- Query rewrites are cached for 24 hours
- Cache key: `query_rewrite:{md5(query+language)}`
- Reduces LLM costs for repeated queries

**Language Support:**
- Croatian (hr) - default
- English (en)

**LLM Settings:**
- Model: GPT-4o-mini
- Temperature: 0.3 (consistent rewrites)
- Response format: JSON object

**Fallback Behavior:**
- On LLM error: Returns original query 3 times
- On malformed JSON: Returns original query 3 times
- System never crashes on rewriting failures

### Benefits

1. **Improved Recall**: 3 variants capture more relevant documents
2. **Better Precision**: Specific variant targets exact legal terms
3. **Broader Coverage**: Broad variant expands to related concepts
4. **Formal Language**: Structured variant uses official terminology
5. **Deduplication**: Prevents showing same document 3 times
6. **Transparent**: Returns variants used for debugging

## Context Compression

### How It Works

Search results are intelligently compressed to fit within LLM token budgets while preserving legal relevance.

**Default Token Budget**: 4000 tokens
**Token Estimation**: 1 token ≈ 4 characters

### Compression Strategies

**Strategy 1: Keep Short Content** (<200 tokens)
- No compression needed
- Preserves full content as-is

**Strategy 2: Extract Relevant Sentences** (200-300 tokens)
- Score sentences by keyword presence
- Boost for legal patterns (članak, NN numbers, Zakon o)
- Take top 50% of sentences
- Re-sort by original order to preserve flow

**Strategy 3: Truncate with Citations** (>300 tokens)
- Truncate to target length
- End at sentence boundary
- Append up to 3 citations
- Add [...] ellipsis

### Legal Pattern Recognition

**Article References:**
- `čl. 93`, `članak 123`
- Scoring boost: +5 points

**Law Numbers:**
- `NN 93/14`, `NN 152/08`
- Scoring boost: +5 points

**Law Names:**
- `Zakon o radu`, `Zakon o obveznim odnosima`
- Scoring boost: +3 points

**Keyword Matches:**
- From title, law_number, tags
- Scoring boost: +10 points each

### Usage

```php
use App\Services\ContextCompressor;

$compressor = app(ContextCompressor::class);

// Compress search results
$results = $lawSearch->search('otkaz bez razloga');
$compressed = $compressor->compress($results, 3000);

// Each compressed result contains:
[
    'content' => 'Compressed text...',        // Compressed version
    'full_content' => 'Original text...',     // Original preserved
    'compressed' => true,                      // Compression flag
    'title' => '...',
    'law_number' => '...',
    // ... other metadata
]

// Calculate compression ratio
$ratio = $compressor->calculateCompressionRatio($results, $compressed);
// e.g., 0.35 = 35% of original size (65% compression)
```

### Configuration

**Token Budget:**
```php
// Use default (4000 tokens)
$compressed = $compressor->compress($results);

// Custom budget
$compressed = $compressor->compress($results, 2000);
```

**Compression Thresholds:**
- **<200 tokens**: No compression
- **200-300 tokens**: Relevant sentence extraction
- **>300 tokens**: Truncation with citations

### Benefits

1. **More Context**: Fit 2-3x more documents in same token budget
2. **Relevance**: Prioritizes legally relevant sentences
3. **Citations**: Preserves important legal references
4. **Readability**: Maintains original sentence order
5. **Transparency**: Keeps original content accessible
6. **Measurable**: Compression ratio provides metrics

## Combined Usage: Rewriting + Compression

For optimal search quality, combine both techniques:

```php
use App\Services\LawSearchService;
use App\Services\ContextCompressor;

$lawSearch = app(LawSearchService::class);
$compressor = app(ContextCompressor::class);

// Step 1: Search with query rewriting
$results = $lawSearch->searchWithRewriting('Can employer fire me?', [
    'search_type' => 'hybrid',
    'limit' => 20,  // Get more results
    'language' => 'hr',
]);

// Step 2: Compress results to fit LLM context
$compressed = $compressor->compress($results['data'], 4000);

// Step 3: Use compressed results in LLM prompt
$context = implode("\n\n", array_column($compressed, 'content'));
$prompt = "Based on the following legal context:\n\n{$context}\n\nAnswer: Can my employer fire me without notice?";
```

## Best Practices

### Query Rewriting

**When to Use:**
- ✅ User enters natural language queries
- ✅ Informal or conversational queries
- ✅ Queries in English (Croatian system)
- ✅ When recall is more important than speed

**When NOT to Use:**
- ❌ Already optimized legal terminology
- ❌ Specific law number lookups (e.g., "NN 93/14")
- ❌ Real-time chat applications (adds ~500ms latency)

**Tips:**
- Use `searchWithRewriting()` for end-user queries
- Use standard `search()` for agent/system queries
- Monitor `variants_used` field for debugging
- Check logs for rewriting performance

### Context Compression

**When to Use:**
- ✅ Sending many search results to LLM
- ✅ Context window approaching limits
- ✅ Long legal documents (>1000 tokens)
- ✅ When cost optimization matters

**When NOT to Use:**
- ❌ Already short results (<200 tokens each)
- ❌ When full text is legally required
- ❌ Detailed analysis tasks requiring complete content

**Tips:**
- Start with 4000 token budget, adjust based on needs
- Monitor compression ratio to ensure quality
- Use `full_content` field when detail is needed
- Preserve originals for follow-up queries

### Performance

**Query Rewriting:**
- Adds ~500ms per query (LLM call)
- Cached for 24 hours (subsequent queries instant)
- 3x more search calls (but parallel execution)

**Context Compression:**
- Fast (<50ms for typical results)
- No external API calls
- CPU-bound (sentence splitting, scoring)

**Combined:**
- ~550ms first query (rewriting + compression)
- ~50ms cached queries (compression only)
- Enables 2-3x more context for same LLM cost

## Cost Analysis

### Query Rewriting

**Model**: GPT-4o-mini ($0.15 per 1M tokens)

**Per Query Estimate:**
- Input: ~500 tokens (system prompt + user query)
- Output: ~100 tokens (3 variants)
- Cost: ~$0.00009 per query
- With caching (24h): ~$0.00009 per unique query per day

**Monthly Usage (1000 users, 10 queries/user/day):**
- Total queries: 300,000/month
- Unique queries (50% cache hit): 150,000/month
- Monthly cost: ~$13.50

### Context Compression

**Cost**: $0 (no API calls)

**Computation:**
- CPU-bound processing only
- Negligible server cost (<1ms CPU time)

### Combined Cost

For typical usage (1000 users, 10 queries/day):
- Query rewriting: ~$13.50/month
- Context compression: $0/month
- **Total**: ~$13.50/month

**ROI**: Improved search quality increases user satisfaction and reduces support costs.

## Monitoring & Debugging

### Logging

Both services log extensively:

**Query Rewriting:**
```php
Log::info('Query rewritten successfully', [
    'original' => 'Can employer fire me?',
    'variants' => [...],
]);

Log::info('Query rewriting results', [
    'total_before_dedup' => 45,
    'total_after_dedup' => 18,
    'final_count' => 10,
]);
```

**Context Compression:**
```php
// Calculate and log compression metrics
$ratio = $compressor->calculateCompressionRatio($original, $compressed);
Log::info('Context compressed', [
    'original_size' => 12000,
    'compressed_size' => 4200,
    'ratio' => 0.35,
    'items' => 15,
]);
```

### Debugging Tips

**Query Rewriting Issues:**
1. Check `variants_used` in response
2. Verify language parameter ('hr' vs 'en')
3. Review OpenAI logs for API errors
4. Clear cache to test fresh rewrites

**Compression Issues:**
1. Check compression ratio (should be 0.3-0.6)
2. Verify `compressed` flag in results
3. Compare `content` vs `full_content`
4. Adjust token budget if needed

## Related Documentation

- [RAG Guide](RAG_GUIDE.md) - Full RAG system overview
- [Autonomous Agent](AUTONOMOUS_AGENT_README.md) - Agent-based research
- [Autonomous Decision Discovery](AUTONOMOUS_DECISION_DISCOVERY.md) - Decision ingestion

## Future Enhancements

### Query Rewriting
1. **Query Type Detection**: Route to specialized rewrites (case law vs statutes)
2. **Multi-Language Support**: Add more languages beyond Croatian/English
3. **Custom Templates**: Domain-specific rewriting patterns
4. **A/B Testing**: Compare rewritten vs original query performance

### Context Compression
1. **LLM-Based Compression**: Use GPT-4o-mini for intelligent summarization
2. **Semantic Deduplication**: Remove semantically similar sentences
3. **Citation Ranking**: Prioritize more authoritative citations
4. **Custom Scoring**: Allow user-defined relevance weights

## Support

For issues or questions:
- Check logs: `storage/logs/laravel.log`
- Review service code: `app/Services/QueryRewriter.php`, `app/Services/ContextCompressor.php`
- Run tests: `php artisan test --filter=QueryRewriter`
- Open GitHub issue with query examples and logs
