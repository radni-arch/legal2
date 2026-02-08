# Sprint 1 — Scenario Ingestion & First‑Class Inputs
  Goal: make the documents first‑class inputs for LegalArtillery.
  Tasks

  - Create scenario bundle folder and store PDFs + extracted text.
      - Target: storage/app/legal-artillery/scenarios/pp_prz_74_2025/
  - Build scenario manifest JSON with timeline + facts.
      - Target: app/Services/LegalArtillery/ScenarioLoader.php (new)
      - Target: app/Livewire/LegalArtillery/NewGeneration.php + view

  - Loader returns structured context from manifest.

# Sprint 2 — Profile Templates & Prompt Packs
  Goal: build the real profile pack used for this scenario.

  Tasks

  - Add 7 document profiles for the scenario:
      1. uvid_spis_initial
      2. pozurnica
      3. zurna_predstavka_predsjednici
      5. podsjetnik_dopuna
      7. upravni_nadzor
  - Add profile mapping to config.
  - Add “required sections” per profile for validation.
      - Target: app/Services/LegalArtillery/ArgumentValidator.php

  - Each profile renders a valid document structure.

  ———

# Sprint 3

  Goal: bind filings to source documents and enforce references.
  Tasks
  - Extend ProfileContextBuilder to attach scenario docs + timeline.
      - Target: app/Services/LegalArtillery/ProfileContextBuilder.php
      - Target: app/Services/LegalArtillery/ArgumentValidator.php or ResponseHandler
  - Enforce that drafts reference correct filings by source map checks.

  Acceptance
  - Missing source references are flagged.

  ———

# Sprint 4

  Goal: one‑click barrage with all filings generated in order.

  Tasks

      - Target: app/Agents/LegalArtilleryAgent.php::barrage()
  - Add output folder for the scenario.

  - One action produces all 7 filings.

  ———

# Sprint 5

  Goal: enforce real‑world legal consistency.

  Tasks

  - Create golden test set from real documents.
  - Build checks for:
      - correct case number & dates
      - legal basis citations
      - required core docs list
      - formal request structure
  - Add quality score gating for approval.
      - Target: app/Services/LegalArtillery/QualityGate.php

  Acceptance

  - Tests detect missing sections or wrong citations.
  - Drafts below threshold fail approval.

# Sprint 6 — Review Pack + Approval & Dispatch Readiness

  Goal: allow real‑world review and safe approval.

  Tasks

  - Add UI “Review Pack” view showing all 7 drafts + source map.
      - Target: app/Livewire/LegalArtillery/RunDetails.php
  - Approval gate for each document with signature + dispatch preview.
      - Target: app/Agents/LegalArtilleryAgent.php
      - Target: app/Services/LegalArtillery/DocxRenderer.php

  Acceptance

  - Lawyer can approve one or all filings.
  - Dispatch blocked without approval.

  ———

  # Sprint 7 — Escalation Ladder Suggester

  Goal: after failed complaint, suggest next higher echelon.

  Implementation

  - Configurable ladder:
      - config/escalation-ladders.php defines hierarchy (court admin → higher court admin → ministry → judicial council → ombudsman).
  - Suggester logic:
      - app/Services/LegalArtillery/EscalationLadderSuggester.php
      - Ingests failed attempt + response type + elapsed time; selects next rung; returns needed inputs and attachments.
  - Run integration:
      - Store escalation state in DocumentGenerationRun::model_config (last action, response, next rung).
      - Files: app/Agents/LegalArtilleryAgent.php, app/Models/DocumentGenerationRun.php
  - UI:
      - “Next Escalation Suggestion” panel with confirm/override.
      - Files: app/Livewire/LegalArtillery/RunDetails.php, resources/views/livewire/legal-artillery/run-details.blade.php
  - Safety guard:
      - Require explicit confirmation before generating escalation docs.

  Tests

  - Feature: tests/Feature/LegalArtilleryEscalationFlowTest.php
  - Config: tests/Unit/Config/EscalationLaddersTest.php

  Acceptance

  - After failed outcome, system proposes next rung.
  - Suggestion is visible in UI and requires confirmation.
  - Ladder is configurable per jurisdiction.
  - Escalation suggestion logged in run metadata.

