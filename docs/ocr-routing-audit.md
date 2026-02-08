# OCR Routing Logic Audit

**Date:** 2026-02-03
**Auditor:** AI Implementation Agent
**Scope:** `DocumentOcrRouter`, `OcrQualityComparator`, OCR pipeline steps

---

## Summary

An audit of the OCR routing and quality comparison subsystem revealed 5 critical and 4 moderate issues. The most severe is a rule contradiction in `DocumentOcrRouter::recommend()` that causes incorrect engine selection for low-confidence documents containing structured content.

---

## Critical Issues

### 1. Rule Contradiction in DocumentOcrRouter

**Location:** `DocumentOcrRouter::recommend()`

**Problem:** Sequential if-statements allow later rules to override earlier ones. Rule 3 (structured content -> Textract) executes after Rule 1 (low confidence -> Tesseract), silently overriding it.

**Example:** A document with confidence 0.60 AND tables:
- Rule 1 fires: confidence < 0.70 -> recommendation = `tesseract`
- Rule 3 fires: has tables -> recommendation = `textract` (overrides Rule 1)

The document ends up routed to Textract despite low confidence, which contradicts the intended safety net of Rule 1.

**Impact:** Documents that should receive local OCR fallback are sent to an external service that may also struggle with them, wasting API calls and potentially producing poor results.

**Fix:** Replace sequential if-statements with a weighted scoring model or explicit priority chain with early return.

---

### 2. Diacritic Scoring Saturates

**Location:** `OcrQualityComparator::scoreDiacritics()`

**Problem:** The formula `min(1.0, ($diacriticCount / $len) * 20)` saturates at approximately 5% diacritic density. For languages like Croatian, where diacritic density commonly reaches or exceeds 5%, both OCR engines produce similar density levels, making the comparison score identical and therefore useless as a differentiator.

**Example (Croatian text):**
- Tesseract output: 5.2% diacritics -> `min(1.0, 0.052 * 20)` = `min(1.0, 1.04)` = **1.0**
- Textract output: 4.8% diacritics -> `min(1.0, 0.048 * 20)` = `min(1.0, 0.96)` = **0.96**

Both scores cluster near 1.0, destroying the discriminative power of the metric.

**Impact:** The quality comparator cannot meaningfully distinguish OCR engine output for diacritic-heavy languages, leading to arbitrary engine selection.

**Fix:** Use a language-aware expected diacritic density baseline and score deviation from that baseline, or use a logarithmic scale instead of linear with hard cap.

---

### 3. needsReview Flag Never Consumed

**Location:** `CheckOcrQualityStep` (sets flag), `TesseractOcrStep` (should check flag)

**Problem:** `CheckOcrQualityStep` sets `needsReview = true` on the pipeline context when quality thresholds are not met, but `TesseractOcrStep` never reads this flag. The review signal is written but never acted upon.

**Impact:** Documents flagged for human review proceed through the pipeline without any review gate, meaning low-quality OCR results are silently accepted.

**Fix:** Add a `needsReview` check in `TesseractOcrStep` (or a dedicated review gate step) that either queues the document for manual review or triggers a re-OCR with different parameters.

---

### 4. No Retry on Transient Failures

**Location:** S3 upload calls, Textract API calls throughout the pipeline

**Problem:** Neither S3 uploads nor Textract API calls implement retry logic. Transient network errors, throttling responses (HTTP 429), and temporary service unavailability cause immediate pipeline failure.

**Impact:** Intermittent AWS service issues cause permanent document processing failures that require manual resubmission.

**Fix:** Add a retry decorator or middleware to pipeline steps that wraps external service calls with exponential backoff and jitter. Laravel's `retry()` helper or a dedicated retry middleware on the pipeline would suffice.

---

### 5. No Health Check Before Tesseract Path

**Location:** Tesseract engine selection path

**Problem:** When the router selects Tesseract as the OCR engine, there is no pre-flight check to verify the Tesseract binary is installed and accessible. If the binary is missing or the path is misconfigured, the pipeline fails silently with no alert or fallback.

**Impact:** Silent failures in the Tesseract path produce empty or missing OCR output with no visibility into the root cause. Operators may not realize Tesseract is unavailable until documents are discovered with missing text.

**Fix:** Add a health check (e.g., `tesseract --version`) before routing to Tesseract. If the check fails, log an alert and either fall back to Textract or fail loudly.

---

## Moderate Issues

### 6. TesseractOcrService Spawns 2 Processes Per Page

**Location:** `TesseractOcrService`

**Problem:** Each page processed by Tesseract requires two separate process spawns: one for ImageMagick `convert` (to prepare the image) and one for `tesseract` (to perform OCR). This doubles the process overhead per page.

**Impact:** Increased memory usage, slower throughput, and higher risk of process exhaustion under load.

---

### 7. No Deskew/Denoise Preprocessing

**Location:** OCR pipeline preprocessing stage

**Problem:** Documents are passed to OCR engines without deskew or denoise preprocessing. Scanned documents with slight rotation or noise produce significantly worse OCR results.

**Impact:** Reduced OCR accuracy for real-world scanned documents, which are rarely perfectly aligned or clean.

---

### 8. Temp File Cleanup in Finally Block Without Verification

**Location:** Pipeline step implementations

**Problem:** Temporary files are cleaned up in `finally` blocks, but there is no verification that the cleanup actually succeeded. Failed cleanup leads to disk space accumulation over time.

**Impact:** Gradual disk space exhaustion in long-running environments, potentially causing unrelated failures.

---

### 9. No Pipeline-Level Timeout

**Location:** `WaitAndFetchStep` (Textract polling)

**Problem:** The Textract polling loop in `WaitAndFetchStep` has no pipeline-level timeout. If Textract never returns a completed status, the polling continues indefinitely, blocking the pipeline worker.

**Impact:** Hung pipeline workers reduce processing capacity and can eventually exhaust the worker pool.

---

## Recommendations

### Short-Term (Sprint Scope)

1. **Replace sequential if/else routing with weighted scoring** -- Assign weights to confidence, structure detection, and language signals. Sum scores per engine and select the highest. This eliminates rule contradiction by design.

2. **Add retry decorator to pipeline steps** -- Wrap all external service calls (S3, Textract) with exponential backoff retry logic. Use Laravel's `retry()` helper with configurable max attempts and delay.

3. **Wire up the needsReview flag** -- Add a review gate step or check in the pipeline that respects the `needsReview` flag set by `CheckOcrQualityStep`.

### Medium-Term

4. **Replace ImageMagick + Tesseract with ocrmypdf** -- The `ocrmypdf` tool handles image preprocessing (deskew, denoise, cleanup) and OCR in a single process invocation, addressing issues 6 and 7 simultaneously.

5. **Add circuit breaker for external service calls** -- Implement a circuit breaker pattern for Textract and S3 calls to prevent cascading failures when AWS services are degraded.

6. **Add pipeline-level timeout** -- Set a maximum wall-clock time for the entire OCR pipeline, with graceful termination and error reporting when exceeded.

### Long-Term

7. **Language-aware diacritic scoring** -- Build a diacritic density baseline per language and score OCR output against expected values rather than using a fixed linear formula.

8. **Tesseract health monitoring** -- Add a periodic health check for the Tesseract binary with alerting, rather than checking per-request.
