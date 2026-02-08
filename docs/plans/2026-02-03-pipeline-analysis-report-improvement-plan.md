# OCR Pipeline & Embedding Architecture — Improvement Plan

*Based on: Pipeline Analysis Report v3 + source code analysis of TextractJobActions, TextractManager, ProcessTextractJob, GenerateEmbeddingsJob, RegenerateTextractEmbeddings, ReprocessTextractJob, TextractJob model.*

---

## New Discoveries From Source Code (Post-v3)

### ✅ RESOLVED: Canonical Embedding Job Question

**Both jobs are actively used from different entry points.** They are NOT alternatives — they co-exist and create conflicting data:

| Job | Dispatch Source | Trigger | When |
|-----|----------------|---------|------|
| `GenerateEmbeddingsJob` (System C) | `ProcessTextractJob::dispatchFollowUpJobs()` | Auto after OCR | 30s delay, `embeddings` queue |
| `GenerateEmbeddingsJob` (System C) | `TextractJobActions::generateEmbeddings()` | Manual UI button | Immediate |
| `GenerateEmbeddingsJob` (System C) | `TextractJobActions::retryEmbeddings()` | Manual UI retry | Immediate |
| `RegenerateTextractEmbeddings` (System B) | `TextractManager::regenerateEmbeddings()` | Manual manager action | Immediate |
| `RegenerateTextractEmbeddings` (System B) | `TextractJob::booted()` model event | Auto on content change | Immediate (on `updated` event) |

**Consequence:** After initial OCR, `GenerateEmbeddingsJob` runs (single truncated vector in CourtDecisionVectorStore). If content is later edited, `RegenerateTextractEmbeddings` runs (chunked vectors in textract_documents). Both set `embedding_status='synced'`. The CourtDecision single-vector becomes stale silently.

### 🐛 NEW BUG: Status Value Mismatch (CRITICAL)

- `ProcessTextractJob` sets status to `'completed'` (line ~128)
- `RegenerateTextractEmbeddings::handle()` checks `if ($job->status !== 'succeeded')` → **silently returns**
- `TextractJob::booted()` model event checks `in_array($job->status, ['completed', 'succeeded'])` → dispatches correctly

**Result:** The model event fires RegenerateTextractEmbeddings on content changes, but the job silently skips because status is `'completed'` not `'succeeded'`. **Auto-regeneration after content edits is silently broken for all ProcessTextractJob-processed documents.**

### 🐛 NEW BUG: Race Condition in Model Auto-Dispatch

`TextractJob::booted()` dispatches BOTH jobs simultaneously on content change:

```php
// In static::updated()
\App\Jobs\RegenerateTextractEmbeddings::dispatch($job->id);  // embeddings
\App\Jobs\SyncTextractToGraph::dispatch($job->id);            // graph sync
```

But SyncTextractToGraph requires `embedding_status === 'synced'` (Gate 3). Since both are dispatched at the same time, graph sync will almost always silently skip because embeddings haven't finished yet. The graph sync should be chained AFTER embeddings complete, not dispatched in parallel.

### 🐛 CONFIRMED: GenerateEmbeddingsJob Missing Brace + No `failed()` Method

The catch block's `broadcastFailed` and retry `throw` are inside `if ($this->sourceType === 'textract_job')`. For `batch` source type:
- Failures won't broadcast to UI
- Failures won't trigger retry (exception swallowed)

Additionally, `GenerateEmbeddingsJob` has **no `failed()` method** at all. When all retries are exhausted, Laravel calls `failed()` for permanent failure handling — but it doesn't exist. Compare with `RegenerateTextractEmbeddings` which has a proper `failed()` method that marks the job permanently failed.

### 🐛 NEW BUG: Embedding Status Overwrite

When `ProcessTextractJob` completes, `dispatchFollowUpJobs()` dispatches `GenerateEmbeddingsJob` (30s delay). Meanwhile, if content auto-sync is enabled, the model's `booted()` listener may also dispatch `RegenerateTextractEmbeddings`. Two jobs race to set `embedding_status`:

```
t=0s:   ProcessTextractJob completes → status='completed'
t=0s:   Model event fires → dispatches RegenerateTextractEmbeddings (but will SKIP due to status bug)
t=30s:  GenerateEmbeddingsJob runs → single vector → embedding_status='synced'
```

Currently "works" only because the status mismatch bug prevents RegenerateTextractEmbeddings from running. If the status bug is fixed, both jobs would race.

### ℹ️ TextractJob Model Event Architecture

The model has sophisticated auto-dispatch logic in `booted()`:
- **On content change** (`manual_content` or `extracted_content` dirty): auto-dispatches RegenerateTextractEmbeddings + SyncTextractToGraph
- **On embedding failure**: auto-blocks graph sync by setting `graph_sync_status = 'blocked'`
- **On delete**: cascades to delete related TextractDocuments
- **Config gate**: `textract.auto_sync` must be true (default true)

### ℹ️ ReprocessTextractJob — Audit Trail Architecture

Sophisticated re-OCR with:
- Transaction-wrapped archival of existing job (marked `'superseded'`)
- Case ID preservation across job versions
- Metadata audit trail linking new → old job
- `lockForUpdate()` to prevent race conditions
- Fresh TextractJob creation before delegating to ProcessDrivePdf

---

## Sprint Architecture

### Dependency Graph

```
Sprint 0 (Critical Bugs) ──────────────────────────────────────────────┐
  ├── 0.1 Status mismatch fix                                         │
  ├── 0.2 GenerateEmbeddingsJob brace + failed()                      │
  └── 0.3 Model event race condition                                   │
                                                                       │
Sprint 1 (Embedding Consolidation) ◄───────────────────────────────────┤
  ├── 1.1 Decide canonical architecture                                │
  ├── 1.2 Implement unified embedding service                          │
  ├── 1.3 Fix TextractDocument search O(n)                             │
  └── 1.4 Migration + cleanup                                         │
                                                                       │
Sprint 2 (OCR Pipeline) ◄─────────────────────────── independent ──────┤
  ├── 2.1 Add existing text check (skip-text)                          │
  ├── 2.2 Fix DocumentOcrRouter contradictions                         │
  ├── 2.3 OcrQualityComparator diacritic fix                          │
  └── 2.4 Dead needsReview flag cleanup                                │
                                                                       │
Sprint 3 (Resilience & Observability) ◄─── after Sprint 0 ────────────┤
  ├── 3.1 Unified job chaining (embeddings → graph)                    │
  ├── 3.2 External notifications (webhook/email)                       │
  ├── 3.3 Case-complete trigger                                        │
  └── 3.4 Health dashboard                                             │
                                                                       │
Sprint 4 (Graph Hardening) ◄─── after Sprint 1 ───────────────────────┘
  ├── 4.1 Graph sync retry on Neo4j unavailability
  ├── 4.2 Extractor test coverage
  └── 4.3 Cross-database integrity scheduling
```

**Parallelization:** Sprints 1 and 2 can run simultaneously after Sprint 0. Sprint 3 depends on Sprint 0. Sprint 4 depends on Sprint 1.

---

## Sprint 0: Critical Bug Fixes

**Priority:** IMMEDIATE — these bugs cause silent data loss
**Estimated effort:** ~6 hours total
**All 3 tasks are independent and can run as parallel agents**

### Task 0.1: Fix Status Value Mismatch

**File:** `app/Jobs/RegenerateTextractEmbeddings.php`
**Bug:** Checks `$job->status !== 'succeeded'` but ProcessTextractJob sets `'completed'`
**Impact:** Auto-regeneration after content edits silently broken for all ProcessTextractJob documents

**Fix:**
```php
// RegenerateTextractEmbeddings::handle(), around line 118
// BEFORE:
if ($job->status !== 'succeeded') {
// AFTER:
if (!in_array($job->status, ['completed', 'succeeded'])) {
```

**Verify consistency across codebase:**
- `TextractJob::isReadyForEmbedding()` — already uses `in_array(['completed', 'succeeded'])` ✓
- `TextractJob::isReadyForGraphSync()` — already uses `in_array(['completed', 'succeeded'])` ✓
- `TextractJobActions::getAvailableActionsProperty()` — already uses `in_array(['completed', 'succeeded'])` ✓
- `SyncTextractToGraph` — needs same check verified

**Test:**
1. Process a document via ProcessTextractJob (status='completed')
2. Edit its content manually
3. Verify RegenerateTextractEmbeddings actually processes (not silently skips)
4. Verify embedding_status transitions: pending → processing → synced

**Estimate:** 1 hour

---

### Task 0.2: Fix GenerateEmbeddingsJob — Missing Brace + Missing `failed()` Method

**File:** `app/Jobs/GenerateEmbeddingsJob.php`
**Bug 1:** Missing closing brace for `if ($this->sourceType === 'textract_job')` in catch block (~line 100)
**Bug 2:** No `failed()` method — permanent failures after all retries are unhandled
**Impact:** Batch failures don't broadcast, don't retry, and don't mark permanent failure

**Fix — Brace:**
```php
// In the catch block, add closing brace after the update block:
            if ($this->sourceType === 'textract_job') {
                $failedJob = TextractJob::find($this->sourceId);
                if ($failedJob && $this->attempts() >= $this->tries) {
                    $failedJob->update([
                        'embedding_status' => 'failed',
                        'error' => 'Embedding generation failed: '.$e->getMessage(),
                    ]);
                }
            } // ← ADD THIS BRACE

            if ($this->userId) {
                $this->broadcastFailed($this->userId, $broadcastJobId, $e->getMessage(), 'Embedding Generation');
            }

            if ($this->attempts() < $this->tries) {
                throw $e;
            }
        }
    }
```

**Fix — Add `failed()` method** (model after RegenerateTextractEmbeddings::failed()):
```php
    public function failed(\Throwable $e): void
    {
        Log::error('GenerateEmbeddingsJob - Permanently failed', [
            'source_id' => $this->sourceId,
            'source_type' => $this->sourceType,
            'attempts' => $this->tries,
            'error' => $e->getMessage(),
        ]);

        if ($this->sourceType === 'textract_job') {
            $job = TextractJob::find($this->sourceId);
            if ($job) {
                $job->update([
                    'embedding_status' => 'failed',
                    'error' => sprintf(
                        'Embedding generation permanently failed after %d attempts: %s',
                        $this->tries,
                        $e->getMessage()
                    ),
                ]);
            }
        } elseif ($this->sourceType === 'batch') {
            $batch = EmbeddingBatch::find($this->sourceId);
            if ($batch) {
                $batch->markFailed($e->getMessage());
            }
        }

        if ($this->userId) {
            $this->broadcastFailed(
                $this->userId,
                $this->sourceType . '_' . $this->sourceId,
                'Permanently failed after ' . $this->tries . ' attempts: ' . $e->getMessage(),
                'Embedding Generation'
            );
        }
    }
```

**Test:**
1. Mock OpenAI to fail → verify batch-type failures now broadcast and retry
2. Exhaust all retries → verify `failed()` marks TextractJob as permanently failed
3. Verify batch processing error handling with invalid item IDs

**Estimate:** 2 hours

---

### Task 0.3: Fix Model Event Race Condition

**File:** `app/Models/TextractJob.php`
**Bug:** `booted()` dispatches RegenerateTextractEmbeddings and SyncTextractToGraph simultaneously on content changes. Graph sync always silently skips because embeddings aren't ready.
**Impact:** After manual content edits, graph sync never happens (silently lost)

**Fix — Remove parallel graph dispatch, rely on chaining:**
```php
// In TextractJob::booted(), static::updated() callback
// BEFORE:
if ($autoSync && ! empty($job->effective_content)) {
    \App\Jobs\RegenerateTextractEmbeddings::dispatch($job->id);
    // This always silently fails because embeddings aren't synced yet:
    if (config('neo4j.sync.enabled', false)) {
        \App\Jobs\SyncTextractToGraph::dispatch($job->id);
    }
}

// AFTER:
if ($autoSync && ! empty($job->effective_content)) {
    \App\Jobs\RegenerateTextractEmbeddings::dispatch($job->id);
    // Graph sync will be triggered AFTER embeddings complete.
    // See: RegenerateTextractEmbeddings auto_sync config,
    // or GenerateEmbeddingsJob's SyncTextractToGraph chain.
    // DO NOT dispatch graph sync here — it will always skip.
    \Illuminate\Support\Facades\Log::info('Auto-dispatched embedding regeneration for TextractJob', [
        'job_id' => $job->id,
        'trigger' => 'content_updated',
        'note' => 'Graph sync will chain after embeddings complete',
    ]);
}
```

**Also verify:** `RegenerateTextractEmbeddings` should dispatch SyncTextractToGraph after success (it currently does this via `neo4j.sync.auto_sync` config check in TextractVectorStoreService). Confirm this path works.

**Test:**
1. Edit content manually
2. Verify only RegenerateTextractEmbeddings is dispatched (not SyncTextractToGraph)
3. After embeddings complete, verify SyncTextractToGraph is dispatched via the service
4. Verify graph_sync_status transitions: pending → (waits) → pending → processing → synced

**Estimate:** 2 hours

---

## Sprint 1: Embedding Architecture Consolidation

**Priority:** HIGH — resolves architectural confusion, eliminates data duplication
**Estimated effort:** ~16 hours total
**Tasks 1.2 and 1.3 can run in parallel after 1.1 (which is a design decision)**

### Task 1.1: Decide Canonical Embedding Architecture

**Type:** Architecture decision (no code)
**Deliverable:** ADR (Architecture Decision Record)

**Current state — two competing systems:**

| Dimension | System B (RegenerateTextractEmbeddings) | System C (GenerateEmbeddingsJob) |
|-----------|----------------------------------------|----------------------------------|
| Chunking | 1000 chars, 200 overlap, sentence-boundary | First 1500 chars (truncated) |
| Storage | `textract_documents` table (pgvector) | `CourtDecisionVectorStoreService` (external) |
| Vectors per doc | Many (one per chunk) | One (single vector) |
| Search quality | Better (chunk-level precision) | Worse (entire doc in one vector, truncated) |
| Search impl | PHP cosine iteration (O(n) bug) | Via external service |
| Memory | Generator-based, gc_collect_cycles() | Simple, no memory management |
| Graph chain | Optional (config-based) | Always chains SyncTextractToGraph |

**Recommendation:** Consolidate on **System B** (chunked, TextractVectorStoreService) as canonical:
- Superior search quality (chunk-level, not truncated)
- Already has better memory management
- Already used for regeneration (the more common path)

**Migration strategy:** Keep GenerateEmbeddingsJob temporarily but have it delegate to TextractVectorStoreService (same chunking), then deprecate CourtDecisionVectorStoreService for textract content.

**Estimate:** 2 hours (decision + ADR document)

---

### Task 1.2: Implement Unified Embedding Dispatch

**Files:**
- `app/Jobs/ProcessTextractJob.php`
- `app/Jobs/GenerateEmbeddingsJob.php`
- `app/Http/Livewire/Components/TextractJobActions.php`

**Goal:** All paths dispatch the same embedding job with consistent behavior.

**Changes:**

1. **ProcessTextractJob::dispatchFollowUpJobs()** — switch from GenerateEmbeddingsJob to RegenerateTextractEmbeddings:
```php
protected function dispatchFollowUpJobs(TextractJob $job): void
{
    if (config('distributed-processing.textract.auto_extract_tables', true)) {
        ExtractTablesFromTextractJob::dispatch($job->id)
            ->onQueue('textract-tables')
            ->delay(now()->addSeconds(10));
    }

    if (config('distributed-processing.textract.auto_generate_embeddings', true)) {
        // Use chunked embedding (System B) as canonical path
        RegenerateTextractEmbeddings::dispatch($job->id)
            ->delay(now()->addSeconds(30));
    }
}
```

2. **TextractJobActions** — switch generateEmbeddings/retryEmbeddings:
```php
public function generateEmbeddings(): void
{
    $this->job->update(['embedding_status' => 'pending']);
    \App\Jobs\RegenerateTextractEmbeddings::dispatch($this->job->id);
    $this->dispatch('notify', message: 'Embedding generation queued');
    $this->dispatch('refreshJobs');
}

public function retryEmbeddings(): void
{
    $this->job->update(['embedding_status' => 'pending', 'error' => null]);
    \App\Jobs\RegenerateTextractEmbeddings::dispatch($this->job->id);
    $this->dispatch('notify', message: 'Embedding retry queued');
    $this->dispatch('refreshJobs');
}
```

3. **GenerateEmbeddingsJob** — keep for batch processing only (or deprecate entirely if batches can use RegenerateTextractEmbeddings per-item).

**Test:**
1. Process new document → verify chunked embeddings in textract_documents (not single vector)
2. Manual "Generate Embeddings" button → verify same chunked path
3. Retry after failure → verify same path
4. Batch processing still works if GenerateEmbeddingsJob kept for batches

**Estimate:** 4 hours

---

### Task 1.3: Fix TextractDocument Search — O(n) to pgvector

**File:** `app/Services/TextractVectorStoreService.php`
**Bug:** `searchSimilar()` loads ALL TextractDocument records into PHP and computes cosine similarity iteratively. No pgvector `<=>` operator used.
**Impact:** Will not scale beyond a few thousand documents.

**Fix:** Replace PHP iteration with pgvector query:
```php
public function searchSimilar(string $query, int $limit = 10, float $minSimilarity = 0.7, ?int $caseId = null): Collection
{
    $openai = app(OpenAIService::class);
    $embedding = $openai->embeddings($query, 'text-embedding-3-small');
    $vector = $embedding['data'][0]['embedding'];
    $vectorString = '[' . implode(',', $vector) . ']';

    $query = TextractDocument::query()
        ->selectRaw('*, (embedding <=> ?::vector) as distance', [$vectorString])
        ->whereNotNull('embedding')
        ->where('processing_status', 'completed');

    if ($caseId) {
        $query->where('case_id', $caseId);
    }

    return $query
        ->havingRaw('(embedding <=> ?::vector) <= ?', [$vectorString, 1 - $minSimilarity])
        ->orderBy('distance', 'asc')
        ->limit($limit)
        ->get()
        ->map(function ($doc) {
            $doc->similarity = 1 - $doc->distance;
            return $doc;
        });
}
```

**Also:** Add pgvector index if not exists:
```sql
CREATE INDEX IF NOT EXISTS idx_textract_documents_embedding
ON textract_documents
USING ivfflat (embedding vector_cosine_ops)
WITH (lists = 100);
```

**Test:**
1. Insert 1000+ test documents with embeddings
2. Benchmark old vs new search (should be 100x+ faster)
3. Verify similarity scores match between PHP cosine and pgvector
4. Verify case_id filtering works
5. Verify minSimilarity threshold works correctly (note: pgvector distance = 1 - cosine similarity)

**Estimate:** 4 hours

---

### Task 1.4: Migration — Backfill & Cleanup

**Goal:** Ensure all existing documents have chunked embeddings (System B format)

**Steps:**
1. Identify TextractJobs with `embedding_status='synced'` but no TextractDocument children (these only have System C single-vector)
2. Dispatch RegenerateTextractEmbeddings for each
3. After verification, remove stale CourtDecisionVectorStore entries for textract sources
4. Add `@deprecated` annotation to GenerateEmbeddingsJob (if keeping for batch only)

**Artisan command:**
```php
// app/Console/Commands/BackfillChunkedEmbeddings.php
public function handle(): void
{
    $jobs = TextractJob::where('embedding_status', 'synced')
        ->whereDoesntHave('documents')
        ->where(fn($q) => $q->where('status', 'completed')->orWhere('status', 'succeeded'))
        ->get();

    $this->info("Found {$jobs->count()} jobs needing chunked embedding backfill");

    $bar = $this->output->createProgressBar($jobs->count());
    foreach ($jobs as $job) {
        RegenerateTextractEmbeddings::dispatch($job->id);
        $bar->advance();
    }
    $bar->finish();
}
```

**Estimate:** 6 hours (including testing on production data)

---

## Sprint 2: OCR Pipeline Improvements

**Priority:** HIGH — reduces costs, improves quality
**Estimated effort:** ~12 hours total
**All 4 tasks are independent — full parallel execution**
**No dependency on Sprint 1**

### Task 2.1: Add Existing Text Check (skip-text)

**Files:** Pipeline step (new), `OcrmypdfService`, `ProcessDrivePdf`
**Issue:** Every PDF goes through Textract regardless of existing text. Wastes ~$1.50/1000 pages.

**Implementation:**
1. Create `CheckExistingTextStep` — runs before S3 upload
2. Use `pdftotext` for fast local check (words-per-page threshold)
3. If text coverage > 80%, route to `OcrmypdfService` with `--skip-text` flag
4. If low coverage, proceed to Textract as normal

**Key metric:** Track `ocr_routing_metadata.skipped_textract = true` for cost reporting.

**Estimate:** 3 hours

---

### Task 2.2: Fix DocumentOcrRouter Contradictions

**File:** `app/Services/Ocr/DocumentOcrRouter.php`
**Issue:** Contradictory routing rules (from v1 sprint plan analysis)

**Estimate:** 2 hours

---

### Task 2.3: Fix OcrQualityComparator Diacritic Saturation

**File:** `app/Services/Ocr/OcrQualityComparator.php`
**Issue:** Croatian diacritics (č, ć, ž, š, đ) penalize quality scores

**Fix:** Normalize diacritics before quality comparison, or weight diacritic-rich text positively (indicates better OCR, not worse).

**Estimate:** 3 hours

---

### Task 2.4: Dead needsReview Flag Cleanup

**Files:** TextractManager, TextractJob model, pipeline steps
**Issue:** `needsReview` flag in metadata is set but never acted upon automatically. Only used as a filter in TextractManager stats.

**Options:**
- A) Remove it entirely (simplify)
- B) Wire it to block auto-embedding until human review
- Recommendation: B — add a `needs_review` status gate to RegenerateTextractEmbeddings

**Estimate:** 4 hours

---

## Sprint 3: Resilience & Observability

**Priority:** MEDIUM — improves operational reliability
**Estimated effort:** ~14 hours total
**Depends on Sprint 0 (race condition fix)**
**Tasks 3.1 and 3.2 can run in parallel; 3.3 and 3.4 can run in parallel**

### Task 3.1: Unified Job Chaining — Embeddings → Graph

**Files:** `RegenerateTextractEmbeddings`, `TextractVectorStoreService`, `TextractJob` model
**Issue:** Graph sync dispatch is fragmented across 3 locations (model event, GenerateEmbeddingsJob chain, TextractVectorStoreService config). After Sprint 0, model event no longer dispatches graph sync — need reliable chain.

**Implementation:**
1. After RegenerateTextractEmbeddings succeeds and marks `embedding_status='synced'`
2. Always dispatch SyncTextractToGraph with 5s delay (if Neo4j enabled)
3. Single source of truth for the chain — in the embedding job, not scattered
4. Remove graph sync dispatch from TextractVectorStoreService (optional config path)

```php
// RegenerateTextractEmbeddings::handle() — after success
$result = $vectorStore->ingestTextractJob($this->textractJobId, $this->options);

// Chain graph sync
if (config('neo4j.sync.enabled', false)) {
    SyncTextractToGraph::dispatch($this->textractJobId)
        ->delay(now()->addSeconds(5));

    Log::info('RegenerateTextractEmbeddings: Chained graph sync', [
        'textract_job_id' => $this->textractJobId,
    ]);
}
```

**Test:**
1. Edit content → verify embedding runs → verify graph sync chains after
2. Disable Neo4j → verify no graph sync dispatched
3. Embedding fails → verify graph_sync_status stays 'pending' (not 'blocked' from old model event)

**Estimate:** 3 hours

---

### Task 3.2: External Notifications

**Files:** New notification classes, config
**Issue:** Only Livewire browser events — no notification if user closes browser.

**Implementation:**
1. Create `TextractPipelineNotification` (Laravel Notification)
2. Channels: database (always), mail (configurable), webhook (configurable)
3. Fire on: pipeline complete, embedding complete, graph sync complete, permanent failure
4. Aggregate: don't send per-document for batch operations — send summary after batch

**Estimate:** 4 hours

---

### Task 3.3: Case-Complete Trigger

**Files:** New listener, TextractJob model
**Issue:** No automatic analysis after all documents for a case are processed.

**Implementation:**
1. After each TextractJob reaches `graph_sync_status='synced'`
2. Check: are ALL TextractJobs for this case_id fully synced?
3. If yes, dispatch `CaseAnalysisJob` which calls `CaseSearchService::analyzeCase()`
4. Fire `CaseReadyForReview` event

```php
// New: CheckCaseCompleteness listener
TextractJob::where('case_id', $job->case_id)
    ->where('graph_sync_status', '!=', 'synced')
    ->where('status', '!=', 'failed')
    ->doesntExist();  // All non-failed jobs are synced
```

**Estimate:** 4 hours

---

### Task 3.4: Health Dashboard Metrics

**Files:** New Livewire component or extend TextractManager stats
**Issue:** No visibility into pipeline health beyond basic counts.

**Metrics to add:**
- Embedding status breakdown (pending/processing/synced/failed/blocked)
- Graph sync status breakdown
- Average pipeline duration (OCR → embedding → graph)
- Failed job age (how long have failures been sitting?)
- Stale `pending` detection (jobs stuck in pending > 1 hour)
- CourtDecisionVectorStore vs TextractDocument count mismatch (pre-consolidation)

**Estimate:** 3 hours

---

## Sprint 4: Graph Hardening

**Priority:** MEDIUM — improves graph reliability
**Estimated effort:** ~10 hours total
**Depends on Sprint 1 (embedding consolidation)**
**All 3 tasks can run in parallel**

### Task 4.1: Graph Sync Retry on Neo4j Unavailability

**File:** `app/Jobs/SyncTextractToGraph.php`
**Issue:** When Neo4j is unavailable, job sets `status='pending'` and returns silently (no retry). Jobs are permanently lost.

**Fix:** Instead of silently returning, release the job back to queue with delay:
```php
if (!$graphDb->isAvailable()) {
    $healthStatus = $graphDb->getHealthStatus();
    Log::warning('SyncTextractToGraph: Neo4j unavailable, releasing for retry', [
        'job_id' => $this->textractJobId,
        'health' => $healthStatus,
        'attempt' => $this->attempts(),
    ]);

    // Release back to queue with exponential backoff
    $this->release(min(60 * pow(2, $this->attempts()), 3600)); // max 1 hour
    return;
}
```

**Estimate:** 3 hours

---

### Task 4.2: Extractor Test Coverage

**Files:** Tests for all 8 extractors
**Issue:** Legal extractors handle complex Croatian grammar — need regression tests for edge cases.

**Priority extractors:**
1. `ArticleExtractor` — Croatian grammatical cases (Članak/Članku/Člankom)
2. `VerdictExtractor` — pattern ordering (partial before granted)
3. `LegalConceptExtractor` — stemming edge cases
4. `HrLegalCitationsDetector` — NN citation parsing, ECLI format

**Deliverable:** Test suite with at least 10 real-world Croatian legal text samples per extractor.

**Estimate:** 4 hours

---

### Task 4.3: Cross-Database Integrity Scheduling

**File:** New scheduled command
**Issue:** `GraphDataIntegrityService` and `DataQualityService` exist but aren't scheduled.

**Implementation:**
1. Schedule `GraphDataIntegrityService::generateFullReport()` daily
2. Schedule `DataQualityService::runAllChecks()` weekly
3. Auto-merge duplicates if quality score > threshold
4. Email report to admin
5. Alert on health_status degradation

**Estimate:** 3 hours

---

## Parallel Agent Dispatch Plan

### Wave 1 — Sprint 0 (all 3 tasks, parallel)

```
Agent A: Task 0.1 — Status mismatch fix
Agent B: Task 0.2 — GenerateEmbeddingsJob brace + failed()
Agent C: Task 0.3 — Model event race condition
```

**Gate:** All 3 must pass tests before proceeding.

### Wave 2 — Sprints 1 + 2 (up to 7 tasks, parallel)

```
Agent D: Task 1.1 — Architecture decision (ADR) ← blocks 1.2, 1.4
Agent E: Task 1.3 — Fix TextractDocument search O(n) ← independent
Agent F: Task 2.1 — Existing text check (skip-text)
Agent G: Task 2.2 — DocumentOcrRouter contradictions
Agent H: Task 2.3 — OcrQualityComparator diacritic fix
Agent I: Task 2.4 — needsReview flag cleanup
```

After Agent D completes:
```
Agent D: Task 1.2 — Unified embedding dispatch
Agent J: Task 1.4 — Migration & backfill (after 1.2 merges)
```

### Wave 3 — Sprints 3 + 4 (up to 7 tasks, parallel)

```
Agent K: Task 3.1 — Unified job chaining
Agent L: Task 3.2 — External notifications
Agent M: Task 3.3 — Case-complete trigger
Agent N: Task 3.4 — Health dashboard
Agent O: Task 4.1 — Graph sync retry
Agent P: Task 4.2 — Extractor test coverage
Agent Q: Task 4.3 — Integrity scheduling
```

### Summary

| Wave | Tasks | Max Parallel Agents | Est. Wall Clock | Est. Total Hours |
|------|-------|-------------------|-----------------|-----------------|
| 1 | Sprint 0 (3 tasks) | 3 | ~2 hours | 5 hours |
| 2 | Sprint 1 + 2 (8 tasks) | 6→8 | ~6 hours | 28 hours |
| 3 | Sprint 3 + 4 (7 tasks) | 7 | ~4 hours | 24 hours |
| **Total** | **18 tasks** | | **~12 hours wall** | **~57 hours effort** |

---

## Quick Reference — All Bugs Found

| # | Bug | Severity | File | Sprint |
|---|-----|----------|------|--------|
| 1 | Status mismatch: `'completed'` vs `'succeeded'` check | **CRITICAL** | RegenerateTextractEmbeddings.php:118 | 0.1 |
| 2 | Missing brace in catch block | **HIGH** | GenerateEmbeddingsJob.php:~100 | 0.2 |
| 3 | No `failed()` method | **HIGH** | GenerateEmbeddingsJob.php | 0.2 |
| 4 | Race condition: parallel dispatch of embeddings + graph sync | **HIGH** | TextractJob.php (booted) | 0.3 |
| 5 | Embedding status overwrite (two jobs race) | **HIGH** | ProcessTextractJob + TextractJob model | 0.3 + 1.2 |
| 6 | TextractDocument search O(n) PHP iteration | **MEDIUM** | TextractVectorStoreService.php | 1.3 |
| 7 | No existing text check before OCR | **MEDIUM** | Pipeline (missing step) | 2.1 |
| 8 | DocumentOcrRouter contradictory rules | **MEDIUM** | DocumentOcrRouter.php | 2.2 |
| 9 | Diacritic saturation in quality scoring | **MEDIUM** | OcrQualityComparator.php | 2.3 |
| 10 | Silent graph sync loss on Neo4j unavailability | **MEDIUM** | SyncTextractToGraph.php | 4.1 |
| 11 | Dead needsReview flag | **LOW** | Multiple files | 2.4 |
