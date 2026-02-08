# Legal Platform Source of Truth (Ingestion + Analysis)

Last verified: **February 8, 2026**

This folder documents how data actually flows through the application today.
It is intended as the canonical reference for:

- Authentication and upload entry points.
- OCR and embedding pipelines.
- Law ingestion and MCP availability.
- Court practice ingestion/search across sources.
- Case-document analysis and defense support.
- Legal Artillery generation/approval/dispatch behavior.
- Known gaps, weak sectors, and probable dead/unused paths.

## Document Index

1. `documentation/source-of-truth/01-auth-uploader-and-ingest-entrypoints.md`
2. `documentation/source-of-truth/02-google-drive-ocr-embedding-graph-pipeline.md`
3. `documentation/source-of-truth/03-law-ingestion-and-mcp-exposure.md`
4. `documentation/source-of-truth/04-court-practice-ingestion-and-querying.md`
5. `documentation/source-of-truth/05-document-analysis-defense-pipeline.md`
6. `documentation/source-of-truth/06-legal-artillery-lifecycle.md`
7. `documentation/source-of-truth/07-gaps-risks-obsolete-sections.md`
8. `documentation/source-of-truth/08-implementation-backlog-p0-p2.md`
9. `documentation/source-of-truth/09-sprint-execution-plan.md`

## Quick Truth Table

| Domain | Implemented in code | Key truth |
|---|---|---|
| Login + `/uploader` | Yes | Uploads are stored, but no automatic analysis pipeline trigger from `UploadController` / `UploadService`. |
| Google Drive ingest | Yes | Full OCR -> metadata -> case ingest -> embeddings -> optional graph sync exists via Textract pipeline. |
| Law ingest + article PDFs + metadata | Yes | NN + Zakon.hr paths split laws to article level, render per-article PDFs, store metadata, embed to `laws`, expose via MCP. |
| Court practice ingest | Yes | Odluke + USUD + ESLJP/ECHR + Informator collectors exist; ingestion depth differs by source. |
| Case document analysis for defense | Partial | Layer-1 extraction is wired; several case-level analyzers exist but are not auto-dispatched. |
| Legal Artillery escalation/dispatch | Yes (with caveats) | Approval + preview + signature + dispatch gates exist; some auto-send options are inconsistent between API and Livewire path. |

## Core Architecture Reality

- There are **two major ingestion worlds**:
  - `/uploads` (simple file storage endpoints).
  - Textract/Drive ingestion (`ProcessDrivePdf`, `ProcessTextractJob`, embedding and graph jobs).
- Court/law ingestion paths are mostly **service- and command-driven** (not direct `/uploader` continuation).
- Defense analytics are **partially event-driven** and **partially manually-triggered**.
