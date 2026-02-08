Below is a fully decomposed, agent‑ready task list for all sprints (1–8). Each sprint includes per‑file/class targets, test commands, and acceptance criteria. I kept each list to 4–6 bullets per section.

  Sprint 1
  
  Implementation
  - Approval Gate (LegalArtilleryAgent, DocumentGenerationController): enforce server‑side guard in app/Agents/LegalArtilleryAgent.php::dispatchApproved() and add an approval API action in app/Http/Controllers/Api/DocumentGenerationController.php (or Livewire action) that writes approved_at/approved_by to app/Models/
    DocumentGenerationRun.php.
  - Completeness Validation (ArgumentValidator): implement required‑section checks per profile in app/Services/LegalArtillery/ArgumentValidator.php and store results in model_config.completeness_check; block approval when invalid.
  - DOCX Integrity (DocxRenderer, LegalArtilleryAgent): verify render output exists and is readable in app/Services/LegalArtillery/DocxRenderer.php and enforce a failure status in app/Agents/LegalArtilleryAgent.php when missing.
  - UI Flow (Livewire): add Generate → Review → Approve → Dispatch UI state in app/Livewire/LegalArtillery/RunDetails.php and resources/views/livewire/legal-artillery/run-details.blade.php, including an approval banner and disabled dispatch buttons.

  - Unit: php artisan test tests/Unit/Services/LegalArtillery/ArgumentValidatorTest.php (new tests for completeness rules).
  - Livewire: php artisan test tests/Feature/Livewire/LegalArtilleryRunDetailsTest.php (new, button states and banners).
  - Integration: php artisan test tests/Feature/Commands/LegalArtillery/FireCommandTest.php (verify DOCX path exists).

  Acceptance
  - Gate: dispatch fails when approved_at/approved_by is missing with a clear error.
  - Metadata: run details show prompt version, profile key, and model config.
  - DOCX: run is not “ready” unless DOCX exists and is readable.
  - UI: lawyers can complete Generate → Review → Approve → Dispatch without CLI/DB edits.
  ———


  Sprint 2 — Evidence‑Grounded Drafts (Trust + Provenance)
  
  Implementation
  - Context Integration (ProfileContextBuilder): add Evidence + Misconduct outputs to the generation context in app/Services/LegalArtillery/ProfileContextBuilder.php, reading case_id/evidence_ids from DocumentGenerationController.
  - Citation Map (ResponseHandler): implement paragraph‑level provenance mapping in app/Services/LegalArtillery/ResponseHandler.php, storing a paragraph_citations map in DocumentGenerationRun::model_config.
  - No‑Source Enforcement (LegalArtilleryAgent): block approval when any paragraph is ungrounded; add config threshold in config/legal-artillery.php.
  - Legal Citation Validation (ArgumentValidator): validate ZKP/Ustav references in app/Services/LegalArtillery/ArgumentValidator.php with app/Services/HrLegalCitationsDetector.php.
  Tests
  - Unit: php artisan test tests/Unit/Services/LegalArtillery/ProfileContextBuilderTest.php (new, evidence/misconduct injection).
  - Unit: php artisan test tests/Unit/Services/LegalArtillery/ResponseHandlerTest.php (new, citation map).
  - Feature API: php artisan test tests/Feature/Api/DocumentGenerationGroundingTest.php (new, approval blocked without citations).
  Acceptance
  - Grounding: every paragraph has at least one source or is flagged.
  - Approval Block: ungrounded drafts cannot be approved.

  Sprint 3 — Practical Dispatch + Signing Reliability

  - Dispatch Preview (Gmail/EKom): add dry‑run mode returning payload + attachment path in app/Services/LegalArtillery/GmailDispatcher.php and app/Services/LegalArtillery/EKomunikacijaDispatcher.php.
  - Signing Preflight (DigitalSigner): check device, certificate, and PIN before signing in app/Services/LegalArtillery/DigitalSigner.php.
  - Dispatch Logging (LegalArtilleryAgent): persist dispatch result + status in DocumentGenerationRun::model_config and add retry entrypoint in app/Agents/LegalArtilleryAgent.php.
  - UI Status (Livewire): add dispatch status and retry controls in app/Livewire/LegalArtillery/RunDetails.php.


  - Unit: php artisan test tests/Unit/Services/LegalArtillery/DigitalSignerTest.php (preflight failure modes).
  - Unit: php artisan test tests/Unit/Services/LegalArtillery/GmailDispatcherTest.php (dry‑run payload).


  - Preview: users can view exact outgoing document before dispatch.
  - Retry: failed dispatches can be retried without regenerating.
  ———

Sprint 4

  Implementation

  - Fixtures (Tests): add gold‑set docs in tests/Fixtures/LegalArtillery/ and expected sections/citations.
  - Quality Gate (New Service): add app/Services/LegalArtillery/QualityGate.php that scores drafts and stores quality_score in model_config.
  - CI Hook: add a focused test entry in scripts/run-focused-tests.sh (if used) or CI workflow.

  Tests

  - Unit: php artisan test tests/Unit/Services/LegalArtillery/QualityGateTest.php.
  - Unit: php artisan test tests/Unit/Services/LegalArtillery/ArgumentValidatorTest.php (new failure modes).
  - Regression: php artisan test tests/Feature/LegalArtilleryGoldenSetTest.php.


Sprint 5
  
  Acceptance

  - Quality: drafts below threshold cannot be approved.
  - Validation: common failure modes are blocked with clear errors.

  Implementation

  - Template Library (resources): add court‑specific templates under resources/legal-artillery/templates/{court}/.
  - Config Mapping: map courts to templates in config/legal-artillery.php.
  - Renderer Selection: select templates by court/recipient in app/Services/LegalArtillery/DocxRenderer.php.
  - Profile Adjustments: update DocumentProfile definitions to include court metadata.
  Tests

  - Unit: php artisan test tests/Unit/Services/LegalArtillery/DocxRendererTest.php (template selection).
  - Unit: php artisan test tests/Unit/Services/LegalArtillery/TemplateLintTest.php.
  - Regression: php artisan test tests/Feature/LegalArtilleryGoldenSetTest.php (court variants).
  Acceptance
  - Template Match: correct court template selected for a run.
  - Render: DOCX output matches court format rules.

  ———

  Sprint 6 — Case Intake → Draft Auto‑Flow
  Implementation

  - Case Bridge (New Service): add app/Services/LegalArtillery/CaseBridge.php to assemble case facts, timeline, and evidence IDs.
  - Context Builder: use CaseBridge in ProfileContextBuilder for case_id requests.
  - UI Action: add “Generate from Case” action in app/Livewire/LegalArtillery/NewGeneration.php.
  - API Input: extend GenerateDocumentRequest and controller to accept case_id and optional evidence_ids.
  - Warnings: include “missing evidence” warnings in run details when CaseBridge data is incomplete.

  Tests

  - Unit: php artisan test tests/Unit/Services/LegalArtillery/CaseBridgeTest.php.
  - Feature: php artisan test tests/Feature/Api/DocumentGenerationCaseTest.php.
  - Livewire: php artisan test tests/Feature/Livewire/LegalArtilleryNewGenerationTest.php.
  - Integration: php artisan test tests/Feature/CaseIntakeToLegalArtilleryTest.php.

  Acceptance

  - One‑Click: user can generate a draft from an existing case in UI.
  - Context: key case facts and evidence appear in assembled context.
  - Warnings: missing evidence is flagged in run details.

  ———

  Sprint 7 — Reliability & Cost Control
  Implementation

  - Adaptive Iteration: update LegalArtilleryOrchestrator::shouldStop() to stop on stable score/low delta.
  - Cost Budget: add per‑run token budget in app/Services/LegalArtillery/LlmClient.php and store spend in DocumentGenerationRun::model_config.
  - Rate Limits: add per‑user generation throttles in DocumentGenerationController or middleware.
  - Caching: use PromptVersionManager to cache prompts and avoid re‑fetch.
  - Monitoring: emit run duration/cost metrics in logs.

  Tests

  - Unit: php artisan test tests/Unit/Services/LegalArtillery/LlmClientTest.php (budget enforcement).
  - Unit: php artisan test tests/Unit/Agents/LegalArtilleryOrchestratorTest.php (stop logic).
  - Feature: php artisan test tests/Feature/Api/DocumentGenerationRateLimitTest.php.
  - Performance: php artisan test tests/Performance/LegalArtilleryCostTest.php (if present).

  Acceptance

  - Budget: runs stop or fail gracefully when budget exceeded.
  - Efficiency: iterations stop early when convergence reached.
  - Throttling: per‑user rate limit enforced with clear response.

  ———

  Sprint 8 — Auditability & Compliance
  Implementation

  - Immutable Archive: store final document + context snapshot + hash in storage/app/legal-artillery/archive/ with reference in DocumentGenerationRun.
  - PII Redaction: add app/Services/LegalArtillery/PiiRedactor.php and apply before logging or external calls.
  - Audit Report: add /api/documents/runs/{id}/audit endpoint in DocumentGenerationController exporting JSON/PDF.
  - Access Control: restrict audit access to run owner or admins in policies.
  - Retention: add retention settings in config/legal-artillery.php and a cleanup command.

  Tests

  - Unit: php artisan test tests/Unit/Services/LegalArtillery/PiiRedactorTest.php.
  - Feature: php artisan test tests/Feature/Api/DocumentGenerationAuditTest.php.
  - Policy: php artisan test tests/Feature/Authorization/DocumentGenerationAuditPolicyTest.php.
  - Command: php artisan test tests/Feature/Commands/LegalArtilleryArchiveCleanupTest.php.

  Acceptance

  - Audit: export contains inputs, outputs, citations, approval/dispatch metadata.
  - PII: sensitive data is redacted in logs and external payloads.
  - Retention: archive cleanup respects policy without data loss.
