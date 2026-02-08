# 07 - Gaps, Risks, Obsolete/Weak Sectors

Last reviewed: **February 8, 2026**

## 1. Critical functional gaps

1. `/uploader` does not trigger analysis pipeline
- Upload endpoints store files only.
- No automatic OCR/embedding/graph/case-analysis dispatch from upload completion.
- Files: `app/Http/Controllers/UploadController.php`, `app/Services/UploadService.php`

2. Case-level analysis modules are present but not orchestrated end-to-end
- Contradiction/gap/strategy analyzers exist but are not wired into automatic post-ingest execution.
- Files: `app/Services/Analysis/CaseLevel/AI/*.php`, `app/Jobs/Analysis/RunDocumentExtractionJob.php`

3. Completeness builder is stubbed
- `DocumentIdentityBuilder` currently returns placeholder-like output.
- File: `app/Services/Analysis/CaseLevel/DocumentIdentityBuilder.php`

## 2. Legal Artillery weak spots

1. Livewire auto-send options appear not fully used
- `sendEmail/asDraft/toEmail` passed to `GenerateLegalDocumentJob`, but not persisted into the same pending dispatch mechanism as API flow.
- Files: `app/Livewire/LegalArtillery/NewGeneration.php`, `app/Jobs/GenerateLegalDocumentJob.php`, `app/Agents/LegalArtilleryAgent.php`

2. Approval state duplication
- Both DB columns and `model_config` carry approval state; flows are not fully uniform.
- Files: `app/Models/DocumentGenerationRun.php`, `app/Livewire/LegalArtillery/RunDetails.php`, `app/Agents/LegalArtilleryAgent.php`

3. Duplicate provider registration
- `LegalArtilleryOrchestrator` / `LegalArtilleryAgent` registered in multiple providers with differing dependency wiring.
- Files: `app/Providers/AppServiceProvider.php`, `app/Providers/LegalArtilleryServiceProvider.php`

4. Escalation helper duplication
- Multiple similarly named ladder suggesters exist without one obvious canonical runtime path.
- Files:
  - `app/Services/EscalationLadderSuggester.php`
  - `app/Services/EscalationHierarchySuggester.php`
  - `app/Services/LegalArtillery/EscalationLadderSuggester.php`

## 3. OCR pipeline risk indicators

1. Potential step-contract inconsistency
- `CreateMetadataStep` requires `ocrDocument`; skip-Textract path may not guarantee it is set in every branch.
- Files: `app/Pipelines/Textract/CollectLinesStep.php`, `app/Pipelines/Textract/CreateMetadataStep.php`

2. Config namespace inconsistency
- OCR quality step reads from `vizra-adk.ocr.*` while broader OCR config exists in `config/ocr.php`.
- Files: `app/Pipelines/Textract/CheckOcrQualityStep.php`, `config/ocr.php`

## 4. Law and source coverage gaps

1. Informator ingestion is not connected to law vector pipeline
- Scraping exists, but no direct insertion path to `laws`/`ingested_laws` pipeline is evident.
- Files: `app/Services/Informator/InformatorClient.php`, `app/Console/Commands/InformatorScrapeCommand.php`

## 5. Repository hygiene issues

1. Merge artifact in API routes comments
- `routes/api.php` still contains `<<<<<<< HEAD` marker text inside a comment block.
- Not runtime-fatal (commented), but signals unresolved merge hygiene.

## 6. Practical recommendations (priority order)

1. Bridge `/uploads` completion to a canonical ingestion orchestrator (or clearly deprecate `/uploader` for production ingest).
2. Add one case-level analysis orchestrator job chaining contradiction/gap/strategy modules after document extraction.
3. Implement `DocumentIdentityBuilder` fully and bind it into defense/completeness views.
4. Unify Legal Artillery approval and dispatch state model (single source of truth + consistent API/UI behavior).
5. Consolidate duplicate providers/escalation helpers and remove dead paths.
6. Resolve OCR step contract assumptions and config namespace split.

## 7. Actionable backlog

For execution-ready prioritization, see:

- `documentation/source-of-truth/08-implementation-backlog-p0-p2.md`
