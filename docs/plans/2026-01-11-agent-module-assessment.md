# AI Legal War Machine - Agent & Module Assessment Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Comprehensive assessment of all AI agents, AbuseDetectionFramework, evidence relativization, and drug overcharging modules with grading and improvement recommendations.

**Architecture:** Analysis-driven assessment with capability grading (1-10), completeness metrics, and actionable improvement tasks.

**Tech Stack:** Laravel TALL Stack, OpenAI GPT-4o, Neo4j Graph DB, Croatian Legal Framework (ZKP, KZ, Ustav RH)

---

## Executive Summary

| Category | Components | Overall Grade | Status |
|----------|------------|---------------|--------|
| AI Agents | 12 total (1 deprecated) | 7.8/10 | Production Ready |
| AbuseDetectionFramework | 7 detectors (3 planned) | 8.5/10 | Production Ready |
| Evidence Relativization | 6 services | 8.2/10 | 95% Complete |
| Drug Overcharging | 1 detector | 9.0/10 | 100% Complete |

---

## Part 1: AI Agent Assessment

### Agent Inventory

| # | Agent | Status | Game-Changing Grade (1-10) | Completeness |
|---|-------|--------|---------------------------|--------------|
| 1 | AutonomousResearchAgent | **DEPRECATED** | 6/10 | 100% (legacy) |
| 2 | DecisionDiscoveryAgent | Active | **9/10** | 100% |
| 3 | OdlukeAgent | Active | 7/10 | 100% |
| 4 | RecursiveDocumentWritingAgent | Active | **8/10** | 100% |
| 5 | ResearchSpecialistAgent | Active | **9/10** | 100% |
| 6 | PrecedentAnalystAgent | Active | **8/10** | 100% |
| 7 | StrategySpecialistAgent | Active | **9/10** | 100% |
| 8 | RiskAnalystAgent | Active | 8/10 | 100% |
| 9 | OrchestratorService | Active | 7/10 | 100% |
| 10 | DynamicAgentSpawner | Active | 7/10 | 100% |
| 11 | OdlukeSearchAgent | Active | **8/10** | 100% |
| 12 | AgentPlanValidator | Active | 6/10 | 100% |

### Detailed Agent Analysis

#### Tier 1: Game-Changers (9-10/10)

**DecisionDiscoveryAgent** - Grade: 9/10
- **Purpose:** Autonomous discovery of Croatian court decisions from odluke.sudovi.hr
- **Why Game-Changing:**
  - Self-generating research topics via LLM
  - Autonomous relevance scoring (0-100)
  - Active learning with confidence calibration
  - Batch processing with rate limiting
- **Capabilities:** Topic generation, query rewriting, decision scoring, precedent analysis
- **Dependencies:** OdlukeClient, OpenAIService, ActiveLearningService, ConfidenceCalibrator
- **Improvement Potential:** Add cross-jurisdiction comparison (EU courts)

**ResearchSpecialistAgent** - Grade: 9/10
- **Purpose:** Comprehensive legal research with hybrid search
- **Why Game-Changing:**
  - Graph-enhanced research (Neo4j integration)
  - Court authority multipliers (Supreme: 1.5x, High: 1.3x)
  - Nested reasoning traces for explainability
  - Hybrid vector + keyword search
- **Capabilities:** Research planning, law/decision search, result prioritization
- **Dependencies:** LawSearchService, DecisionSearchService, GraphResearchEnhancer
- **Improvement Potential:** Add international law integration

**StrategySpecialistAgent** - Grade: 9/10
- **Purpose:** Legal strategy development and action planning
- **Why Game-Changing:**
  - Multi-argument generation (3-5 per case)
  - Counterargument identification
  - Settlement analysis
  - Tiered action planning (immediate/short/medium-term)
- **Capabilities:** Argument development, recommendations, procedural planning
- **Improvement Potential:** Add opponent modeling for adversarial strategy

#### Tier 2: High Value (7-8/10)

**RecursiveDocumentWritingAgent** - Grade: 8/10
- **Purpose:** Iterative document generation with Worker-Critic loop
- **Capabilities:** Convergence detection, multi-iteration tracking, weighted scoring
- **Improvement Potential:** Add template library for common document types

**PrecedentAnalystAgent** - Grade: 8/10
- **Purpose:** Precedent applicability and binding authority assessment
- **Capabilities:** Applicability scoring, binding/persuasive classification, factor extraction
- **Improvement Potential:** Add timeline visualization of precedent evolution

**RiskAnalystAgent** - Grade: 8/10
- **Purpose:** Risk identification and mitigation strategy
- **Capabilities:** Argument risk analysis, adverse precedent detection, procedural risk matrix
- **Improvement Potential:** Add quantitative risk modeling (Monte Carlo)

**OdlukeSearchAgent** - Grade: 8/10
- **Purpose:** Home search warrant case extraction from court decisions
- **Capabilities:** 14-field extraction, validation, deduplication, statistical analysis
- **Improvement Potential:** Expand to other case types beyond home search

**OdlukeAgent** - Grade: 7/10
- **Purpose:** MCP-based court decision search and download
- **Capabilities:** Async execution, caching, user-friendly summarization
- **Improvement Potential:** Add bulk download with progress tracking

**OrchestratorService** - Grade: 7/10
- **Purpose:** Multi-agent pipeline orchestration
- **Capabilities:** Budget enforcement, shared context, execution history
- **Improvement Potential:** Add parallel agent execution

**DynamicAgentSpawner** - Grade: 7/10
- **Purpose:** Dynamic agent spawning with recursion safety
- **Capabilities:** MAX_DEPTH=3, spawn loop detection, cost estimation
- **Improvement Potential:** Add adaptive budget allocation based on task complexity

#### Tier 3: Deprecated/Support (6/10)

**AutonomousResearchAgent** - Grade: 6/10 (DEPRECATED)
- **Status:** Use ResearchOrchestrator instead
- **Reason:** Superseded by specialist agent collaboration pattern
- **Action:** Remove from codebase after migration verification

**AgentPlanValidator** - Grade: 6/10
- **Purpose:** Plan validation before execution
- **Improvement Potential:** Add semantic validation of plan coherence

---

## Part 2: AbuseDetectionFramework Assessment

### Framework Overview

**Status:** Production Ready (95% complete)
**Architecture:** Strategy Pattern with TopicAnalyzer base class
**Legal Framework:** ZKP, KZ, Ustav RH (Croatian law)

### Detector Inventory

| # | Detector | Status | Grade (1-10) | Test Coverage |
|---|----------|--------|--------------|---------------|
| 1 | HomeSearchAbuseDetector | **COMPLETE** | **9/10** | 23 tests |
| 2 | DrugChargeAbuseDetector | **COMPLETE** | **9/10** | 22 tests |
| 3 | IllegalSearchDetector | **COMPLETE** | 8/10 | Complete |
| 4 | DisproportionateSentencingDetector | **COMPLETE** | 8/10 | Complete |
| 5 | ExcessivePretensionDetector | **COMPLETE** | 8/10 | Complete |
| 6 | ConstitutionalViolationDetector | **COMPLETE** | 8/10 | Integration |
| 7 | MisconductDetector | **COMPLETE** | 8/10 | Complete |
| 8 | BailAbuseDetector | **PLANNED** | - | - |
| 9 | DetentionAbuseDetector | **PLANNED** | - | - |
| 10 | WitnessAbuseDetector | **PLANNED** | - | - |

### Detailed Detector Analysis

#### HomeSearchAbuseDetector - Grade: 9/10
**File:** `app/Modules/HomeSearch/Services/HomeSearchAbuseDetector.php` (522 lines)

**Detected Patterns (7 types):**
| Pattern | Severity | Legal Basis |
|---------|----------|-------------|
| No judicial approval | 95 | ZKP Čl. 215 |
| Search exceeded warrant scope | 90 | ZKP Čl. 220 |
| Minor offense with invasive search | 85 | Ustav RH Čl. 34 |
| Pretextual searches | 85 | ZKP Čl. 179 |
| Disproportionate force | 80 | ZKP Čl. 179 |
| Night raids for minor offenses | 75 | ZKP Čl. 217 |
| Weak/vague justification | 70 | ZKP Čl. 216 |

**Capabilities:**
- Abuse pattern detection with severity scoring (0-100)
- Legal violation identification
- Defense strategy generation
- Proportionality analysis integration

**Supporting Services:**
- ProportionalityAnalyzer (428 lines) - Three-part proportionality test
- StatisticalAnalyzer (580 lines) - Regional pattern analysis
- OdlukeSearchAgent (1168 lines) - Court decision extraction

#### DrugChargeAbuseDetector - Grade: 9/10
**File:** `app/Modules/Topics/Analyzers/DrugChargeAbuseDetector.php` (673 lines)

**Personal Use Thresholds:**
| Drug | Threshold | Typical Range |
|------|-----------|---------------|
| Cannabis | 30g | 20-50g |
| Cocaine | 1g | 0.5-2g |
| Heroin | 1g | 0.5-2g |
| MDMA/Ecstasy | 5 pills | 3-10 pills |
| Amphetamine | 2g | 1-3g |

**Overcharging Patterns (3 types):**
| Pattern | Severity | Description |
|---------|----------|-------------|
| Minimal amount (<50% threshold) | 90 | Charged as dealing |
| Personal use amount | 85 | Within threshold but charged as KZ Čl. 190 |
| No evidence of dealing intent | 80 | No scales, baggies, cash, phone records |

**Capabilities:**
- Threshold analysis against case law
- AI-powered drug info extraction from court decisions
- Regional disparity comparison
- Defense strategy generation

### Framework Strengths
- Extensible Strategy Pattern architecture
- Comprehensive legal framework integration
- Consistent severity scoring (0-100) across all detectors
- Defense strategy generation for each abuse type
- Real data integration via OdlukeSearchAgent

### Framework Gaps
- BailAbuseDetector (planned but not implemented)
- DetentionAbuseDetector (planned but not implemented)
- WitnessAbuseDetector (planned but not implemented)

---

## Part 3: Evidence Relativization Module Assessment

### Module Overview

**Status:** 95% Complete
**Location:** `app/Modules/Evidence/`
**Purpose:** Counter prosecutor's selective evidence presentation

### Service Inventory

| # | Service | Lines | Grade (1-10) | Test Coverage |
|---|---------|-------|--------------|---------------|
| 1 | RecontextualizationService | 641 | **9/10** | Integration only |
| 2 | ContextAnalyzer | 650 | **8/10** | Integration only |
| 3 | EvidenceAdmissibilityChecker | 291 | 8/10 | Unit + Integration |
| 4 | ConstitutionalViolationDetector | 257 | 8/10 | Integration only |
| 5 | AlternativeInterpretationAnalyzer | 236 | 7/10 | Integration only |
| 6 | SuppressionMotionGenerator | 215 | 7/10 | Integration only |
| 7 | EvidenceAnalysisModule (Orchestrator) | 457 | 8/10 | Feature tests |

### Detailed Service Analysis

#### RecontextualizationService - Grade: 9/10
**Purpose:** Generate defense recontextualization based on actual evidence

**Capabilities:**
- Prosecution narrative extraction
- Defense recontextualization generation (GPT-4o)
- Key difference highlighting
- Supporting evidence identification
- Credibility scoring (0-100)

**Credibility Scoring:**
| Factor | Points |
|--------|--------|
| Base (neutral) | 50 |
| Prosecutor omitted significant context | +20 |
| Supporting evidence for defense | +15 |
| Objective support (metadata/timestamps) | +15 |

**Ethical Framework:**
- ✅ Based on actual evidence only
- ✅ Shows omitted context from prosecutor
- ✅ Legitimate alternative interpretations
- ❌ No fabrication, distortion, or false evidence

#### ContextAnalyzer - Grade: 8/10
**Purpose:** Identify prosecutor's selective presentation

**Detects 5 Types of Selective Presentation:**
1. Partial Messages (SMS/email/chat)
2. Cherry-Picked Timestamps
3. Out-of-Context Media (photos/videos)
4. Partial Witness Statements
5. Selective Financial Records

**Output Fields:**
- `prosecution_presentation` - What prosecutor showed
- `full_context` - Complete available context
- `selective_presentation` - Detection results
- `omitted_context` - Specific facts left out
- `recontextualization_opportunities` - Defense angles

#### EvidenceAdmissibilityChecker - Grade: 8/10
**Purpose:** Check evidence admissibility under Croatian ZKP

**Checks Performed:**
| Check | Legal Basis |
|-------|-------------|
| Lawfulness | ZKP Čl. 9 |
| No Coercion | ZKP Čl. 10 |
| Chain of Custody | ZKP Čl. 11 |
| Relevance | ZKP Čl. 292 |
| Authentication | ZKP Čl. 293 |
| Procedural Compliance | ZKP Čl. 405 |

### Module Strengths
- Comprehensive selective presentation detection
- Ethical guardrails preventing evidence fabrication
- Strong legal framework integration (ZKP, Ustav RH)
- AI-powered analysis with GPT-4o
- Credibility scoring system

### Module Gaps
- Missing unit tests for RecontextualizationService
- Missing unit tests for ContextAnalyzer
- Missing unit tests for ConstitutionalViolationDetector
- Missing unit tests for AlternativeInterpretationAnalyzer
- SuppressionMotionGenerator uses placeholder for evidence fetching

---

## Part 4: Improvement Tasks

### Task 1: Add Unit Tests for RecontextualizationService

**Files:**
- Create: `tests/Unit/Modules/Evidence/RecontextualizationServiceTest.php`

**Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Modules\Evidence;

use App\Modules\Evidence\Services\RecontextualizationService;
use App\Services\OpenAIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class RecontextualizationServiceTest extends TestCase
{
    use RefreshDatabase;

    private RecontextualizationService $service;
    private $mockOpenAI;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockOpenAI = Mockery::mock(OpenAIService::class);
        $this->service = new RecontextualizationService($this->mockOpenAI);
    }

    /** @test */
    public function it_generates_recontextualization_for_selective_evidence()
    {
        $this->mockOpenAI->shouldReceive('chatJson')
            ->once()
            ->andReturn([
                'defense_narrative' => 'Full context shows...',
                'key_differences' => ['Prosecutor omitted timestamps'],
                'supporting_evidence' => ['metadata', 'documents'],
            ]);

        $result = $this->service->recontextualize([
            'evidence_id' => 'EV-001',
            'prosecution_presentation' => 'Defendant sent threatening message',
            'full_context' => 'Message was response to provocation',
        ]);

        $this->assertArrayHasKey('defense_narrative', $result);
        $this->assertArrayHasKey('credibility_score', $result);
        $this->assertGreaterThan(50, $result['credibility_score']);
    }

    /** @test */
    public function it_calculates_credibility_score_correctly()
    {
        // Test credibility scoring logic
        $result = $this->service->calculateCredibilityScore([
            'omitted_context' => true,
            'supporting_evidence' => ['doc1', 'doc2'],
            'objective_support' => true,
        ]);

        $this->assertEquals(100, $result); // 50 + 20 + 15 + 15
    }

    /** @test */
    public function it_refuses_to_fabricate_evidence()
    {
        $result = $this->service->recontextualize([
            'evidence_id' => 'EV-002',
            'prosecution_presentation' => 'Defendant confessed',
            'full_context' => null, // No actual context available
        ]);

        $this->assertFalse($result['fabrication_attempted']);
        $this->assertNull($result['defense_narrative']);
    }
}
```

**Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Modules/Evidence/RecontextualizationServiceTest.php -v`
Expected: FAIL (test file doesn't exist)

**Step 3: Create test file with full implementation**

**Step 4: Run test to verify it passes**

Run: `php artisan test tests/Unit/Modules/Evidence/RecontextualizationServiceTest.php -v`
Expected: PASS

**Step 5: Commit**

```bash
git add tests/Unit/Modules/Evidence/RecontextualizationServiceTest.php
git commit -m "test: add unit tests for RecontextualizationService"
```

---

### Task 2: Add Unit Tests for ContextAnalyzer

**Files:**
- Create: `tests/Unit/Modules/Evidence/ContextAnalyzerTest.php`

**Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Modules\Evidence;

use App\Modules\Evidence\Services\ContextAnalyzer;
use App\Services\OpenAIService;
use Mockery;
use Tests\TestCase;

class ContextAnalyzerTest extends TestCase
{
    /** @test */
    public function it_detects_partial_message_selective_presentation()
    {
        $mockOpenAI = Mockery::mock(OpenAIService::class);
        $mockOpenAI->shouldReceive('chatJson')->andReturn([
            'selective_presentation_detected' => true,
            'type' => 'partial_message',
            'severity' => 'high',
        ]);

        $analyzer = new ContextAnalyzer($mockOpenAI);

        $result = $analyzer->analyzeContext([
            'evidence_type' => 'sms',
            'prosecution_excerpt' => 'I will kill you',
            'full_thread' => 'You: I will kill you (joking about video game)',
        ]);

        $this->assertTrue($result['selective_presentation']['detected']);
        $this->assertEquals('partial_message', $result['selective_presentation']['type']);
    }

    /** @test */
    public function it_identifies_all_five_selective_presentation_types()
    {
        $types = ContextAnalyzer::SELECTIVE_PRESENTATION_TYPES;

        $this->assertContains('partial_message', $types);
        $this->assertContains('cherry_picked_timestamps', $types);
        $this->assertContains('out_of_context_media', $types);
        $this->assertContains('partial_witness_statement', $types);
        $this->assertContains('selective_financial_records', $types);
    }
}
```

**Step 2-5:** Same pattern as Task 1

---

### Task 3: Implement BailAbuseDetector

**Files:**
- Create: `app/Modules/Topics/Analyzers/BailAbuseDetector.php`
- Create: `tests/Unit/Topics/BailAbuseDetectorTest.php`

**Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Topics;

use App\Modules\Topics\Analyzers\BailAbuseDetector;
use Tests\TestCase;

class BailAbuseDetectorTest extends TestCase
{
    /** @test */
    public function it_detects_excessive_bail_for_minor_offense()
    {
        $detector = new BailAbuseDetector();

        $result = $detector->analyzeCase([
            'offense_type' => 'misdemeanor',
            'offense_severity' => 'minor',
            'bail_amount' => 500000, // 500,000 HRK
            'defendant_income' => 5000, // Monthly income
        ]);

        $this->assertTrue($result['abuse_detected']);
        $this->assertGreaterThan(70, $result['severity']);
        $this->assertContains('excessive_bail', $result['patterns']);
    }

    /** @test */
    public function it_detects_bail_denial_without_flight_risk()
    {
        $detector = new BailAbuseDetector();

        $result = $detector->analyzeCase([
            'bail_denied' => true,
            'flight_risk_evidence' => null,
            'community_ties' => 'strong',
            'prior_court_appearances' => 'all_attended',
        ]);

        $this->assertTrue($result['abuse_detected']);
        $this->assertContains('unjustified_denial', $result['patterns']);
    }
}
```

**Step 2:** Run test to verify it fails

**Step 3: Implement BailAbuseDetector**

```php
<?php

namespace App\Modules\Topics\Analyzers;

use App\Modules\Topics\TopicAnalyzer;

class BailAbuseDetector extends TopicAnalyzer
{
    protected string $topicKey = 'bail_abuse';
    protected string $topicName = 'Bail Denial/Excessive Bail Abuse';

    // Bail thresholds as percentage of monthly income
    private const EXCESSIVE_BAIL_THRESHOLD = 100; // 100x monthly income
    private const HIGH_BAIL_THRESHOLD = 50;

    public function analyzeCase(array $caseData): array
    {
        $patterns = $this->detectPatterns($caseData);
        $severity = $this->calculateAbuseSeverity($patterns, $caseData);
        $defenseStrategy = $this->generateDefenseStrategy($patterns, $caseData);

        return [
            'abuse_detected' => !empty($patterns),
            'patterns' => $patterns,
            'severity' => $severity,
            'defense_strategy' => $defenseStrategy,
            'legal_violations' => $this->identifyLegalViolations($patterns),
        ];
    }

    public function detectPatterns(array $caseData): array
    {
        $patterns = [];

        // Pattern 1: Excessive bail for minor offense
        if ($this->isExcessiveBail($caseData)) {
            $patterns[] = 'excessive_bail';
        }

        // Pattern 2: Bail denied without flight risk evidence
        if ($this->isDeniedWithoutFlightRisk($caseData)) {
            $patterns[] = 'unjustified_denial';
        }

        // Pattern 3: Bail conditions impossible to meet
        if ($this->hasImpossibleConditions($caseData)) {
            $patterns[] = 'impossible_conditions';
        }

        return $patterns;
    }

    // ... additional methods
}
```

**Step 4-5:** Run tests and commit

---

### Task 4: Fix SuppressionMotionGenerator Database Integration

**Files:**
- Modify: `app/Modules/Evidence/Services/SuppressionMotionGenerator.php`

**Step 1: Write the failing test**

```php
/** @test */
public function it_fetches_evidence_from_database()
{
    $evidence = Evidence::factory()->create([
        'id' => 'EV-001',
        'type' => 'physical',
        'description' => 'Seized documents',
    ]);

    $generator = app(SuppressionMotionGenerator::class);
    $motion = $generator->generate('EV-001', ['violation' => 'illegal_search']);

    $this->assertStringContains('Seized documents', $motion['evidence_description']);
}
```

**Step 2-5:** Implement and test

---

### Task 5: Remove Deprecated AutonomousResearchAgent

**Files:**
- Delete: `app/Agents/AutonomousResearchAgent.php`
- Modify: References in service providers

**Step 1: Verify no active usage**

Run: `grep -r "AutonomousResearchAgent" app/ --include="*.php" | grep -v "use ResearchOrchestrator"`

**Step 2: Remove file if no active usage**

**Step 3: Update service provider bindings**

**Step 4: Run full test suite**

**Step 5: Commit**

```bash
git add -A
git commit -m "refactor: remove deprecated AutonomousResearchAgent"
```

---

## Part 5: Summary Recommendations

### High Priority (Immediate)
1. Add unit tests for RecontextualizationService and ContextAnalyzer
2. Implement BailAbuseDetector (planned but missing)
3. Fix SuppressionMotionGenerator database integration

### Medium Priority (Next Sprint)
1. Implement DetentionAbuseDetector
2. Implement WitnessAbuseDetector
3. Add parallel execution to OrchestratorService
4. Remove deprecated AutonomousResearchAgent

### Low Priority (Future)
1. Add international law integration to ResearchSpecialistAgent
2. Add opponent modeling to StrategySpecialistAgent
3. Add Monte Carlo risk modeling to RiskAnalystAgent
4. Expand OdlukeSearchAgent beyond home search cases

### Overall Assessment

| Category | Grade | Verdict |
|----------|-------|---------|
| AI Agents | 7.8/10 | Strong foundation, 1 deprecated agent to remove |
| AbuseDetectionFramework | 8.5/10 | Production ready, 3 detectors planned |
| Evidence Relativization | 8.2/10 | Needs unit test coverage |
| Drug Overcharging | 9.0/10 | Fully complete, excellent coverage |
| **OVERALL** | **8.4/10** | **Production Ready with minor gaps** |

---

Plan complete and saved to `docs/plans/2026-01-11-agent-module-assessment.md`. Two execution options:

**1. Subagent-Driven (this session)** - I dispatch fresh subagent per task, review between tasks, fast iteration

**2. Parallel Session (separate)** - Open new session with executing-plans, batch execution with checkpoints

**Which approach?**
