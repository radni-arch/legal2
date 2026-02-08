# Decisions Ingestion Flow (Odluke-based)

Updated: 2025-10-26

This document describes the consolidated court decisions ingestion pipeline introduced in Milestone C, Sprint C1 (tasks C1.1 and C1.2). The flow centralizes ingestion under a single command and delegates chunking/embeddings to `OdlukeIngestService`.

## Overview

- Single command signature: `decisions:ingest`.
- Source: odluke.sudovi.hr via `OdlukeClient`.
- Orchestration: search → meta → download → ingest.
- Chunking + embeddings: only in `OdlukeIngestService`.
- Resilient to download failures: errors are logged, batch continues.

## Command

Class: `App\\Console\\Commands\\IngestCourtDecisionsEmbeddings`

Signature:

```
php artisan decisions:ingest \
  [--id=*] \
  [--query=<q>] [--params=<raw_qs>] [--limit=50] [--page=1] \
  [--prefer=auto|html|pdf] \
  [--model=<embedding-model>] [--chunk=1500] [--overlap=200] \
  [--sync-graph] \
  [--dry]
```

- `--id`: one or more odluke IDs to ingest directly
- `--query`/`--params`: perform remote list search and collect IDs via `OdlukeClient->collectIdsFromList()`
- `--limit`, `--page`: pagination for search
- `--prefer`: choose source priority (auto→HTML→PDF fallback; or force one)
- `--model`, `--chunk`, `--overlap`: embedding + chunking parameters (applied in service)
- `--dry`: do everything except persist to DB/vector store
- `--sync-graph`: optionally sync most recent decisions to graph after ingest

Output: a summary table with processed IDs, inserted chunks, errors, skipped, model, dry-run flag.

## Services

- `OdlukeClient`
  - `collectIdsFromList(q, params, limit, page)` → list page scrape to decision IDs
  - `fetchDecisionMeta(id)` → parse metadata (DOM-first with fallbacks)
  - `downloadHtml(id)`, `downloadPdf(id)` → retrieve primary sources
- `OdlukeIngestService`
  - `ingestByIds(ids, options)` → end-to-end per-ID ingestion
    - Upsert decision from meta
    - Prefer HTML text; fallback to PDF (+OCR when configured)
    - Persist source artifacts under `storage/app/court_decisions/...`
    - Chunk text and create embeddings via `CourtDecisionVectorStoreService`
  - `ingestText(text, meta, options)` → offline helper for validation

## Error handling

- HTML/PDF download failures are logged with ID, URL, status and ingestion continues.
- If no text is extracted after attempts, the ID is counted as `skipped` and logged.
- Any per-ID unexpected exception increments `errors` but does not abort the batch.

## Data model touch points

- `court_decisions` (upsert by `ecli` or `case_number`+`court`)
- `court_decision_documents` / vector store entries created via `CourtDecisionVectorStoreService`
- `court_decision_document_uploads` when storing PDF artifacts

## Examples

1) Ingest specific decisions by ID (auto prefer):

```
php artisan decisions:ingest --id=1a2b3c --id=4d5e6f
```

2) Search and ingest first 25 results for a query:

```
php artisan decisions:ingest --query="kazneni postupak" --limit=25
```

3) Force PDF path with OCR and dry-run:

```
php artisan decisions:ingest --id=1a2b3c --prefer=pdf --dry
```

4) Ingest and sync to graph:

```
php artisan decisions:ingest --query="ECLI:HR" --limit=10 --sync-graph
```

## Notes

- Only one ingestion command is registered; legacy eoglasna-specific ingestion paths were removed from the command.
- Embedding provider/model selection is configurable via `config/openai.php` and can be overridden with `--model`.
- Rate-limiting and polite scraping are handled in `OdlukeClient` with throttle/backoff.

