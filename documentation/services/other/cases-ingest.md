# CasesIngest Command — Batch Case Document Ingestion

This document explains the purpose, options, end-to-end flow, data contracts, and operational notes for the Laravel Artisan command implemented in `app/Console/Commands/CasesIngest.php`.

- Command name: `cases:ingest`
- Primary purpose: Batch-ingest PDF documents for an existing legal case by extracting text (locally or via AWS Textract) and feeding it into the `CaseIngestPipeline` for chunking and persistence.
- Scope: Recursively walks a directory for PDFs, creates upload records, extracts text, calls the ingest pipeline, and reports a summary.

## TL;DR

- Provide a `--path` to a directory and `--case` (ULID of an existing `LegalCase`).
- The command will find PDFs, store each into `storage/app/cases/{caseId}/{docId}/`, create a `CaseDocumentUpload`, extract text (locally or via Textract), and ingest via `CaseIngestPipeline` with configurable chunking.
- A progress bar and a colored summary are printed at the end.

---

## Command Signature and Options

```
php artisan cases:ingest \
  --path=/path/to/pdfs \
  --case=<case-ulid> \
  [--chunk=1200] \
  [--overlap=150] \
  [--ocr] \
  [--local-ocr] \
  [--skip-existing] \
  [--dry-run] \
  [--verbose]
```

- `--path` (required): Absolute or relative path to a directory containing PDFs. Recursively scanned.
- `--case` (required): ULID of the `LegalCase` to associate documents with. Must exist.
- `--chunk` (default `1200`): Character chunk size used by the ingest pipeline.
- `--overlap` (default `150`): Overlap in characters between adjacent chunks.
- `--ocr`: Force OCR using AWS Textract for text extraction.
- `--local-ocr`: Use local OCR (likely Tesseract via `OcrService`).
- `--skip-existing`: Skip files if a `CaseDocumentUpload` with the same `sha256` already exists for this case.
- `--dry-run`: Show what would be processed without actually storing or ingesting. Note: In dry-run, the command increments the "processed" stat for visibility but performs no side effects.
- `--verbose`: Print per-file details (skips, failures, chunk counts, Textract steps).

Exit codes:
- `0` on success (even if some files fail; see summary for counts)
- `1` on early validation errors (missing options, bad path, unknown case)

---

## High-level Flow

1. Validate inputs (`--path`, `--case`, directory exists) and fetch the `LegalCase`.
2. Recursively find all `.pdf` files under `--path`.
3. Initialize stats and a progress bar.
4. For each PDF, call `processFile()`:
   - Compute `sha256` and optionally skip if `--skip-existing`.
   - In `--dry-run`, log and return early.
   - Store the file to the `local` disk under `cases/{caseId}/{docId}/{filename}`.
   - Create a `CaseDocumentUpload` with status `stored`.
   - Extract text:
     - If `--ocr`: Use AWS Textract via `TextractService`.
     - Else if `--local-ocr`: Use local OCR (`OcrService::extractTextFromPdf`).
     - Else (default): Try local extraction via `OcrService`.
   - If no text: mark upload `failed` and increment `failed` stat.
   - If text is present: call `CaseIngestPipeline::ingest` with chunking and metadata.
   - Update stats based on pipeline result; set upload status to `completed` or `failed`.
5. Print a colored summary of totals, processed, skipped, failed, and needs-review counts.

---

## Data Contracts and Artifacts

### Inputs
- PDFs from the filesystem under `--path`.
- An existing `LegalCase` referenced by `--case`.

### Side effects
- Files copied into the `local` disk (usually `storage/app`):
  - `cases/{caseId}/{docId}/{originalFilename}`
- Database rows in `CaseDocumentUpload` with fields populated (examples):
  - `id` (ULID), `case_id`, `doc_id` (`doc-{ulid}`), `disk=local`, `local_path`, `original_filename`, `mime_type=application/pdf`, `file_size`, `sha256`, `status`, `uploaded_at`, `metadata`.
- When `--ocr` (Textract) is used:
  - The original PDF is uploaded to S3 at `{S3_INPUT_PREFIX}/{docId}.pdf`.
  - Textract analysis blocks are saved to S3 and to local storage by `TextractService::saveResultsToS3AndLocal`.
  - `CaseDocumentUpload.metadata` updates include `s3_input_key`, `s3_json_key`, `local_json_path`, `source=cli-textract`, `textract_job_id`.

### Outputs
- Console progress bar and verbose logs (when `--verbose`).
- Final summary with counts; colors indicate status types.

### Result contract from `CaseIngestPipeline::ingest`
The command expects `ingest()` to return a structure like:
- `status`: `'completed'` or other (treated as failure)
- `chunk_count`: number of chunks created (used only for display)
- `needs_review`: boolean (increments `needs_review` stat if true)
- `error`: optional string for failures

---

## Detailed Walkthrough

### 1) Discovery and validation
- `handle()` reads options, checks for `--path` and `--case`, validates the directory, and loads the `LegalCase`.
- It recursively enumerates PDFs using `RecursiveDirectoryIterator` + `RecursiveIteratorIterator`.

### 2) Duplicate detection (optional)
- If `--skip-existing` is set, the command computes `sha256` of the PDF and checks `CaseDocumentUpload` for an existing record with the same hash and `case_id`. If present, increments `skipped` and continues.

### 3) Dry-run (optional)
- If `--dry-run` is set, the file is not stored/ingested. The command increments `processed` for visibility and prints an info line in `--verbose` mode.

### 4) Storage and model creation
- Generates a document ID like `doc-<ULID>`.
- Persists the original file to the `local` disk under `cases/{caseId}/{docId}/{filename}`.
- Creates a `CaseDocumentUpload` with `status='stored'` capturing metadata.

### 5) Text extraction
- Local extraction: `OcrService::extractTextFromPdf($absoluteLocalPath)`.
  - Any exception logs a warning in `--verbose` and returns an empty string.
- AWS Textract extraction (`--ocr`): `extractTextWithTextract()`
  - Uploads `{docId}.pdf` to S3 under `S3_INPUT_PREFIX` (default `textract/input`).
  - Starts Textract Document Analysis with feature types: `LAYOUT`, `FORMS`, `TABLES`, `SIGNATURES`.
  - Polls until finished, fetches all blocks.
  - Saves the raw blocks both to S3 and locally via `TextractService`.
  - Extracts text from `LINE` blocks and concatenates with newlines.
  - Updates `CaseDocumentUpload.metadata` with S3/local paths, `source`, and `textract_job_id`.

> Note: When neither `--ocr` nor `--local-ocr` is given, the command uses the local `OcrService` path by default. It does not automatically fall back to Textract if local extraction yields no text.

### 6) Ingestion pipeline
- Calls `CaseIngestPipeline::ingest()` with:
  - `caseId`, `docId`, `rawText`, `ocrBlocks` (array when Textract; empty otherwise)
  - `options`:
    - `chunk_size`, `overlap`
    - `upload_id` (for traceability)
    - `language` hardcoded to `hr`
    - `metadata` including `original_path`, `cli_ingested=true`, `ingested_at` (ISO8601)
- Upload status is updated to `completed` when successful, otherwise `failed` with an error message.

### 7) Reporting
- Per-file messages (only with `--verbose`).
- End-of-run summary with totals and colored counts:
  - Total files, Processed, Skipped, Failed, Needs review.

---

## Configuration and Environment

- Storage disk `local`: configured in `config/filesystems.php`. Files are written under `storage/app` by default.
- AWS Textract (when using `--ocr`):
  - AWS credentials and region must be configured (e.g., via environment variables or SDK default chain).
  - `S3_INPUT_PREFIX` env var controls where PDFs are uploaded before Textract processing (default: `textract/input`).
- Database: `CaseDocumentUpload` table must support the fields used by the command (see creation fields above).
- `OcrService` and `TextractService` must be correctly bound and configured in the service container.

---

## Usage Examples

- Basic ingest using local extraction (default):

```bash
php artisan cases:ingest --path=/data/cases/CASE123/pdfs --case=01J8N9D9HAFQGRR5X6G2K1AQ2W
```

- Force AWS Textract for OCR:

```bash
php artisan cases:ingest --path=/data/cases/CASE123/pdfs --case=01J8N9D9HAFQGRR5X6G2K1AQ2W --ocr --verbose
```

- Use local OCR (Tesseract) explicitly and custom chunking:

```bash
php artisan cases:ingest --path=./incoming --case=01J8N9D9HAFQGRR5X6G2K1AQ2W --local-ocr --chunk=1600 --overlap=200
```

- Skip duplicates based on sha256 hash:

```bash
php artisan cases:ingest --path=/mnt/casebox --case=01J8N9D9HAFQGRR5X6G2K1AQ2W --skip-existing
```

- Dry-run with detailed output:

```bash
php artisan cases:ingest --path=~/downloads/pdfs --case=01J8N9D9HAFQGRR5X6G2K1AQ2W --dry-run --verbose
```

---

## Error Handling and Edge Cases

- Missing `--path` or `--case`: command exits with code `1` and an error message.
- Non-existent directory: exits with code `1`.
- Unknown case ID: exits with code `1`.
- No PDFs found: prints a warning and exits `0` (nothing to do).
- Duplicate files with `--skip-existing`: counted in `skipped`.
- Extraction failures (local or Textract): counted in `failed`; upload status set to `failed` with an error message.
- Textract timeouts/network errors: caught and logged in `--verbose`; returns empty text leading to a failure for that file.
- Very large PDFs: processing time increases; ensure sufficient disk space and memory.
- Permissions: ensure the process can read `--path` and write to the `local` disk.

---

## Observability and Notes

- Progress bar: reflects the number of files discovered at start.
- `processed` count includes successful ingests and also increments during `--dry-run` to show intent.
- `needs_review`: bubbled from the pipeline; often used to flag low OCR quality or uncertainties.
- Colored summary uses ANSI formatting supported by Artisan:
  - Processed: green, Skipped/Needs review: yellow, Failed: red.

---

## Extending and Customizing

- Add fallback behavior: if local extraction returns no text, optionally fall back to Textract before failing.
- Enrich metadata: capture extra attributes (e.g., page counts, detected languages) on `CaseDocumentUpload`.
- Parallelization: process files concurrently (queue jobs) for large batches; ensure idempotency with `sha256`.
- More granular feature flags: allow disabling S3 writes for Textract or choosing output formats.

---

## Quick Smoke Test

1. Create or identify a `LegalCase` ULID.
2. Put 1–2 small PDF files in a test directory.
3. Run:

```bash
php artisan cases:ingest --path=/tmp/test-pdfs --case=<your-case-ulid> --dry-run --verbose
```

4. Verify the command lists files to process and shows a correct summary. Then run without `--dry-run` and confirm files appear under `storage/app/cases/{caseId}/` and rows in `CaseDocumentUpload` are created.

---

## Related Classes

- `App\Services\CaseIngestPipeline` — Orchestrates chunking and persistence.
- `App\Services\OcrService` — Local extraction/OCR.
- `App\Services\TextractService` — AWS S3 upload, Textract analysis, and results persistence.
- `App\Models\CaseDocumentUpload` — Tracks file storage and processing status/metadata.
- `App\Models\LegalCase` — Parent entity for associating documents.

---

If you need to modify behavior, start by reading `app/Console/Commands/CasesIngest.php` alongside the services above. This doc maps line-by-line semantics to operational behavior to guide safe changes.
