# OCR Pipeline Analysis Report v3

*Updated with source code analysis of: RegenerateTextractEmbeddings, GenerateEmbeddingsJob, SyncTextractToGraph, TextractVectorStoreService, and 20+ Graph services/extractors.*

---

## ⚠️ Major Correction: Embedding Architecture

**v2 claimed** RegenerateTextractEmbeddings uploaded to OpenAI Vector Store for file_search. **This was wrong.**

The actual source code reveals **three** embedding systems, all using pgvector/local storage — none upload to OpenAI Vector Store directly.

---

## The Three Embedding Systems

### System A: CaseDocument Embeddings (Inline — CaseIngestPipeline)

From v2 analysis (unchanged). Generated during the OCR pipeline's Stage 3:

```
PersistReconstructedStep → CaseIngestPipeline::ingest()
  → OcrQualityAnalyzer (confidence/coverage gates)
  → HrLanguageNormalizer (NFC, diacritics, whitespace)
  → Smart chunking (1200 chars, 150 overlap, Croatian sentence-aware)
  → CaseVectorStoreService::ingest() → pgvector in cases_documents table
```

- **Model:** text-embedding-3-small (1536d)
- **Storage:** `cases_documents.embedding_vector` (pgvector)
- **Search:** `CaseSearchService::vectorSearch()` using `<=>` operator
- **When:** Inline during pipeline — works immediately

---

### System B: TextractDocument Embeddings (Deferred — RegenerateTextractEmbeddings)

**CORRECTED from v2.** This does NOT upload to OpenAI. It creates chunked local embeddings.

**Source:** `RegenerateTextractEmbeddings` → `TextractVectorStoreService::ingestTextractJob()`

**What it actually does:**

```php
// RegenerateTextractEmbeddings::handle()
$result = $vectorStore->ingestTextractJob($this->textractJobId, $this->options);
```

`TextractVectorStoreService::ingestTextractJob()` performs:

1. **Validate** — loads TextractJob, checks `effective_content` exists, status is `succeeded`
2. **Delete old chunks** — `TextractDocument::where('textract_job_id', $id)->delete()`
3. **Chunk with generator** — memory-efficient generator pattern:
   - Default: 1000 chars, 200 overlap (configurable)
   - Sentence-boundary breaking (`. ! ?` followed by space)
   - Paragraph fallback (`\n`)
4. **Batch embed** — batches of 20 chunks → `OpenAIService::embeddings()`
   - 3x retry with exponential backoff for rate limits
   - 100ms delay between batches
5. **Store per-chunk** — creates `TextractDocument` records:
   ```php
   TextractDocument::create([
       'textract_job_id' => $job->id,
       'case_id'         => $job->case_id,
       'content'         => $chunk['content'],
       'chunk_index'     => $chunk['chunk_index'],
       'chunk_overlap'   => $chunk['chunk_overlap'],
       'embedding'       => $vec,          // pgvector
       'embedding_provider' => 'openai',
       'embedding_model'    => 'text-embedding-3-small',
       'embedding_dimensions' => $dims,
       'token_count'     => estimateTokens($chunk['content']),
       'processing_status' => 'completed',
   ]);
   ```
6. **Mark synced** — `$job->markEmbeddingSynced()`
7. **Optional graph sync** — if `neo4j.sync.auto_sync` enabled, calls `GraphRagOrchestrator::syncTextractJob()`

**Configuration:**
- Queue: default (not named)
- Tries: 3 with backoff [2s, 4s, 8s]
- Timeout: 600s (10 minutes)
- Batch size: 20 chunks per API call

**Storage:** `textract_documents` table (pgvector per chunk)
**Search:** `TextractVectorStoreService::searchSimilar()` — cosine similarity in PHP against all `TextractDocument` records (no pgvector operator yet, iterates all docs)
**When:** Deferred — `embedding_status='pending'` → job dispatched → `'synced'`

---

### System C: Single-Vector Textract Embedding (GenerateEmbeddingsJob)

**NEW DISCOVERY.** A separate, older embedding path exists.

**Source:** `GenerateEmbeddingsJob` → `CourtDecisionVectorStoreService::upsert()`

**What it does (for sourceType='textract_job'):**

1. **Load TextractJob** and get `effective_content`
2. **Truncate** — if content > 2× chunk_size (default 1500), takes only first chunk:
   ```php
   if ($textLength > $chunkSize * 2) {
       $text = mb_substr($text, 0, $chunkSize);
   }
   ```
3. **Single embedding** — `$openai->embeddings($text, 'text-embedding-3-small')`
4. **Store in external vector store** — `CourtDecisionVectorStoreService::upsert()`:
   ```php
   $vectorStore->upsert([
       [
           'id'       => 'textract_' . $job->id,
           'vector'   => $vector,
           'metadata' => [
               'source'          => 'textract',
               'job_id'          => $job->id,
               'drive_file_id'   => $job->drive_file_id,
               'drive_file_name' => $job->drive_file_name,
               'case_id'         => $job->case_id,
               'text_length'     => strlen($text),
               'manually_edited' => $job->manually_edited,
           ],
       ],
   ]);
   ```
5. **Mark synced** — `$job->markEmbeddingSynced()`
6. **Chain graph sync** — dispatches `SyncTextractToGraph` with 5s delay (if Neo4j enabled)

**Key difference from System B:** Single vector for entire document (truncated), not chunked. Uses `CourtDecisionVectorStoreService` (likely an external/different vector store, not TextractDocuments table).

**Also handles batches:** When `sourceType='batch'`, loads `EmbeddingBatch` and dispatches individual `GenerateEmbeddingsJob` per item.

**Configuration:**
- Queue: `embeddings`
- Tries: 2 with backoff [120s, 600s]
- Timeout: 900s (15 minutes)
- Uses `BroadcastsJobProgress` trait for real-time UI updates

---

### Embedding Systems Comparison

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
│ When                │ Immediate (inline)       │ Deferred (queue job)       │ Deferred (queue job)    │
├─────────────────────┼──────────────────────────┼────────────────────────────┼─────────────────────────┤
│ Retry               │ 3x (1s, 2s, 4s)         │ 3x (2s, 4s, 8s)           │ 2x (120s, 600s)         │
├─────────────────────┼──────────────────────────┼────────────────────────────┼─────────────────────────┤
│ Chains Graph Sync   │ GraphRagOrchestrator     │ Optional (config)          │ SyncTextractToGraph     │
│                     │ ::syncCase() inline       │                            │ (5s delay)              │
└─────────────────────┴──────────────────────────┴────────────────────────────┴─────────────────────────┘
```

**Critical Question:** Which job gets dispatched when? Both System B and C track via `embedding_status` on TextractJob. They may be alternatives (one replaced the other), or they may serve different contexts. The `TextractManager::regenerateEmbeddings()` dispatches **RegenerateTextractEmbeddings** (System B). The `GenerateEmbeddingsJob` (System C) may be dispatched from a different code path — potentially from the older pipeline or batch processing.

---

## SyncTextractToGraph — Full Analysis

**Source:** `SyncTextractToGraph` job → `GraphRagOrchestrator::syncTextractJob()`

### Pre-flight Checks (4 gates)

```php
// Gate 1: Neo4j sync enabled?
if (!config('neo4j.sync.enabled', false)) → skip

// Gate 2: Neo4j available?
$graphDb = app(GraphDatabaseService::class);
if (!$graphDb->isAvailable()) → set status='pending', skip (no retry/throw)

// Gate 3: Embeddings ready?
if ($job->embedding_status !== 'synced') → skip (no retry/throw)

// Gate 4: Job succeeded?
if (!in_array($job->status, ['completed', 'succeeded'])) → skip
```

**Important:** Gates 2–4 return silently without throwing — the job completes "successfully" but doesn't sync. Only actual sync errors trigger retry (3x with [2s, 4s, 8s] backoff).

### Graph Operations (via GraphRagOrchestrator)

The orchestrator calls specialized services. Based on the services shared:

#### 1. CaseGraphSyncService::sync()

For each case document:
```
Create CaseDocument node (upsert)
  → GraphKeywordLinker::link()      — extract & link keywords
  → GraphCitationLinker::link()     — extract & link law/case citations
  → GraphSimilarityLinker::link()   — find & link similar documents
  → TaggingService::autoTag()       — auto-tag based on content + metadata
```

#### 2. GraphCitationLinker — Rich Citation Extraction

Uses **dual extraction** strategy:

**A. HrLegalCitationsDetector (primary):**
- Statute citations → `CITES` → LawDocument (with article, paragraph, item, canonical)
- Case number citations → `REFERENCES` → CaseDocument
- ECLI citations → `REFERENCES`
- Narodne Novine citations → `CITES` → LawDocument

**B. Legacy regex patterns (backward compatibility):**
- `NN 123/20` patterns
- `članak X Zakona (NN Y/Z)` patterns
- Named law patterns: `Zakon o ... (NN Y/Z)`
- Case reference patterns

**Law resolution strategy:**
1. Try exact `law_number` match in `laws` table
2. Try abbreviation ILIKE match
3. Try title ILIKE match
4. Try keyword intersection match

**Relationships created:**
- `(Document)-[:CITES]->(LawDocument)` with props: citation_type, article, paragraph, item, canonical
- `(Document)-[:REFERENCES]->(CaseDocument)` for case-to-case citations
- `(Decision)-[:INTERPRETS]->(LawDocument)` for statutory interpretations

#### 3. ArticleGraphSyncService — Law Structure

Parses law text into individual articles:
```
LawDocument -[:CONTAINS]-> Article (article_number, title, content, paragraph_count, position)
Decision -[:CITES_ARTICLE]-> Article (paragraph, context)
```

Uses `ArticleExtractor` with Croatian grammar-aware patterns:
- `Članak/Članku/Člankom 1.` (all grammatical cases)
- `Čl. 1. st. 2.` (abbreviated with paragraph)
- Handles article numbers like `1a`, `101`

#### 4. ContradictionDetectionService — LLM-Based

Uses GPT-4o (temperature=0.1) to detect contradictions between decisions:
- Types: legal_conclusion, factual_finding, legal_reasoning, procedural_ruling
- Severity: low/medium/high
- Confidence: 0.0–1.0
- Creates `CONTRADICTS` relationships
- Batch processing support

#### 5. ContradictionRadarService — Proactive Alerts

Scans pinned nodes for 6 alert types:
1. **Direct contradictions** — `CONTRADICTS` relationships (CRITICAL)
2. **Superseded laws** — `CITES` → law with `SUPERSEDED_BY` (CAUTION)
3. **Outdated citations** — decision_date > law.valid_until (CAUTION)
4. **Overruled precedents** — `CITES` → decision with `OVERRULES` (CRITICAL)
5. **Distinguished precedents** — `CITES` → decision with `DISTINGUISHES` (WARNING)
6. **Weak citation chains** — `CITES` → decision with `MODIFIES` (CAUTION)

Alerts are deterministic (SHA-256 ID), dismissible, and session-scoped.

---

## Graph Extractor Ecosystem

The system has 8 specialized extractors for Croatian legal text:

### Text Extractors

| Extractor | Input | Output | Key Patterns |
|-----------|-------|--------|--------------|
| **ArticleExtractor** | Law text | Article refs + structure | `Članak/Čl.` + all Croatian cases |
| **DateEventExtractor** | Any text | Date events with types | Croatian month names, dd.mm.yyyy, ISO |
| **EvidenceExtractor** | Decision text | Evidence items | Documentary, testimonial, expert, physical |
| **LawyerExtractor** | Decision text | Lawyers/attorneys | Punomoćnik, Odvjetnik, firm patterns |
| **LegalArgumentExtractor** | Decision text | Arguments by position | Plaintiff/defendant/court markers |
| **LegalConceptExtractor** | Any text | Legal concepts | 90+ Croatian legal concepts with stemming |
| **LegalDefinitionExtractor** | Law text | Definitions | `"X" znači Y`, `Pod X se smatra Y` |
| **LegalTopicExtractor** | Any text | Topics | 6-category taxonomy with child topics |
| **VerdictExtractor** | Decision text | Verdict outcome | Granted/denied/dismissed/remanded/withdrawn |

### Detail: LegalConceptExtractor

Contains a dictionary of **90+ Croatian legal concepts** across 7 categories:
- Procedural (tužba, presuda, rješenje, žalba, revizija, ovrha, zastara, pravomoćnost...)
- Substantive Civil (ugovor, obveza, vlasništvo, hipoteka, nasljedstvo, ništavost...)
- Criminal (kazneno djelo, krivnja, kazna, ubojstvo, optužnica, pokušaj...)
- Labor (radni odnos, otkaz, plaća, otpremnina, bolovanje...)
- Family (brak, razvod, skrbništvo, alimentacija, posvojenje...)
- Commercial (trgovačko društvo, dionice, stečaj, likvidacija, mjenica...)
- Administrative (upravni akt, inspekcijski nadzor, koncesija...)

Uses Croatian-aware stemming: strips vowel endings to match all grammatical cases (e.g., `krađ` matches krađa/krađe/krađi/krađom).

### Detail: VerdictExtractor

Pattern ordering matters — checks `partial` BEFORE `granted`/`denied`:
```
partial  → "Djelomično se usvaja" / "Usvaja se djelomično"
granted  → "Usvaja se tužbeni zahtjev" / "Žalba se uvažava"
denied   → "Odbija se tužbeni zahtjev" / "Odbija se kao neosnovana"
dismissed → "Odbacuje se tužba" / "Odbacuje se kao nedopuštena"
remanded → "Ukida se...vraća" / "Upućuje se na ponovno"
withdrawn → "Obustavlja se postupak" / "Povlači se tužba"
```

Extracts: outcome_type, raw_text, relief_granted/denied, damages_amount/currency.

### Detail: LegalArgumentExtractor

Segments decision text into positioned arguments:
1. Finds **Obrazloženje** (reasoning) section
2. Identifies argument markers by position: plaintiff (`Tužitelj navodi/ističe/tvrdi`), defendant (`Tuženik navodi/osporava`), court (`Sud nalazi/utvrđuje`)
3. Classifies each argument: procedural, substantive, or evidentiary
4. Determines acceptance: whether court accepted/rejected the argument

---

## Graph Data Quality & Maintenance

### DataQualityService (Sprint 8.4)

Automated quality checks:
- **Duplicate detection** — nodes with same key property but different IDs (per node type)
- **Orphan detection** — nodes with zero relationships
- **Inconsistency detection** — missing required properties (defined per node type)
- **Auto-merge** — keeps oldest node, moves all relationships, deletes duplicates
- **Quality score** — weighted: duplicates 50%, orphans 30%, consistency 20%
- **Email reports** — automated quality reports to admin email

### GraphDataIntegrityService

Cross-database integrity:
- **Orphan nodes** — Neo4j nodes without PostgreSQL records (paginated, 1000/batch)
- **Missing nodes** — PostgreSQL records without Neo4j nodes (chunked, 500/batch)
- **Dangling relationships** — relationships to nodes with unexpected labels
- **Full integrity report** — all checks + health_status flag

### CircuitBreaker (Graph)

In-memory or cache-backed circuit breaker for Neo4j:
- States: closed → open → half-open
- Configurable: failure_threshold (5), recovery_timeout (30s)
- Any failure in half-open → reopen
- Success in half-open → close

### GraphEmbeddingService (Sprint 4.5)

Node2Vec graph embeddings via Python script:
- Trains on Neo4j citation graph structure
- Stores 128d vectors in `decision_graph_embeddings` table
- Supports pgvector cosine similarity with PHP fallback
- Enables **hybrid similarity** (content embedding + graph structure)

---

## Complete Post-Upload Lifecycle (Corrected)

```
Stage 1: OCR & Text Extraction ────────── IMMEDIATE (pipeline)
  │  Download → S3 → Textract → Parse → Quality Check → OCR Route → Pick best
  │
Stage 2: Legal Metadata Extraction ─────── IMMEDIATE (pipeline)
  │  CreateMetadataStep → LegalMetadataExtractor
  │  (citations, courts, parties, classification, key phrases)
  │
Stage 3: Persist + pgvector Embeddings ── IMMEDIATE (pipeline)
  │  PersistReconstructedStep → CaseIngestPipeline
  │  → Quality gate → Normalize → Chunk (1200/150) → CaseVectorStoreService
  │  → cases_documents table (pgvector, per chunk)
  │  → Minimal Neo4j upsert (Case↔Doc relationship)
  │
  │  embedding_status = 'pending'    ← set here
  │  graph_sync_status = 'pending'   ← set here
  │
Stage 4: TextractDocument Embeddings ──── DEFERRED (queue)
  │  RegenerateTextractEmbeddings → TextractVectorStoreService
  │  → Delete old chunks → Re-chunk (1000/200) → Batch embed (20/batch)
  │  → textract_documents table (pgvector, per chunk)
  │  → embedding_status = 'synced'
  │
  │  OR (alternative/older path):
  │
  │  GenerateEmbeddingsJob → CourtDecisionVectorStoreService
  │  → Truncate to 1500 chars → Single embedding
  │  → External vector store → embedding_status = 'synced'
  │  → Chains: SyncTextractToGraph (5s delay)
  │
Stage 5: Full Neo4j Graph Sync ────────── DEFERRED (queue)
  │  SyncTextractToGraph → GraphRagOrchestrator::syncTextractJob()
  │  Pre-checks: Neo4j enabled? Available? Embeddings synced? Job succeeded?
  │  → CaseGraphSyncService: node + keywords + citations + similarity + tags
  │  → ArticleGraphSyncService: law articles + CITES_ARTICLE relationships
  │  → ContradictionDetection: LLM-based contradiction analysis
  │  → graph_sync_status = 'synced'
  │
Stage 6: Graph Analysis (ON-DEMAND) ───── USER-TRIGGERED
     ContradictionRadarService::scanNode() — proactive alerts
     CaseSearchService::analyzeCase() — strength/risk/timeline/evidence
     CaseIntakeService — full intake workflow with predictions
     DataQualityService — quality scoring and maintenance
```

---

## Full Neo4j Graph Schema (Confirmed from Source)

### Node Types

| Node | Key Properties | Source |
|------|---------------|--------|
| CaseDocument | case_id, doc_id, title, category, language, chunk_index, content_hash, source | CaseGraphSyncService |
| CourtDecisionDocument | case_number, court, jurisdiction, title, decision_date, decision_type | DecisionGraphSyncService |
| LawDocument | doc_id, title, law_number, law_code | GraphCitationLinker |
| Article | law_id, article_number, title, content, paragraph_count | ArticleGraphSyncService |
| Court | name | EndToEndSyncTest |
| Jurisdiction | name | EndToEndSyncTest |
| Judge | name | EndToEndSyncTest |
| Keyword | keyword | CaseGraphSyncService |
| Topic | topic | DataQualityService |
| Tag | name | DataQualityService |
| LegalConcept | name | DataQualityService |

### Relationship Types

| Relationship | From → To | Properties | Created By |
|-------------|-----------|------------|------------|
| CITES | Document → LawDocument | citation_type, article, paragraph, item, canonical | GraphCitationLinker |
| CITES_ARTICLE | CourtDecisionDocument → Article | paragraph, context | ArticleGraphSyncService |
| REFERENCES | Document → CaseDocument | citation_type | GraphCitationLinker |
| INTERPRETS | CourtDecisionDocument → LawDocument | article, interpretation_type, interpretation, binding | GraphCitationLinker |
| CONTAINS | LawDocument → Article | position | ArticleGraphSyncService |
| CONTRADICTS | Decision ↔ Decision | confidence, type, severity | ContradictionDetectionService |
| SUPERSEDED_BY | Law → Law | — | (external/manual) |
| OVERRULES | Decision → Decision | reason | ContradictionRadarService queries |
| DISTINGUISHES | Decision → Decision | — | ContradictionRadarService queries |
| MODIFIES | Decision → Decision | — | ContradictionRadarService queries |
| DECIDED_BY | CourtDecisionDocument → Court | — | EndToEndSyncTest |
| BELONGS_TO_JURISDICTION | CourtDecisionDocument → Jurisdiction | — | EndToEndSyncTest |
| PRESIDED_BY | CourtDecisionDocument → Judge | — | EndToEndSyncTest |
| HAS_KEYWORD | Document → Keyword | — | GraphKeywordLinker |
| SIMILAR_TO | Document → Document | score | GraphSimilarityLinker |

---

## Resilience Patterns

### Circuit Breakers

| Service | Breaker Name | Failure Threshold | Recovery Timeout | Cache-backed |
|---------|-------------|-------------------|------------------|-------------|
| TextractService | aws_textract | 5 (configurable) | 30s | No (separate CB class) |
| Graph services | CircuitBreaker | 5 | 30s | Optional (cache) |

### Retry Strategies

| Job | Tries | Backoff | Timeout |
|-----|-------|---------|---------|
| RegenerateTextractEmbeddings | 3 | [2s, 4s, 8s] | 600s |
| SyncTextractToGraph | 3 | [2s, 4s, 8s] | 300s |
| GenerateEmbeddingsJob | 2 | [120s, 600s] | 900s |
| TextractVectorStoreService (embed) | 3 | exponential (1s, 2s, 4s) | — |

### Failure Handling

All three deferred jobs follow the same pattern:
1. **`handle()` try/catch** — catches exceptions, updates status to `'failed'`, logs with context
2. **Re-throw** — allows Laravel queue retry mechanism
3. **`failed()` method** — permanent failure after all retries, marks status `'failed'` with attempt count in error message
4. **BroadcastsJobProgress** — real-time UI updates (started/completed/failed events)

SyncTextractToGraph has unique **silent failure** behavior:
- Neo4j disabled → return (no error)
- Neo4j unavailable → set `status='pending'`, error message, return (no retry)
- Embeddings not ready → return (no retry)
- Job not succeeded → return (no retry)
- Only actual sync errors trigger retry/failure

---

## Updated Gap Resolution

| # | Gap | v2 Status | v3 Status | New Finding |
|---|-----|-----------|-----------|-------------|
| 1 | Embedding job | ✅ RESOLVED | ✅ **FULLY ANALYZED** | TWO embedding jobs exist: RegenerateTextractEmbeddings (chunked, TextractDocuments) and GenerateEmbeddingsJob (truncated single-vector, CourtDecisionVectorStore). Neither uploads to OpenAI Vector Store. |
| 2 | Graph sync job | ✅ RESOLVED | ✅ **FULLY ANALYZED** | 4 pre-flight gates (config, availability, embedding status, job status). Silent skip on infrastructure issues. Chains through rich extractor ecosystem. |
| 3 | Ordering invariant | ✅ RESOLVED | ✅ **CONFIRMED** | Both jobs enforce: SyncTextractToGraph checks `embedding_status !== 'synced'`; GenerateEmbeddingsJob chains SyncTextractToGraph after success. |
| 4 | Case-level trigger | ⚠️ PARTIAL | ⚠️ **UNCHANGED** | Still no automatic trigger after all documents uploaded. |
| 5 | Notifications | ❌ OPEN | ⚠️ **PARTIALLY ADDRESSED** | BroadcastsJobProgress provides real-time Livewire events for all 3 deferred jobs. But no external notification (email/webhook/Slack). |
| 6 | Web UI pipeline | ✅ RESOLVED | ✅ **CONFIRMED** | — |
| 7 | Existing text check | ❌ OPEN | ❌ **UNCHANGED** | Sprint plan Task 2. |
| 8 | **NEW:** Embedding duplication | — | ⚠️ **NEW CONCERN** | Two embedding jobs both set `embedding_status`. If both run, one overwrites the other's work. Need to determine which is canonical. |
| 9 | **NEW:** TextractDocument search is O(n) | — | ⚠️ **NEW CONCERN** | `TextractVectorStoreService::searchSimilar()` loads ALL documents into PHP and computes cosine similarity iteratively. No pgvector `<=>` operator used. Will not scale. |
| 10 | **NEW:** Graph embedding coverage | — | ℹ️ **INFO** | Node2Vec graph embeddings exist but rely on external Python script. Coverage tracking available via `GraphEmbeddingService::getStatistics()`. |

---

## New Action Items

1. ~~Share RegenerateTextractEmbeddings~~ ✅ **DONE** — creates chunked TextractDocument embeddings, NOT OpenAI VS
2. ~~Share SyncTextractToGraph~~ ✅ **DONE** — 4 gates + GraphRagOrchestrator with rich extractors
3. **Determine canonical embedding job** — which of GenerateEmbeddingsJob vs RegenerateTextractEmbeddings is current? Are they used in different contexts? Could one be deprecated?
4. **Fix TextractDocument search** — use pgvector `<=>` operator instead of PHP iteration
5. **Wire `--skip-text` into pipeline** — Sprint Plan Task 2
6. **Add case-complete trigger** — auto-trigger case analysis after last document
7. **Add external notifications** — webhook/email beyond Livewire broadcasts

---

## Code Quality Observations

### Bug: Missing Brace in GenerateEmbeddingsJob

```php
// Line ~100 in GenerateEmbeddingsJob.php
if ($this->sourceType === 'textract_job') {
    $failedJob = TextractJob::find($this->sourceId);
    if ($failedJob && $this->attempts() >= $this->tries) {
        $failedJob->update([...]);
    }
// ← MISSING closing brace for the if ($this->sourceType === 'textract_job') block
if ($this->userId) {
    $this->broadcastFailed(...);
}
```

The `broadcastFailed` and the retry `throw` are inside the `textract_job` conditional, so batch failures won't broadcast or retry properly.

### Memory Management: TextractVectorStoreService

Good practices observed:
- Generator-based chunking (`chunkTextGenerator`) avoids loading all chunks into memory
- Explicit `gc_collect_cycles()` after each batch
- Memory usage logging before processing
- `unset()` calls to free large variables

### Defensive Coding: SyncTextractToGraph

Excellent resilience pattern — checks Neo4j availability via `GraphDatabaseService::isAvailable()` and `getHealthStatus()` before attempting any operations. Sets status back to 'pending' with informative error message rather than failing.
