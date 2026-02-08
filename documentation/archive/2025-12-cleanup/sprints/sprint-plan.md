# Sprint Plan: Prosecutorial Misconduct & Evidence Recontextualization Modules

**Project**: AI Legal War Machine - Croatian Legal Defense System
**Date**: 2025-10-28
**Estimated Total Time**: 24-28 hours (4 sprints × 6-7 hours each)

---

## Overview

This sprint plan covers implementation of two critical defense modules:

1. **ProsecutorialMisconductModule** - Detecting and responding to prosecutorial misconduct (fabricated probable cause, hidden evidence, backdated documents, rights violations, threats/lying)
2. **EvidenceRelativizationModule** - Legitimate recontextualization of evidence (showing full context when prosecutor uses partial/selective evidence)

**Ethical Framework**:
- ✅ Detecting actual misconduct through pattern analysis
- ✅ Legitimate recontextualization (showing full context vs. prosecutor's selective use)
- ✅ Fact-based alternative interpretations
- ❌ Fabricating evidence or misconduct
- ❌ Creating false narratives
- ❌ Obstructing justice

---

## Sprint 1: ProsecutorialMisconductModule Foundation (6-7 hours)

### Objective
Build the core misconduct detection system that identifies patterns of prosecutorial abuse in Croatian criminal proceedings.

### Tasks

#### Task 1.1: Create ProsecutorialMisconductModule Entry Point
**File**: `app/Modules/Misconduct/ProsecutorialMisconductModule.php` (NEW)
**Time**: 1.5 hours

**Purpose**: Main entry point for misconduct analysis

**Structure**:
```php
<?php

namespace App\Modules\Misconduct;

use App\Models\LegalCase;
use App\Modules\Misconduct\Services\MisconductDetector;
use App\Modules\Misconduct\Services\MisconductPatternAnalyzer;
use Illuminate\Support\Facades\Log;

class ProsecutorialMisconductModule
{
    public function __construct(
        protected MisconductDetector $detector,
        protected MisconductPatternAnalyzer $patternAnalyzer
    ) {}

    /**
     * Analyze case for prosecutorial misconduct
     */
    public function analyzeMisconduct(string $caseId, array $options = []): array
    {
        $case = LegalCase::with(['documents', 'evidence'])->findOrFail($caseId);

        // Detect specific instances of misconduct
        $misconductInstances = $this->detector->detect($case, $options);

        // Identify patterns across the case
        $patterns = $this->patternAnalyzer->analyzePatterns($misconductInstances, $case);

        // Calculate severity score
        $severityScore = $this->calculateSeverityScore($misconductInstances);

        return [
            'case_id' => $caseId,
            'misconduct_detected' => !empty($misconductInstances),
            'total_violations' => count($misconductInstances),
            'severity_score' => $severityScore,
            'instances' => $misconductInstances,
            'patterns' => $patterns,
            'recommended_actions' => $this->recommendActions($misconductInstances, $patterns),
            'dismissal_grounds' => $this->identifyDismissalGrounds($misconductInstances),
        ];
    }

    protected function calculateSeverityScore(array $instances): int
    {
        if (empty($instances)) return 0;

        $totalSeverity = array_sum(array_column($instances, 'severity'));
        $avgSeverity = $totalSeverity / count($instances);

        // Bonus for multiple violations
        $multiplier = 1 + (count($instances) * 0.1);

        return min(100, (int)($avgSeverity * $multiplier));
    }

    protected function recommendActions(array $instances, array $patterns): array
    {
        $actions = [];

        // High severity = dismissal motion
        if ($this->calculateSeverityScore($instances) >= 80) {
            $actions[] = [
                'action' => 'file_dismissal_motion',
                'priority' => 'urgent',
                'description' => 'File motion to dismiss based on egregious prosecutorial misconduct',
            ];
        }

        // Pattern of rights violations = complaint to judicial council
        if (isset($patterns['rights_violations']) && count($patterns['rights_violations']) >= 2) {
            $actions[] = [
                'action' => 'file_judicial_complaint',
                'priority' => 'high',
                'description' => 'File complaint with Državno sudbeno vijeće (Judicial Council)',
            ];
        }

        return $actions;
    }

    protected function identifyDismissalGrounds(array $instances): array
    {
        $grounds = [];

        foreach ($instances as $instance) {
            if ($instance['severity'] >= 85 || $instance['mandates_dismissal']) {
                $grounds[] = [
                    'type' => $instance['type'],
                    'legal_basis' => $instance['legal_basis'],
                    'description' => $instance['description'],
                    'remedy' => 'Case dismissal',
                ];
            }
        }

        return $grounds;
    }
}
```

**Acceptance Criteria**:
- ✅ Module instantiates with dependency injection
- ✅ `analyzeMisconduct()` returns structured analysis
- ✅ Severity score calculated correctly (0-100)
- ✅ Dismissal grounds identified for severe violations
- ✅ Recommended actions prioritized

---

#### Task 1.2: Create MisconductDetector Service
**File**: `app/Modules/Misconduct/Services/MisconductDetector.php` (NEW)
**Time**: 3 hours

**Purpose**: Detect specific instances of prosecutorial misconduct

**Key Methods**:
```php
public function detect(LegalCase $case, array $options = []): array
{
    $violations = [];

    // Check for fabricated probable cause
    $violations = array_merge($violations, $this->detectFabricatedProbableCause($case));

    // Check for hidden/withheld evidence (Brady violations)
    $violations = array_merge($violations, $this->detectHiddenEvidence($case));

    // Check for backdated documents
    $violations = array_merge($violations, $this->detectBackdatedDocuments($case));

    // Check for rights violations (no lawyer, coerced statements)
    $violations = array_merge($violations, $this->detectRightsViolations($case));

    // Check for prosecutor threats/lying
    $violations = array_merge($violations, $this->detectThreatsOrLying($case));

    // Check for misdemeanor pretexting (charging misdemeanor to investigate felony)
    $violations = array_merge($violations, $this->detectMisdemeanorPretexting($case));

    return $violations;
}

protected function detectFabricatedProbableCause(LegalCase $case): array
{
    // Use OpenAI to analyze arrest/search warrant affidavits
    // Look for: vague descriptions, conclusory statements, no specific facts
    // Compare to actual evidence collected
}

protected function detectHiddenEvidence(LegalCase $case): array
{
    // Brady v. Maryland violations
    // Check if prosecution disclosed all exculpatory evidence
    // Look for late disclosures (disclosed right before trial)
}

protected function detectBackdatedDocuments(LegalCase $case): array
{
    // Analyze document metadata, timestamps
    // Compare dates in document headers vs. file creation dates
    // Look for inconsistencies in chronology
}

protected function detectRightsViolations(LegalCase $case): array
{
    // Check if defendant was:
    // - Informed of rights (Ustav RH Članak 29)
    // - Allowed lawyer access
    // - Interrogated without lawyer present
    // - Subjected to threats/coercion
}
```

**Misconduct Types to Detect**:

| Type | Legal Basis | Severity | Remedy |
|------|-------------|----------|--------|
| Fabricated Probable Cause | ZKP Članak 9, Ustav RH Čl. 32 | 90 | Evidence suppression, dismissal |
| Hidden Evidence (Brady) | ZKP Članak 292, Ustav RH Čl. 29 | 95 | Dismissal, retrial |
| Backdated Documents | ZKP Članak 11 | 85 | Evidence suppression |
| Rights Violations (no lawyer) | Ustav RH Članak 29(3) | 90 | Statement suppression |
| Prosecutor Threats/Lying | Ustav RH Članak 23, 29 | 95 | Dismissal, disciplinary action |
| Misdemeanor Pretexting | ZKP Članak 9 | 75 | Evidence suppression |

**Acceptance Criteria**:
- ✅ Detects all 6 misconduct types
- ✅ Each violation includes: type, description, legal_basis, severity, remedy
- ✅ Uses OpenAI for pattern recognition in documents
- ✅ Returns empty array if no misconduct found
- ✅ Logs all detection attempts

---

#### Task 1.3: Create MisconductPatternAnalyzer Service
**File**: `app/Modules/Misconduct/Services/MisconductPatternAnalyzer.php` (NEW)
**Time**: 2 hours

**Purpose**: Identify patterns of misconduct across multiple violations

**Key Methods**:
```php
public function analyzePatterns(array $instances, LegalCase $case): array
{
    return [
        'repeated_violations' => $this->findRepeatedViolations($instances),
        'escalating_severity' => $this->detectEscalatingSeverity($instances),
        'rights_violations_pattern' => $this->analyzeRightsViolationPattern($instances),
        'evidence_suppression_pattern' => $this->analyzeEvidenceSuppressionPattern($instances),
        'systemic_issues' => $this->identifySystemicIssues($instances, $case),
    ];
}

protected function findRepeatedViolations(array $instances): array
{
    // Group by type, count occurrences
    $types = array_column($instances, 'type');
    $counts = array_count_values($types);

    return array_filter($counts, fn($count) => $count >= 2);
}

protected function detectEscalatingSeverity(array $instances): bool
{
    // Check if violations are getting more severe over time
    usort($instances, fn($a, $b) => $a['timestamp'] <=> $b['timestamp']);

    $severities = array_column($instances, 'severity');

    // If severity is generally increasing, return true
    // Simple heuristic: compare first half to second half
}

protected function identifySystemicIssues(array $instances, LegalCase $case): array
{
    // If multiple types of violations from same prosecutor/police unit
    // Indicates systemic problem, not isolated incident

    $prosecutors = array_column($instances, 'prosecutor');
    $policeUnits = array_column($instances, 'police_unit');

    // Return systemic issues if same actors appear multiple times
}
```

**Acceptance Criteria**:
- ✅ Identifies repeated violation types
- ✅ Detects escalating severity patterns
- ✅ Groups rights violations
- ✅ Identifies systemic issues (same prosecutor/unit)
- ✅ Returns structured pattern analysis

---

#### Task 1.4: Create Unit Tests for Sprint 1
**File**: `tests/Unit/MisconductDetectorTest.php` (NEW)
**Time**: 1.5 hours

**Test Cases**:
- `test_detects_fabricated_probable_cause()`
- `test_detects_hidden_evidence()`
- `test_detects_backdated_documents()`
- `test_detects_rights_violations()`
- `test_detects_threats_or_lying()`
- `test_returns_empty_array_when_no_misconduct()`
- `test_pattern_analyzer_finds_repeated_violations()`
- `test_pattern_analyzer_detects_systemic_issues()`

**Acceptance Criteria**:
- ✅ All tests pass
- ✅ Code coverage > 80%
- ✅ Uses RefreshDatabase trait
- ✅ Mocks OpenAI service

---

## Sprint 2: ProsecutorialMisconductModule Legal Actions (7-8 hours)

### Objective
Build legal action generators (dismissal motions, complaints, appeals) based on detected misconduct.

### Tasks

#### Task 2.1: Create DismissalMotionGenerator
**File**: `app/Modules/Misconduct/Services/DismissalMotionGenerator.php` (NEW)
**Time**: 2.5 hours

**Purpose**: Generate motions to dismiss case based on prosecutorial misconduct

**Key Methods**:
```php
public function generate(LegalCase $case, array $misconductInstances): array
{
    // Filter for dismissal-worthy violations (severity >= 85)
    $dismissalGrounds = array_filter(
        $misconductInstances,
        fn($v) => $v['severity'] >= 85 || $v['mandates_dismissal']
    );

    if (empty($dismissalGrounds)) {
        return ['dismissal_warranted' => false];
    }

    // Generate motion text in Croatian
    $motionText = $this->generateMotionText($case, $dismissalGrounds);

    return [
        'dismissal_warranted' => true,
        'motion_type' => 'Prijedlog za obustavu postupka',
        'grounds' => $dismissalGrounds,
        'motion_text' => $motionText,
        'legal_authorities' => $this->gatherAuthorities($dismissalGrounds),
        'filing_instructions' => $this->getFilingInstructions($case),
    ];
}

protected function generateMotionText(LegalCase $case, array $grounds): string
{
    $prompt = <<<PROMPT
Generate a formal Croatian court motion to dismiss criminal case due to prosecutorial misconduct.

Case: {$case->title}
Court: {$case->court}

Grounds for Dismissal:
{$this->formatGrounds($grounds)}

Generate "PRIJEDLOG ZA OBUSTAVU POSTUPKA" with structure:
1. Naslov: PRIJEDLOG ZA OBUSTAVU POSTUPKA
2. Sud i broj predmeta
3. Stranke
4. Pravna osnova (ZKP Članak 175, 177, Ustav RH)
5. Činjenično stanje (Facts - describe misconduct)
6. Pravna argumentacija (Explain why misconduct mandates dismissal)
7. Zahtjev: "Predlažem sudu da obustavi kazneni postupak..."
8. Potpis i datum

Use formal Croatian legal language. Cite specific ZKP and Ustav RH articles.
PROMPT;

    return $this->openAI->chat([
        ['role' => 'system', 'content' => 'You are a Croatian criminal defense attorney.'],
        ['role' => 'user', 'content' => $prompt],
    ], 'gpt-4o', ['temperature' => 0.3])['choices'][0]['message']['content'];
}
```

**Acceptance Criteria**:
- ✅ Generates dismissal motion only when warranted (severity >= 85)
- ✅ Motion text in Croatian legal format
- ✅ Cites ZKP and Ustav RH authorities
- ✅ Includes filing instructions
- ✅ Returns dismissal_warranted = false if not justified

---

#### Task 2.2: Create ComplaintGenerator
**File**: `app/Modules/Misconduct/Services/ComplaintGenerator.php` (NEW)
**Time**: 2 hours

**Purpose**: Generate complaints to State Attorney's Office and Judicial Council

**Complaint Types**:
1. **Complaint to State Attorney's Office** (Državno odvjetništvo) - for individual prosecutor misconduct
2. **Complaint to Judicial Council** (Državno sudbeno vijeće) - for judge misconduct
3. **Complaint to Police Internal Affairs** - for police misconduct

**Key Methods**:
```php
public function generateComplaint(
    LegalCase $case,
    array $misconductInstances,
    string $complaintType
): array {
    switch ($complaintType) {
        case 'state_attorney':
            return $this->generateStateAttorneyComplaint($case, $misconductInstances);
        case 'judicial_council':
            return $this->generateJudicialCouncilComplaint($case, $misconductInstances);
        case 'police_internal_affairs':
            return $this->generatePoliceComplaint($case, $misconductInstances);
        default:
            throw new \InvalidArgumentException("Unknown complaint type: $complaintType");
    }
}

protected function generateStateAttorneyComplaint(LegalCase $case, array $instances): array
{
    // Format: "PRIGOVOR NA RAD DRŽAVNOG ODVJETNIKA"
    // Submit to: Chief State Attorney (Glavni državni odvjetnik)
    // Legal basis: Zakon o državnom odvjetništvu (State Attorney Act)
}
```

**Acceptance Criteria**:
- ✅ Generates 3 types of complaints
- ✅ Croatian legal format for each type
- ✅ Includes submission instructions (where to file, deadlines)
- ✅ Cites legal authorities
- ✅ Attaches evidence of misconduct

---

#### Task 2.3: Create AppealBuilder
**File**: `app/Modules/Misconduct/Services/AppealBuilder.php` (NEW)
**Time**: 2 hours

**Purpose**: Build appeals based on prosecutorial misconduct

**Key Methods**:
```php
public function buildAppeal(
    LegalCase $case,
    array $misconductInstances,
    string $appealType = 'zalba'
): array {
    // Croatian appeal types:
    // 1. Žalba (appeal to higher court)
    // 2. Zahtjev za zaštitu zakonitosti (request for protection of legality - Supreme Court)
    // 3. Ustavna tužba (constitutional complaint - Constitutional Court)

    return [
        'appeal_type' => $appealType,
        'appeal_text' => $this->generateAppealText($case, $misconductInstances, $appealType),
        'grounds' => $this->identifyAppealGrounds($misconductInstances),
        'legal_authorities' => $this->gatherAppealAuthorities($appealType),
        'filing_deadline' => $this->calculateDeadline($case, $appealType),
    ];
}

protected function generateAppealText(
    LegalCase $case,
    array $instances,
    string $appealType
): string {
    // Generate "ŽALBA" or "USTAVNA TUŽBA" based on type
    // Structure:
    // 1. Naziv suda
    // 2. Žalitelj (appellant)
    // 3. Pobijana odluka (challenged decision)
    // 4. Žalbeni razlozi (grounds for appeal)
    // 5. Zahtjev (request - overturn conviction, dismiss case, etc.)
}
```

**Acceptance Criteria**:
- ✅ Supports 3 appeal types (žalba, zaštita zakonitosti, ustavna tužba)
- ✅ Generates appeal text in Croatian legal format
- ✅ Calculates filing deadlines (15 days for žalba, 30 days for ustavna tužba)
- ✅ Identifies strongest appeal grounds
- ✅ Cites relevant case law

---

#### Task 2.4: Create MisconductController
**File**: `app/Http/Controllers/MisconductController.php` (NEW)
**Time**: 1.5 hours

**Endpoints**:
- `POST /api/misconduct/analyze/{caseId}` - Analyze case for misconduct
- `POST /api/misconduct/dismissal-motion/{caseId}` - Generate dismissal motion
- `POST /api/misconduct/complaint/{caseId}` - Generate complaint
- `POST /api/misconduct/appeal/{caseId}` - Build appeal

**Structure**:
```php
<?php

namespace App\Http\Controllers;

use App\Modules\Misconduct\ProsecutorialMisconductModule;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MisconductController extends Controller
{
    public function __construct(
        protected ProsecutorialMisconductModule $misconductModule
    ) {}

    public function analyzeMisconduct(Request $request, string $caseId): JsonResponse
    {
        $options = $request->input('options', []);
        $result = $this->misconductModule->analyzeMisconduct($caseId, $options);

        return response()->json(['success' => true, 'data' => $result]);
    }

    public function generateDismissalMotion(Request $request, string $caseId): JsonResponse
    {
        // ... implementation
    }

    public function generateComplaint(Request $request, string $caseId): JsonResponse
    {
        $validated = $request->validate([
            'complaint_type' => 'required|in:state_attorney,judicial_council,police_internal_affairs',
        ]);

        // ... implementation
    }
}
```

**Acceptance Criteria**:
- ✅ All 4 endpoints functional
- ✅ Proper validation on requests
- ✅ Returns JSON responses
- ✅ Error handling with appropriate HTTP codes
- ✅ Logs all requests

---

#### Task 2.5: Create Routes and Feature Tests
**Files**:
- `routes/misconduct.php` (NEW)
- `tests/Feature/MisconductModuleTest.php` (NEW)

**Time**: 1.5 hours

**Routes**:
```php
Route::prefix('misconduct')->group(function () {
    Route::post('/analyze/{caseId}', [MisconductController::class, 'analyzeMisconduct']);
    Route::post('/dismissal-motion/{caseId}', [MisconductController::class, 'generateDismissalMotion']);
    Route::post('/complaint/{caseId}', [MisconductController::class, 'generateComplaint']);
    Route::post('/appeal/{caseId}', [MisconductController::class, 'buildAppeal']);
});
```

**File to Modify**: `routes/api.php`
**Modification**: Add at line ~40 (after evidence routes):
```php
// Prosecutorial Misconduct Module
require __DIR__.'/misconduct.php';
```

**Test Cases**:
- `test_analyze_misconduct_endpoint()`
- `test_detects_fabricated_probable_cause()`
- `test_detects_hidden_evidence_brady_violation()`
- `test_generates_dismissal_motion_for_severe_violations()`
- `test_does_not_generate_dismissal_for_minor_violations()`
- `test_generates_state_attorney_complaint()`
- `test_builds_appeal_with_misconduct_grounds()`

**Acceptance Criteria**:
- ✅ Routes registered in `routes/api.php`
- ✅ All tests pass
- ✅ Feature tests cover all endpoints
- ✅ Tests validate JSON structure
- ✅ Code coverage > 85%

---

## Sprint 3: EvidenceRelativizationModule (6-7 hours)

### Objective
Enhance existing EvidenceAnalysisModule with context-aware recontextualization capabilities to counter prosecutor's selective use of evidence.

### Tasks

#### Task 3.1: Create ContextAnalyzer Service
**File**: `app/Modules/Evidence/Services/ContextAnalyzer.php` (NEW)
**Time**: 2.5 hours

**Purpose**: Analyze full context of evidence to identify prosecutor's selective presentation

**Key Methods**:
```php
public function analyzeContext(array $evidence, LegalCase $case): array
{
    return [
        'evidence_id' => $evidence['id'],
        'prosecution_presentation' => $this->extractProsecutionPresentation($evidence),
        'full_context' => $this->extractFullContext($evidence, $case),
        'selective_presentation' => $this->detectSelectivePresentation($evidence, $case),
        'omitted_context' => $this->identifyOmittedContext($evidence, $case),
        'recontextualization_opportunities' => $this->findRecontextualizationOpportunities($evidence),
    ];
}

protected function detectSelectivePresentation(array $evidence, LegalCase $case): ?array
{
    // Examples of selective presentation:
    // 1. Partial SMS/email messages (prosecutor shows only incriminating part)
    // 2. Cherry-picked timestamps (ignoring exculpatory timeline)
    // 3. Out-of-context photos/videos
    // 4. Partial witness statements
    // 5. Selective financial records

    $prompt = <<<PROMPT
Analyze this evidence for selective presentation by prosecution:

Evidence Type: {$evidence['type']}
Prosecution's Presentation: {$evidence['prosecution_description']}
Available Full Evidence: {$evidence['full_content']}

Detect if prosecutor is:
1. Using partial messages/communications
2. Cherry-picking timestamps
3. Taking photos/videos out of context
4. Using partial witness statements
5. Showing selective financial records

Return JSON:
{
    "selective_presentation_detected": boolean,
    "type": "partial_message|cherry_picked_timeline|out_of_context_media|partial_statement|selective_records",
    "what_prosecutor_showed": "...",
    "what_prosecutor_omitted": "...",
    "why_omission_matters": "..."
}
PROMPT;

    $response = $this->openAI->chat([
        ['role' => 'system', 'content' => 'You are a defense expert analyzing evidence for selective presentation.'],
        ['role' => 'user', 'content' => $prompt],
    ], 'gpt-4o-mini', ['response_format' => ['type' => 'json_object']]);

    return json_decode($response['choices'][0]['message']['content'], true);
}

protected function identifyOmittedContext(array $evidence, LegalCase $case): array
{
    // What did prosecutor leave out?
    // - Prior messages in conversation
    // - Following messages that clarify intent
    // - Surrounding timeline events
    // - Alternative explanations visible in full evidence

    // Use OpenAI to compare full evidence to prosecution's presentation
    // Return specific omitted facts that change interpretation
}
```

**Examples of Legitimate Recontextualization**:

| Prosecutor Shows | Full Context | Defense Recontextualization |
|------------------|--------------|------------------------------|
| SMS: "I'll get the stuff tonight" | Full conversation: discussing buying groceries | "Stuff" refers to food, not drugs |
| Photo of defendant at scene | Timestamped photo metadata | Photo taken 2 hours before crime |
| Witness: "I saw him running" | Full statement: "running to catch bus" | Running was innocent activity |
| Bank withdrawal $5000 | Loan repayment to brother | Legitimate financial transaction |
| "He was angry at victim" | Full context: friendly argument about sports | No actual animosity |

**Acceptance Criteria**:
- ✅ Detects 5 types of selective presentation
- ✅ Identifies omitted context that changes interpretation
- ✅ Returns structured analysis with specific omissions
- ✅ Does NOT fabricate context (only uses actual evidence)
- ✅ Logs all context analysis

---

#### Task 3.2: Create RecontextualizationService
**File**: `app/Modules/Evidence/Services/RecontextualizationService.php` (NEW)
**Time**: 2.5 hours

**Purpose**: Generate defense recontextualization showing full context

**Key Methods**:
```php
public function recontextualize(array $evidence, array $contextAnalysis, LegalCase $case): array
{
    // If no selective presentation detected, return neutral
    if (!($contextAnalysis['selective_presentation']['selective_presentation_detected'] ?? false)) {
        return ['recontextualization_needed' => false];
    }

    return [
        'recontextualization_needed' => true,
        'prosecution_narrative' => $this->extractProsecutionNarrative($evidence, $contextAnalysis),
        'defense_recontextualization' => $this->generateDefenseRecontextualization(
            $evidence,
            $contextAnalysis,
            $case
        ),
        'key_differences' => $this->highlightKeyDifferences($contextAnalysis),
        'supporting_evidence' => $this->identifySupportingEvidence($contextAnalysis, $case),
        'credibility_score' => $this->calculateCredibilityScore($contextAnalysis),
    ];
}

protected function generateDefenseRecontextualization(
    array $evidence,
    array $contextAnalysis,
    LegalCase $case
): array {
    $prompt = <<<PROMPT
Generate a LEGITIMATE defense recontextualization of this evidence.

Evidence: {$evidence['description']}

Prosecution's Selective Presentation:
{$contextAnalysis['selective_presentation']['what_prosecutor_showed']}

Omitted Context:
{$contextAnalysis['selective_presentation']['what_prosecutor_omitted']}

Generate defense recontextualization that:
1. Shows FULL context (not just prosecutor's selective excerpt)
2. Explains why omitted context changes interpretation
3. Is based on ACTUAL evidence (no fabrication)
4. Provides alternative interpretation that favors defendant

ETHICAL CONSTRAINTS:
- Must be based on actual evidence
- Cannot fabricate context
- Cannot distort clear facts
- Must show genuine alternative interpretation

Return JSON with defense narrative and supporting facts.
PROMPT;

    $response = $this->openAI->chat([
        ['role' => 'system', 'content' => 'You are a defense attorney providing legitimate recontextualization.'],
        ['role' => 'user', 'content' => $prompt],
    ], 'gpt-4o', ['temperature' => 0.4, 'response_format' => ['type' => 'json_object']]);

    return json_decode($response['choices'][0]['message']['content'], true);
}

protected function calculateCredibilityScore(array $contextAnalysis): int
{
    // Score how credible the recontextualization is (0-100)
    // Higher score = more likely to create reasonable doubt

    $score = 50; // Base score

    // +20 if prosecutor omitted significant context
    if (isset($contextAnalysis['selective_presentation']['why_omission_matters'])) {
        $score += 20;
    }

    // +15 if we have supporting evidence for our recontextualization
    if (!empty($contextAnalysis['omitted_context'])) {
        $score += 15;
    }

    // +15 if recontextualization is based on objective evidence (metadata, timestamps)
    if ($this->hasObjectiveSupport($contextAnalysis)) {
        $score += 15;
    }

    return min(100, $score);
}
```

**Acceptance Criteria**:
- ✅ Generates recontextualization only when prosecutor used selective presentation
- ✅ Defense narrative based on full context, not fabricated
- ✅ Highlights specific omissions by prosecutor
- ✅ Calculates credibility score (0-100)
- ✅ Identifies supporting evidence for defense narrative

---

#### Task 3.3: Integrate with EvidenceAnalysisModule
**File to Modify**: `app/Modules/Evidence/EvidenceAnalysisModule.php`
**Time**: 1.5 hours

**Modifications**:

**Add at line ~20 (constructor dependencies)**:
```php
public function __construct(
    protected EvidenceAdmissibilityChecker $admissibilityChecker,
    protected ConstitutionalViolationDetector $constitutionalDetector,
    protected AlternativeInterpretationAnalyzer $interpretationAnalyzer,
    protected SuppressionMotionGenerator $motionGenerator,
    protected ContextAnalyzer $contextAnalyzer,               // NEW
    protected RecontextualizationService $recontextualizationService  // NEW
) {}
```

**Add new method at line ~200**:
```php
/**
 * Recontextualize evidence (show full context vs. prosecutor's selective use)
 */
public function recontextualizeEvidence(string $caseId, array $evidence): array
{
    $case = LegalCase::with('documents')->findOrFail($caseId);

    // Analyze context
    $contextAnalysis = $this->contextAnalyzer->analyzeContext($evidence, $case);

    // Generate recontextualization
    $recontextualization = $this->recontextualizationService->recontextualize(
        $evidence,
        $contextAnalysis,
        $case
    );

    return [
        'evidence_id' => $evidence['id'],
        'context_analysis' => $contextAnalysis,
        'recontextualization' => $recontextualization,
    ];
}
```

**Modify `analyzeEvidence()` method at line ~60** to include recontextualization:
```php
// Add after line ~120 (after alternative_interpretations):
'context_analysis' => $this->contextAnalyzer->analyzeContext($item, $case),
'recontextualization' => $this->recontextualizationService->recontextualize(
    $item,
    $this->contextAnalyzer->analyzeContext($item, $case),
    $case
),
```

**Acceptance Criteria**:
- ✅ `analyzeEvidence()` includes context analysis and recontextualization
- ✅ New method `recontextualizeEvidence()` works standalone
- ✅ Backward compatible (doesn't break existing code)
- ✅ All existing tests still pass

---

#### Task 3.4: Add Controller Endpoints
**File to Modify**: `app/Http/Controllers/EvidenceController.php`
**Time**: 1 hour

**Add new endpoint at line ~215**:
```php
/**
 * Recontextualize evidence (show full context)
 *
 * POST /api/evidence/recontextualize/{caseId}
 */
public function recontextualizeEvidence(Request $request, string $caseId): JsonResponse
{
    $validator = Validator::make($request->all(), [
        'evidence' => 'required|array',
        'evidence.id' => 'required|string',
        'evidence.type' => 'required|string',
        'evidence.description' => 'required|string',
        'evidence.prosecution_description' => 'sometimes|string',
        'evidence.full_content' => 'sometimes|string',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'error' => 'Validation failed',
            'details' => $validator->errors(),
        ], 422);
    }

    try {
        $evidence = $request->input('evidence');

        $result = $this->evidenceModule->recontextualizeEvidence($caseId, $evidence);

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
        ], 500);
    }
}
```

**File to Modify**: `routes/evidence.php`
**Add at line ~36**:
```php
// Recontextualize evidence
Route::post('/recontextualize/{caseId}', [EvidenceController::class, 'recontextualizeEvidence'])
    ->name('evidence.recontextualize');
```

**Acceptance Criteria**:
- ✅ New endpoint accessible at `/api/evidence/recontextualize/{caseId}`
- ✅ Validates request structure
- ✅ Returns context analysis and recontextualization
- ✅ Returns 422 on validation errors
- ✅ Returns 500 on exceptions

---

#### Task 3.5: Add Tests for Recontextualization
**File to Modify**: `tests/Feature/EvidenceModuleTest.php`
**Time**: 1.5 hours

**Add test cases at line ~205**:
```php
/** @test */
public function it_detects_selective_presentation_of_sms_messages()
{
    $evidence = [
        'id' => 'ev_sms1',
        'type' => 'communication',
        'description' => 'SMS message',
        'prosecution_description' => "I'll get the stuff tonight",
        'full_content' => "Full conversation:\n[10:00] Friend: Can you pick up groceries?\n[10:05] Defendant: I'll get the stuff tonight\n[10:06] Friend: Thanks, we need milk and bread",
    ];

    $module = app(EvidenceAnalysisModule::class);
    $result = $module->recontextualizeEvidence($this->testCase->id, $evidence);

    $this->assertTrue($result['context_analysis']['selective_presentation']['selective_presentation_detected']);
    $this->assertEquals('partial_message', $result['context_analysis']['selective_presentation']['type']);
    $this->assertNotEmpty($result['recontextualization']['defense_recontextualization']);
}

/** @test */
public function it_identifies_omitted_timeline_context()
{
    $evidence = [
        'id' => 'ev_photo1',
        'type' => 'photo',
        'description' => 'Photo of defendant at crime scene',
        'prosecution_description' => 'Defendant present at scene of crime',
        'full_content' => 'Photo metadata: timestamp 14:30, crime occurred at 16:45',
    ];

    $module = app(EvidenceAnalysisModule::class);
    $result = $module->recontextualizeEvidence($this->testCase->id, $evidence);

    $this->assertArrayHasKey('omitted_context', $result['context_analysis']);
    $this->assertGreaterThan(60, $result['recontextualization']['credibility_score']);
}

/** @test */
public function api_endpoint_recontextualizes_evidence()
{
    $evidence = [
        'id' => 'ev1',
        'type' => 'testimonial',
        'description' => 'Witness statement',
        'prosecution_description' => 'Witness said "he was angry"',
        'full_content' => 'Full statement: "He was angry at the referee during the soccer game, not at the victim"',
    ];

    $response = $this->postJson("/api/evidence/recontextualize/{$this->testCase->id}", [
        'evidence' => $evidence,
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'data' => [
                'evidence_id',
                'context_analysis',
                'recontextualization',
            ],
        ]);
}

/** @test */
public function does_not_recontextualize_when_no_selective_presentation()
{
    $evidence = [
        'id' => 'ev1',
        'type' => 'physical',
        'description' => 'Clear physical evidence with no context issues',
        'prosecution_description' => 'Defendant fingerprints on weapon',
        'full_content' => 'Defendant fingerprints on weapon (no additional context)',
    ];

    $module = app(EvidenceAnalysisModule::class);
    $result = $module->recontextualizeEvidence($this->testCase->id, $evidence);

    $this->assertFalse($result['recontextualization']['recontextualization_needed']);
}
```

**Acceptance Criteria**:
- ✅ Tests detect selective presentation (partial messages, cherry-picked timeline)
- ✅ Tests verify omitted context identification
- ✅ Tests validate API endpoint
- ✅ Tests verify no recontextualization when not needed
- ✅ All tests pass

---

## Sprint 4: Integration, Testing, and Documentation (5-6 hours)

### Objective
Integrate modules, comprehensive testing, and complete documentation.

### Tasks

#### Task 4.1: Integration Testing
**File**: `tests/Feature/MisconductEvidenceIntegrationTest.php` (NEW)
**Time**: 2 hours

**Purpose**: Test integration between ProsecutorialMisconductModule and EvidenceRelativizationModule

**Test Cases**:
```php
/** @test */
public function hidden_evidence_detected_and_recontextualized()
{
    // Scenario: Prosecutor hides exculpatory SMS messages
    // 1. MisconductModule detects Brady violation
    // 2. EvidenceModule recontextualizes with full messages
    // 3. DismissalMotion generated with both grounds
}

/** @test */
public function backdated_document_triggers_dismissal_and_appeal()
{
    // Scenario: Prosecutor backdates search warrant
    // 1. MisconductModule detects backdating
    // 2. Severity >= 85, triggers dismissal motion
    // 3. Appeal grounds include misconduct
}

/** @test */
public function pattern_of_violations_triggers_complaint()
{
    // Scenario: Multiple violations by same prosecutor
    // 1. PatternAnalyzer detects systemic issues
    // 2. Generates complaint to State Attorney
    // 3. Recommends judicial council complaint
}
```

**Acceptance Criteria**:
- ✅ Tests cover integration scenarios
- ✅ Tests verify data flows between modules
- ✅ Tests validate combined outputs (dismissal + recontextualization)
- ✅ All tests pass

---

#### Task 4.2: Create MISCONDUCT_MODULE.md Documentation
**File**: `docs/MISCONDUCT_MODULE.md` (NEW)
**Time**: 1.5 hours

**Structure** (similar to EVIDENCE_MODULE.md):
1. Overview
2. Ethical Use Statement
3. Features (6 misconduct detectors)
4. API Endpoints (4 endpoints)
5. Usage Examples (PHP code)
6. Croatian Legal Framework (ZKP, Ustav RH, Zakon o državnom odvjetništvu)
7. Components (all services)
8. Best Practices
9. Testing
10. Changelog

**Key Sections**:
```markdown
## Misconduct Types Detected

| Type | Legal Basis | Severity | Remedy |
|------|-------------|----------|--------|
| Fabricated Probable Cause | ZKP Članak 9, Ustav RH Čl. 32 | 90 | Evidence suppression, dismissal |
| Hidden Evidence (Brady) | ZKP Članak 292, Ustav RH Čl. 29 | 95 | Dismissal, retrial |
...

## API Endpoints

### 1. Analyze Misconduct
**POST** `/api/misconduct/analyze/{caseId}`
...

## Usage Examples

### Example 1: Detect Hidden Evidence
```php
use App\Modules\Misconduct\ProsecutorialMisconductModule;

$module = app(ProsecutorialMisconductModule::class);
$result = $module->analyzeMisconduct($caseId);

if ($result['severity_score'] >= 85) {
    echo "SEVERE MISCONDUCT DETECTED\n";
    // Generate dismissal motion
}
```
```

**Acceptance Criteria**:
- ✅ Documentation complete and thorough
- ✅ All 6 misconduct types documented
- ✅ API endpoints documented with examples
- ✅ Croatian legal citations accurate
- ✅ Usage examples functional

---

#### Task 4.3: Create EVIDENCE_RECONTEXTUALIZATION.md Documentation
**File**: `docs/EVIDENCE_RECONTEXTUALIZATION.md` (NEW)
**Time**: 1.5 hours

**Structure**:
1. Overview - What is recontextualization?
2. Ethical Framework - Legitimate vs. unethical
3. Selective Presentation Types (5 types)
4. How It Works (ContextAnalyzer → RecontextualizationService)
5. API Endpoint
6. Usage Examples
7. Real-World Scenarios (SMS, photos, witness statements, financial records, timeline)
8. Best Practices
9. Testing

**Key Content**:
```markdown
## What is Recontextualization?

**Recontextualization** is the process of showing the FULL context of evidence when prosecutors present only selective, incriminating portions.

### ✅ Ethical Recontextualization:
- Showing complete SMS conversation (not just prosecutor's excerpt)
- Revealing full timeline (not just cherry-picked timestamps)
- Providing complete witness statements (not partial quotes)
- Showing full financial records (not selective transactions)

### ❌ NOT Recontextualization:
- Fabricating context
- Creating fake messages
- Distorting clear evidence
- Manufacturing alternative timelines

---

## Real-World Scenarios

### Scenario 1: Partial SMS Messages

**Prosecutor Shows**: "I'll get the stuff tonight"
**Full Context**: Conversation about buying groceries
**Defense Recontextualization**: "Stuff" refers to food, not drugs

### Scenario 2: Cherry-Picked Timeline

**Prosecutor Shows**: Photo of defendant at scene
**Full Context**: Photo metadata shows 2 hours before crime
**Defense Recontextualization**: Defendant not present during crime

...
```

**Acceptance Criteria**:
- ✅ Explains recontextualization clearly
- ✅ Shows ethical boundaries
- ✅ Provides real-world examples
- ✅ API usage documented
- ✅ Integration with EvidenceModule explained

---

#### Task 4.4: Update Main Documentation
**Files to Modify**:
- `README.md` (add links to new modules)
- `docs/API.md` (if exists, add new endpoints)

**Time**: 1 hour

**Modifications to README.md** (around line ~50, after EvidenceAnalysisModule):
```markdown
### 4. ProsecutorialMisconductModule

Detects and responds to prosecutorial misconduct under Croatian law:
- Fabricated probable cause detection
- Hidden evidence (Brady violations)
- Backdated documents
- Rights violations
- Prosecutor threats/lying
- Misdemeanor pretexting

[Full Documentation](docs/MISCONDUCT_MODULE.md)

### 5. Evidence Recontextualization

Shows full context when prosecutors use selective evidence presentation:
- Complete message/communication context
- Full timeline (not cherry-picked)
- Complete witness statements
- Full financial records

[Full Documentation](docs/EVIDENCE_RECONTEXTUALIZATION.md)
```

**Acceptance Criteria**:
- ✅ README updated with new modules
- ✅ Links to documentation work
- ✅ API documentation updated (if applicable)
- ✅ Table of contents updated

---

#### Task 4.5: Final Testing and Code Review
**Time**: 1 hour

**Checklist**:
- [ ] Run full test suite: `php artisan test`
- [ ] Verify code coverage > 80%
- [ ] Check for N+1 queries (use Laravel Debugbar)
- [ ] Review all OpenAI prompts for clarity
- [ ] Verify Croatian legal citations accuracy
- [ ] Check ethical constraints in all services
- [ ] Test API endpoints with Postman/Insomnia
- [ ] Review error handling
- [ ] Check logging coverage
- [ ] Verify no secrets in code

**Acceptance Criteria**:
- ✅ All tests pass
- ✅ Code coverage > 80%
- ✅ No performance issues
- ✅ Ethical constraints enforced
- ✅ Croatian legal framework accurate

---

## Summary

### Total Time Estimate: 24-28 hours

| Sprint | Focus | Time | Files Created | Files Modified |
|--------|-------|------|---------------|----------------|
| Sprint 1 | Misconduct Detection | 6-7h | 4 | 0 |
| Sprint 2 | Legal Actions | 7-8h | 6 | 1 |
| Sprint 3 | Recontextualization | 6-7h | 2 | 3 |
| Sprint 4 | Integration & Docs | 5-6h | 3 | 2 |
| **TOTAL** | **All Modules** | **24-28h** | **15** | **6** |

### Files Created (15 total):

**ProsecutorialMisconductModule**:
1. `app/Modules/Misconduct/ProsecutorialMisconductModule.php`
2. `app/Modules/Misconduct/Services/MisconductDetector.php`
3. `app/Modules/Misconduct/Services/MisconductPatternAnalyzer.php`
4. `app/Modules/Misconduct/Services/DismissalMotionGenerator.php`
5. `app/Modules/Misconduct/Services/ComplaintGenerator.php`
6. `app/Modules/Misconduct/Services/AppealBuilder.php`
7. `app/Http/Controllers/MisconductController.php`
8. `routes/misconduct.php`
9. `tests/Unit/MisconductDetectorTest.php`
10. `tests/Feature/MisconductModuleTest.php`

**EvidenceRelativizationModule**:
11. `app/Modules/Evidence/Services/ContextAnalyzer.php`
12. `app/Modules/Evidence/Services/RecontextualizationService.php`

**Integration & Documentation**:
13. `tests/Feature/MisconductEvidenceIntegrationTest.php`
14. `docs/MISCONDUCT_MODULE.md`
15. `docs/EVIDENCE_RECONTEXTUALIZATION.md`

### Files Modified (6 total):
1. `app/Modules/Evidence/EvidenceAnalysisModule.php` - Add recontextualization
2. `app/Http/Controllers/EvidenceController.php` - Add recontextualize endpoint
3. `routes/evidence.php` - Add recontextualize route
4. `routes/api.php` - Register misconduct routes
5. `tests/Feature/EvidenceModuleTest.php` - Add recontextualization tests
6. `README.md` - Add new modules to documentation

---

## Dependencies

### External Services:
- OpenAI API (GPT-4o, GPT-4o-mini)

### Laravel Packages:
- Laravel Queue (for background processing)
- Laravel Broadcasting (already implemented in Sprint 1-2 from previous work)

### Croatian Legal Sources:
- ZKP (Zakon o kaznenom postupku)
- Ustav RH (Croatian Constitution)
- Zakon o državnom odvjetništvu (State Attorney Act)
- Case law references (for appeals)

---

## Implementation Notes

### Ethical Safeguards:
1. **ContextAnalyzer** only uses actual evidence (no fabrication)
2. **MisconductDetector** requires evidence of actual misconduct (not assumptions)
3. All services log operations for accountability
4. Credibility scoring ensures only strong recontextualizations used

### Performance:
- Cache OpenAI responses (24 hours TTL)
- Queue heavy analysis jobs
- Use GPT-4o-mini for pattern detection (faster, cheaper)
- Use GPT-4o for motion generation (higher quality)

### Croatian Legal Compliance:
- All motions use proper Croatian legal format
- Citations accurate (ZKP článci, Ustav RH članci)
- Filing instructions specific to Croatian courts
- Deadlines calculated per Croatian procedural law

---

## Next Steps After Implementation

1. **User Acceptance Testing**: Test with real Croatian case scenarios
2. **Legal Review**: Have Croatian attorney review motions/complaints
3. **Performance Testing**: Load test with multiple concurrent analyses
4. **UI Integration**: Build frontend components for misconduct detection and recontextualization
5. **Monitoring**: Set up alerts for high-severity misconduct detections

---

## Questions for Clarification

1. Should we integrate with any specific Croatian legal databases (e.g., PIRS - Pravosuđe Informacijski Sustav)?
2. Do you want automatic complaint filing, or just generation (manual filing)?
3. Should we track prosecutor/police patterns across multiple cases (database of known bad actors)?
4. Do you want real-time alerts when severe misconduct detected (e.g., email/Slack)?

---

**Ready to Proceed?**

This sprint plan provides detailed, manageable tasks for a coding agent. Each task has:
- ✅ Specific file paths (new or modified)
- ✅ Code structure and examples
- ✅ Clear acceptance criteria
- ✅ Time estimates
- ✅ Dependencies identified

Once approved, we can begin Sprint 1 implementation.
