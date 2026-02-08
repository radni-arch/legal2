# OCR Pipeline Architecture

## Overview

The OCR pipeline processes PDF documents through a dual-engine strategy optimized for Croatian legal documents. It uses AWS Textract as the primary engine and ocrmypdf (wrapping Tesseract) as a secondary engine for better diacritic handling.

## Pipeline Flow

```
PDF → Download from Drive → Upload to S3 → Textract Analysis → Save Results
  → Collect Lines (OcrDocument) → Check Quality → OCR Routing Decision
  → [If Tesseract] ocrmypdf with deskew → Quality Comparison → Pick Winner
  → Legal Metadata Extraction → PDF Reconstruction → Upload Output → Persist
```

## Pipeline Steps (in order)

| # | Step | Purpose |
|---|------|---------|
| 1 | EnsureJobStep | Create/verify TextractJob DB record |
| 2 | DownloadDriveFileStep | Download PDF from Google Drive |
| 3 | UploadInputToS3Step | Upload PDF to S3 (with retry) |
| 4 | StartAnalysisStep | Start Textract async job (with retry) |
| 5 | WaitAndFetchStep | Poll Textract status with timeout |
| 6 | SaveResultsStep | Save Textract JSON blocks |
| 7 | CollectLinesStep | Convert blocks to OcrDocument structure |
| 8 | CheckOcrQualityStep | Analyze quality, flag for review |
| 9 | TesseractOcrStep | Route to ocrmypdf if needed, compare quality |
| 10 | CreateMetadataStep | Extract legal metadata |
| 11 | ReconstructPdfStep | Rebuild readable PDF |
| 12 | UploadOutputStep | Upload to S3 (with retry) |
| 13 | PersistReconstructedStep | Save final results to DB |

## OCR Routing (DocumentOcrRouter)

Uses weighted scoring instead of sequential rules:

| Signal | Weight | Direction |
|--------|--------|-----------|
| Very low Textract confidence (<0.70) | +3.0 | → Tesseract |
| Moderate Textract confidence (0.70-0.85) | +1.0 | → Tesseract |
| High Textract confidence (≥0.85) | -2.0 | → Textract |
| Croatian content detected | +1.5 | → Tesseract |
| Structured content (tables/forms) | -1.0 | → Textract |

Score > 0 → Tesseract; Score ≤ 0 → Textract.

Key insight: Very low confidence (+3.0) cannot be overridden by structured content (-1.0).

## Quality Comparison (OcrQualityComparator)

When both engines produce output, quality is compared on:
- **Valid word ratio** (40%): Ratio of dictionary-like words
- **Diacritic score** (30%): Logistic curve for Croatian diacritics
- **Completeness** (15%): Word count normalization
- **Line quality** (15%): Average characters per line

Winner must improve by ≥5% (configurable) to replace Textract output.

## Resilience Features

- **RetryableStep trait**: Exponential backoff on transient failures (S3, Textract API)
- **OcrAvailabilityGuard**: Cached health check for ocrmypdf/Tesseract binaries
- **Pipeline timeout**: Configurable max wait for Textract polling
- **Quality-gated re-OCR**: Force Tesseract path when quality check fails

## Configuration

All thresholds in `config/ocr.php`. Key environment variables:

| Variable | Default | Purpose |
|----------|---------|---------|
| OCR_DEFAULT_ENGINE | auto | Force specific engine or auto-route |
| OCRMYPDF_BINARY | ocrmypdf | Path to ocrmypdf CLI |
| OCRMYPDF_LANGUAGES | hrv+eng | Tesseract language packs |
| OCR_FORCE_REOCR_ON_REVIEW | true | Re-OCR when quality check fails |

## Dependencies

```bash
pip install ocrmypdf
apt-get install tesseract-ocr tesseract-ocr-hrv ghostscript
```
