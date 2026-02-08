# ADR-001: Canonical Embedding Architecture

**Status:** Proposed
**Date:** 2026-02-03
**Decision Makers:** Architecture Team
**Technical Area:** Vector Embeddings, Document Processing

---

## Context

The AI Legal War Machine application currently has two competing embedding systems for Textract document content. This creates confusion, inconsistent search quality, and maintenance burden.

### System A: RegenerateTextractEmbeddings + TextractVectorStoreService

**Job:** `App\Jobs\RegenerateTextractEmbeddings`
**Service:** `App\Services\TextractVectorStoreService`
**Storage:** `textract_documents` table (pgvector)

| Characteristic | Value |
|----------------|-------|
| **Chunking Strategy** | 1000 chars default, 200 char overlap |
| **Chunk Boundary** | Sentence-aware (breaks at `.`, `!`, `?` + whitespace) |
| **Fallback Boundary** | Paragraph breaks (`\n`) |
| **Vectors per Document** | Many (one per chunk) |
| **Memory Management** | Generator-based, `gc_collect_cycles()` |
| **Batch Size** | 20 chunks per API call |
| **Retry Strategy** | 3 attempts, exponential backoff (2s, 4s, 8s) |
| **Timeout** | 600 seconds (10 minutes) |
| **Graph Sync** | Optional (requires both `neo4j.sync.enabled` AND `neo4j.sync.auto_sync`) |

**Code Evidence (TextractVectorStoreService lines 317-363):**
```php
protected function chunkTextGenerator(string $content, array $options = []): \Generator
{
    $targetSize = $options['chunk_size'] ?? 1000;
    $overlap = $options['chunk_overlap'] ?? 200;
    // ... sentence-boundary breaking logic
}
```

### System B: GenerateEmbeddingsJob + CourtDecisionVectorStoreService

**Job:** `App\Jobs\GenerateEmbeddingsJob`
**Service:** `App\Services\CourtDecisionVectorStoreService`
**Storage:** `court_decision_documents` table (pgvector)

| Characteristic | Value |
|----------------|-------|
| **Chunking Strategy** | First 1500 chars (truncation, not chunking) |
| **Chunk Boundary** | None - simple substring truncation |
| **Vectors per Document** | One (entire document represented by truncated prefix) |
| **Memory Management** | Simple, no explicit memory management |
| **Batch Size** | Single document at a time |
| **Retry Strategy** | 2 attempts, longer backoff (120s, 600s) |
| **Timeout** | 900 seconds (15 minutes) |
| **Graph Sync** | Always chains `SyncTextractToGraph` when Neo4j enabled |

**Code Evidence (GenerateEmbeddingsJob lines 160-173):**
```php
$chunkSize = config('distributed-processing.embeddings.chunk_size', 1500);
$textLength = mb_strlen($text);

if ($textLength > $chunkSize * 2) {
    // For large documents, use first chunk for embedding
    // In production, you may want to chunk and process all parts
    $text = mb_substr($text, 0, $chunkSize);
    // ... truncation, NOT chunking
}
```

### Comparison Summary

| Dimension | System A (TextractVectorStoreService) | System B (GenerateEmbeddingsJob) |
|-----------|---------------------------------------|----------------------------------|
| **Search Precision** | Chunk-level (finds relevant sections) | Document-level (only first 1500 chars) |
| **Long Document Support** | Full coverage via multiple chunks | Truncated to first 1500 chars |
| **Search Quality** | Higher (semantic chunks with overlap) | Lower (single truncated vector) |
| **Memory Efficiency** | Generator pattern prevents OOM | May struggle with large docs |
| **Storage Location** | `textract_documents` | `court_decision_documents` |
| **Use Case Match** | Designed for Textract content | Repurposed from court decisions |

---

## Decision

**Consolidate on System A (TextractVectorStoreService) as the canonical embedding architecture for Textract document content.**

### Rationale

1. **Superior Search Quality**: Chunk-level embeddings with overlap enable finding relevant sections within large documents, rather than relying on a single truncated representation.

2. **Full Document Coverage**: System A embeds the entire document across multiple chunks, while System B silently discards everything after 1500 characters.

3. **Better Memory Management**: Generator-based processing with explicit `gc_collect_cycles()` calls handles large documents without memory exhaustion.

4. **Proper Chunking**: Sentence-boundary aware chunking preserves semantic coherence, while truncation arbitrarily cuts mid-sentence.

5. **Existing Regeneration Path**: The regeneration workflow (the more common operation) already uses System A.

6. **Architectural Fit**: `TextractVectorStoreService` was designed specifically for Textract content, while `CourtDecisionVectorStoreService` is repurposed from a different domain.

---

## Consequences

### Positive

1. **Unified embedding strategy** - Single code path to maintain and optimize
2. **Improved search quality** - Chunk-level precision for semantic search
3. **Full document searchability** - No content truncation
4. **Simplified mental model** - One clear answer for "how do we embed Textract content?"
5. **Better debugging** - Single service to instrument and monitor

### Negative

1. **Migration required** - Existing single-vector embeddings need regeneration
2. **More storage** - Multiple chunks per document vs. one
3. **API cost increase** - More embedding API calls per document

### Risks

1. **PHP-based search is O(n)** - Current `searchSimilar()` iterates all documents. This is a known issue being addressed separately (planned: pgvector ANN indexes).

2. **Graph sync behavior change** - System B always chains graph sync; System A makes it optional. Ensure configuration is set appropriately.

---

## Migration Path

### Phase 1: Unify Job Dispatch (Non-breaking)

Modify `GenerateEmbeddingsJob` to delegate to `TextractVectorStoreService` instead of using truncation:

```php
// In GenerateEmbeddingsJob::processTextractJob()
// BEFORE: truncate and call CourtDecisionVectorStoreService::upsert()
// AFTER: delegate to TextractVectorStoreService::ingestTextractJob()

protected function processTextractJob(OpenAIService $openai, TextractVectorStoreService $vectorStore): void
{
    $job = TextractJob::find($this->sourceId);
    if (!$job) { return; }

    $job->update(['embedding_status' => 'processing']);
    $vectorStore->ingestTextractJob($this->sourceId, []);

    // Graph sync happens inside TextractVectorStoreService if enabled
}
```

### Phase 2: Deprecate CourtDecisionVectorStoreService for Textract

1. Add `@deprecated` annotation to `CourtDecisionVectorStoreService::upsert()` for Textract use case
2. Log warnings when textract content flows through old path
3. Update documentation to reference `TextractVectorStoreService` exclusively

### Phase 3: Regenerate Existing Embeddings

1. Identify TextractJobs with single-vector embeddings in `court_decision_documents`
2. Queue `RegenerateTextractEmbeddings` for each
3. Verify chunk counts in `textract_documents`
4. Remove orphaned records from `court_decision_documents`

### Phase 4: Remove Legacy Code

1. Remove Textract handling from `GenerateEmbeddingsJob` (keep batch processing if still needed)
2. Consider renaming or removing `CourtDecisionVectorStoreService` if no longer used

---

## Verification Criteria

The migration is complete when:

1. All `TextractJob` embeddings are stored in `textract_documents` table
2. Each job has multiple `TextractDocument` records (not single vectors)
3. `GenerateEmbeddingsJob` delegates to `TextractVectorStoreService`
4. No new records created in `court_decision_documents` for textract content
5. Search quality tests pass with chunk-level precision

---

## Related Documents

- Task 1.2: Standardize Embedding Chunking Strategy (implements unified chunking config)
- Task 1.3: Consolidate Embedding Job Dispatch (implements Phase 1 migration)
- Task 2.3: Optimize Vector Search with pgvector (addresses O(n) search concern)

---

## References

### Files Analyzed

| File | Lines | Purpose |
|------|-------|---------|
| `app/Jobs/RegenerateTextractEmbeddings.php` | 227 | Queue job for System A |
| `app/Jobs/GenerateEmbeddingsJob.php` | 367 | Queue job for System B |
| `app/Services/TextractVectorStoreService.php` | 891 | Service for System A |
| `app/Services/CourtDecisionVectorStoreService.php` | 773 | Service for System B |

### Configuration Keys

- `openai.models.embeddings` - Embedding model selection
- `distributed-processing.embeddings.chunk_size` - Default chunk size (System B)
- `neo4j.sync.enabled` - Graph sync master switch
- `neo4j.sync.auto_sync` - Auto-sync on embedding completion

---

**Proposed by:** Claude Code (Implementer Agent)
**Review requested from:** Architecture Team, AI Engineering Team
