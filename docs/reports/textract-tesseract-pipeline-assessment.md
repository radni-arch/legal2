# Textract/Tesseract File Ingestion Pipeline Assessment

**Date:** 2026-02-01
**Scope:** Full assessment of the dual-engine OCR document ingestion pipeline
**Codebase:** AI Legal War Machine (Laravel TALL Stack)

---

## Executive Summary

The file ingestion pipeline implements a **dual-engine OCR architecture** combining AWS Textract and local Tesseract OCR, with intelligent routing optimized for Croatian legal documents. The pipeline is well-structured using Laravel's Pipeline pattern with 13 discrete steps, from file download through to embedding generation and graph database synchronization.

**Overall assessment: Production-capable with identified areas for improvement.**

### Key Strengths
- Intelligent OCR routing based on document characteristics
- Croatian-specific language optimization (diacritics, legal terms)
- Comprehensive error recovery with exponential backoff
- Memory-efficient chunking via PHP generators
- Circuit breaker protection on AWS API calls

### Key Concerns
- Single point of failure on Google Drive download step
- No dead-letter queue for permanently failed documents
- Quality thresholds reference a `vizra-adk.ocr` config namespace that differs from the `ocr` config file
- Limited observability — no metrics export or alerting integration

---

## 1. Architecture Overview

### Pipeline Flow

```
Google Drive PDF
    │
    ├─ EnsureJobStep ─────── Create/validate TextractJob record
    ├─ DownloadDriveFileStep  Fetch PDF from Google Drive
    ├─ UploadInputToS3Step ── Store in s3://bucket/textract/input/
    ├─ StartAnalysisStep ──── Submit to AWS Textract (LAYOUT, FORMS, TABLES, SIGNATURES)
    ├─ WaitAndFetchStep ───── Poll every 5s, max 30 minutes
    ├─ SaveResultsStep ────── Persist JSON to S3 + local
    ├─ CollectLinesStep ───── Group Textract blocks by page
    ├─ CheckOcrQualityStep ── Analyze confidence & coverage
    ├─ TesseractOcrStep ───── Route to Tesseract if needed, compare, pick winner
    ├─ CreateMetadataStep ─── Extract legal metadata
    ├─ ReconstructPdfStep ─── Build searchable PDF
    ├─ UploadOutputStep ───── Store output PDF to S3
    └─ PersistReconstructedStep  Finalize job record
         │
         ├─ GenerateEmbeddingsJob (30s delay)
         │    ├─ Chunk text (1000 chars, 200 overlap)
         │    ├─ OpenAI text-embedding-3-small
         │    └─ Store as TextractDocument rows
         │
         ├─ ExtractTablesFromTextractJob (10s delay)
         └─ SyncTextractToGraph (if Neo4j enabled)
```

**Orchestrator:** `App\Actions\Textract\ProcessDrivePdf` (`app/Actions/Textract/ProcessDrivePdf.php`)
Uses `lorisleiva/laravel-actions` for Action/Job duality and Laravel's `Illuminate\Pipeline\Pipeline`.

### Data Model

| Model | Table | Purpose |
|-------|-------|---------|
| `TextractJob` | `textract_jobs` | Primary processing record — status, content, metadata |
| `TextractDocument` | `textract_documents` | Chunked content with embeddings (vector store) |
| `TextractBatch` | `textract_batches` | Batch processing container |
| `FailedIngestion` | `failed_ingestions` | Error recovery tracking with exponential backoff |

---

## 2. OCR Engine Analysis

### 2.1 AWS Textract (Primary Engine)

**Service:** `App\Services\TextractService`

- Async document analysis via `startDocumentAnalysis()`
- Feature types: LAYOUT, FORMS, TABLES, SIGNATURES
- Polling-based result retrieval (5s intervals, 30min max)
- Circuit breaker: 5 failures → open, 2 successes → close, 60s timeout
- Results saved to S3 (`textract/json/`) and local storage

**Strengths:**
- Excellent structured content extraction (tables, forms)
- AWS-managed scaling
- Signature detection capability

**Weaknesses:**
- ~$1.50/page for full analysis features (cost consideration at scale)
- Croatian diacritics (č, ć, ž, š, đ) occasionally misrecognized
- 30-minute polling max could timeout on large documents

### 2.2 Tesseract OCR (Secondary/Fallback Engine)

**Service:** `App\Services\Ocr\TesseractOcrService`

- PDF → TIFF conversion via ImageMagick at 300 DPI
- Per-page OCR with configurable timeout (120s/page)
- Croatian + English language packs (`hrv+eng`)
- PSM 3 (fully automatic page segmentation), OEM 1 (LSTM neural net)

**Strengths:**
- Superior Croatian diacritic recognition
- No per-page cost (local processing)
- Layout preservation mode

**Weaknesses:**
- CPU-bound — no GPU acceleration configured
- ImageMagick PDF conversion can be memory-intensive on large documents
- No parallel page processing (sequential per-page OCR)

### 2.3 Intelligent Routing

**Router:** `App\Services\Ocr\DocumentOcrRouter`

Decision rules (evaluated in order):

| Priority | Condition | Engine | Rationale |
|----------|-----------|--------|-----------|
| 1 | Textract confidence < 0.70 | Tesseract | Low-quality Textract output |
| 2 | Croatian score ≥ 0.60 AND Textract confidence < 0.85 | Tesseract | Better diacritics |
| 3 | Structured content ratio ≥ 0.30 | Textract | Superior table/form extraction |
| 4 | Textract confidence ≥ 0.85 | Textract | Already high quality |
| Default | No rules triggered | Textract | Default engine |

Croatian detection uses a **weighted score** combining:
- Legal term frequency (60% weight): 35 Croatian legal terms
- Diacritic density (40% weight): presence of č, ć, ž, š, đ

Post-routing comparison (`OcrQualityComparator`) requires Tesseract to show >5% improvement to win — preventing unnecessary engine switches.

**Finding:** The routing logic has a subtle precedence issue. Rule 3 (structured content) can override Rule 2 (Croatian text), meaning a Croatian legal document with tables would use Textract despite potentially poor diacritic handling. This is likely intentional for table fidelity but should be documented as a known trade-off.

---

## 3. Quality Assurance

### 3.1 Quality Analysis

**Analyzer:** `App\Services\Ocr\OcrQualityAnalyzer`

Metrics computed from Textract blocks:
- **Confidence** (0-1): Average word-level confidence
- **Coverage** (0-1): Proportion of document successfully extracted
- **Low-confidence pages**: Pages below quality threshold

**Review triggers** (from `CheckOcrQualityStep`):
- Overall confidence < 82%
- Coverage < 75%
- More than 3 low-confidence pages

**Finding:** The quality thresholds in `CheckOcrQualityStep` reference `config('vizra-adk.ocr.min_confidence')` but the actual config file is `config/ocr.php`. This config key mismatch means the step always falls through to hardcoded defaults (0.82, 0.75, 3). This works but means the thresholds are not configurable via environment variables as intended.

### 3.2 Content Validation

From `config/textract.php`:
- Minimum content: 10 characters
- Maximum content: 10MB
- Empty content blocked by default

---

## 4. Error Handling & Recovery

### 4.1 Job-Level Retries

| Job | Max Retries | Backoff | Timeout |
|-----|-------------|---------|---------|
| `ProcessTextractJob` | 3 | 60s, 300s, 900s | 1800s |
| `GenerateEmbeddingsJob` | 2 | 120s, 600s | 900s |
| `ExtractTablesFromTextractJob` | 2 | — | 600s |

### 4.2 Failed Ingestion Recovery

The `FailedIngestion` model provides structured error recovery:

- **Failure categories:** Download, extraction, embedding, graph sync, network
- **Exponential backoff:** 1min → 2min → 4min → 8min → 16min (capped at 1 hour)
- **Max attempts:** 5 (configurable per record)
- **Status flow:** pending → retrying → failed/succeeded/abandoned
- **Manual override:** `resetRetryCounter()` and `markAbandoned()`

**Finding:** The `FailedIngestion` model has a documented PHP 8.4 compiler bug that causes stack overflow when running 8+ tests consecutively. This is noted as a PHP 8.4.14 issue, not a code defect. Production environments using PHP 8.4 should monitor for this.

### 4.3 Circuit Breaker

AWS Textract calls are protected by a circuit breaker:
- Opens after 5 consecutive failures
- Closes after 2 consecutive successes
- 60s timeout in open state
- 30s retry-after period

### 4.4 Cascade Protection

- Embedding failure blocks graph sync (prevents partial state)
- Tesseract failure falls back to Textract output (graceful degradation)
- TextractDocument deletion cascades Neo4j node removal via Observer
- Pipeline exceptions mark TextractJob as `failed` with error message

---

## 5. Performance Characteristics

### 5.1 Processing Pipeline

| Stage | Typical Duration | Bottleneck |
|-------|-----------------|------------|
| Drive download | 2-10s | Network I/O |
| S3 upload | 1-5s | Network I/O |
| Textract analysis | 30s-5min | AWS processing |
| Tesseract OCR (if routed) | 10s-120s/page | CPU |
| Embedding generation | 100-200ms/batch | OpenAI API |
| Graph sync | 1-5s | Neo4j write |

### 5.2 Memory Management

- Generator pattern (`chunkTextGenerator`) for memory-efficient chunking
- Batch processing: 20 chunks per embedding API call
- Garbage collection between batches
- Temp file cleanup in `finally` blocks (Tesseract)

### 5.3 Concurrency Limits

| Queue | Max Concurrent |
|-------|---------------|
| `textract` | 3 |
| `embeddings` | 2 |
| `textract-tables` | 2 |

### 5.4 Storage Layout

```
s3://bucket/
├── textract/input/      # Source PDFs
├── textract/output/     # Reconstructed searchable PDFs
├── textract/json/       # Raw Textract analysis results
└── textract/tables/     # Extracted table data
```

---

## 6. Test Coverage Assessment

### Existing Test Files

| Category | Tests | Coverage Area |
|----------|-------|---------------|
| Unit | `TextractJobTest`, `TextractDocumentTest` | Model logic, scopes, relationships |
| Unit | `ProcessTextractJobTest`, `GenerateEmbeddingsJobTest` | Job dispatch, retry, timeout |
| Unit | `TextractServiceTest` | AWS API wrapper |
| Unit | `TesseractOcrServiceTest` | Local OCR extraction |
| Unit | `DocumentOcrRouterTest` | Routing decisions |
| Unit | `CheckOcrQualityStepTest`, `TesseractOcrStepTest` | Pipeline step logic |
| Unit | `TextractDocumentObserverTest` | Neo4j cleanup |
| Feature | `TextractPipelineIntegrationTest` | Full pipeline flow |
| Feature | `TextractErrorScenariosTest` | Error handling paths |
| Feature | `TextractPerformanceTest` | Performance benchmarks |
| Feature | `TextractDeletionSyncTest` | Cleanup verification |
| Integration | `TextractPipelineFlowTest` | End-to-end flow |
| Browser | `TextractManagerTest`, `TextractPdfPreviewTest` | UI testing |

**Coverage is comprehensive** across unit, feature, integration, and browser layers. The OCR routing and quality analysis steps have dedicated unit tests.

---

## 7. Identified Issues & Recommendations

### 7.1 Critical

| # | Issue | Location | Recommendation |
|---|-------|----------|----------------|
| C1 | Config namespace mismatch: `vizra-adk.ocr.*` vs `ocr.*` | `CheckOcrQualityStep.php:47-49` | Update to use `ocr.routing.*` or create `vizra-adk` config |
| C2 | No dead-letter handling for permanently failed documents | `FailedIngestion` model | Add scheduled command to alert on documents exceeding max attempts |

### 7.2 High

| # | Issue | Location | Recommendation |
|---|-------|----------|----------------|
| H1 | Sequential page OCR in Tesseract (no parallelism) | `TesseractOcrService::extractText()` | Consider `pcntl_fork` or queue-per-page for large documents |
| H2 | No monitoring/alerting integration | Pipeline-wide | Export processing metrics (duration, engine choice, failure rate) to monitoring system |
| H3 | `forceTextract` parameter bypasses routing but not quality check | `ProcessDrivePdf::handle()` | Ensure `forceTextract` flag propagates correctly through TesseractOcrStep |

### 7.3 Medium

| # | Issue | Location | Recommendation |
|---|-------|----------|----------------|
| M1 | Textract polling uses fixed 5s interval | `WaitAndFetchStep` | Consider adaptive polling (start fast, slow down) to reduce API calls |
| M2 | No document deduplication check | `EnsureJobStep` | Add content hash comparison to skip re-processing identical documents |
| M3 | Embedding batch size config (100) differs from actual usage (20) | `config/textract.php:34` vs `TextractVectorStoreService` | Align config with implementation |
| M4 | ImageMagick `convert` command for PDF→TIFF has no memory limit | `TesseractOcrService::convertPdfToImages()` | Add `-limit memory 512MiB` flag to prevent OOM on large PDFs |

### 7.4 Low

| # | Issue | Location | Recommendation |
|---|-------|----------|----------------|
| L1 | Routing rule 3 can override rule 2 for Croatian+structured docs | `DocumentOcrRouter::recommend()` | Document this trade-off or implement weighted multi-engine strategy |
| L2 | No S3 lifecycle policy mentioned for input/output cleanup | Storage config | Implement S3 lifecycle rules for old input/json files |
| L3 | PHP 8.4 compiler bug affecting `FailedIngestion` tests | `FailedIngestion.php` header note | Pin CI to PHP 8.3 or track upstream PHP fix |
| L4 | Tesseract temp directory uses `uniqid()` — not collision-safe under concurrency | `TesseractOcrService:60` | Use `tempnam()` or add PID to path |

---

## 8. Security Considerations

- **S3 signed URLs** expire after 1 hour (configurable) for PDF preview
- **File validation** occurs before processing (type, size)
- **No user-supplied filenames** reach shell commands (ImageMagick/Tesseract paths are system-generated)
- **Upload authorization** checked via `Law::class` policy

**No critical security issues identified.** The pipeline correctly avoids passing user input to shell commands.

---

## 9. Cost Implications

| Component | Cost Driver | Estimate per Document |
|-----------|------------|----------------------|
| AWS Textract | $1.50/page (full analysis) | $75 for 50-page doc |
| OpenAI Embeddings | ~$0.02/1M tokens | ~$0.001 per document |
| S3 Storage | $0.023/GB/month | Negligible |
| Tesseract | CPU time only | Free (local) |

**Recommendation:** For high-volume Croatian documents where Textract confidence is consistently low, consider routing directly to Tesseract to reduce Textract costs. The current pipeline always runs Textract first, then optionally runs Tesseract — meaning both engines incur cost/compute for routed documents.

---

## 10. Summary

The Textract/Tesseract ingestion pipeline is a well-engineered dual-engine OCR system with intelligent routing optimized for Croatian legal documents. The 13-step Laravel Pipeline architecture provides clear separation of concerns and testability. Error recovery is robust with exponential backoff, circuit breakers, and graceful degradation.

**Priority actions:**
1. Fix the `vizra-adk.ocr` config namespace mismatch (C1)
2. Add dead-letter alerting for permanently failed documents (C2)
3. Align embedding batch size config with actual usage (M3)
4. Add ImageMagick memory limits (M4)

The pipeline is production-capable and handles the complex requirements of multilingual legal document processing effectively.
