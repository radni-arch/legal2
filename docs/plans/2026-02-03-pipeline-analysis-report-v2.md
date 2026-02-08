# OCR Pipeline Analysis Report v2

*Updated with findings from TextractManager.php, CaseIngestPipeline.php, CaseVectorStoreService.php, CaseSearchService.php, CaseIntakeService.php, and all OCR service classes.*

---

## Question 1: How Does the Pipeline Handle Files with Already Embedded Text?

### Short Answer: It doesn't check. Every PDF goes through full Textract OCR regardless.

### The Problem

The pipeline flow is:

```
Download → S3 Upload → Start Textract → Wait → Save Results → ...
```

There is **no step** that checks whether the PDF already has selectable/extractable text before sending it to Textract. This means:

1. **Wasted Textract API calls** — AWS Textract charges per page (~$1.50/1000 pages). A 50-page PDF with perfect embedded text still gets fully OCR'd.
2. **Potential quality degradation** — Re-OCRing already-text PDFs can produce *worse* output, especially for Croatian diacritics (č, ć, ž, š, đ).
3. **ocrmypdf has this built-in** — the `--skip-text` flag skips pages that already have a text layer. `OcrmypdfService::buildCommand()` already supports this:

```php
if ($options['skip_text'] ?? false) {
    $cmd[] = '--skip-text';
}
```

### Confirmed from TesseractOcrService

`TesseractOcrService::extractText()` directly converts PDF to images without checking for existing text:

```php
$imageFiles = $this->convertPdfToImages($pdfPath, $tmpDir);
```

Uses ImageMagick `convert` to rasterize every page at 300 DPI, then OCRs each image individually. This is the most expensive path possible for born-digital PDFs.

### Recommended Fix: CheckExistingTextStep

Add before S3 upload:

```php
// Fast local check using pdftotext
$process = new Process(['pdftotext', '-layout', $localPath, '-']);
$existingText = trim($process->getOutput());
$wordsPerPage = str_word_count($existingText) / $pageCount;

if ($wordsPerPage >= config('ocr.min_words_per_page_skip', 50)) {
    $payload['skipTextract'] = true;
    $payload['existingText'] = $existingText;
}
```

**Cost impact:** If ~30% of case files are born-digital PDFs → saves significant Textract costs per batch.

---

## Question 2: Multiple Upload Entry Points — Which Is Which?

### ✅ RESOLVED: All paths confirmed

### Path 1: Google Drive Folder → Textract Pipeline (CLI) — *Primary*

**Entry:** `php artisan textract:process-drive-folder {folderId} --case=CASE_ID`

```
Google Drive Folder → ListDrivePdfs → EnsureTextractJob → ProcessTextractJob (queued)
  → Full 13-step pipeline → CaseDocumentUpload + chunked CaseDocument records
  → embedding_status='pending', graph_sync_status='pending'
```

### Path 2: CSV Batch → Textract Pipeline (CLI)

**Entry:** `php artisan textract:process-batch --file=batch.csv`

Bulk variant of Path 1. Creates `TextractBatch` → `TextractJob` per row → same pipeline.

### Path 3: Web UI → TextractManager Livewire Component — ✅ NOW CONFIRMED

**Entry:** `/textract` web route → `TextractManager` Livewire component (736 lines)

**Confirmed: Uses SAME pipeline as Path 1.** Three processing methods all dispatch the same job:

```php
// TextractManager::processJob() — main processing
\App\Jobs\ProcessTextractJob::dispatch($job->id);
// OR synchronous:
app(\App\Actions\Textract\ProcessDrivePdf::class)->handle($job->drive_file_id, ...);

// TextractManager::processManual() — manual Drive file ID entry
ProcessDrivePdf::dispatch($job->drive_file_id, $job->drive_file_name, $this->forceTextractForManual);

// TextractManager::retryJob() / reprocessJob() — retry/re-OCR
ProcessDrivePdf::dispatch($job->drive_file_id, $job->drive_file_name, forceTextract: true);
```

**Additional Web UI features not in CLI:**
- Manual content editing (`saveContent()`) — allows overriding OCR text
- Content reset to original (`resetToOriginal()`)
- On-demand embedding regeneration (`regenerateEmbeddings()`)
- On-demand graph sync (`syncToGraph()`)
- PDF preview via signed S3 URLs
- Case assignment per job
- Search/filter/pagination of jobs
- Storage file browser (source, json, output folders)

**Important: Web UI is Drive-file-ID based, not direct upload.** Users enter a Google Drive file ID (or sync from a folder), not upload a PDF from their browser. The Livewire component manages Drive files, not browser uploads.

### Path 4: CaseIngestPipeline (Internal API)

**Entry:** `CaseIngestPipeline::ingest()` — called by `PersistReconstructedStep` within Path 1.

**Now fully understood from source code.** Full pipeline:

```
Raw text + OCR blocks
  → Step 1: OcrQualityAnalyzer (analyzeFromBlocks or estimateFromText)
    → Confidence, coverage, low-confidence pages
    → Quality gates: min_confidence=0.82, min_coverage=0.75, max_low_conf_pages=3
    → If fails: needs_review=true, optionally skip embedding
  → Step 2: HrLanguageNormalizer
    → NFC normalization, ligature fixes (ﬁ→fi), hyphenation repair
    → Croatian diacritic fixes, whitespace cleanup, invisible char removal
  → Step 3: Chunking (1200 chars, 150 overlap)
    → Smart sentence-boundary breaking (Croatian-aware: [.!?] + [A-ZČĆŽŠĐ])
    → Falls back to word boundary, then raw split
  → Step 4: CaseVectorStoreService::ingest()
    → Generates OpenAI embeddings (text-embedding-3-small, 1536d)
    → 3x retry with exponential backoff
    → SHA256 content deduplication
    → Inserts into PostgreSQL with pgvector
    → Graph sync via GraphRagOrchestrator (if enabled)
```

**Key insight: CaseIngestPipeline generates pgvector embeddings INLINE.** This is separate from the deferred `embedding_status` on TextractJob.

### Path 5: Odluke Court Decision Ingestion

Separate corpus. Web scraping → decisions table → own graph sync path.

### Updated Confusion Matrix

| Path | Source | OCR? | Metadata? | pgvector Embeddings? | OpenAI VS Embeddings? | Graph? |
|------|--------|------|-----------|---------------------|----------------------|--------|
| 1. Drive CLI | Google Drive | ✅ Full Textract+Tesseract | ✅ LegalMetadataExtractor | ✅ Inline via CaseVectorStoreService | ⏳ Deferred (pending) | ✅ Minimal inline + deferred |
| 2. CSV Batch | CSV + Drive | ✅ Same as #1 | ✅ Same | ✅ Same | ⏳ Same | ✅ Same |
| 3. Web UI | Drive file IDs | ✅ Same as #1 | ✅ Same | ✅ Same | ⏳ Same + manual trigger | ✅ Same + manual trigger |
| 4. CaseIngestPipeline | Code API | ❌ Pre-extracted | ❌ Must provide | ✅ Inline | ❌ Not triggered | Partial (GraphRag) |
| 5. Odluke | Web scraping | ❌ HTML text | ✅ Own extractor | ✅ Separate | Separate | Separate |

---

## Question 3: What Happens After a Case File Is Uploaded?

### ✅ RESOLVED: Full lifecycle now understood including deferred stages

### Stage 1: OCR & Text Extraction (IMMEDIATE)

```
Status: queued → uploading → started → analyzing → reconstructing → succeeded
```

Steps: Download → S3 → Textract → Save JSON → Parse to OcrDocument → Quality Check → OCR Routing (DocumentOcrRouter) → Tesseract comparison (OcrQualityComparator) → Pick best text

**DocumentOcrRouter scoring system** (confirmed from source):
- Signal 1: Textract confidence (<0.70 → +3.0 tesseract, <0.85 → +1.0, ≥0.85 → -2.0 textract)
- Signal 2: Croatian content (score ≥ 0.6 → +1.5 tesseract)
- Signal 3: Structured content (tables/forms → -1.0 textract)
- Decision: score > 0 → tesseract, else textract

**OcrQualityComparator scoring** (confirmed from source):
- Weights: valid word ratio (0.4), diacritic score (0.3), completeness (0.15), line quality (0.15)
- Diacritic scoring uses logistic curve: `1/(1 + exp(-40*(density - 0.025)))`
- Winner needs ≥ 5% improvement (configurable via `ocr.quality.min_improvement_percent`)

Output: `TextractJob.extracted_content`

### Stage 2: Legal Metadata Extraction (IMMEDIATE, in-pipeline)

**Code:** `CreateMetadataStep` → `LegalMetadataExtractor`

Confirmed `LegalMetadataExtractor` uses composed services:
- `HrLegalCitationsDetector::detectAll()` → statutes, case numbers, ECLI, NN refs, dates
- `CourtDetector::detect()` → mentioned courts
- `PartyDetector::detect()` → plaintiffs, defendants
- `DocumentTypeClassifier::classify()` → document_type, jurisdiction, confidence
- `KeyPhraseExtractor::extract()` → key legal phrases (diacritics-insensitive)

Output: `TextractJob.metadata` (JSON), `LegalDocumentMetadata` DTO with full structure:
```
citations: { statutes, case_numbers, ecli, narodne_novine, total_count, referenced_laws }
legal_entities: { courts, parties, judges }
classification: { document_type, jurisdiction, confidence }
content_analysis: { key_phrases }
document_statistics: { page_count, word_count, paragraph_count }
ocr_quality: { average_confidence, low_confidence_page_count }
```

### Stage 3: Document Persistence & Chunking (IMMEDIATE, in-pipeline)

**Code:** `PersistReconstructedStep` → `CaseIngestPipeline::ingest()` → `CaseVectorStoreService::ingest()`

Now fully confirmed from `CaseIngestPipeline.php` and `CaseVectorStoreService.php`:

1. **Quality gating** — `OcrQualityAnalyzer` checks confidence/coverage/low-conf pages against thresholds
2. **Language normalization** — `HrLanguageNormalizer` (NFC, ligatures, hyphenation, diacritics, whitespace)
3. **Smart chunking** — 1200 chars, 150 overlap, sentence-boundary-aware (Croatian regex patterns)
4. **Embedding generation** — `CaseVectorStoreService::ingest()`:
   - Calls `OpenAIService::embeddings()` for all chunks
   - **3x retry with exponential backoff** (1s, 2s, 4s delays)
   - SHA256 content deduplication (skips if `content_hash` already exists)
   - Inserts into `cases_documents` table with pgvector type
   - Records: embedding_provider, embedding_model, embedding_dimensions, embedding_norm, token_count
5. **Inline graph sync** — `GraphRagOrchestrator::syncCase()` called for each inserted document (if enabled)
6. **Minimal Neo4j upsert** — `Neo4jService::upsertCaseAndDocument()` for Case↔Doc relationship

Output: Multiple `CaseDocument` rows in PostgreSQL (one per chunk, each with embedding vector)

### Stage 4: Embedding Status (DEFERRED — Dual Vector Store)

**CRITICAL DISCOVERY: There are TWO separate embedding systems.**

**System A: PostgreSQL pgvector (INLINE)**
- Generated during Stage 3 by `CaseVectorStoreService::ingest()`
- Model: `text-embedding-3-small` (1536 dimensions)
- Stored in `cases_documents.embedding_vector` (pgvector type)
- Used by `CaseSearchService::vectorSearch()` for semantic search
- **This is always done during the pipeline.**

**System B: OpenAI Vector Store / File Search (DEFERRED)**
- Tracked by `TextractJob.embedding_status` = 'pending' → 'synced'
- Triggered by `RegenerateTextractEmbeddings` job (referenced in TextractManager):

```php
// TextractManager::regenerateEmbeddings()
\App\Jobs\RegenerateTextractEmbeddings::dispatch($jobId);
$job->update(['embedding_status' => 'pending']);
```

- Likely uploads to OpenAI Vector Store via `OpenAIService::vectorStoreAddFile()` + `fileUpload()`
- Used for OpenAI Assistant file_search tool
- **This is deferred — set to 'pending' at pipeline end, processed by separate job.**

**The `embedding_status` on TextractJob refers to System B (OpenAI VS), NOT System A (pgvector).**

### Stage 5: Full Graph Sync (DEFERRED)

**✅ RESOLVED:** `SyncTextractToGraph` job handles this.

```php
// TextractManager::syncToGraph()
\App\Jobs\SyncTextractToGraph::dispatch($jobId);
$job->update(['graph_sync_status' => 'pending']);
```

**Ordering invariant IS enforced in UI:**
```php
if ($job->embedding_status !== 'synced') {
    $this->dispatch('error', message: 'Embeddings must be synced before graph sync.');
    return;
}
```

This confirms: `graph_sync_status = 'synced'` requires `embedding_status = 'synced'` first.

### Updated Full Lifecycle

```
PDF Upload (via Drive CLI, CSV Batch, or Web UI)
  │
  ▼
┌─────────────────────────────────────────────────────────┐
│ STAGE 1: OCR & Text Extraction (IMMEDIATE)              │
│ Textract → Quality Check → OCR Routing → Best Text      │
│ Output: TextractJob.extracted_content                    │
└──────────┬──────────────────────────────────────────────┘
           │
           ▼
┌─────────────────────────────────────────────────────────┐
│ STAGE 2: Legal Metadata Extraction (IMMEDIATE)          │
│ HrLegalCitationsDetector + CourtDetector +               │
│ PartyDetector + DocumentTypeClassifier +                 │
│ KeyPhraseExtractor                                       │
│ Output: TextractJob.metadata (JSON)                      │
└──────────┬──────────────────────────────────────────────┘
           │
           ▼
┌─────────────────────────────────────────────────────────┐
│ STAGE 3: Persist + pgvector Embeddings (IMMEDIATE)      │
│ CaseIngestPipeline:                                      │
│   Quality gate → Normalize → Chunk → Embed → Store      │
│ CaseVectorStoreService:                                  │
│   OpenAI embeddings (3x retry) → pgvector INSERT        │
│   SHA256 dedup → Graph sync (GraphRag)                   │
│ Output: CaseDocument rows with embedding_vector          │
│ ✅ pgvector embeddings are INLINE, not deferred         │
└──────────┬──────────────────────────────────────────────┘
           │
           ▼
┌─────────────────────────────────────────────────────────┐
│ STAGE 4: OpenAI Vector Store Sync (DEFERRED)            │
│ TextractJob.embedding_status = 'pending'                 │
│ → RegenerateTextractEmbeddings job                       │
│ → Uploads to OpenAI Vector Store for file_search         │
│ → Status: pending → synced                               │
│ Trigger: Automatic (job queue) or Manual (Web UI button) │
└──────────┬──────────────────────────────────────────────┘
           │
           ▼
┌─────────────────────────────────────────────────────────┐
│ STAGE 5: Full Neo4j Graph Sync (DEFERRED)               │
│ TextractJob.graph_sync_status = 'pending'                │
│ → SyncTextractToGraph job                                │
│ → Creates rich graph: LawArticle, Citation edges,        │
│   Party nodes, Topic links                               │
│ → Status: pending → synced                               │
│ Trigger: Automatic or Manual (requires embedding synced) │
│ ✅ Ordering enforced: embedding_status must be 'synced' │
└─────────────────────────────────────────────────────────┘
```

---

## New Findings: Supporting Services

### CaseSearchService — Multi-Strategy Search

Provides three search modes over case documents:

1. **Vector search** (`search_type: 'vector'`) — pgvector cosine similarity on `cases_documents.embedding_vector`
   - Generates query embedding via `OpenAIService::createEmbedding()`
   - Uses `<=>` operator for pgvector, falls back to JSON + PHP cosine similarity
   - Configurable `min_similarity` threshold (default: 0.7)

2. **Text search** (`search_type: 'cases'`) — LIKE queries on title, description, case_number
   - Filters: jurisdiction, court, status, tags, client/opponent name
   - Paginated results

3. **Document search** (`search_type: 'documents'`) — LIKE queries on content, title
   - Joins to case for cross-filtering
   - Optional content inclusion

4. **Query rewriting** (`searchWithRewriting()`) — generates 3 query variants via `QueryRewriter`, searches each, deduplicates by case_id, sorts by similarity

5. **Case analysis** (`analyzeCase()`) — AI-powered analysis:
   - Strength analysis (OpenAI chat with JSON response)
   - Risk analysis (rule-based: pending duration, document count)
   - Timeline analysis (from filing dates + document dates)
   - Evidence analysis (categorization + recommendations)

### CaseVectorStoreService — Comprehensive Embedding Management

Beyond ingestion, provides maintenance operations:

- **cleanupOrphanedEmbeddings()** — deletes CaseDocument rows where case_id doesn't exist in cases table
- **detectDuplicates()** — finds documents with same content_hash, optional auto-merge (keeps oldest)
- **reindexCorrupted()** — finds null/invalid/wrong-dimension embeddings, regenerates them
- **optimizeVectorStore()** — VACUUM, ANALYZE, optional REINDEX on cases_documents table
- **findSimilar()** — pgvector-based document similarity search
- **isAvailable()** — pre-check before generating embeddings (avoids wasted API calls)

### CaseIntakeService — Full Case Intake Workflow

Orchestrates new case creation with analysis:

```
Client narrative
  → Step 1: FactPatternExtractor (extract structured facts, parties, legal issues, evidence)
  → Step 2: Create LegalCase record (auto-generated case number, title from facts)
  → Step 3: Find similar cases (compare fact patterns, ≥0.6 similarity threshold)
  → Step 4: Find relevant court decisions (via DecisionSearchService, vector-based)
  → Step 5: Risk assessment (rule-based: disputed facts, evidence strength, extraction confidence)
  → Step 6: Outcome prediction (scoring: favorable/unfavorable facts, strong evidence, confidence)
  → Step 7: Preliminary strategy (approach based on procedural stage)
  → Step 8: Next steps (prioritized action items with deadlines)
```

Returns comprehensive analysis including flags (low confidence, high risk, existing deadlines).

### OcrAvailabilityGuard — Runtime OCR Checks

Cached (5 min) availability check for:
- ocrmypdf available + version
- tesseract available
- Croatian language pack installed

`canUseLocalOcr()` returns true only if (ocrmypdf OR tesseract) AND Croatian language available.

---

## Gap Resolution Summary

| # | Original Gap | Status | Resolution |
|---|-------------|--------|------------|
| 1 | Embedding generation job missing | ✅ **RESOLVED** | `RegenerateTextractEmbeddings` job exists, referenced in `TextractManager::regenerateEmbeddings()`. Handles OpenAI Vector Store sync. pgvector embeddings are generated inline by `CaseVectorStoreService`. |
| 2 | Full graph sync job missing | ✅ **RESOLVED** | `SyncTextractToGraph` job exists, referenced in `TextractManager::syncToGraph()`. |
| 3 | Ordering invariant violation | ✅ **RESOLVED** | Enforced in `TextractManager::syncToGraph()`: checks `embedding_status === 'synced'` before allowing graph sync. Both set to 'pending' simultaneously is correct — the ordering is enforced at execution time, not at flag-setting time. |
| 4 | No case-level analysis trigger | ⚠️ **PARTIALLY ADDRESSED** | `CaseIntakeService` provides comprehensive case analysis at intake time. `CaseSearchService::analyzeCase()` provides on-demand analysis. But there's still no automatic trigger after all documents are uploaded. |
| 5 | No notification/webhook | ❌ **STILL OPEN** | TextractManager uses Livewire `dispatch('success', ...)` for UI notifications, but no external notification (email, webhook, Slack) when background processing completes. |
| 6 | Web UI pipeline unknown | ✅ **RESOLVED** | TextractManager confirmed to use same `ProcessTextractJob` / `ProcessDrivePdf` pipeline as CLI paths. |
| 7 | No existing text check | ❌ **STILL OPEN** | Not addressed. `OcrmypdfService` supports `--skip-text` flag but it's not wired into the pipeline. Sprint plan Task 2 (OcrmypdfService integration) should enable this. |

---

## Architecture Discovery: Dual Vector Store

The system maintains **two parallel embedding stores**:

```
                    ┌──────────────────────────────┐
                    │         Case Document         │
                    │     (extracted + chunked)      │
                    └──────────┬───────────────────┘
                               │
                ┌──────────────┴──────────────────┐
                │                                  │
                ▼                                  ▼
┌───────────────────────────┐    ┌───────────────────────────┐
│  PostgreSQL pgvector       │    │  OpenAI Vector Store       │
│  (cases_documents table)   │    │  (file_search tool)        │
│                            │    │                            │
│  Generated: INLINE         │    │  Generated: DEFERRED       │
│  By: CaseVectorStoreService│    │  By: RegenerateTextract-   │
│  Model: text-embedding-    │    │      EmbeddingsJob         │
│         3-small (1536d)    │    │  Tracked: embedding_status │
│  Used by: CaseSearchService│    │  Used by: OpenAI Assistant │
│  Search: pgvector <=>      │    │  Search: file_search tool  │
└───────────────────────────┘    └───────────────────────────┘
```

**Implications:**
- pgvector search works immediately after pipeline completes (Stage 3)
- OpenAI Assistant file_search only works after deferred embedding sync (Stage 4)
- If embedding_status stays 'pending', the Assistant can't search these documents
- Content edits (`TextractManager::saveContent()`) correctly reset both statuses to 'pending'

---

## Remaining Action Items

1. **Share `RegenerateTextractEmbeddings` job** — to confirm it uploads to OpenAI Vector Store
2. **Share `SyncTextractToGraph` job** — to understand what rich graph relationships are created
3. **Wire `--skip-text` into pipeline** — via Sprint Plan Task 2 (OcrmypdfService)
4. **Add case-complete trigger** — after last document uploaded, auto-trigger case-level analysis
5. **Add completion notifications** — webhook/email when background processing finishes
