# 08 - Implementation Backlog (P0/P1/P2)

Backlog created: **February 8, 2026**  
Input source: `documentation/source-of-truth/07-gaps-risks-obsolete-sections.md`

This backlog is implementation-oriented and prioritized for production risk reduction and end-to-end ingestion reliability.

Sprint-bucketed execution plan:

- `documentation/source-of-truth/09-sprint-execution-plan.md`

## P0 (Immediate, high-risk/high-impact)

| ID | Objective | Implementation | Acceptance Criteria | Primary Files |
|---|---|---|---|---|
| P0-01 | Make `/uploader` a real ingest entrypoint | Add a canonical orchestrator dispatch on `UploadController@complete` and `direct` (or explicitly route to existing Textract/case-ingest pipeline). | Uploading via `/uploader` creates a tracked ingest run and triggers downstream OCR/embedding/analysis path. | `app/Http/Controllers/UploadController.php`, `app/Services/UploadService.php`, ingest orchestration service/job |
| P0-02 | Produce required analysis inputs for defense modules | Add `DateContextExtractor` and `CaseReferenceExtractor` to `DocumentAnalysisPipeline` output set. | `dates_with_context` and `case_references` are generated for new ingested case docs and visible in persisted analysis records. | `app/Services/Analysis/DocumentAnalysisPipeline.php`, `app/Services/Analysis/Analyzers/DateContextExtractor.php`, `app/Services/Analysis/Analyzers/CaseReferenceExtractor.php` |
| P0-03 | Auto-run case-level AI analysis | Add post-extraction job chain that runs contradiction/gap/strategy analyzers after Layer-1 extraction completes. | A single ingest event produces both document-level and case-level analysis artifacts automatically. | `app/Jobs/Analysis/RunDocumentExtractionJob.php`, `app/Listeners/TriggerDocumentAnalysis.php`, `app/Services/Analysis/CaseLevel/AI/*.php` |
| P0-04 | Implement true completeness matrix | Replace `DocumentIdentityBuilder` stub with full KLASA/URBROJ/case-reference completeness logic and connect command/UI. | `case:completeness` output shows non-empty, correct completeness state based on parsed identity data. | `app/Services/Analysis/CaseLevel/DocumentIdentityBuilder.php`, `app/Console/Commands/CaseFileCompletenessCommand.php` |
| P0-05 | Eliminate Legal Artillery approval-state ambiguity | Normalize approval source of truth (DB columns vs `model_config`) and keep API/UI paths consistent. | Approve/preview/dispatch read and write one canonical approval state without divergence. | `app/Models/DocumentGenerationRun.php`, `app/Agents/LegalArtilleryAgent.php`, `app/Livewire/LegalArtillery/RunDetails.php`, `app/Http/Controllers/Api/DocumentGenerationController.php` |
| P0-06 | Make Livewire send options operational | Persist and use `sendEmail/asDraft/toEmail` in Livewire generation path exactly like API pending dispatch behavior. | A run generated in Livewire can go through the same pending dispatch preview/dispatch flow as API-generated runs. | `app/Livewire/LegalArtillery/NewGeneration.php`, `app/Jobs/GenerateLegalDocumentJob.php`, `app/Agents/LegalArtilleryAgent.php` |
| P0-07 | Fix OCR step contract risk | Ensure `CreateMetadataStep` always receives `ocrDocument` (including skip-Textract path) or make metadata step resilient to missing structure. | No failures caused by missing `ocrDocument` across both Textract and skip-Textract branches. | `app/Pipelines/Textract/CollectLinesStep.php`, `app/Pipelines/Textract/CreateMetadataStep.php`, `app/Pipelines/Textract/LocalOcrRouteStep.php` |
| P0-08 | Clean route hygiene artifact | Remove merge marker artifact from route comments and enforce CI check for unresolved conflict markers. | `routes/api.php` contains no conflict marker strings; CI fails if new markers appear. | `routes/api.php`, CI script/config |

## P1 (Near-term hardening and consolidation)

| ID | Objective | Implementation | Acceptance Criteria | Primary Files |
|---|---|---|---|---|
| P1-01 | Consolidate Legal Artillery DI bindings | Keep one provider as canonical for `LegalArtilleryOrchestrator` and `LegalArtilleryAgent`; remove duplicate divergent bindings. | Container resolves one deterministic dependency graph in all environments. | `app/Providers/AppServiceProvider.php`, `app/Providers/LegalArtilleryServiceProvider.php` |
| P1-02 | Consolidate escalation helper services | Merge duplicate ladder suggesters into one canonical service + interface and migrate call sites. | One ladder suggester remains active; old classes removed or deprecated with references updated. | `app/Services/EscalationLadderSuggester.php`, `app/Services/EscalationHierarchySuggester.php`, `app/Services/LegalArtillery/EscalationLadderSuggester.php` |
| P1-03 | Integrate Informator into law corpus | Add ingestion adapter that feeds Informator outputs into `ingested_laws` + `laws` pipeline with metadata/embeddings. | Informator-imported legal docs are searchable through existing law search + MCP law tools. | `app/Services/Informator/InformatorClient.php`, `app/Console/Commands/InformatorScrapeCommand.php`, law ingest services |
| P1-04 | Unify OCR config namespaces | Standardize on one config namespace for OCR routing/quality thresholds and update all consumers. | All OCR steps read the same configuration keys with no duplicated divergent paths. | `config/ocr.php`, `app/Pipelines/Textract/CheckOcrQualityStep.php`, OCR services |
| P1-05 | Add end-to-end ingest observability | Add stage-level run IDs, metrics, and structured logs from upload -> OCR -> embedding -> graph -> analysis. | One run ID traces all stages; dashboard/queries can identify stage failures and throughput bottlenecks quickly. | ingest jobs, Textract jobs, monitoring/logging services |
| P1-06 | Add regression tests for full ingestion chain | Add integration tests for both `/uploader` and Google Drive paths covering downstream pipeline triggers and outputs. | CI proves both entrypaths produce expected OCR/embedding/analysis artifacts. | tests under `tests/Integration` / `tests/Feature`, related jobs/services |

## P2 (Optimization and long-tail cleanup)

| ID | Objective | Implementation | Acceptance Criteria | Primary Files |
|---|---|---|---|---|
| P2-01 | Clarify product behavior for `/uploader` | If `/uploader` remains storage-only for specific workflows, make UI and docs explicit and add link to full ingest paths. | No operator confusion about whether `/uploader` triggers analysis. | `resources/views/uploader.blade.php`, docs |
| P2-02 | Remove or archive unused Legal Artillery legacy services | Decommission unreferenced legacy writer/refiner classes after compatibility check. | Dead code removed (or archived with clear deprecation note) and tests stay green. | `app/Services/LegalArtillery/RecursiveDocumentWriter.php`, `app/Services/LegalArtillery/IterativeRefiner.php` |
| P2-03 | Diagram/testing automation for architecture drift | Add automated checks ensuring docs/diagrams stay aligned with route and job topology (static checks + smoke tests). | Drift alerts exist when route/job wiring changes without doc updates. | docs tooling + CI scripts |
| P2-04 | Throughput optimization for high-volume ingest | Add queue partitioning, backpressure rules, and retry policy tuning by source type (Odluke/USUD/ECHR/Drive). | Better ingestion latency under load with bounded error rate and predictable queue behavior. | queue config, ingestion jobs/services, monitoring |

## Suggested Execution Order

1. P0-01, P0-02, P0-03, P0-04 (core ingestion + analysis continuity).
2. P0-07, P0-08 (pipeline reliability + hygiene safety).
3. P0-05, P0-06 (Legal Artillery lifecycle correctness).
4. P1-01 through P1-06 (consolidation and hardening).
5. P2 items (cleanup and optimization).

## Definition of Done (Global)

1. End-to-end ingest from at least two entry points (`/uploader`, Drive) automatically reaches OCR/embedding/graph/analysis where configured.
2. Defense-dependent analysis artifacts (`dates_with_context`, `case_references`, completeness state) are generated without manual intervention.
3. Legal Artillery approval/dispatch lifecycle behaves identically across API and Livewire flows.
4. Observability can trace one document from entry to final analysis with a single correlation ID.
