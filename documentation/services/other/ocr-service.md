# OcrService — PDF Text Extraction Flow

This document explains how `app/Services/OcrService.php` extracts text from PDF files, the tools it depends on, and how to use and troubleshoot it.

## Summary

`OcrService::extractTextFromPdf(string $pdfPath): string` tries to extract text from a PDF using a fast, layout-preserving method first, then falls back to OCR when needed:

1. Validate the PDF path exists.
2. Try `pdftotext` (Poppler) to extract text directly from the PDF stream.
3. If that fails or returns empty text, fall back to OCR via `tesseract` after rasterizing pages with ImageMagick `convert`.
4. Aggregate results, normalize whitespace, and return the final string. If all methods fail, return an empty string.

It also logs useful diagnostics and cleans up temporary files it creates during OCR.

## Dependencies

The service discovers required binaries at runtime using `which`:

- `pdftotext` (Poppler utils) — preferred path for text-based PDFs
- `tesseract` — OCR engine
- `convert` (ImageMagick) — rasterizes PDF pages for OCR

Example installation (Debian/Ubuntu):

```bash
sudo apt-get update
sudo apt-get install -y poppler-utils tesseract-ocr tesseract-ocr-eng tesseract-ocr-hr imagemagick
```

Notes:
- Add language packs as needed (e.g., `tesseract-ocr-hr` and `tesseract-ocr-eng`). The service uses `-l hr+eng`.
- On some systems, ImageMagick PDF support may require Ghostscript; ensure policies allow PDF reading.

## Detailed Flow

High-level pseudocode of `extractTextFromPdf`:

```
if !file_exists(pdfPath):
  log warn and return ''

if pdftotext exists:
  run: pdftotext -enc UTF-8 -layout <pdfPath> -  (to stdout)
  if success and non-empty output:
    return trimmed output
  else log warning
else log info (not available)

if tesseract and convert exist:
  tmpBase = tempnam()
  delete tmpBase to use as prefix
  imgBase = tmpBase + '_page'

  run: convert -density 300 <pdfPath> <imgBase>.tif
  if success:
    pages = glob(<imgBase>-*.tif) or <imgBase>.tif (single page)
    for each page image:
      outTxtBase = tmpBase + '_' + pageIndex
      run: tesseract <img> <outTxtBase> -l hr+eng
      if success and <outTxtBase>.txt exists:
        read text and collect
      log and continue on page-level failure
      delete page image

    if any texts collected:
      join with double newlines, normalize whitespace, trim, and return
  else log warning (convert failed)
else log info for whichever tools are missing

log warning (all methods failed) and return ''
```

### Timeouts

- `pdftotext`: 60 seconds
- `convert`: 120 seconds
- `tesseract`: 120 seconds per page

These limits prevent runaway processes on large/complex PDFs.

### Logging

The service logs to help diagnose issues:
- PDF file not found (warning)
- `pdftotext` unavailable (info) or failed (warning, with exit code and stderr)
- `convert` unavailable (info) or failed (warning)
- `tesseract` unavailable (info) or per-page failures (warning)
- All methods failed or produced no text (warning)

### Output Normalization

- `pdftotext` path returns the raw `stdout` trimmed.
- OCR path concatenates page texts with blank lines and normalizes whitespace via regex (`/\s+/u` → single space), then trims.

### Temporary Files and Cleanup

- OCR flow creates temporary TIFFs and `tesseract` outputs (`<tmp>_N.txt`).
- Page images and per-page text files are deleted after processing.
- `tempnam()` is used as a unique prefix; the initial placeholder file is removed before use.

## Usage

Inject or resolve the service and call `extractTextFromPdf` with an absolute or project-relative path to a readable PDF file.

Example (Controller/Job/Service):

```php
use App\Services\OcrService;

public function handle(OcrService $ocr)
{
    $pdf = storage_path('app/documents/example.pdf');
    $text = $ocr->extractTextFromPdf($pdf);

    if ($text === '') {
        // handle empty extraction (log, retry, queue OCR later, etc.)
    }

    // Process $text further...
}
```

Laravel auto-wires `OcrService` via the service container since it has no constructor dependencies.

## When to Expect OCR vs. Direct Text

- Text-based PDFs (digitally created or with embedded text) should succeed with `pdftotext` and be fast.
- Scanned PDFs (image-only) will likely have empty `pdftotext` results and trigger the OCR fallback.

## Common Failure Modes and Fixes

- Missing tools: Install `poppler-utils`, `tesseract`, language packs, and `imagemagick`.
- ImageMagick policy blocks PDF: Update `/etc/ImageMagick-6/policy.xml` (or IM7 path) to permit reading PDFs, or use `magick` binary if applicable.
- Language accuracy: Add appropriate Tesseract language packs and adjust `-l` (e.g., `hr+eng+deu`).
- Performance on large PDFs: Consider lowering DPI (e.g., `-density 200`) or OCR selected pages.
- Memory issues during convert: Use `-limit` flags or split the PDF into chunks.

## Extensibility Ideas

- Make timeouts, DPI, and languages configurable via `config/mcp.php` or a new `config/ocr.php`.
- Prefer `magick` over `convert` when IM7 is installed.
- Parallelize per-page OCR using a queue for very large documents.
- Persist intermediate artifacts for debugging when running in a non-production environment.
- Add unit/integration tests with small sample PDFs.

## Contract

- Input: file path to a PDF
- Output: extracted text (UTF-8), possibly normalized; empty string if extraction fails
- Errors: No exceptions thrown by design; issues are logged. Callers should handle empty results.

## Security Considerations

- Avoid running on untrusted PDFs without sandboxing; `convert` processes PDFs and could be resource-intensive.
- Enforce sane timeouts (already present) and consider per-request resource limits in production.

---

For implementation details, see `app/Services/OcrService.php`.
