# OCR Pipeline Analysis Report

## Question 1: How Does the Pipeline Handle Files with Already Embedded Text?

### Short Answer: It doesn't check. Every PDF goes through full Textract OCR regardless.

### The Problem

Looking at the pipeline steps in `ProcessDrivePdf` (doc 6), the flow is:

```
Download → S3 Upload → Start Textract → Wait → Save Results → Collect Lines → ...
```

There is **no step** that checks whether the PDF already has selectable/extractable text before sending it to Textract. This means:

1. **Wasted Textract API calls** — AWS Textract charges per page. A 50-page PDF with perfect embedded text still gets fully OCR'd.
2. **Potential quality degradation** — Textract re-OCRing an already-text PDF can sometimes produce *worse* output than the original embedded text (especially for Croatian diacritics).
3. **ocrmypdf has this built-in** — the `--skip-text` flag skips pages that already have a text layer, and `--redo-ocr` replaces bad existing text.

### Where It Should Be Checked

There are two natural places:

**Option A: Before Textract (early exit)** — Add a step between `DownloadDriveFileStep` and `UploadInputToS3Step`:

```
DownloadDriveFile → [NEW: CheckExistingTextStep] → UploadInputToS3 → StartAnalysis → ...
```

This step would run `pdftotext` (fast, local) and if it finds substantial text, skip Textract entirely and use the extracted text directly.

**Option B: In TesseractOcrStep (ocrmypdf handles it)** — When we integrate ocrmypdf (from the sprint plan), it natively supports `--skip-text` which handles this transparently.

### What the Code Actually Does Now

In `TesseractOcrService::extractText()` (doc 38), there's no text-layer check:
```php
// Directly converts PDF to images and OCRs — never checks if text already exists
$imageFiles = $this->convertPdfToImages($pdfPath, $tmpDir);
```

In `DocumentOcrRouter::recommend()` (doc 28), routing is based on the Textract output quality, not on whether the PDF had original text:
```php
// Only analyzes the Textract-extracted text and blocks
$textAnalysis = $this->analyzeDocument($text);
$blockAnalysis = $this->analyzeTextractBlocks($blocks);
```

### Recommended Fix

Add a `CheckExistingTextStep` as the first pipeline step after download:

```php
class CheckExistingTextStep
{
    public function handle(array $payload, Closure $next): mixed
    {
        $localPath = $payload['localPath'];
        
        // Fast local text extraction using pdftotext
        $process = new Process(['pdftotext', '-layout', $localPath, '-']);
        $process->setTimeout(30);
        $process->run();
        
        $existingText = trim($process->getOutput());
        $wordCount = str_word_count($existingText);
        
        // Get page count
        $pageProcess = new Process(['pdfinfo', $localPath]);
        $pageProcess->run();
        preg_match('/Pages:\s+(\d+)/', $pageProcess->getOutput(), $m);
        $pageCount = (int) ($m[1] ?? 0);
        
        // Heuristic: if there's substantial text (>50 words per page average), skip OCR
        $wordsPerPage = $pageCount > 0 ? $wordCount / $pageCount : 0;
        $minWordsPerPage = (int) config('ocr.min_words_per_page_skip', 50);
        
        if ($wordsPerPage >= $minWordsPerPage) {
            // PDF already has good text — skip Textract entirely
            $payload['existingTextFound'] = true;
            $payload['existingText'] = $existingText;
            $payload['existingWordCount'] = $wordCount;
            $payload['skipTextract'] = true;
        } else {
            $payload['existingTextFound'] = $wordCount > 0;
            $payload['skipTextract'] = false;
        }
        
        return $next($payload);
    }
}
```

Then modify `StartAnalysisStep`, `WaitAndFetchStep`, etc. to check `$payload['skipTextract']` and short-circuit.

### Cost Impact

If ~30% of your case files are born-digital PDFs (not scanned), this could save significant Textract costs. At AWS pricing of ~$1.50 per 1000 pages for LAYOUT feature, a batch of 500 documents averaging 20 pages = $15 saved per batch.

---

## Question 2: Multiple Upload Entry Points — Which Is Which?

### Identified Upload Paths

Based on the code you shared and our past conversations, there are **at least 4 distinct entry points** for getting documents into the system:

### Path 1: Google Drive Folder → Textract Pipeline (CLI)

**Entry:** `php artisan textract:process-drive-folder {folderId} --case=CASE_ID`

**Code:** `TextractProcessDriveFolder` command (doc 14) → dispatches `ProcessTextractJob` per file

**Flow:**
```
Google Drive Folder
  → ListDrivePdfs (list files)
  → EnsureTextractJob (create/find job record)
  → ProcessTextractJob (queued)
    → Full 13-step pipeline (download, S3, Textract, OCR routing, metadata, persist)
    → Creates: TextractJob, CaseDocumentUpload, CaseDocument
    → Triggers: Embedding (pending), Graph sync (pending), Neo4j upsert
```

**This is the primary, fully-featured path.** It's the one all your pipeline code supports.

### Path 2: CSV Batch → Textract Pipeline (CLI)

**Entry:** `php artisan textract:process-batch --file=path/to/batch.csv`

**Code:** `TextractProcessBatch` command (doc 13)

**Flow:**
```
CSV file (drive_file_id, drive_file_name, case_id)
  → Creates TextractBatch record
  → Creates TextractJob per row
  → Dispatches ProcessTextractJob per job
  → Same pipeline as Path 1
```

**This is a bulk variant of Path 1.** Used for re-processing or importing known file lists.

### Path 3: Web UI → TextractManager Livewire Component

**Entry:** `/textract` web route → `TextractManager` Livewire component

**Code:** Referenced in past conversations (736-line Livewire component)

**Flow (likely):**
```
User uploads PDF via web form
  → TextractManager Livewire component
  → Creates TextractJob
  → Dispatches ProcessTextractJob (or runs sync)
  → Same pipeline as Path 1
```

**Status: I don't have the TextractManager source in the provided documents**, but from the smoke test code (past conversation), the web upload route is `/api/textract/upload` or the Livewire component handles it directly.

### Path 4: Direct API / CaseIngestPipeline

**Entry:** `CaseIngestPipeline::ingest()` called directly

**Code:** Referenced in `PersistReconstructedStep` (doc 20):
```php
$result = $this->ingestPipeline->ingest(
    caseId: $case->id,
    docId: 'doc-' . $driveFileId,
    rawText: $fullText,
    ocrBlocks: $blocks,
    options: $ingestOptions
);
```

**Flow:**
```
Raw text + OCR blocks
  → CaseIngestPipeline::ingest()
  → Quality gating (min confidence, min coverage)
  → Chunking (1200 chars, 150 overlap)
  → Creates CaseDocument records (with chunks)
  → Sets embedding_status = 'pending'
  → Sets graph_sync_status = 'pending'
```

**This is NOT an upload path** — it's the ingestion service that Path 1 calls internally. But it *could* be called directly if you already have extracted text from another source.

### Path 5(?): Odluke Court Decision Ingestion

**Entry:** `OdlukeIngestService` (from past conversations about odluke.sudovi.hr)

**Flow:**
```
HTML court decisions from web scraping
  → OdlukeIngestService
  → Extract metadata (case number, court, date, etc.)
  → Store in decisions table
  → Optional: Graph sync via GraphDatabaseService
```

**This is a separate corpus** (web-scraped court decisions), not the same as case file uploads.

### The Confusion Matrix

| Path | Source | Has OCR? | Has Metadata? | Has Embedding? | Has Graph? |
|------|--------|----------|---------------|----------------|------------|
| 1. Drive Folder CLI | Google Drive | ✅ Full Textract+Tesseract | ✅ LegalMetadataExtractor | ✅ Pending (set) | ✅ Neo4j upsert |
| 2. CSV Batch CLI | CSV + Drive | ✅ Same as #1 | ✅ Same as #1 | ✅ Same as #1 | ✅ Same as #1 |
| 3. Web UI Upload | Browser upload | ❓ Unknown | ❓ Unknown | ❓ Unknown | ❓ Unknown |
| 4. CaseIngestPipeline | Code API | ❌ Expects pre-extracted text | ❌ Must provide | ✅ Sets pending | ❌ No graph upsert |
| 5. Odluke Ingest | Web scraping | ❌ HTML text | ✅ Own extractor | ✅ Separate | ✅ Separate |

### The Problem

**Path 3 (Web UI) is the one users interact with, and I can't confirm it goes through the same complete pipeline as Path 1.** From the code analysis (the 736-line `TextractManager.php` mentioned in past conversations), the web upload may have its own simplified flow that doesn't include all 13 pipeline steps.

### What You Should Check

1. **Open `app/Livewire/TextractManager.php`** — does it call `ProcessDrivePdf::run()` or `ProcessTextractJob::dispatch()`? Or does it have its own simplified processing?
2. **Check if there's a direct file upload route** (not Drive-based) — the web UI might upload to local storage or S3 directly, bypassing the Google Drive download step.
3. **Look for any OpenAI-related upload endpoints** — from your mention "not sure if that is upload to OpenAI or to web application", there might be a vector store upload path (e.g., uploading documents to OpenAI's assistant file search) that's separate from the Textract pipeline.

### Recommendation

**Consolidate to one pipeline with multiple entry points:**

```
[Web Upload] ─┐
[Drive CLI]  ──┤──→ Unified CaseFilePipeline ──→ [Textract/OCR] ──→ [Ingest] ──→ [Embed] ──→ [Graph]
[CSV Batch]  ──┤
[API Direct] ──┘
```

Currently, the paths diverge after the entry point, and it's unclear if the web upload gets the same quality of processing (metadata extraction, OCR quality checks, Tesseract comparison, etc.).

---

## Question 3: What Happens After a Case File Is Uploaded?

### The Post-Upload Pipeline (from the code)

Based on `ProcessDrivePdf` (doc 6), `PersistReconstructedStep` (doc 20), and the invariants from past conversations, here's the complete chain:

### Stage 1: OCR & Text Extraction (Immediate)

```
TextractJob.status: queued → uploading → started → analyzing → reconstructing → succeeded
```

Steps: Download → S3 → Textract → Save JSON → Parse to OcrDocument → Quality Check → OCR Routing → Tesseract comparison → Pick best text

**Output:** `extracted_content` column on TextractJob, `finalText` in payload

### Stage 2: Legal Metadata Extraction (Immediate, in-pipeline)

**Code:** `CreateMetadataStep` (doc 17) → `LegalMetadataExtractor` (doc 31)

Extracts:
- Legal citations (statute references, case numbers, ECLI, Narodne Novine)
- Courts mentioned
- Parties (plaintiff, defendant)
- Document classification (judgment, motion, contract + jurisdiction)
- Key legal phrases
- Dates

**Output:** `TextractJob.metadata` column (JSON), `LegalDocumentMetadata` DTO

### Stage 3: Document Persistence & Chunking (Immediate, in-pipeline)

**Code:** `PersistReconstructedStep` (doc 20) → `CaseIngestPipeline::ingest()`

What happens:
1. Creates `CaseDocumentUpload` record (file metadata, SHA256, disk path)
2. Calls `CaseIngestPipeline::ingest()` which:
   - Quality-gates the text (min confidence, min coverage from config)
   - Chunks the text (1200 chars, 150 overlap — configurable)
   - Creates `CaseDocument` records per chunk
   - Each chunk gets: content, content_hash, metadata, chunk_index, source info
3. Falls back to single-document save if pipeline fails

**Output:** Multiple `CaseDocument` rows in Postgres (one per chunk)

### Stage 4: Embedding Generation (Deferred — status flag only)

**Code:** In `ProcessDrivePdf::handle()` (doc 6):
```php
$payload['job']->update([
    'status' => 'succeeded',
    'embedding_status' => 'pending',  // ← Just sets the flag
    'graph_sync_status' => 'pending', // ← Just sets the flag
]);
```

**What's actually happening:**
- The pipeline **sets `embedding_status = 'pending'`** but does NOT generate embeddings inline.
- There must be a separate job/command that picks up `pending` records and generates embeddings.
- The configured model is `text-embedding-3-small` (OpenAI), with 1536 dimensions.

**⚠️ I don't see the actual embedding generation job in the provided code.** It's likely in a separate service (e.g., `TextractVectorStoreService` mentioned in past conversations) that runs on a schedule or queue.

### Stage 5: Neo4j Graph Sync (Partial — inline upsert only)

**Code:** At the bottom of `PersistReconstructedStep` (doc 20):
```php
if ((bool) config('neo4j.sync.enabled', true)) {
    $neo = app(\App\Services\Neo4jService::class);
    $neo->upsertCaseAndDocument(
        $case->id,
        $case->title ?? $case->case_number ?? $case->id,
        'doc-' . $driveFileId,
        $fileName
    );
}
```

This does a **minimal upsert**: creates/updates a `Case` node and a `CaseDocument` node with a `BELONGS_TO` relationship. That's it.

**What it does NOT do (but should per your invariants):**
- Create `LawArticle` nodes from extracted statute citations
- Create `CourtDecision` relationships from case number references
- Link to `Topic` or `LawVersion` nodes
- Create citation edges between documents
- Full property sync (content_hash, metadata, etc.)

**⚠️ The `graph_sync_status = 'pending'` flag suggests a more comprehensive graph sync should happen later**, but I don't see that job in the provided code either.

### The Full Lifecycle Diagram

```
PDF Upload
  │
  ▼
┌─────────────────────────────────┐
│ STAGE 1: OCR (immediate)        │
│ Textract → Quality Check →      │
│ OCR Routing → Best Text         │
│ Output: extracted_content       │
└──────────┬──────────────────────┘
           │
           ▼
┌─────────────────────────────────┐
│ STAGE 2: Metadata (immediate)   │
│ Citations, Courts, Parties,     │
│ Document Type, Key Phrases      │
│ Output: TextractJob.metadata    │
└──────────┬──────────────────────┘
           │
           ▼
┌─────────────────────────────────┐
│ STAGE 3: Persist (immediate)    │
│ CaseDocumentUpload + chunked    │
│ CaseDocument records            │
│ Output: DB rows with chunks     │
└──────────┬──────────────────────┘
           │
           ▼
┌─────────────────────────────────┐
│ STAGE 4: Embedding (DEFERRED)   │
│ embedding_status = 'pending'    │
│ → Separate job generates        │
│   OpenAI embeddings per chunk   │
│ → Status: pending → synced      │
│ ⚠️ Job code not in provided     │
│   documents                     │
└──────────┬──────────────────────┘
           │
           ▼
┌─────────────────────────────────┐
│ STAGE 5: Graph Sync (PARTIAL)   │
│ graph_sync_status = 'pending'   │
│ → Inline: minimal Case ↔ Doc   │
│   upsert to Neo4j               │
│ → Full sync: DEFERRED           │
│ ⚠️ Full graph sync job not in   │
│   provided documents            │
└─────────────────────────────────┘
```

### What's Missing / Gaps

1. **Embedding generation job** — something needs to pick up `embedding_status = 'pending'` and call OpenAI. Not visible in provided code.
2. **Full graph sync job** — something needs to pick up `graph_sync_status = 'pending'` and create rich Neo4j relationships (citation links, law references, party nodes). The inline upsert is just Case ↔ Document.
3. **Ordering invariant** — from your invariants doc: "graph_sync_status ∈ {synced, processing} → embedding_status = synced". This means graph sync should wait for embeddings. Currently both are set to 'pending' simultaneously.
4. **No case-level analysis trigger** — after all documents for a case are uploaded, there's no automated "now analyze the full case" step (e.g., cross-document citation analysis, timeline construction, contradiction detection).
5. **No notification/webhook** — nobody gets told when processing completes.
