# ADR-001: Canonical Embedding Architecture

**Status:** Accepted
**Date:** 2026-02-05
**Decision Makers:** Architecture Team, AI Engineering Team
**Technical Area:** Vector Embeddings, Document Processing, Search Quality
**Supersedes:** `docs/architecture/decisions/ADR-001-canonical-embedding-architecture.md` (Proposed, 2026-02-03, covered only 2 of 3 systems)

---

## 1. Context

The AI Legal War Machine application has three independent embedding systems that process document content into vector representations for semantic search. These systems evolved organically to serve different entry points (case uploads, Textract OCR, court decision imports) but now create data duplication, inconsistent search quality, and race conditions when the same document is processed through multiple paths.

### System A: CaseVectorStoreService (Case Document Pipeline)

**Service:** `App\Services\CaseVectorStoreService` (1228 lines)
**Model:** `App\Models\CaseDocument`
**Storage:** `cases_documents` table (pgvector with JSON fallback)
**Caller:** `App\Pipelines\Textract\PersistReconstructedStep`

| Characteristic | Value |
|----------------|-------|
| **Chunking Strategy** | 1200 chars, 150 char overlap |
| **Chunk Boundary** | Relies on caller to pre-chunk; service accepts array of docs |
| **Vectors per Document** | Many (one per pre-chunked doc in array) |
| **Memory Management** | Standard PHP -- no generator, no gc_collect_cycles() |
| **Batch Processing** | All chunks sent to OpenAI in single `embeddings()` call |
| **Retry Strategy** | 3 attempts, exponential backoff (1s, 2s, 4s via `usleep`) |
| **Deduplication** | SHA-256 content_hash per (case_id, hash) pair |
| **Graph Sync** | Auto-sync via `GraphRagOrchestrator::syncCase()` when neo4j.sync.enabled AND auto_sync |
| **Language** | Croatian by default (`language: 'hr'` in PersistReconstructedStep) |
| **Config Source** | `vizra-adk.vector_memory.chunking` (chunk_size, overlap) |

**Code Evidence** (`PersistReconstructedStep` lines 123-127):
```php
$chunkCfg = config('vizra-adk.vector_memory.chunking', []);
$ingestOptions = [
    'chunk_size' => (int) ($chunkCfg['chunk_size'] ?? 1200),
    'overlap' => (int) ($chunkCfg['overlap'] ?? 150),
    'language' => 'hr',  // Croatian by default
];
```

**Code Evidence** (`CaseVectorStoreService` line 78):
```php
// All chunk contents sent in one batch call
$inputs = array_map(fn ($d) => (string) $d['content'], $docs);
$emb = $this->generateEmbeddingsWithRetry($inputs, $model, $caseId);
```

### System B: TextractVectorStoreService (Textract Chunked Pipeline)

**Service:** `App\Services\TextractVectorStoreService` (1063 lines)
**Job:** `App\Jobs\RegenerateTextractEmbeddings` (250 lines)
**Model:** `App\Models\TextractDocument`
**Storage:** `textract_documents` table (pgvector with PHP fallback)

| Characteristic | Value |
|----------------|-------|
| **Chunking Strategy** | 1000 chars default, 200 char overlap |
| **Chunk Boundary** | Sentence-aware: breaks at `. ! ?` + whitespace; fallback to `\n` |
| **Vectors per Document** | Many (one per chunk, generated internally) |
| **Memory Management** | Generator-based (`chunkTextGenerator`), explicit `gc_collect_cycles()`, `unset()` |
| **Batch Processing** | 20 chunks per OpenAI API call |
| **Retry Strategy** | 3 attempts, exponential backoff (1s, 2s, 4s); rate-limit aware |
| **Job Retry** | 3 attempts, backoff [2s, 4s, 8s], 600s timeout |
| **Deduplication** | Deletes all existing chunks before re-ingesting |
| **Graph Sync** | Optional via `GraphRagOrchestrator::syncTextractJob()` (requires both neo4j.sync.enabled AND auto_sync) |
| **Search** | pgvector `<=>` cosine distance with dynamic column-type detection; PHP O(n) fallback |
| **Config Source** | `textract.chunking` (chunk_size, chunk_overlap) |

**Code Evidence** (`TextractVectorStoreService` lines 317-363):
```php
protected function chunkTextGenerator(string $content, array $options = []): \Generator
{
    $targetSize = $options['chunk_size'] ?? 1000;
    $overlap = $options['chunk_overlap'] ?? 200;
    // ... sentence-boundary breaking logic with preg_match
    // ... yields chunks one at a time for memory efficiency
}
```

**Code Evidence** (`TextractVectorStoreService` lines 70-78, memory management):
```php
// Delete old chunks for this job before processing to free memory
TextractDocument::where('textract_job_id', $textractJobId)->delete();

// Process chunks using generator to avoid loading all into memory
$result = $this->processChunksWithGenerator($job, $content, $options);

// Free memory
unset($content);
gc_collect_cycles();
```

### System C: GenerateEmbeddingsJob (Truncated Single-Vector Pipeline)

**Job:** `App\Jobs\GenerateEmbeddingsJob` (366 lines)
**Service:** `App\Services\CourtDecisionVectorStoreService` (772 lines)
**Model:** `App\Models\CourtDecisionDocument`
**Storage:** `court_decision_documents` table (pgvector with JSON fallback)

| Characteristic | Value |
|----------------|-------|
| **Chunking Strategy** | **Truncation, not chunking**: first 1500 chars only |
| **Chunk Boundary** | None -- raw `mb_substr($text, 0, 1500)` |
| **Vectors per Document** | **One** (entire document represented by truncated prefix) |
| **Memory Management** | None -- simple sequential processing |
| **Batch Processing** | Single document upsert via `CourtDecisionVectorStoreService::upsert()` |
| **Retry Strategy** | 2 attempts, long backoff [120s, 600s], 900s timeout |
| **Deduplication** | Upsert by ID (updates existing, inserts new) |
| **Graph Sync** | Always dispatches `SyncTextractToGraph` when neo4j.sync.enabled |
| **Batch Mode** | Supports processing `EmbeddingBatch` collections |
| **Config Source** | `distributed-processing.embeddings` (chunk_size: 1500) |

**Code Evidence** (`GenerateEmbeddingsJob` lines 160-173):
```php
$chunkSize = config('distributed-processing.embeddings.chunk_size', 1500);
$textLength = mb_strlen($text);

if ($textLength > $chunkSize * 2) {
    // For large documents, use first chunk for embedding
    // In production, you may want to chunk and process all parts
    $text = mb_substr($text, 0, $chunkSize);
}
```

**Code Evidence** (`GenerateEmbeddingsJob` lines 183-198, upsert with metadata):
```php
$vectorStore->upsert([
    [
        'id' => 'textract_'.$job->id,
        'vector' => $vector,
        'metadata' => [
            'source' => 'textract',
            'job_id' => $job->id,
            // ... metadata
        ],
    ],
]);
```

### Comparison Summary

| Dimension | System A (CaseVectorStore) | System B (TextractVectorStore) | System C (GenerateEmbeddingsJob) |
|-----------|---------------------------|-------------------------------|----------------------------------|
| **Chunk Size** | 1200 chars | 1000 chars | 1500 chars (truncated) |
| **Overlap** | 150 chars | 200 chars | N/A |
| **Boundary Handling** | Caller-managed | Sentence-aware (`. ! ?`, `\n`) | None (raw truncation) |
| **Vectors/Document** | Multiple | Multiple | **Single** |
| **Document Coverage** | Full (pre-chunked) | Full (generator-chunked) | **First 1500 chars only** |
| **Memory Safety** | Standard | Generator + gc_collect | Standard |
| **Search Method** | pgvector + JSON fallback | pgvector + PHP fallback | Via CourtDecisionVectorStore |
| **Storage Table** | `cases_documents` | `textract_documents` | `court_decision_documents` |
| **Dedup Strategy** | Hash-based skip | Delete-and-recreate | Upsert by ID |
| **Config Key** | `vizra-adk.vector_memory.chunking` | `textract.chunking` | `distributed-processing.embeddings` |

### Problems Caused by Three Systems

1. **Data Duplication**: The same Textract document can be embedded through Systems A, B, and C simultaneously, creating redundant vectors across three tables with different chunking strategies.

2. **Race Conditions**: When `PersistReconstructedStep` (System A) and `GenerateEmbeddingsJob` (System C) run concurrently for the same content, they produce conflicting representations in different tables.

3. **Inconsistent Search Quality**: Queries hitting `textract_documents` (System B) get chunk-level precision. Queries hitting `court_decision_documents` (System C) only see the first 1500 characters. Queries hitting `cases_documents` (System A) use yet another chunking strategy.

4. **Silent Data Loss**: System C's truncation silently discards all content beyond 1500 characters. For legal documents (often 10,000+ chars), this means 85%+ of the content is not searchable. The code even acknowledges this with the comment: "In production, you may want to chunk and process all parts."

5. **Configuration Fragmentation**: Three separate config keys (`vizra-adk.vector_memory.chunking`, `textract.chunking`, `distributed-processing.embeddings`) control chunking, making it impossible to apply a consistent strategy.

6. **Maintenance Burden**: Bug fixes, model upgrades, or chunking improvements must be applied in three places.

---

## 2. Decision

**Consolidate on System B (TextractVectorStoreService) as the canonical embedding architecture for all Textract/OCR document content.**

### Rationale

1. **Superior Search Quality**: Chunk-level embeddings with 200-char overlap enable finding relevant sections within long legal documents, rather than relying on a single truncated vector or differently-sized chunks.

2. **Full Document Coverage**: System B embeds the entire document via multiple generator-yielded chunks. System C silently discards everything after 1500 characters. System A depends on callers to pre-chunk.

3. **Best Memory Management**: Generator-based processing (`chunkTextGenerator`) with explicit `gc_collect_cycles()` and `unset()` prevents OOM on large documents. This is critical for legal documents that can be 50+ pages of OCR text.

4. **Sentence-Aware Boundaries**: System B breaks chunks at sentence boundaries (`. ! ?` + whitespace, fallback to paragraph breaks), preserving semantic coherence. System A relies on callers. System C truncates arbitrarily.

5. **Self-Contained Chunking**: System B handles the full pipeline internally (text in, chunked vectors out). System A requires pre-chunked input from callers, creating coupling. System C does not truly chunk at all.

6. **Already the Regeneration Path**: `RegenerateTextractEmbeddings` (the more frequent operation post-initial-import) already uses System B exclusively.

7. **Rate-Limit Awareness**: System B's retry logic specifically detects rate-limit errors (429, "Too Many Requests") and applies appropriate backoff, while other systems use generic retry.

8. **Smaller Batch Size**: System B uses batches of 20 (vs. all-at-once for System A), reducing the blast radius of API failures and staying within rate limits.

---

## 3. Consequences

### Positive

1. **Single embedding code path** -- One service to maintain, optimize, and debug for Textract content
2. **Improved search quality** -- Chunk-level semantic precision across the full document
3. **No silent data loss** -- All content is embedded, not just the first 1500 characters
4. **Unified configuration** -- One config key (`textract.chunking`) for all Textract content chunking
5. **Simplified mental model** -- One clear answer for "how do we embed Textract/OCR content?"
6. **Better monitoring** -- Single service to instrument with metrics and alerts
7. **Memory safety** -- Generator pattern prevents OOM regardless of document size

### Negative

1. **Migration effort** -- Documents embedded via Systems A and C need re-embedding through System B
2. **Increased storage** -- Multiple chunks per document vs. one vector (System C) or different-sized chunks (System A)
3. **Higher API cost per document** -- More embedding API calls (multiple chunks vs. one truncated call)
4. **Temporary dual-path** -- During migration, both old and new paths coexist

### Risks

1. **O(n) PHP fallback search** -- When pgvector is unavailable, `searchWithPhpFallback()` iterates all documents. This is a known issue being addressed separately (planned: pgvector ANN indexes, Task 2.3).

2. **Graph sync behavior change** -- System C always dispatches `SyncTextractToGraph` when neo4j.sync.enabled. System B requires both `neo4j.sync.enabled` AND `neo4j.sync.auto_sync`. Configuration must be verified during migration.

3. **Batch processing gap** -- System C supports `EmbeddingBatch` collections for bulk processing. System B processes one TextractJob at a time. Bulk dispatch must be handled at the job level.

4. **CaseVectorStoreService scope** -- System A serves the broader case document pipeline (not just Textract content). This ADR covers Textract/OCR content consolidation; System A may remain relevant for non-Textract case documents that are pre-chunked by their own pipelines.

---

## 4. Migration Plan

### Phase 1: Delegate GenerateEmbeddingsJob to TextractVectorStoreService (Non-breaking)

**Goal:** Stop creating truncated single-vector embeddings for Textract content.

Modify `GenerateEmbeddingsJob::processTextractJob()` to delegate to `TextractVectorStoreService` instead of truncating and calling `CourtDecisionVectorStoreService::upsert()`:

```php
// BEFORE (current - System C):
protected function processTextractJob(OpenAIService $openai, CourtDecisionVectorStoreService $vectorStore): void
{
    // ... truncates to 1500 chars, upserts single vector to court_decision_documents
}

// AFTER (delegating to System B):
protected function processTextractJob(OpenAIService $openai, TextractVectorStoreService $vectorStore): void
{
    $job = TextractJob::find($this->sourceId);
    if (!$job) { return; }

    $vectorStore->ingestTextractJob($this->sourceId, []);
    // Graph sync handled internally by TextractVectorStoreService
}
```

**Changes required:**
- Update `GenerateEmbeddingsJob::handle()` type hint from `CourtDecisionVectorStoreService` to `TextractVectorStoreService`
- Simplify `processTextractJob()` to delegate
- Keep batch processing (`processBatch()`) for backward compatibility
- Update service container bindings if needed

### Phase 2: Deprecate CourtDecisionVectorStoreService for Textract Content

**Goal:** Signal to developers that Textract content should not flow through this service.

1. Add `@deprecated` annotation to `CourtDecisionVectorStoreService::upsert()` with migration guidance
2. Add runtime log warnings when textract content flows through the old path
3. Update developer documentation to reference `TextractVectorStoreService` exclusively
4. Audit all callers of `CourtDecisionVectorStoreService::upsert()` for textract-related usage

### Phase 3: Regenerate Existing Embeddings

**Goal:** Replace truncated single-vector embeddings with proper chunked embeddings.

1. Query `court_decision_documents` for records with `source = 'textract'` or `id LIKE 'textract_%'`
2. For each, find the corresponding `TextractJob` and dispatch `RegenerateTextractEmbeddings`
3. Verify that each job produces multiple `TextractDocument` records in `textract_documents`
4. After verification, remove the orphaned records from `court_decision_documents`

```bash
# Verification query (after migration):
SELECT tj.id,
       (SELECT COUNT(*) FROM textract_documents td WHERE td.textract_job_id = tj.id) as chunk_count,
       (SELECT COUNT(*) FROM court_decision_documents cdd WHERE cdd.id = CONCAT('textract_', tj.id)) as legacy_count
FROM textract_jobs tj
WHERE tj.status IN ('completed', 'succeeded');
```

### Phase 4: Unify Configuration

**Goal:** Single source of truth for Textract chunking parameters.

1. Consolidate on `textract.chunking` config key
2. Remove `distributed-processing.embeddings.chunk_size` for Textract use (keep for other use cases if needed)
3. Ensure `PersistReconstructedStep` also uses `textract.chunking` instead of `vizra-adk.vector_memory.chunking` (or align both)
4. Document the canonical configuration in `.env.example`

### Phase 5: Remove Legacy Code

**Goal:** Clean up after migration is verified.

1. Remove Textract handling from `GenerateEmbeddingsJob` entirely (convert to thin dispatcher or remove if unused)
2. Remove textract-related upsert paths from `CourtDecisionVectorStoreService`
3. Clean up orphaned records in `court_decision_documents` table
4. Remove unused config keys

---

## 5. Verification Criteria

The migration is complete when:

1. **All TextractJob embeddings** are stored exclusively in `textract_documents` table
2. **Each job has multiple TextractDocument records** (chunk-level, not single vectors)
3. **No new textract records** are created in `court_decision_documents`
4. **GenerateEmbeddingsJob** delegates to `TextractVectorStoreService` (or is removed)
5. **Search quality tests** pass with chunk-level precision
6. **Graph sync** works correctly through the new path (verify neo4j config)
7. **Zero orphaned records** in `court_decision_documents` with source 'textract'

---

## 6. Scope Clarification

This ADR covers the consolidation of embedding systems **for Textract/OCR document content specifically**.

- **CaseVectorStoreService (System A)** may continue to serve non-Textract case documents that are pre-chunked by their own domain-specific pipelines (e.g., manual document uploads with custom chunking). The `PersistReconstructedStep` caller should be migrated to use `TextractVectorStoreService` for OCR content.

- **CourtDecisionVectorStoreService (System C)** may continue to serve pure court decision data that does not originate from Textract. However, the `upsert()` method should no longer be used for Textract-sourced content.

---

## 7. Related Work

| Task | Relationship |
|------|-------------|
| Task 1.2: Standardize Embedding Chunking Strategy | Implements unified chunking config referenced in Phase 4 |
| Task 1.3: Consolidate Embedding Job Dispatch | Implements Phase 1 (GenerateEmbeddingsJob delegation) |
| Task 2.3: Optimize Vector Search with pgvector | Addresses the O(n) PHP fallback search risk |

---

## 8. References

### Files Analyzed

| File | Lines | Role in Architecture |
|------|-------|---------------------|
| `app/Services/CaseVectorStoreService.php` | 1228 | System A: Case document embedding service |
| `app/Services/TextractVectorStoreService.php` | 1063 | System B: Textract chunked embedding service (CANONICAL) |
| `app/Services/CourtDecisionVectorStoreService.php` | 772 | System C: Court decision embedding service |
| `app/Jobs/GenerateEmbeddingsJob.php` | 366 | System C: Queue job with truncation |
| `app/Jobs/RegenerateTextractEmbeddings.php` | 250 | System B: Queue job for regeneration |
| `app/Pipelines/Textract/PersistReconstructedStep.php` | 144+ | System A caller: configures 1200/150 chunking |
| `config/textract.php` | - | System B config: chunk_size=1000, overlap=200 |
| `config/distributed-processing.php` | - | System C config: chunk_size=1500 |
| `config/vizra-adk.php` | - | System A config: chunk_size (varies) |

### Configuration Keys

| Config Key | System | Default | Purpose |
|-----------|--------|---------|---------|
| `textract.chunking.chunk_size` | B | 1000 | Target chunk size in characters |
| `textract.chunking.chunk_overlap` | B | 200 | Overlap between consecutive chunks |
| `distributed-processing.embeddings.chunk_size` | C | 1500 | Truncation threshold |
| `vizra-adk.vector_memory.chunking.chunk_size` | A | 1200 | Case document chunk size |
| `vizra-adk.vector_memory.chunking.overlap` | A | 150 | Case document chunk overlap |
| `neo4j.sync.enabled` | All | false | Master switch for graph sync |
| `neo4j.sync.auto_sync` | A, B | false/true | Auto-sync on embedding completion |

### Storage Tables

| Table | System | Key Columns |
|-------|--------|------------|
| `cases_documents` | A | case_id, doc_id, embedding_vector, content_hash |
| `textract_documents` | B | textract_job_id, case_id, embedding, chunk_index |
| `court_decision_documents` | C | decision_id, doc_id, embedding, content_hash |

---

**Accepted by:** Architecture Team
**Date Accepted:** 2026-02-05
**Implementation Tracking:** Tasks 1.2, 1.3 (Sprint: OCR Pipeline Consolidation)
