# ContextCompressor Test Suite

## Overview
This test suite provides comprehensive coverage for the `ContextCompressor` service, which compresses retrieved legal documents to fit within LLM token budgets while preserving the most relevant content.

## Test Results
✅ **47 tests passing**
✅ **69 assertions**
✅ **No external dependencies** (pure unit tests)

## Test Coverage

### Basic Compression (5 tests)
- Compression to fit token budget
- Default token budget usage (4000 tokens)
- Empty results handling
- Results without content field
- Results with empty content

### Short Content Handling (2 tests)
- Content ≤200 tokens kept as-is
- No compression flag for short content

### Long Content Compression (4 tests)
- Long content compression
- Original content preservation in `full_content` field
- Compressed flag marking
- Ellipsis suffix addition

### Token Budget Management (3 tests)
- Stop processing when budget exceeded
- Token tracking across multiple results
- Maximum 300 tokens allocated per compressed item

### Legal Keywords Extraction (5 tests)
- Keywords from title
- Keywords from law_number
- Keywords from tags array
- Missing metadata field handling
- Case-insensitive keyword matching

### Relevant Sentences Extraction (8 tests)
- Sentence scoring by keyword matches
- Sentence boosting for article citations (članak, čl.)
- Sentence boosting for NN references (NN 123/2024)
- Sentence boosting for "Zakon o" pattern
- Sentence order preservation after scoring
- Top 50% sentence selection
- Minimum 3 sentences kept
- Content without sentence delimiters handling
- Empty keywords graceful handling

### Citation Extraction (5 tests)
- NN law number extraction (NN 123/2024)
- Article reference extraction (članak, čl.)
- Citation limit to 3
- Content without citations handling
- Citation deduplication

### Truncation (3 tests)
- Truncation at sentence boundary
- Text without periods handling
- Space reservation for citations and ellipsis

### Compression Ratio Calculation (6 tests)
- Basic ratio calculation
- Multiple results ratio
- Identical content (ratio = 1.0)
- Empty original (ratio = 1.0)
- Missing content handling
- Mixed content ratio

### Integration Tests (6 tests)
- Realistic legal document compression
- Mixed length results processing
- Metadata preservation
- Croatian diacritics handling (đ, č, ć, š, ž)
- Very long content compression
- Reasonable compression size

## Service Features

### Token Estimation
- **Formula**: 1 token ≈ 4 characters
- **Short threshold**: ≤200 tokens (≤800 chars) kept as-is
- **Compression target**: Max 300 tokens per item

### Compression Strategy

**Strategy 1: Relevant Sentence Extraction**
- Extract legal keywords from metadata (title, law_number, tags)
- Score sentences by keyword matches (+10 per match)
- Boost legal patterns:
  - Article citations (`članak`, `čl.`) +5
  - NN references (`NN 123/2024`) +5
  - Law names (`Zakon o`) +3
- Select top 50% of sentences (minimum 3)
- Preserve original sentence order

**Strategy 2: Truncation with Citations**
- Truncate to target character count
- Preserve sentence boundaries (last period)
- Extract and append citations (max 3)
- Add ellipsis suffix `[...]`

### Citation Extraction
- **NN references**: `NN 123/2024` pattern
- **Article refs**: `članak 15`, `čl. 20` patterns
- Deduplication
- Maximum 3 citations shown

## Running the Tests

```bash
# Run all ContextCompressor tests
vendor/bin/phpunit tests/Unit/Services/ContextCompressorTest.php

# Or use artisan
php artisan test --filter=ContextCompressorTest

# Run with coverage
vendor/bin/phpunit --coverage-html coverage tests/Unit/Services/ContextCompressorTest.php
```

## Usage Example

```php
$compressor = new ContextCompressor();

$results = [
    [
        'content' => $longLegalDocument,
        'title' => 'Zakon o radu',
        'law_number' => 'NN 93/2014',
        'tags' => ['employment', 'labor'],
    ],
];

// Compress to fit 500 token budget
$compressed = $compressor->compress($results, 500);

// Original preserved in full_content
$original = $compressed[0]['full_content'];
$shortened = $compressed[0]['content'];
$wasCompressed = $compressed[0]['compressed'] ?? false;

// Calculate compression ratio
$ratio = $compressor->calculateCompressionRatio($results, $compressed);
```

## Compression Output Format

### Short Content (≤200 tokens)
```
Original content unchanged
```

### Long Content (>200 tokens)
```
Most relevant sentence one. Most relevant sentence two.
Most relevant sentence three containing članak 15 reference.
[Cites: NN 93/2014, članak 15, čl. 20] [...]
```

### Result Structure
```php
[
    'content' => 'Compressed content...',
    'full_content' => 'Original full content...',  // Added when compressed
    'compressed' => true,                          // Added when compressed
    'title' => 'Original title',                   // Preserved
    // ... other original fields preserved ...
]
```

## Croatian Legal Patterns

### Citation Patterns
- **NN Format**: Narodne Novine (Official Gazette)
  - Pattern: `NN {edition}/{year}`
  - Examples: `NN 93/2014`, `NN 123/2024`

- **Article References**:
  - `članak {number}` - Full form
  - `čl. {number}` - Abbreviated form
  - Examples: `članak 15`, `čl. 20`

### Legal Keywords
- **Zakon o** - Law about/on
  - Example: `Zakon o radu` (Labor Law)
  - Example: `Zakon o međunarodnom privatnom pravu`

### Diacritics
Tests verify proper handling of Croatian characters:
- č, ć, dž, đ, lj, nj, š, ž

## Performance Characteristics

### Time Complexity
- **O(n × m)** where:
  - n = number of results
  - m = average number of sentences per result

### Space Complexity
- **O(n)** - Preserves original content in `full_content`

### Optimizations
- Early exit when token budget exceeded
- Minimal sentence splitting (regex split)
- In-place scoring and sorting

## Token Budget Guidelines

### Recommended Budgets
- **Small context**: 1000-2000 tokens
- **Medium context**: 2000-4000 tokens (default)
- **Large context**: 4000-8000 tokens

### Budget Allocation
- Short items: Actual size (≤200 tokens)
- Long items: 300 tokens each
- Example: 4000 token budget ≈ 13 long documents

## Edge Cases Handled

1. **Empty/Missing Content**
   - Returns item unchanged
   - No compression flag

2. **Very Short Documents**
   - Kept as-is (under 200 token threshold)
   - Original flow preserved

3. **No Keywords Available**
   - Uses original content
   - Still applies sentence splitting if needed

4. **No Sentence Delimiters**
   - Handles continuous text
   - Truncates by character count

5. **No Citations Found**
   - Compresses without citation list
   - Still adds ellipsis

6. **Duplicate Citations**
   - Automatically deduplicated
   - Max 3 shown

## Integration Points

### Used By
- LLM prompt builders
- RAG (Retrieval-Augmented Generation) pipelines
- Search result formatters
- Context window managers

### Input Sources
- Legal document search results
- Court decision retrievals
- Law article collections
- Case law databases

## Future Enhancements

Potential improvements:
1. Semantic similarity scoring (using embeddings)
2. Configurable compression strategies
3. Legal entity recognition (NER)
4. Multi-document summarization
5. Importance ranking by document metadata
6. Customizable citation formats
7. Language-specific optimizations
8. Compression quality metrics

## Related Services

- **FactExtractionService**: Extracts structured facts from legal documents
- **IngestPipelineService**: Chunks and embeds legal documents
- **OpenAIService**: Provides LLM integration for prompts
- **MetadataBuilder**: Generates structured metadata

## Notes

### Token Estimation Accuracy
The service uses a rough heuristic (1 token ≈ 4 characters). For precise token counting, consider integrating:
- GPT tokenizer libraries (tiktoken)
- Model-specific tokenizers

### Croatian Text Handling
- UTF-8 encoding required
- mb_string functions used for diacritics
- Case-insensitive matching with mb_strtolower

### Compression Trade-offs
- **Information loss**: Non-selected sentences discarded
- **Context preservation**: Maintains sentence order
- **Readability**: Keeps complete sentences
- **Citations**: Important legal references preserved

### PHPUnit Deprecations
Tests use `@test` annotations which will be deprecated in PHPUnit 12. Migrate to PHP 8 attributes:

```php
// Current
/** @test */
public function it_does_something() { }

// Future
#[Test]
public function it_does_something() { }
```
