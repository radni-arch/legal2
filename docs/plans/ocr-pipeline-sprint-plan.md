# OCR Pipeline & Embedding Architecture — Consolidated Sprint Plan

**Version:** 1.0  
**Generated:** 2026-02-05  
**Sources:** Pipeline Analysis Reports v1-v3 + Improvement Plan  
**Execution Model:** Parallel Subagents

---

## Executive Summary

This document consolidates findings from four progressive analysis reports into **18 actionable tasks** across **5 sprints**. The analysis uncovered **11 bugs** ranging from critical data loss issues to scalability concerns.

**Key Discoveries:**

1. **Three competing embedding systems** exist (not two as originally thought), creating data duplication and race conditions
2. **Critical status mismatch bug** silently breaks auto-regeneration for all ProcessTextractJob documents
3. **Race condition in model events** causes graph sync to always silently fail after content edits
4. **O(n) vector search** in TextractVectorStoreService will not scale beyond a few thousand documents
5. **No existing text check** — every PDF goes through full Textract OCR regardless of embedded text (~$1.50/1000 pages wasted)

**Estimated Effort:**
- **Wall clock time:** ~12 hours (with full parallelization)
- **Total engineering hours:** ~57 hours
- **Max concurrent agents:** 8

---

## All Discovered Bugs

| # | Bug | Severity | File | Sprint | Task |
|---|-----|----------|------|--------|------|
| 1 | Status mismatch: `'completed'` vs `'succeeded'` check | **CRITICAL** | `RegenerateTextractEmbeddings.php:118` | 0 | 0.1 |
| 2 | Missing closing brace in catch block | **HIGH** | `GenerateEmbeddingsJob.php:~100` | 0 | 0.2 |
| 3 | No `failed()` method for permanent failure handling | **HIGH** | `GenerateEmbeddingsJob.php` | 0 | 0.2 |
| 4 | Race condition: parallel dispatch of embeddings + graph sync | **HIGH** | `TextractJob.php (booted)` | 0 | 0.3 |
| 5 | Embedding status overwrite (two jobs race on same flag) | **HIGH** | `ProcessTextractJob` + `TextractJob` | 0 + 1 | 0.3, 1.2 |
| 6 | TextractDocument search O(n) PHP iteration | **MEDIUM** | `TextractVectorStoreService.php` | 1 | 1.3 |
| 7 | No existing text check before OCR | **MEDIUM** | Pipeline (missing step) | 2 | 2.1 |
| 8 | DocumentOcrRouter contradictory scoring rules | **MEDIUM** | `DocumentOcrRouter.php` | 2 | 2.2 |
| 9 | Diacritic saturation penalizes Croatian quality scoring | **MEDIUM** | `OcrQualityComparator.php` | 2 | 2.3 |
| 10 | Silent graph sync loss on Neo4j unavailability | **MEDIUM** | `SyncTextractToGraph.php` | 4 | 4.1 |
| 11 | Dead needsReview flag never acted upon | **LOW** | Multiple files | 2 | 2.4 |

---

## Architecture Context

### The Three Embedding Systems

```
┌─────────────────────┬──────────────────────────┬────────────────────────────┬─────────────────────────┐
│                     │ System A                 │ System B                   │ System C                │
│                     │ CaseDocument             │ TextractDocument           │ Single-Vector Textract  │
├─────────────────────┼──────────────────────────┼────────────────────────────┼─────────────────────────┤
│ Job                 │ Inline (pipeline)        │ RegenerateTextract-        │ GenerateEmbeddingsJob   │
│                     │                          │ Embeddings                 │                         │
├─────────────────────┼──────────────────────────┼────────────────────────────┼─────────────────────────┤
│ Service             │ CaseVectorStoreService   │ TextractVectorStoreService │ CourtDecisionVector-    │
│                     │                          │                            │ StoreService            │
├─────────────────────┼──────────────────────────┼────────────────────────────┼─────────────────────────┤
│ Chunking            │ 1200 chars, 150 overlap  │ 1000 chars, 200 overlap    │ First 1500 chars only   │
│                     │ Croatian sentence-aware   │ Sentence-boundary          │ (truncated)             │
├─────────────────────┼──────────────────────────┼────────────────────────────┼─────────────────────────┤
│ Storage             │ cases_documents           │ textract_documents         │ CourtDecisionVector-    │
│                     │ (.embedding_vector)       │ (.embedding)               │ StoreService (external) │
├─────────────────────┼──────────────────────────┼────────────────────────────┼─────────────────────────┤
│ Search              │ pgvector <=> operator     │ PHP cosine (iterative)     │ Via external service    │
├─────────────────────┼──────────────────────────┼────────────────────────────┼─────────────────────────┤
│ Retry               │ 3x (1s, 2s, 4s)         │ 3x (2s, 4s, 8s)           │ 2x (120s, 600s)         │
├─────────────────────┼──────────────────────────┼────────────────────────────┼─────────────────────────┤
│ Chains Graph Sync   │ GraphRagOrchestrator     │ Optional (config)          │ SyncTextractToGraph     │
│                     │ ::syncCase() inline       │                            │ (5s delay)              │
└─────────────────────┴──────────────────────────┴────────────────────────────┴─────────────────────────┘
```

**Recommendation:** Consolidate on System B (chunked, TextractVectorStoreService) as canonical.

### Dispatch Source Matrix

| Job | Dispatch Source | Trigger | When |
|-----|----------------|---------|------|
| `GenerateEmbeddingsJob` | `ProcessTextractJob::dispatchFollowUpJobs()` | Auto after OCR | 30s delay, `embeddings` queue |
| `GenerateEmbeddingsJob` | `TextractJobActions::generateEmbeddings()` | Manual UI button | Immediate |
| `GenerateEmbeddingsJob` | `TextractJobActions::retryEmbeddings()` | Manual UI retry | Immediate |
| `RegenerateTextractEmbeddings` | `TextractManager::regenerateEmbeddings()` | Manual manager action | Immediate |
| `RegenerateTextractEmbeddings` | `TextractJob::booted()` model event | Auto on content change | Immediate (on `updated` event) |

---

## Sprint Dependency Graph

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

**Parallelization Rules:**
- Sprint 0 tasks are fully independent (3 parallel agents)
- Sprints 1 and 2 can run simultaneously after Sprint 0
- Sprint 3 depends on Sprint 0 completion
- Sprint 4 depends on Sprint 1 completion
- Within Sprint 1: Task 1.1 blocks 1.2 and 1.4; Task 1.3 is independent
- Within Sprints 2, 3, 4: All tasks are independent

---

## Sprint 0: Critical Bug Fixes

**Priority:** IMMEDIATE — these bugs cause silent data loss  
**Estimated effort:** ~5 hours total  
**Parallelization:** All 3 tasks can run as parallel agents  
**Gate:** All 3 must pass tests before proceeding to Sprint 1 or 3

---

### Task 0.1: Fix Status Value Mismatch

**Severity:** CRITICAL  
**Estimate:** 1 hour  
**File:** `app/Jobs/RegenerateTextractEmbeddings.php`

#### Problem

```php
// RegenerateTextractEmbeddings::handle(), around line 118
if ($job->status !== 'succeeded') {  // ❌ BUG: ProcessTextractJob sets 'completed'
    return;  // Silently skips ALL documents processed by ProcessTextractJob
}
```

`ProcessTextractJob` sets status to `'completed'` (line ~128), but `RegenerateTextractEmbeddings::handle()` checks `if ($job->status !== 'succeeded')` — **silently returns without processing**.

**Impact:** Auto-regeneration after content edits is silently broken for ALL ProcessTextractJob-processed documents.

#### Fix

```php
// BEFORE:
if ($job->status !== 'succeeded') {

// AFTER:
if (!in_array($job->status, ['completed', 'succeeded'])) {
```

#### Verification Checklist

Before implementing, verify consistency across codebase:

| Location | Current Check | Status |
|----------|---------------|--------|
| `TextractJob::isReadyForEmbedding()` | `in_array(['completed', 'succeeded'])` | ✅ Correct |
| `TextractJob::isReadyForGraphSync()` | `in_array(['completed', 'succeeded'])` | ✅ Correct |
| `TextractJobActions::getAvailableActionsProperty()` | `in_array(['completed', 'succeeded'])` | ✅ Correct |
| `SyncTextractToGraph::handle()` | Needs verification | ⚠️ Check |
| `RegenerateTextractEmbeddings::handle()` | `!== 'succeeded'` | ❌ **FIX THIS** |

#### Test Plan

```gherkin
Feature: Status mismatch fix verification

  Scenario: ProcessTextractJob document can regenerate embeddings
    Given a document processed via ProcessTextractJob
    And its status is 'completed'
    When I edit its content manually
    Then RegenerateTextractEmbeddings should actually process
    And embedding_status should transition: pending → processing → synced

  Scenario: Old 'succeeded' documents still work
    Given a document with status 'succeeded'
    When I edit its content manually
    Then RegenerateTextractEmbeddings should process successfully
```

#### Files to Modify

1. `app/Jobs/RegenerateTextractEmbeddings.php` — Line ~118

---

### Task 0.2: Fix GenerateEmbeddingsJob — Missing Brace + Missing `failed()` Method

**Severity:** HIGH  
**Estimate:** 2 hours  
**File:** `app/Jobs/GenerateEmbeddingsJob.php`

#### Problem 1: Missing Closing Brace

```php
// In the catch block around line ~100:
if ($this->sourceType === 'textract_job') {
    $failedJob = TextractJob::find($this->sourceId);
    if ($failedJob && $this->attempts() >= $this->tries) {
        $failedJob->update([
            'embedding_status' => 'failed',
            'error' => 'Embedding generation failed: '.$e->getMessage(),
        ]);
    }
// ❌ MISSING CLOSING BRACE HERE

if ($this->userId) {
    $this->broadcastFailed($this->userId, $broadcastJobId, $e->getMessage(), 'Embedding Generation');
}

if ($this->attempts() < $this->tries) {
    throw $e;  // Only retries for textract_job, not batch!
}
```

**Impact:** For `batch` source type: failures don't broadcast to UI and don't trigger retry (exception swallowed).

#### Problem 2: No `failed()` Method

`GenerateEmbeddingsJob` has **no `failed()` method**. When all retries are exhausted, Laravel calls `failed()` for permanent failure handling — but it doesn't exist.

Compare with `RegenerateTextractEmbeddings` which has a proper `failed()` method that marks the job permanently failed.

**Impact:** After all retries exhausted, no permanent failure status is recorded.

#### Fix — Brace

```php
// Add closing brace after the update block:
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

#### Fix — Add `failed()` Method

```php
/**
 * Handle permanent failure after all retries exhausted.
 */
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

#### Test Plan

```gherkin
Feature: GenerateEmbeddingsJob error handling

  Scenario: Batch failures now broadcast and retry
    Given a batch embedding job
    When OpenAI API fails
    Then failure should broadcast to UI via BroadcastsJobProgress
    And job should retry up to $tries times

  Scenario: Permanent failure handling after retries exhausted
    Given an embedding job that has exhausted all retries
    When failed() is called by Laravel
    Then TextractJob.embedding_status should be 'failed'
    And TextractJob.error should contain attempt count and error message
    And failure should broadcast to UI

  Scenario: Batch type permanent failure
    Given a batch embedding job that has exhausted all retries
    When failed() is called
    Then EmbeddingBatch should be marked failed
```

#### Files to Modify

1. `app/Jobs/GenerateEmbeddingsJob.php` — Add brace ~line 100, add `failed()` method

---

### Task 0.3: Fix Model Event Race Condition

**Severity:** HIGH  
**Estimate:** 2 hours  
**File:** `app/Models/TextractJob.php`

#### Problem

`TextractJob::booted()` dispatches BOTH jobs simultaneously on content change:

```php
// In static::updated()
if ($autoSync && ! empty($job->effective_content)) {
    \App\Jobs\RegenerateTextractEmbeddings::dispatch($job->id);
    // This ALWAYS silently fails because embeddings aren't synced yet:
    if (config('neo4j.sync.enabled', false)) {
        \App\Jobs\SyncTextractToGraph::dispatch($job->id);  // ❌ Race condition
    }
}
```

`SyncTextractToGraph` requires `embedding_status === 'synced'` (Gate 3 in its `handle()` method). Since both are dispatched simultaneously, graph sync will **always silently skip** because embeddings haven't finished.

**Impact:** After manual content edits, graph sync never happens (silently lost).

#### Fix

Remove parallel graph dispatch from model event; rely on proper chaining:

```php
// In TextractJob::booted(), static::updated() callback
// BEFORE:
if ($autoSync && ! empty($job->effective_content)) {
    \App\Jobs\RegenerateTextractEmbeddings::dispatch($job->id);
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

#### Verification

Confirm `RegenerateTextractEmbeddings` dispatches `SyncTextractToGraph` after success. Check `TextractVectorStoreService` for `neo4j.sync.auto_sync` config handling.

#### Test Plan

```gherkin
Feature: Model event race condition fix

  Scenario: Content edit triggers only embedding job
    Given a TextractJob with synced embeddings
    When I edit its content manually
    Then only RegenerateTextractEmbeddings should be dispatched
    And SyncTextractToGraph should NOT be dispatched immediately

  Scenario: Graph sync chains after embedding success
    Given a TextractJob with content recently edited
    When RegenerateTextractEmbeddings completes successfully
    Then SyncTextractToGraph should be dispatched
    And graph_sync_status should eventually become 'synced'

  Scenario: Graph sync status transitions correctly
    Given a content edit triggers embedding regeneration
    Then status should transition:
      | embedding_status | graph_sync_status |
      | pending          | pending           |
      | processing       | pending           |
      | synced           | pending           |
      | synced           | processing        |
      | synced           | synced            |
```

#### Files to Modify

1. `app/Models/TextractJob.php` — `booted()` method, `static::updated()` callback

---

## Sprint 1: Embedding Architecture Consolidation

**Priority:** HIGH — resolves architectural confusion, eliminates data duplication  
**Estimated effort:** ~16 hours total  
**Parallelization:** Task 1.1 blocks 1.2 and 1.4; Task 1.3 is independent  
**Depends on:** Sprint 0 completion

---

### Task 1.1: Decide Canonical Embedding Architecture

**Type:** Architecture Decision Record (ADR) — no code  
**Estimate:** 2 hours  
**Deliverable:** ADR document

#### Current State Analysis

| Dimension | System B (RegenerateTextractEmbeddings) | System C (GenerateEmbeddingsJob) |
|-----------|----------------------------------------|----------------------------------|
| Chunking | 1000 chars, 200 overlap, sentence-boundary | First 1500 chars (truncated) |
| Storage | `textract_documents` table (pgvector) | `CourtDecisionVectorStoreService` (external) |
| Vectors per doc | Many (one per chunk) | One (single vector) |
| Search quality | Better (chunk-level precision) | Worse (entire doc in one vector, truncated) |
| Search impl | PHP cosine iteration (O(n) bug) | Via external service |
| Memory | Generator-based, gc_collect_cycles() | Simple, no memory management |
| Graph chain | Optional (config-based) | Always chains SyncTextractToGraph |

#### Recommendation

**Consolidate on System B** (chunked, TextractVectorStoreService) as canonical:
- Superior search quality (chunk-level, not truncated)
- Already has better memory management
- Already used for regeneration (the more common path)

#### Migration Strategy

1. Keep `GenerateEmbeddingsJob` temporarily for backward compatibility
2. Have it delegate to `TextractVectorStoreService` (same chunking as System B)
3. Deprecate `CourtDecisionVectorStoreService` for textract content
4. Remove `GenerateEmbeddingsJob` after migration verified

#### ADR Template

```markdown
# ADR-001: Canonical Embedding Architecture

## Status
Accepted

## Context
Three embedding systems exist, creating data duplication and race conditions...

## Decision
Consolidate on System B (TextractVectorStoreService with chunked embeddings)...

## Consequences
- All embedding paths use consistent chunking
- Single source of truth for embedding status
- Migration required for existing single-vector documents
```

---

### Task 1.2: Implement Unified Embedding Dispatch

**Estimate:** 4 hours  
**Depends on:** Task 1.1 (architecture decision)  
**Files:**
- `app/Jobs/ProcessTextractJob.php`
- `app/Jobs/GenerateEmbeddingsJob.php`
- `app/Http/Livewire/Components/TextractJobActions.php`

#### Goal

All paths dispatch the same embedding job with consistent behavior.

#### Changes

**1. ProcessTextractJob::dispatchFollowUpJobs()**

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

**2. TextractJobActions — generateEmbeddings/retryEmbeddings**

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

**3. GenerateEmbeddingsJob — deprecate or keep for batch only**

Options:
- A) Keep for batch processing, add `@deprecated` for textract_job
- B) Modify batch processing to use `RegenerateTextractEmbeddings` per-item

#### Test Plan

```gherkin
Feature: Unified embedding dispatch

  Scenario: New document gets chunked embeddings
    Given I process a new document via ProcessTextractJob
    When embedding generation completes
    Then textract_documents table should have multiple rows (chunks)
    And NOT a single vector in CourtDecisionVectorStore

  Scenario: Manual generate button uses chunked path
    Given a TextractJob without embeddings
    When I click "Generate Embeddings" in UI
    Then RegenerateTextractEmbeddings should be dispatched
    And chunked embeddings should be created

  Scenario: Retry uses same chunked path
    Given a TextractJob with failed embeddings
    When I click "Retry" in UI
    Then RegenerateTextractEmbeddings should be dispatched
```

---

### Task 1.3: Fix TextractDocument Search — O(n) to pgvector

**Estimate:** 4 hours  
**Independent:** Can run parallel to 1.1/1.2  
**File:** `app/Services/TextractVectorStoreService.php`

#### Problem

`searchSimilar()` loads ALL TextractDocument records into PHP and computes cosine similarity iteratively. No pgvector `<=>` operator used.

```php
// Current implementation (pseudo):
$allDocs = TextractDocument::all();
foreach ($allDocs as $doc) {
    $similarity = cosineSimilarity($queryVector, $doc->embedding);
    // ...
}
```

**Impact:** Will not scale beyond a few thousand documents.

#### Fix — Use pgvector Query

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

#### Migration — Add pgvector Index

```sql
CREATE INDEX IF NOT EXISTS idx_textract_documents_embedding
ON textract_documents
USING ivfflat (embedding vector_cosine_ops)
WITH (lists = 100);
```

Create migration file: `database/migrations/xxxx_add_textract_documents_embedding_index.php`

#### Test Plan

```gherkin
Feature: pgvector search performance

  Scenario: Search uses database operator
    Given 1000+ documents with embeddings
    When I search for similar documents
    Then query should use pgvector <=> operator
    And response time should be < 500ms (vs O(n) seconds)

  Scenario: Similarity scores match
    Given a known document set
    When I compare PHP cosine vs pgvector distance
    Then similarity scores should match within 0.001 tolerance

  Scenario: Case filtering works
    Given documents from multiple cases
    When I search with case_id filter
    Then only documents from that case should return

  Scenario: minSimilarity threshold works
    Given documents with varying similarity
    When I search with minSimilarity = 0.8
    Then no results below 0.8 similarity should return
```

---

### Task 1.4: Migration — Backfill & Cleanup

**Estimate:** 6 hours  
**Depends on:** Task 1.2 (unified dispatch)  
**Goal:** Ensure all existing documents have chunked embeddings (System B format)

#### Steps

1. Identify TextractJobs with `embedding_status='synced'` but no TextractDocument children
2. Dispatch `RegenerateTextractEmbeddings` for each
3. After verification, remove stale `CourtDecisionVectorStore` entries for textract sources
4. Add `@deprecated` annotation to `GenerateEmbeddingsJob` (if keeping for batch only)

#### Artisan Command

Create: `app/Console/Commands/BackfillChunkedEmbeddings.php`

```php
<?php

namespace App\Console\Commands;

use App\Jobs\RegenerateTextractEmbeddings;
use App\Models\TextractJob;
use Illuminate\Console\Command;

class BackfillChunkedEmbeddings extends Command
{
    protected $signature = 'textract:backfill-chunked-embeddings 
                            {--dry-run : Show what would be processed without dispatching}
                            {--limit= : Maximum number of jobs to process}';

    protected $description = 'Backfill chunked embeddings for jobs with only single-vector embeddings';

    public function handle(): int
    {
        $query = TextractJob::where('embedding_status', 'synced')
            ->whereDoesntHave('documents')
            ->where(fn($q) => $q->where('status', 'completed')->orWhere('status', 'succeeded'));

        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
        }

        $jobs = $query->get();

        $this->info("Found {$jobs->count()} jobs needing chunked embedding backfill");

        if ($this->option('dry-run')) {
            $this->table(
                ['ID', 'Drive File Name', 'Status', 'Embedding Status'],
                $jobs->map(fn($j) => [$j->id, $j->drive_file_name, $j->status, $j->embedding_status])
            );
            return 0;
        }

        $bar = $this->output->createProgressBar($jobs->count());
        $dispatched = 0;

        foreach ($jobs as $job) {
            RegenerateTextractEmbeddings::dispatch($job->id);
            $dispatched++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Dispatched {$dispatched} embedding regeneration jobs");

        return 0;
    }
}
```

#### Cleanup Script

After backfill verified:

```php
// Remove stale single-vector entries from CourtDecisionVectorStore
$staleIds = TextractJob::where('embedding_status', 'synced')
    ->whereHas('documents')  // Now has chunked embeddings
    ->pluck('id')
    ->map(fn($id) => 'textract_' . $id);

// Call CourtDecisionVectorStoreService::delete() for each
```

---

## Sprint 2: OCR Pipeline Improvements

**Priority:** HIGH — reduces costs, improves quality  
**Estimated effort:** ~12 hours total  
**Parallelization:** All 4 tasks are fully independent  
**No dependency on Sprint 1** — can run in parallel

---

### Task 2.1: Add Existing Text Check (skip-text)

**Estimate:** 3 hours  
**Files:** New pipeline step, `OcrmypdfService`, `ProcessDrivePdf`

#### Problem

Every PDF goes through Textract regardless of existing text. Wastes ~$1.50/1000 pages.

#### Implementation

**1. Create CheckExistingTextStep**

`app/Actions/Textract/Steps/CheckExistingTextStep.php`

```php
<?php

namespace App\Actions\Textract\Steps;

use Closure;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class CheckExistingTextStep
{
    public function handle(array $payload, Closure $next): mixed
    {
        $localPath = $payload['localPath'];
        $minWordsPerPage = (int) config('ocr.min_words_per_page_skip', 50);

        // Fast local text extraction using pdftotext
        $textProcess = new Process(['pdftotext', '-layout', $localPath, '-']);
        $textProcess->setTimeout(30);
        $textProcess->run();

        $existingText = trim($textProcess->getOutput());
        $wordCount = str_word_count($existingText);

        // Get page count
        $infoProcess = new Process(['pdfinfo', $localPath]);
        $infoProcess->setTimeout(10);
        $infoProcess->run();
        preg_match('/Pages:\s+(\d+)/', $infoProcess->getOutput(), $matches);
        $pageCount = (int) ($matches[1] ?? 1);

        $wordsPerPage = $pageCount > 0 ? $wordCount / $pageCount : 0;
        $hasSubstantialText = $wordsPerPage >= $minWordsPerPage;

        Log::info('CheckExistingTextStep', [
            'file' => basename($localPath),
            'word_count' => $wordCount,
            'page_count' => $pageCount,
            'words_per_page' => round($wordsPerPage, 2),
            'threshold' => $minWordsPerPage,
            'skip_textract' => $hasSubstantialText,
        ]);

        $payload['ocr_routing_metadata'] = array_merge(
            $payload['ocr_routing_metadata'] ?? [],
            [
                'existing_text_check' => true,
                'existing_word_count' => $wordCount,
                'existing_words_per_page' => round($wordsPerPage, 2),
                'skipped_textract' => $hasSubstantialText,
            ]
        );

        if ($hasSubstantialText) {
            $payload['skipTextract'] = true;
            $payload['existingText'] = $existingText;

            Log::info('Skipping Textract - PDF has existing text layer', [
                'file' => basename($localPath),
                'words_per_page' => round($wordsPerPage, 2),
            ]);
        } else {
            $payload['skipTextract'] = false;
        }

        return $next($payload);
    }
}
```

**2. Modify Pipeline to Check Flag**

In `StartAnalysisStep`, `WaitAndFetchStep`, etc., add early return if `$payload['skipTextract'] === true`.

**3. Add Config**

`config/ocr.php`:
```php
'min_words_per_page_skip' => env('OCR_MIN_WORDS_PER_PAGE_SKIP', 50),
```

#### Cost Impact

If ~30% of case files are born-digital PDFs, this saves ~$0.45 per batch of 1000 pages.

#### Test Plan

```gherkin
Feature: Skip Textract for born-digital PDFs

  Scenario: PDF with embedded text skips Textract
    Given a born-digital PDF with 100 words per page
    When I process it through the pipeline
    Then Textract API should NOT be called
    And existing text should be used directly
    And ocr_routing_metadata.skipped_textract should be true

  Scenario: Scanned PDF goes through Textract
    Given a scanned PDF with 0 words per page
    When I process it through the pipeline
    Then Textract API SHOULD be called
    And ocr_routing_metadata.skipped_textract should be false
```

---

### Task 2.2: Fix DocumentOcrRouter Contradictions

**Estimate:** 2 hours  
**File:** `app/Services/Ocr/DocumentOcrRouter.php`

#### Problem

From v1 analysis: contradictory routing rules in scoring logic.

#### Investigation Required

Review the scoring system:
- Signal 1: Textract confidence thresholds
- Signal 2: Croatian content scoring
- Signal 3: Structured content (tables/forms)

Document contradictions and resolve.

#### Test Plan

```gherkin
Feature: DocumentOcrRouter consistency

  Scenario: High-confidence Textract preferred for structured content
    Given a PDF with tables and forms
    And Textract confidence > 0.85
    When routing decision is made
    Then Textract should be selected (not Tesseract)

  Scenario: Croatian content handled correctly
    Given a PDF with high Croatian diacritic density
    When routing decision is made
    Then scoring should account for diacritics appropriately
```

---

### Task 2.3: Fix OcrQualityComparator Diacritic Saturation

**Estimate:** 3 hours  
**File:** `app/Services/Ocr/OcrQualityComparator.php`

#### Problem

Croatian diacritics (č, ć, ž, š, đ) penalize quality scores. The diacritic scoring uses a logistic curve that may saturate incorrectly.

```php
// Current: 1/(1 + exp(-40*(density - 0.025)))
```

#### Fix Options

1. **Normalize diacritics before comparison** — remove diacritics for quality comparison only
2. **Weight diacritic-rich text positively** — indicates better OCR, not worse
3. **Adjust logistic curve parameters** — change center point or slope

#### Test Plan

```gherkin
Feature: Croatian diacritic handling

  Scenario: High diacritic density doesn't penalize quality
    Given OCR output with many Croatian diacritics
    When quality score is computed
    Then diacritics should contribute positively (or neutrally)
    And score should not be penalized vs diacritic-free text
```

---

### Task 2.4: Dead needsReview Flag Cleanup

**Estimate:** 4 hours  
**Files:** `TextractManager`, `TextractJob` model, pipeline steps

#### Problem

`needsReview` flag in metadata is set but never acted upon automatically. Only used as a filter in TextractManager stats.

#### Options

**Option A: Remove it entirely (simplify)**
- Delete the flag setting
- Remove from stats
- Less complexity

**Option B: Wire it to block auto-embedding until human review**
- Add `needs_review` status gate to `RegenerateTextractEmbeddings`
- Documents flagged for review don't auto-generate embeddings
- Manual "approve and embed" action in UI

**Recommendation:** Option B — adds value without removing existing work.

#### Implementation (Option B)

```php
// In RegenerateTextractEmbeddings::handle()
$metadata = $job->metadata ?? [];
if (($metadata['needsReview'] ?? false) && !$this->options['force_review_bypass'] ?? false) {
    Log::info('Skipping embedding - job flagged for review', ['job_id' => $job->id]);
    return;
}
```

Add UI action in TextractManager:
```php
public function approveAndEmbed(int $jobId): void
{
    $job = TextractJob::findOrFail($jobId);
    $metadata = $job->metadata ?? [];
    $metadata['needsReview'] = false;
    $metadata['reviewApprovedAt'] = now()->toIso8601String();
    $job->update(['metadata' => $metadata]);
    
    RegenerateTextractEmbeddings::dispatch($job->id);
    $this->dispatch('notify', message: 'Review approved, embedding queued');
}
```

---

## Sprint 3: Resilience & Observability

**Priority:** MEDIUM — improves operational reliability  
**Estimated effort:** ~14 hours total  
**Depends on:** Sprint 0 completion  
**Parallelization:** 3.1/3.2 parallel; 3.3/3.4 parallel

---

### Task 3.1: Unified Job Chaining — Embeddings → Graph

**Estimate:** 3 hours  
**Files:** `RegenerateTextractEmbeddings`, `TextractVectorStoreService`, `TextractJob`

#### Problem

Graph sync dispatch is fragmented across 3 locations:
1. Model event (now removed in Task 0.3)
2. `GenerateEmbeddingsJob` chain
3. `TextractVectorStoreService` config

After Sprint 0, model event no longer dispatches graph sync — need reliable chain.

#### Implementation

Consolidate chaining in `RegenerateTextractEmbeddings`:

```php
// RegenerateTextractEmbeddings::handle() — after success
$result = $vectorStore->ingestTextractJob($this->textractJobId, $this->options);

// Mark embedding synced
$job->markEmbeddingSynced();

// Chain graph sync - SINGLE SOURCE OF TRUTH
if (config('neo4j.sync.enabled', false)) {
    SyncTextractToGraph::dispatch($this->textractJobId)
        ->delay(now()->addSeconds(5));

    Log::info('RegenerateTextractEmbeddings: Chained graph sync', [
        'textract_job_id' => $this->textractJobId,
    ]);
}
```

Remove graph sync dispatch from `TextractVectorStoreService` (optional config path was confusing).

#### Test Plan

```gherkin
Feature: Unified job chaining

  Scenario: Embedding success chains graph sync
    Given a TextractJob with content
    When RegenerateTextractEmbeddings completes
    Then SyncTextractToGraph should be dispatched with 5s delay
    And graph_sync_status should transition: pending → processing → synced

  Scenario: Neo4j disabled skips graph chain
    Given Neo4j sync is disabled in config
    When RegenerateTextractEmbeddings completes
    Then SyncTextractToGraph should NOT be dispatched

  Scenario: Embedding failure blocks graph
    Given a TextractJob where embedding fails
    Then graph_sync_status should remain 'pending'
    And SyncTextractToGraph should NOT be dispatched
```

---

### Task 3.2: External Notifications

**Estimate:** 4 hours  
**Files:** New notification classes, config

#### Problem

Only Livewire browser events exist — no notification if user closes browser.

#### Implementation

**1. Create Notification Class**

`app/Notifications/TextractPipelineNotification.php`

```php
<?php

namespace App\Notifications;

use App\Models\TextractJob;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TextractPipelineNotification extends Notification
{
    use Queueable;

    public function __construct(
        public TextractJob $job,
        public string $event,  // 'completed', 'embedding_complete', 'graph_complete', 'failed'
        public ?string $error = null
    ) {}

    public function via($notifiable): array
    {
        $channels = ['database'];
        
        if (config('notifications.textract.mail.enabled')) {
            $channels[] = 'mail';
        }
        
        return $channels;
    }

    public function toMail($notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject("Textract: {$this->job->drive_file_name} - {$this->event}");

        if ($this->event === 'failed') {
            $message->error()
                ->line("Processing failed for: {$this->job->drive_file_name}")
                ->line("Error: {$this->error}");
        } else {
            $message->success()
                ->line("Processing complete for: {$this->job->drive_file_name}")
                ->line("Status: {$this->event}");
        }

        return $message;
    }

    public function toArray($notifiable): array
    {
        return [
            'job_id' => $this->job->id,
            'file_name' => $this->job->drive_file_name,
            'event' => $this->event,
            'error' => $this->error,
        ];
    }
}
```

**2. Fire on Lifecycle Events**

In relevant jobs after status updates:
```php
if ($user = $job->user) {
    $user->notify(new TextractPipelineNotification($job, 'completed'));
}
```

**3. Batch Aggregation**

For batch operations, aggregate notifications:
```php
// After batch completes, send single summary
$user->notify(new TextractBatchSummaryNotification($batch, $successCount, $failCount));
```

---

### Task 3.3: Case-Complete Trigger

**Estimate:** 4 hours  
**Files:** New listener, `TextractJob` model

#### Problem

No automatic analysis after all documents for a case are processed.

#### Implementation

**1. Create Listener**

`app/Listeners/CheckCaseCompleteness.php`

```php
<?php

namespace App\Listeners;

use App\Events\TextractJobGraphSynced;
use App\Jobs\CaseAnalysisJob;
use App\Models\TextractJob;
use Illuminate\Support\Facades\Log;

class CheckCaseCompleteness
{
    public function handle(TextractJobGraphSynced $event): void
    {
        $job = $event->textractJob;
        
        if (!$job->case_id) {
            return;
        }

        // Check if ALL TextractJobs for this case are fully synced
        $incompleteExists = TextractJob::where('case_id', $job->case_id)
            ->where('graph_sync_status', '!=', 'synced')
            ->where('status', '!=', 'failed')
            ->exists();

        if (!$incompleteExists) {
            Log::info('All documents synced for case - triggering analysis', [
                'case_id' => $job->case_id,
            ]);

            CaseAnalysisJob::dispatch($job->case_id);
            
            event(new \App\Events\CaseReadyForReview($job->case_id));
        }
    }
}
```

**2. Fire Event in SyncTextractToGraph**

```php
// After successful sync
event(new TextractJobGraphSynced($job));
```

---

### Task 3.4: Health Dashboard Metrics

**Estimate:** 3 hours  
**Files:** New Livewire component or extend TextractManager stats

#### Metrics to Add

| Metric | Query | Purpose |
|--------|-------|---------|
| Embedding status breakdown | `GROUP BY embedding_status` | See processing state |
| Graph sync status breakdown | `GROUP BY graph_sync_status` | See sync state |
| Average pipeline duration | `AVG(completed_at - created_at)` | Track performance |
| Failed job age | `WHERE status='failed' ORDER BY failed_at` | Identify stuck failures |
| Stale pending detection | `WHERE status='pending' AND created_at < now() - interval '1 hour'` | Identify stuck jobs |
| Vector store mismatch | Count CourtDecisionVectorStore vs TextractDocument per job | Pre-consolidation audit |

#### Implementation

Extend `TextractManager::getStatsProperty()` or create dedicated `PipelineHealthDashboard` component.

---

## Sprint 4: Graph Hardening

**Priority:** MEDIUM — improves graph reliability  
**Estimated effort:** ~10 hours total  
**Depends on:** Sprint 1 completion  
**Parallelization:** All 3 tasks can run in parallel

---

### Task 4.1: Graph Sync Retry on Neo4j Unavailability

**Estimate:** 3 hours  
**File:** `app/Jobs/SyncTextractToGraph.php`

#### Problem

When Neo4j is unavailable, job sets `status='pending'` and returns silently (no retry). Jobs are permanently lost.

```php
// Current behavior:
if (!$graphDb->isAvailable()) {
    $job->update(['graph_sync_status' => 'pending', 'error' => '...']);
    return;  // ❌ Job completes "successfully" but never retries
}
```

#### Fix

Release job back to queue with exponential backoff:

```php
if (!$graphDb->isAvailable()) {
    $healthStatus = $graphDb->getHealthStatus();
    
    Log::warning('SyncTextractToGraph: Neo4j unavailable, releasing for retry', [
        'job_id' => $this->textractJobId,
        'health' => $healthStatus,
        'attempt' => $this->attempts(),
    ]);

    // Release back to queue with exponential backoff (max 1 hour)
    $delay = min(60 * pow(2, $this->attempts()), 3600);
    $this->release($delay);
    return;
}
```

#### Test Plan

```gherkin
Feature: Neo4j unavailability handling

  Scenario: Job retries when Neo4j down
    Given Neo4j is unavailable
    When SyncTextractToGraph runs
    Then job should be released back to queue
    And retry delay should increase exponentially
    And max delay should be 1 hour

  Scenario: Job succeeds when Neo4j recovers
    Given Neo4j was unavailable but is now available
    When released job runs again
    Then sync should complete successfully
```

---

### Task 4.2: Extractor Test Coverage

**Estimate:** 4 hours  
**Files:** Tests for all 8 extractors

#### Priority Extractors

| Extractor | Focus | Test Cases Needed |
|-----------|-------|-------------------|
| `ArticleExtractor` | Croatian grammatical cases | Članak/Članku/Člankom variations |
| `VerdictExtractor` | Pattern ordering | Partial before granted/denied |
| `LegalConceptExtractor` | Stemming edge cases | 90+ concepts across 7 categories |
| `HrLegalCitationsDetector` | NN citation parsing, ECLI | Various citation formats |

#### Deliverable

Test suite with at least 10 real-world Croatian legal text samples per extractor.

```php
class ArticleExtractorTest extends TestCase
{
    /** @dataProvider croatianArticleFormats */
    public function test_extracts_articles_in_all_grammatical_cases(string $input, array $expected): void
    {
        $extractor = new ArticleExtractor();
        $result = $extractor->extract($input);
        $this->assertEquals($expected, $result);
    }

    public static function croatianArticleFormats(): array
    {
        return [
            'nominative' => ['Članak 1.', ['article_number' => '1']],
            'locative' => ['u Članku 1.', ['article_number' => '1']],
            'instrumental' => ['Člankom 1.', ['article_number' => '1']],
            'abbreviated' => ['Čl. 1. st. 2.', ['article_number' => '1', 'paragraph' => '2']],
            'alpha suffix' => ['Članak 1a', ['article_number' => '1a']],
            // ... 5+ more cases
        ];
    }
}
```

---

### Task 4.3: Cross-Database Integrity Scheduling

**Estimate:** 3 hours  
**Files:** New scheduled command

#### Problem

`GraphDataIntegrityService` and `DataQualityService` exist but aren't scheduled.

#### Implementation

**1. Schedule Commands**

`app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule): void
{
    // Daily integrity report
    $schedule->call(function () {
        $service = app(GraphDataIntegrityService::class);
        $report = $service->generateFullReport();
        
        if ($report['health_status'] === 'degraded') {
            Mail::to(config('admin.email'))->send(new IntegrityAlertMail($report));
        }
    })->daily()->at('02:00');

    // Weekly quality audit
    $schedule->call(function () {
        $service = app(DataQualityService::class);
        $report = $service->runAllChecks();
        
        // Auto-merge duplicates if quality score > threshold
        if ($report['duplicate_count'] > 0 && $report['quality_score'] > 0.8) {
            $service->autoMergeDuplicates();
        }
        
        Mail::to(config('admin.email'))->send(new QualityReportMail($report));
    })->weekly()->sundays()->at('03:00');
}
```

**2. Create Alert Mailables**

`app/Mail/IntegrityAlertMail.php`
`app/Mail/QualityReportMail.php`

---

## Parallel Agent Dispatch Plan

### Wave 1 — Sprint 0 (Critical Bugs)

**Agents:** 3 parallel  
**Duration:** ~2 hours wall clock  
**Effort:** 5 hours total

```
┌─────────────────────────────────────────────────────────────┐
│ Agent A: Task 0.1 — Status mismatch fix                     │
│ Agent B: Task 0.2 — GenerateEmbeddingsJob brace + failed()  │
│ Agent C: Task 0.3 — Model event race condition              │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
                    ┌─────────────────┐
                    │  GATE: All 3    │
                    │  tests passing  │
                    └─────────────────┘
```

### Wave 2 — Sprints 1 + 2 (Parallel)

**Agents:** 6-8 parallel (Task 1.1 blocks 1.2/1.4)  
**Duration:** ~6 hours wall clock  
**Effort:** 28 hours total

```
Sprint 1 (Embedding):                    Sprint 2 (OCR):
┌────────────────────────────┐          ┌────────────────────────────┐
│ Agent D: Task 1.1 (ADR)    │          │ Agent F: Task 2.1          │
│         ↓ blocks           │          │ Agent G: Task 2.2          │
│ Agent D: Task 1.2          │          │ Agent H: Task 2.3          │
│ Agent J: Task 1.4          │          │ Agent I: Task 2.4          │
│                            │          │                            │
│ Agent E: Task 1.3 (indep)  │          │ (all independent)          │
└────────────────────────────┘          └────────────────────────────┘
```

### Wave 3 — Sprints 3 + 4 (After Dependencies)

**Agents:** 7 parallel  
**Duration:** ~4 hours wall clock  
**Effort:** 24 hours total

```
Sprint 3 (after Sprint 0):              Sprint 4 (after Sprint 1):
┌────────────────────────────┐          ┌────────────────────────────┐
│ Agent K: Task 3.1          │          │ Agent O: Task 4.1          │
│ Agent L: Task 3.2          │          │ Agent P: Task 4.2          │
│ Agent M: Task 3.3          │          │ Agent Q: Task 4.3          │
│ Agent N: Task 3.4          │          │                            │
└────────────────────────────┘          └────────────────────────────┘
```

### Summary

| Wave | Sprints | Tasks | Max Parallel Agents | Wall Clock | Total Hours |
|------|---------|-------|--------------------:|------------|-------------|
| 1 | Sprint 0 | 3 | 3 | ~2 hours | 5 hours |
| 2 | Sprint 1 + 2 | 8 | 6→8 | ~6 hours | 28 hours |
| 3 | Sprint 3 + 4 | 7 | 7 | ~4 hours | 24 hours |
| **Total** | **5 sprints** | **18** | **8 max** | **~12 hours** | **~57 hours** |

---

## Appendix A: File Reference

### Files Modified Per Task

| Task | Files |
|------|-------|
| 0.1 | `app/Jobs/RegenerateTextractEmbeddings.php` |
| 0.2 | `app/Jobs/GenerateEmbeddingsJob.php` |
| 0.3 | `app/Models/TextractJob.php` |
| 1.1 | `docs/adr/001-canonical-embedding-architecture.md` (new) |
| 1.2 | `app/Jobs/ProcessTextractJob.php`, `app/Http/Livewire/Components/TextractJobActions.php` |
| 1.3 | `app/Services/TextractVectorStoreService.php`, `database/migrations/` (new) |
| 1.4 | `app/Console/Commands/BackfillChunkedEmbeddings.php` (new) |
| 2.1 | `app/Actions/Textract/Steps/CheckExistingTextStep.php` (new), `config/ocr.php` |
| 2.2 | `app/Services/Ocr/DocumentOcrRouter.php` |
| 2.3 | `app/Services/Ocr/OcrQualityComparator.php` |
| 2.4 | `app/Http/Livewire/TextractManager.php`, `app/Jobs/RegenerateTextractEmbeddings.php` |
| 3.1 | `app/Jobs/RegenerateTextractEmbeddings.php`, `app/Services/TextractVectorStoreService.php` |
| 3.2 | `app/Notifications/TextractPipelineNotification.php` (new), `config/notifications.php` |
| 3.3 | `app/Listeners/CheckCaseCompleteness.php` (new), `app/Events/` (new) |
| 3.4 | `app/Http/Livewire/TextractManager.php` or new component |
| 4.1 | `app/Jobs/SyncTextractToGraph.php` |
| 4.2 | `tests/Unit/Graph/Extractors/` (new) |
| 4.3 | `app/Console/Kernel.php`, `app/Mail/` (new) |

---

## Appendix B: Configuration Keys

| Key | Default | Task | Purpose |
|-----|---------|------|---------|
| `ocr.min_words_per_page_skip` | `50` | 2.1 | Threshold for skipping Textract |
| `neo4j.sync.enabled` | `false` | 0.3, 3.1 | Enable/disable Neo4j sync |
| `neo4j.sync.auto_sync` | `true` | 3.1 | Auto-chain graph after embeddings |
| `notifications.textract.mail.enabled` | `false` | 3.2 | Enable email notifications |
| `textract.auto_sync` | `true` | 0.3 | Enable model event auto-dispatch |
| `distributed-processing.textract.auto_generate_embeddings` | `true` | 1.2 | Auto-generate embeddings after OCR |

---

## Appendix C: Agent Command Template

For dispatching subagents, use this command template:

```
You are implementing Task {TASK_ID}: {TASK_TITLE}

## Context
{PROBLEM_DESCRIPTION}

## Files to Modify
{FILE_LIST}

## Implementation
{CODE_CHANGES}

## Test Plan
{TEST_SCENARIOS}

## Acceptance Criteria
- [ ] All tests pass
- [ ] No regressions in existing functionality
- [ ] Code follows project conventions
- [ ] Changes are minimal and focused

## Dependencies
{BLOCKING_TASKS or "None - independent task"}
```
