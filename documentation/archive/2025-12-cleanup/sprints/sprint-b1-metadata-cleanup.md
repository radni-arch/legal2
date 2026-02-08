# Sprint B1: Metadata Generation and Deduplication Cleanup

**Milestone B — Laws: Scrape → Parse (Članak) → Embed**

## Objective

Enforce single-path metadata generation at law level (not per article) and deduplicate ingestion logic to ensure efficient, consistent processing.

## Changes Summary

### 1. ZakonHrIngestService.php

**Removed:**
- `generateArticleMetadata()` method that was calling OpenAI for every article
- Per-article metadata enrichment in both `ingestUrls()` and `ingestHtml()`

**Kept:**
- `dispatchMetadataGeneration()` - called exactly once per law (not per article)
- `generateEnhancedMetadata()` - called once for IngestedLaw record creation

**Result:**
- Metadata is now generated once per law, analyzing all articles together
- Simplified article metadata to contain only: `article_number`, `heading_chain`, `file_name`, `chunk_index`
- Eliminated redundant OpenAI calls (was N calls per law, now 1 call per law)

### 2. IngestedLawsManager.php (Livewire)

**Verified:**
- `importSelectedLaws()` contains no article/chunk creation logic
- Cleanly delegates to `$this->ingestService->ingestUrls($this->selectedLawsToImport)`
- No embedding or metadata generation occurs in the Livewire layer

**Flow:**
```
User selects laws → importSelectedLaws() → ZakonHrIngestService::ingestUrls()
```

### 3. ZakonHrScraper.php

**Verified:**
- Scraper is scraping-only in the main ingestion flow
- Only provides URLs to Livewire via `getUniqueLaws()` and `scrapeAllCategories()`
- Does NOT perform article splitting, PDF generation, or embedding in the main flow
- The `scrapeLawContent()` method is a utility for other purposes, not used in the ingestion pipeline

**Flow:**
```
Scraper fetches HTML → Extracts law links → Returns URLs only
```

## Ingestion Flow (Post-Cleanup)

### High-Level Pipeline

```
┌─────────────────────────────────────────────────────────────────────────┐
│ 1. SCRAPING (ZakonHrScraper)                                           │
│    - Fetch category pages                                               │
│    - Extract law URLs                                                    │
│    - Return unique law URLs                                              │
└─────────────────────────────────────────────────────────────────────────┘
                                    ↓
┌─────────────────────────────────────────────────────────────────────────┐
│ 2. LIVEWIRE UI (IngestedLawsManager)                                   │
│    - User selects laws to import                                        │
│    - Calls ingestService.ingestUrls(urls)                               │
└─────────────────────────────────────────────────────────────────────────┘
                                    ↓
┌─────────────────────────────────────────────────────────────────────────┐
│ 3. INGESTION (ZakonHrIngestService)                                    │
│    For each URL:                                                         │
│      a. Fetch HTML from URL                                              │
│      b. Extract title, publication date                                  │
│      c. Create/find IngestedLaw record                                   │
│      d. Parse articles (LawParser.splitIntoArticles)                     │
│      e. Render per-article PDFs                                          │
│      f. Merge full law PDF                                               │
│      g. Build docs array with minimal metadata                           │
│      h. Ingest to vector store (embeddings)                              │
│      i. dispatchMetadataGeneration(lawId, docs) ← ONCE PER LAW          │
└─────────────────────────────────────────────────────────────────────────┘
                                    ↓
┌─────────────────────────────────────────────────────────────────────────┐
│ 4. METADATA GENERATION (GenerateLawMetadata Job)                       │
│    - Receives full law content (all articles)                            │
│    - Calls OpenAI ONCE with complete law text                            │
│    - Extracts: law_code, keywords, legal_domain, summary                 │
│    - Updates IngestedLaw record with generated metadata                  │
└─────────────────────────────────────────────────────────────────────────┘
```

### Detailed Flow: ZakonHrIngestService::ingestUrls()

```php
foreach ($urls as $url) {
    // 1. Fetch & parse
    $html = Http::get($url);
    $title = extractTitle($html);
    $pubDate = extractPublishedDate($html);

    // 2. Create IngestedLaw (once per law)
    $ingested = ensureIngestedLaw($docId, $title, $url, [
        'source' => 'zakon.hr',
        'date_published' => $pubDate,
    ]);

    // 3. Parse into articles (LawParser does this, NOT Scraper)
    $articles = $this->parser->splitIntoArticles($html);

    $docs = [];
    foreach ($articles as $art) {
        // 4. Render article PDF
        $this->renderer->renderArticle([...], $pdfPath);
        $this->recordLawUpload($ingested->id, $docId, $pdfPath);

        // 5. Build minimal doc metadata (NO OpenAI call here!)
        $docs[] = [
            'content' => $plainText,
            'metadata' => [
                'article_number' => $articleNumber,
                'heading_chain' => $art['heading_chain'],
                'file_name' => $pdfFileName,
                'chunk_index' => $index,
            ],
            'law_meta' => [
                'title' => $title,
                'jurisdiction' => 'HR',
                'promulgation_date' => $pubDate,
                'source_url' => $url,
            ],
        ];
    }

    // 6. Merge full PDF
    $this->merger->merge($articlePdfs, $fullPdfPath);

    // 7. Generate embeddings & insert into vector store
    $this->vectorStore->ingest($docId, $docs, [...]);

    // 8. Dispatch metadata generation ONCE per law
    if ($ingested && !empty($docs)) {
        $this->dispatchMetadataGeneration($ingested->id, $docs);
    }
}
```

### Metadata Generation Strategy

**Before (❌ Inefficient):**
```
For law with 50 articles:
  - OpenAI called 50 times (generateArticleMetadata per article)
  - Cost: 50 × API call cost
  - Time: 50 × API latency
  - Inconsistent metadata across articles
```

**After (✅ Efficient):**
```
For law with 50 articles:
  - OpenAI called 1 time (dispatchMetadataGeneration per law)
  - Cost: 1 × API call cost
  - Time: 1 × API latency
  - Consistent metadata across all articles of the law
```

## Responsibility Separation

| Component | Responsibility | What it does NOT do |
|-----------|---------------|-------------------|
| **ZakonHrScraper** | - Fetch category pages<br>- Extract law URLs<br>- Return unique law links | - Parse articles<br>- Generate PDFs<br>- Create embeddings<br>- Generate metadata |
| **IngestedLawsManager** | - UI/UX for law selection<br>- Delegate to IngestService | - Parse HTML<br>- Create chunks<br>- Generate metadata<br>- Handle PDFs |
| **ZakonHrIngestService** | - Fetch law HTML<br>- Parse articles (via LawParser)<br>- Generate PDFs<br>- Create embeddings<br>- Dispatch metadata job | - Scrape law listings<br>- Provide UI |
| **LawParser** | - Split HTML into articles<br>- Extract heading chains<br>- Handle article numbering | - Fetch HTML<br>- Generate PDFs<br>- Create embeddings |
| **GenerateLawMetadata** | - Analyze full law content<br>- Call OpenAI once<br>- Extract metadata<br>- Update IngestedLaw record | - Parse articles<br>- Generate embeddings |

## Acceptance Criteria

- [x] `ingestUrls()` calls `dispatchMetadataGeneration()` exactly once per law
- [x] `ingestHtml()` calls `dispatchMetadataGeneration()` exactly once per law
- [x] No per-article metadata generation remains (removed `generateArticleMetadata()`)
- [x] Livewire `importSelectedLaws()` only calls `ingestUrls()` - no article/chunk logic
- [x] Scraper does not parse articles in main flow - only provides URLs
- [x] LawParser handles article splitting within IngestService
- [x] No unrelated refactors

## Files Modified

1. **app/Services/ZakonHrIngestService.php**
   - Removed `generateArticleMetadata()` method (98 lines removed)
   - Simplified article metadata structure in both `ingestUrls()` and `ingestHtml()`
   - Kept `dispatchMetadataGeneration()` as the single metadata generation entry point

## Files Verified (No Changes Required)

1. **app/Http/Livewire/IngestedLawsManager.php** - Already clean
2. **app/Services/ZakonHrScraper.php** - Already compliant (scraping-only)
3. **app/Jobs/GenerateLawMetadata.php** - Already correct (unchanged as specified)

## Performance Impact

### Before
- Law with 50 articles: 50 OpenAI API calls
- Estimated time: 50 × 2s = 100 seconds
- Estimated cost: 50 × $0.001 = $0.05 per law

### After
- Law with 50 articles: 1 OpenAI API call
- Estimated time: 1 × 2s = 2 seconds
- Estimated cost: 1 × $0.001 = $0.001 per law

**Improvement: 98% reduction in API calls, time, and cost**

## Testing Recommendations

1. **Unit Tests:**
   - Verify `ingestUrls()` calls `dispatchMetadataGeneration()` exactly once
   - Verify `ingestHtml()` calls `dispatchMetadataGeneration()` exactly once
   - Verify metadata structure contains only expected fields

2. **Integration Tests:**
   - Import a law with multiple articles
   - Verify single metadata generation job is queued
   - Verify all articles get consistent metadata from the law-level generation

3. **Manual Testing:**
   - Use Livewire UI to import 3-5 laws
   - Check logs for single `dispatchMetadataGeneration` call per law
   - Verify metadata is populated correctly on IngestedLaw records

## Migration Notes

**No database migrations required** - this is a code-only cleanup that changes the flow but not the schema.

Existing laws in the database are unaffected. New imports will follow the optimized single-metadata-generation flow.

## Related Documentation

- `docs/METADATA_GENERATION_OPTIMIZATION.md` - Original optimization documentation
- `app/Jobs/GenerateLawMetadata.php` - The job that processes law-level metadata
- `docs/MILESTONE_B_IMPROVEMENTS.md` - Broader milestone context
