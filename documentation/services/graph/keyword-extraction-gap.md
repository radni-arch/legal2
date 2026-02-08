# Gap 2: Graph Keyword Extraction Improvements

## Problem Statement

The original keyword extraction in `GraphRagService` relied on a static dictionary of Croatian legal terms. This approach had several limitations:

1. **Single-word only**: Could not extract multi-word concepts like "izvršenje presude" or "ugovor o djelu"
2. **Static dictionary**: Couldn't surface emerging legal terminology not in the predefined list
3. **Shallow graph**: Limited keyword diversity led to a less connected and less useful knowledge graph

**Coverage**: ~60% (Gap 2)

## Solution Overview

The improved keyword extraction system now uses a **hybrid NLP approach** that combines:

1. **Multi-word phrase extraction (n-grams)**: Extracts bigrams and trigrams to capture legal concepts
2. **Statistical analysis (TF-IDF)**: Identifies important terms based on frequency and rarity
3. **Semantic analysis (OpenAI embeddings)**: Finds terms semantically similar to legal concepts
4. **Emerging terminology detection**: Surfaces new legal terms with high semantic similarity but not in dictionary
5. **Legal term boosting**: Prioritizes known Croatian legal terminology

## Key Improvements

### 1. Multi-Word Phrase Extraction

**Before**: Only single words like "ugovor", "presuda", "obveza"

**After**: Multi-word concepts like:
- "izvršenje presude" (execution of judgment)
- "ugovor o djelu" (work contract)
- "naknada štete" (damages/compensation)
- "pravna sigurnost" (legal certainty)
- "sudska praksa" (court practice)

**Implementation**: `extractNgrams()` method in `AdvancedKeywordExtractor`

```php
// Extract bigrams (2-word phrases)
if ($this->maxNgramSize >= 2) {
    for ($i = 0; $i < count($words) - 1; $i++) {
        $bigram = $words[$i] . ' ' . $words[$i + 1];
        // Apply filtering and scoring
    }
}

// Extract trigrams (3-word phrases)
if ($this->maxNgramSize >= 3) {
    for ($i = 0; $i < count($words) - 2; $i++) {
        $trigram = $words[$i] . ' ' . $words[$i + 1] . ' ' . $words[$i + 2];
        // Apply filtering and scoring
    }
}
```

### 2. Emerging Terminology Detection

**Before**: Only terms in the static dictionary were prioritized

**After**: Terms with high semantic similarity to legal concepts are boosted even if not in dictionary

**Implementation**: `boostEmergingTerms()` method

```php
protected function boostEmergingTerms(array $combinedScores, array $semanticScores): array
{
    foreach ($combinedScores as $term => $score) {
        // Boost terms NOT in dictionary but WITH high semantic similarity
        if (!isset($legalTerms[$term]) &&
            isset($semanticScores[$term]) &&
            $semanticScores[$term] >= $this->emergingTermThreshold) {

            $combinedScores[$term] *= 1.5; // 50% boost
        }
    }
}
```

### 3. Enhanced Legal Concept Library

**Before**: 10 single-word concepts

**After**: 28 concepts including multi-word phrases:
- Single words: zakon, ugovor, presuda, odluka, pravo, obveza, postupak, tužba, žalba, sud
- Multi-word: izvršenje presude, ugovor o djelu, parničko pravo, materijalno pravo, etc.

### 4. Phrase-Level Embeddings

The system now computes embeddings for entire phrases, not just individual words, enabling better semantic matching for complex legal concepts.

## Configuration

New configuration options in `config/keywords.php`:

```php
// N-gram (multi-word phrase) extraction
'extract_ngrams' => env('KEYWORDS_EXTRACT_NGRAMS', true),
'max_ngram_size' => env('KEYWORDS_MAX_NGRAM_SIZE', 3),
'ngram_min_score' => env('KEYWORDS_NGRAM_MIN_SCORE', 0.3),

// Emerging terminology detection
'emerging_term_threshold' => env('KEYWORDS_EMERGING_TERM_THRESHOLD', 0.7),
```

## Usage

The improvements are automatically used by `GraphRagService::extractAndLinkKeywords()`:

```php
// GraphRagService automatically uses the enhanced extractor
protected function extractAndLinkKeywords(string $nodeLabel, string $nodeId, string $content): void
{
    if ($this->advancedExtractor && config('keywords.use_hybrid', true)) {
        // Uses hybrid approach with n-grams and emerging term detection
        $keywords = $this->advancedExtractor->extract($content);
    } else {
        // Falls back to legacy extraction
        $keywords = $this->extractKeywords($content);
    }

    // Create keyword nodes and relationships in Neo4j
}
```

## Impact on Knowledge Graph

### Before
```
[Document] --HAS_KEYWORD--> [ugovor]
[Document] --HAS_KEYWORD--> [presuda]
[Document] --HAS_KEYWORD--> [naknada]
```

### After
```
[Document] --HAS_KEYWORD--> [ugovor]
[Document] --HAS_KEYWORD--> [ugovor o djelu]      ← multi-word
[Document] --HAS_KEYWORD--> [presuda]
[Document] --HAS_KEYWORD--> [izvršenje presude]   ← multi-word
[Document] --HAS_KEYWORD--> [naknada štete]       ← multi-word
[Document] --HAS_KEYWORD--> [pravna sigurnost]    ← emerging term
```

## Performance Considerations

1. **Caching**: Embeddings are cached for 30 days to minimize API calls
2. **Batching**: Words are processed in batches of 10 to respect rate limits
3. **Filtering**: N-grams must appear at least 2 times OR match legal patterns
4. **Fallback**: System falls back to TF-IDF when OpenAI API is unavailable

## Testing

To test the improved extraction:

```bash
php artisan benchmark:keywords --sample-size=10
```

To verify configuration:

```php
$extractor = app(AdvancedKeywordExtractor::class);
dd($extractor->getConfig());

// Output:
// [
//   'use_embeddings' => true,
//   'extract_ngrams' => true,
//   'max_ngram_size' => 3,
//   'emerging_term_threshold' => 0.7,
//   'legal_concepts_count' => 28,
//   ...
// ]
```

## Expected Coverage Improvement

- **Before**: ~60% coverage (Gap 2)
- **After**: ~90-95% coverage

The improved keyword extraction should:
1. Capture 2-3x more meaningful keywords per document
2. Create richer graph connections through multi-word concepts
3. Surface emerging legal terminology automatically
4. Enable better document similarity and retrieval

## Files Modified

1. `app/Services/AdvancedKeywordExtractor.php`
   - Added `extractNgrams()` method
   - Added `boostEmergingTerms()` method
   - Added `matchesLegalPattern()` method
   - Expanded legal concepts library
   - Updated `hybridExtraction()` to use n-grams

2. `config/keywords.php`
   - Added n-gram extraction settings
   - Added emerging term detection threshold

## Next Steps

1. Monitor keyword extraction quality in production
2. Adjust emerging term threshold based on results
3. Expand multi-word legal concept library based on usage
4. Consider adding domain-specific NER for entities (law names, case numbers)
