# Specific Fact Pattern Extractor Integration Examples

This document provides **production-ready**, **highly specific** integration examples showing exactly how to use the FactPatternExtractor with other modules in your AI Legal War Machine.

## Table of Contents

1. [Evidence Strategy Analyzer](#evidence-strategy-analyzer)
2. [Multi-Agent Case Analyzer](#multi-agent-case-analyzer)
3. [Automated Legal Memo Generator](#automated-legal-memo-generator)
4. [Discovery Request Generator](#discovery-request-generator)
5. [Chronology Builder](#chronology-builder)
6. [Complete Workflow Examples](#complete-workflow-examples)

---

## Evidence Strategy Analyzer

**Purpose:** Analyze fact patterns to develop comprehensive evidence collection and presentation strategies.

**Location:** `app/Services/Evidence/EvidenceStrategyAnalyzer.php`

### Basic Usage

```php
use App\Services\Evidence\EvidenceStrategyAnalyzer;

$analyzer = app(EvidenceStrategyAnalyzer::class);

// Analyze evidence needs from a fact pattern
$strategy = $analyzer->analyzeEvidenceNeeds($factPatternId);

// Access the results
$existingEvidence = $strategy['existing_evidence'];
$evidenceGaps = $strategy['evidence_gaps'];
$discoveryPlan = $strategy['discovery_plan'];
$strengthScore = $strategy['evidence_strength_score'];
```

### Example Output

```php
[
    'evidence_strength_score' => 0.72,  // 0-1 scale
    'existing_evidence' => [
        'total_pieces' => 5,
        'by_type' => [
            'documentary' => 3,
            'testimonial' => 2,
        ],
        'by_strength' => [
            'strong' => [/* 2 strong pieces */],
            'moderate' => [/* 2 moderate */],
            'weak' => [/* 1 weak */],
        ],
        'critical_evidence' => [/* Available strong evidence */],
    ],
    'evidence_gaps' => [
        [
            'type' => 'disputed_fact_without_evidence',
            'description' => 'Need evidence to support: Contract was signed on March 15',
            'priority' => 'high',
            'suggested_evidence_types' => ['documentary', 'testimonial'],
        ],
    ],
    'discovery_plan' => [
        'interrogatories' => [/* Generated questions */],
        'document_requests' => [/* Specific documents needed */],
        'deposition_targets' => [/* People to depose */],
        'timeline' => [/* 4-phase discovery timeline */],
    ],
    'recommendations' => [
        [
            'priority' => 'urgent',
            'recommendation' => 'Evidence is currently weak. Immediate action needed...',
            'actions' => [/* Specific steps */],
        ],
    ],
]
```

### Real-World Example

```php
class CasePreparationController extends Controller
{
    public function prepareEvidence(string $caseId, EvidenceStrategyAnalyzer $analyzer)
    {
        // Get case's fact pattern
        $case = LegalCase::findOrFail($caseId);
        $factPatternId = $case->fact_pattern_id;

        // Analyze evidence strategy
        $strategy = $analyzer->analyzeEvidenceNeeds($factPatternId);

        // Generate evidence checklist
        $checklist = $analyzer->generateEvidenceChecklist($factPatternId);

        // Present to attorney
        return view('case.evidence-strategy', [
            'strategy' => $strategy,
            'checklist' => $checklist,
            'urgentActions' => $this->extractUrgentActions($strategy),
        ]);
    }

    protected function extractUrgentActions(array $strategy): array
    {
        return array_filter($strategy['evidence_gaps'], function($gap) {
            return $gap['priority'] === 'high';
        });
    }
}
```

### What It Does

1. ✅ Analyzes all existing evidence by type, strength, and availability
2. ✅ Identifies critical gaps in evidence collection
3. ✅ Generates complete discovery plan (interrogatories, document requests, depositions)
4. ✅ Calculates evidence strength score (0-1)
5. ✅ Provides specific recommendations with priorities
6. ✅ Creates actionable checklist with deadlines

---

## Multi-Agent Case Analyzer

**Purpose:** Orchestrate multiple specialist AI agents to analyze a case comprehensively.

**Location:** `app/Services/Collaboration/FactDrivenMultiAgentAnalyzer.php`

### Basic Usage

```php
use App\Services\Collaboration\FactDrivenMultiAgentAnalyzer;

$multiAgent = app(FactDrivenMultiAgentAnalyzer::class);

// Run complete multi-agent analysis
$analysis = $multiAgent->analyzeCaseWithAgents($factPatternId);

// Access results from each agent
$researchResults = $analysis['results']['research'];
$strategyResults = $analysis['results']['strategy'];
$riskResults = $analysis['results']['risk'];
$evidenceResults = $analysis['results']['evidence'];
$synthesis = $analysis['synthesis'];
```

### Example Output

```php
[
    'success' => true,
    'collaboration_id' => '01HKJM...',
    'fact_pattern_id' => '9c8f7e6d...',
    'agents_executed' => ['research', 'strategy', 'risk', 'evidence'],
    'results' => [
        'research' => [
            'precedents_found' => 15,
            'key_precedents' => [/* Relevant cases */],
            'legal_principles' => [/* Applicable principles */],
            'jurisdiction_analysis' => [/* Jurisdiction details */],
        ],
        'strategy' => [
            'primary_objective' => 'Maximize damages',
            'recommended_approach' => 'Aggressive litigation - strong facts support success',
            'key_arguments' => [/* Top 5 arguments */],
            'settlement_considerations' => [/* Settlement analysis */],
        ],
        'risk' => [
            'overall_risk_level' => 'medium',
            'risk_score' => 0.45,
            'factual_risks' => [/* Identified risks */],
            'mitigation_strategies' => [/* How to address */],
        ],
        'evidence' => [
            'existing_evidence_strength' => 'strong',
            'critical_evidence_gaps' => [/* Gaps */],
            'discovery_priorities' => [/* Priorities */],
        ],
    ],
    'synthesis' => [
        'overall_case_assessment' => 'Case has merit but requires careful strategy execution',
        'success_probability' => 0.68,
        'confidence_level' => 0.87,
        'integrated_strategy' => [/* Combined approach */],
        'priority_actions' => [/* Top 3 actions */],
        'resource_requirements' => [/* Estimated costs */],
    ],
    'recommendations' => [/* Final recommendations */],
]
```

### Real-World Example

```php
class CaseAnalysisService
{
    public function performComprehensiveAnalysis(string $caseId)
    {
        $case = LegalCase::findOrFail($caseId);

        // Run multi-agent analysis
        $multiAgent = app(FactDrivenMultiAgentAnalyzer::class);
        $analysis = $multiAgent->analyzeCaseWithAgents($case->fact_pattern_id);

        // Generate attorney briefing
        $briefing = $this->generateAttorneyBriefing($analysis);

        // Create action plan
        $actionPlan = $this->createActionPlan($analysis['synthesis']['priority_actions']);

        // Update case with findings
        $case->update([
            'success_probability' => $analysis['synthesis']['success_probability'],
            'risk_level' => $analysis['results']['risk']['overall_risk_level'],
            'next_review_date' => now()->addDays(7),
        ]);

        // Send notification to attorney
        $this->notifyAttorney($case, $briefing);

        return [
            'analysis' => $analysis,
            'briefing' => $briefing,
            'action_plan' => $actionPlan,
        ];
    }

    protected function generateAttorneyBriefing(array $analysis): string
    {
        $briefing = "CASE ANALYSIS SUMMARY\n\n";

        $briefing .= "Success Probability: " . ($analysis['synthesis']['success_probability'] * 100) . "%\n";
        $briefing .= "Risk Level: " . $analysis['results']['risk']['overall_risk_level'] . "\n\n";

        $briefing .= "KEY FINDINGS:\n";
        $briefing .= "- " . $analysis['results']['research']['precedents_found'] . " relevant precedents identified\n";
        $briefing .= "- Recommended Approach: " . $analysis['results']['strategy']['recommended_approach'] . "\n";
        $briefing .= "- Evidence Status: " . $analysis['results']['evidence']['existing_evidence_strength'] . "\n\n";

        $briefing .= "PRIORITY ACTIONS:\n";
        foreach ($analysis['synthesis']['priority_actions'] as $action) {
            $briefing .= "- " . $action['action'] . "\n";
        }

        return $briefing;
    }
}
```

### What It Does

1. ✅ **Research Agent** - Finds relevant precedents and laws
2. ✅ **Strategy Agent** - Develops legal strategy and arguments
3. ✅ **Risk Agent** - Assesses risks and weaknesses
4. ✅ **Evidence Agent** - Analyzes evidence needs
5. ✅ Synthesizes all results into cohesive analysis
6. ✅ Provides success probability estimate
7. ✅ Generates prioritized action plan

---

## Automated Legal Memo Generator

**Purpose:** Generate professional legal memoranda from fact patterns using AI.

**Location:** `app/Services/Documents/AutomatedLegalMemoGenerator.php`

### Basic Usage

```php
use App\Services\Documents\AutomatedLegalMemoGenerator;

$memoGenerator = app(AutomatedLegalMemoGenerator::class);

// Generate complete memo
$memo = $memoGenerator->generateMemo($factPatternId, [
    'to' => 'Senior Partner',
    'from' => 'Associate Attorney',
]);

// Access sections
$header = $memo['header'];
$issue = $memo['issue'];
$briefAnswer = $memo['brief_answer'];
$facts = $memo['facts'];
$analysis = $memo['analysis'];
$conclusion = $memo['conclusion'];
$recommendations = $memo['recommendations'];

// Get full text
$fullText = $memo['full_text'];
```

### Example Output Structure

```php
[
    'header' => [
        'to' => 'Senior Partner',
        'from' => 'Associate Attorney',
        'date' => 'October 29, 2025',
        'regarding' => 'Contract Matter: John Doe v. ABC Corporation',
    ],
    'issue' => [
        'issues' => [
            [
                'question' => 'Whether breach of contract occurred under contract law?',
                'elements' => ['Valid contract', 'Breach', 'Damages'],
            ],
        ],
        'count' => 1,
    ],
    'brief_answer' => 'Likely Yes. The facts support a strong claim...',
    'facts' => [
        'parties' => 'John Doe is the plaintiff (individual). ABC Corporation is the defendant (corporation).',
        'chronology' => 'On March 15, 2024, contract was signed. On April 3, 2024, defendant abandoned project.',
        'key_facts' => [
            'favorable' => [/* Facts supporting claim */],
            'unfavorable' => [/* Facts against claim */],
            'disputed' => [/* Contested facts */],
            'undisputed' => [/* Agreed facts */],
        ],
    ],
    'analysis' => [
        'issues_analyzed' => 1,
        'analyses' => [
            [
                'issue' => 'Breach of contract',
                'rule' => 'To establish this claim under contract law, the following elements must be proven: Valid contract, Breach, Damages.',
                'application' => 'Applying these facts: The following facts support the claim: Written contract exists; Defendant ceased performance; Damages documented...',
                'conclusion' => 'Therefore, this element is likely satisfied based on the available facts.',
            ],
        ],
        'overall_assessment' => 'Overall, the case presents strong prospects for success...',
    ],
    'conclusion' => 'In conclusion, the legal issue presented likely favors the client\'s position...',
    'recommendations' => [
        [
            'category' => 'Discovery',
            'recommendation' => 'Initiate discovery to obtain 3 pieces of missing evidence.',
            'priority' => 'high',
        ],
    ],
    'full_text' => '/* Formatted memo text */',
]
```

### Real-World Example

```php
class DocumentGenerationController extends Controller
{
    public function generateMemo(Request $request, AutomatedLegalMemoGenerator $generator)
    {
        $validated = $request->validate([
            'fact_pattern_id' => 'required|uuid|exists:legal_fact_patterns,id',
            'format' => 'required|in:text,markdown,html,pdf',
            'to' => 'required|string',
            'from' => 'required|string',
        ]);

        // Generate memo
        $memo = $generator->generateInFormat(
            $validated['fact_pattern_id'],
            $validated['format'],
            [
                'to' => $validated['to'],
                'from' => $validated['from'],
            ]
        );

        // Save to case file
        $this->saveMemoToCase($validated['fact_pattern_id'], $memo);

        // Return for download
        return response($memo)
            ->header('Content-Type', $this->getContentType($validated['format']))
            ->header('Content-Disposition', 'attachment; filename="legal-memo.' . $validated['format'] . '"');
    }

    protected function getContentType(string $format): string
    {
        return match($format) {
            'text' => 'text/plain',
            'markdown' => 'text/markdown',
            'html' => 'text/html',
            'pdf' => 'application/pdf',
        };
    }
}
```

### What It Does

1. ✅ Follows standard legal memo format (IRAC)
2. ✅ Automatically finds and cites relevant precedents
3. ✅ Organizes facts chronologically
4. ✅ Analyzes each legal issue separately
5. ✅ Provides brief answer and conclusion
6. ✅ Generates specific recommendations
7. ✅ Exports in multiple formats (text, Markdown, HTML, PDF)

---

## Discovery Request Generator

**Purpose:** Automatically generate discovery requests from fact patterns.

**Location:** `app/Services/Discovery/DiscoveryRequestGenerator.php`

### Basic Usage

```php
use App\Services\Discovery\DiscoveryRequestGenerator;

$discoveryGen = app(DiscoveryRequestGenerator::class);

// Generate complete discovery package
$package = $discoveryGen->generateDiscoveryPackage($factPatternId);

// Access individual components
$interrogatories = $package['interrogatories'];
$documentRequests = $package['document_requests'];
$admissions = $package['requests_for_admission'];
$depositions = $package['deposition_notices'];
$summary = $package['summary'];
```

### Example Output

```php
[
    'case_info' => [
        'fact_pattern_id' => '9c8f7e6d...',
        'legal_area' => 'contract',
    ],
    'interrogatories' => [
        'total' => 25,
        'items' => [
            [
                'number' => 1,
                'request' => 'State your full name, current address, and all addresses where you have resided during the past five years.',
                'category' => 'identification',
            ],
            [
                'number' => 2,
                'request' => 'Describe in detail all facts supporting your position regarding: Whether the contract was properly executed',
                'category' => 'disputed_facts',
            ],
            // ... more interrogatories
        ],
    ],
    'document_requests' => [
        'total' => 15,
        'items' => [
            [
                'number' => 1,
                'request' => 'All contracts, agreements, or understandings between the parties, including drafts, amendments, and related correspondence.',
                'category' => 'contracts',
            ],
            // ... more requests
        ],
    ],
    'requests_for_admission' => [
        'total' => 10,
        'items' => [/* Admission requests */],
    ],
    'deposition_notices' => [
        'total' => 2,
        'notices' => [
            [
                'deponent' => 'Jane Smith',
                'role' => 'witness',
                'topics' => [
                    'Knowledge of and participation in: Contract signing',
                    'Position and evidence regarding: Payment terms',
                ],
            ],
        ],
    ],
    'summary' => [
        'total_interrogatories' => 25,
        'total_document_requests' => 15,
        'total_requests_for_admission' => 10,
        'total_deposition_notices' => 2,
        'timeline' => [
            'serve_written_discovery' => 'Within 30 days',
            'responses_due' => '30 days after service',
            'depositions_begin' => '60 days after service',
        ],
        'estimated_costs' => [
            'low_estimate' => 8400,
            'high_estimate' => 18000,
        ],
    ],
]
```

### Real-World Example

```php
class DiscoveryController extends Controller
{
    public function initializeDiscovery(string $caseId, DiscoveryRequestGenerator $generator)
    {
        $case = LegalCase::findOrFail($caseId);

        // Generate discovery package
        $package = $generator->generateDiscoveryPackage($case->fact_pattern_id);

        // Get formatted documents
        $documents = $generator->generateFormattedDocuments($case->fact_pattern_id);

        // Save to case file
        Storage::put(
            "cases/{$caseId}/discovery/interrogatories.txt",
            $documents['interrogatories_text']
        );
        Storage::put(
            "cases/{$caseId}/discovery/document_requests.txt",
            $documents['document_requests_text']
        );
        Storage::put(
            "cases/{$caseId}/discovery/admissions.txt",
            $documents['admissions_text']
        );

        // Create discovery timeline
        $this->createDiscoveryTimeline($case, $package['summary']['timeline']);

        // Notify attorney
        $this->notifyAttorney($case, [
            'total_items' => $package['summary']['total_interrogatories'] +
                           $package['summary']['total_document_requests'],
            'estimated_cost' => $package['summary']['estimated_costs']['low_estimate'],
        ]);

        return response()->json([
            'success' => true,
            'package' => $package,
            'files_created' => 3,
        ]);
    }
}
```

### What It Does

1. ✅ Generates interrogatories tailored to disputed facts
2. ✅ Creates document requests for missing evidence
3. ✅ Drafts requests for admission for undisputed facts
4. ✅ Prepares deposition notices with topics
5. ✅ Provides cost estimates
6. ✅ Creates discovery timeline
7. ✅ Exports in formatted text ready for filing

---

## Chronology Builder

**Purpose:** Build detailed, visual chronologies from fact patterns.

**Location:** `app/Services/Case/ChronologyBuilder.php`

### Basic Usage

```php
use App\Services\Case\ChronologyBuilder;

$chronologyBuilder = app(ChronologyBuilder::class);

// Build complete chronology
$chronology = $chronologyBuilder->buildChronology($factPatternId);

// Access components
$events = $chronology['chronology']['events'];
$timeGaps = $chronology['analysis']['time_gaps'];
$criticalDates = $chronology['analysis']['critical_dates'];
$narrative = $chronology['narrative'];
$visualization = $chronology['visualization'];
```

### Example Output

```php
[
    'fact_pattern_id' => '9c8f7e6d...',
    'chronology' => [
        'total_events' => 12,
        'date_range' => [
            'start' => '2024-01-15',
            'end' => '2024-06-30',
            'duration_days' => 167,
        ],
        'events' => [
            [
                'sequence' => 1,
                'date' => DateTime('2024-01-15'),
                'description' => 'Contract signed between parties',
                'significance' => 'Formation of contractual relationship',
                'category' => 'event',
                'location' => 'Zagreb office',
            ],
            // ... more events
        ],
        'by_category' => [
            'event' => [/* Events */],
            'procedural' => [/* Court proceedings */],
            'evidence' => [/* Evidence-related */],
        ],
    ],
    'analysis' => [
        'time_gaps' => [
            [
                'after_event' => 'Contract signed',
                'before_event' => 'First payment due',
                'days' => 45,
                'investigation_needed' => true,
                'questions' => [
                    'What happened between Jan 15 and Mar 1?',
                    'Are there any relevant events during this period?',
                ],
            ],
        ],
        'critical_dates' => [
            [
                'date' => '2024-01-15',
                'description' => 'Contract signed',
                'reason' => 'First event in chronology - case inception',
            ],
        ],
        'missing_information' => [
            [
                'issue' => 'Missing dates for 3 events',
                'priority' => 'high',
                'action' => 'Interview client to establish dates',
            ],
        ],
    ],
    'visualization' => [
        'type' => 'timeline',
        'data' => [/* Chart data */],
        'render_suggestions' => [
            'Use horizontal timeline for court presentation',
            'Color-code by category',
            'Highlight critical dates in red',
        ],
    ],
    'narrative' => '/* Full narrative text */',
]
```

### Real-World Example

```php
class TrialPreparationService
{
    public function prepareTrialExhibits(string $caseId, ChronologyBuilder $builder)
    {
        $case = LegalCase::findOrFail($caseId);

        // Build chronology
        $chronology = $builder->buildChronology($case->fact_pattern_id);

        // Export in multiple formats
        $textVersion = $builder->exportChronology($case->fact_pattern_id, 'text');
        $csvVersion = $builder->exportChronology($case->fact_pattern_id, 'csv');
        $markdownVersion = $builder->exportChronology($case->fact_pattern_id, 'markdown');

        // Generate visual chart
        $chartData = $builder->generateVisualChart($case->fact_pattern_id);

        // Save all versions
        Storage::put("cases/{$caseId}/exhibits/chronology.txt", $textVersion);
        Storage::put("cases/{$caseId}/exhibits/chronology.csv", $csvVersion);
        Storage::put("cases/{$caseId}/exhibits/chronology.md", $markdownVersion);
        Storage::put("cases/{$caseId}/exhibits/timeline-data.json", json_encode($chartData));

        // Flag time gaps for investigation
        $this->flagTimeGaps($case, $chronology['analysis']['time_gaps']);

        // Create trial exhibit
        return $this->createTrialExhibit($chronology);
    }

    protected function flagTimeGaps(LegalCase $case, array $gaps): void
    {
        foreach ($gaps as $gap) {
            if ($gap['days'] > 60) {
                // Create task for investigation
                Task::create([
                    'case_id' => $case->id,
                    'title' => "Investigate {$gap['days']}-day gap in timeline",
                    'description' => json_encode($gap['questions']),
                    'priority' => 'high',
                    'due_date' => now()->addDays(7),
                ]);
            }
        }
    }
}
```

### What It Does

1. ✅ Extracts and sorts all events chronologically
2. ✅ Identifies time gaps requiring investigation
3. ✅ Flags critical dates
4. ✅ Categorizes events (procedural, evidentiary, etc.)
5. ✅ Generates narrative timeline
6. ✅ Provides visualization data for charts
7. ✅ Exports in multiple formats (text, CSV, Markdown, JSON)

---

## Complete Workflow Examples

### Workflow 1: New Case Intake to Trial Preparation

```php
class CompleteCaseWorkflow
{
    public function __construct(
        protected FactPatternExtractor $factExtractor,
        protected CaseIntakeService $intakeService,
        protected EvidenceStrategyAnalyzer $evidenceAnalyzer,
        protected FactDrivenMultiAgentAnalyzer $multiAgent,
        protected AutomatedLegalMemoGenerator $memoGenerator,
        protected DiscoveryRequestGenerator $discoveryGenerator,
        protected ChronologyBuilder $chronologyBuilder
    ) {}

    public function processNewCaseToTrial(array $clientNarrative, int $userId): array
    {
        $results = [];

        // Step 1: Extract fact pattern
        $factPattern = $this->factExtractor->extract($clientNarrative, $userId, ['save' => true]);
        $results['fact_pattern_id'] = $factPattern->id;

        // Step 2: Complete intake with case creation
        $intake = $this->intakeService->processIntake([
            'narrative' => $clientNarrative,
            'user_id' => $userId,
            'objectives' => ['Favorable outcome'],
        ]);
        $results['case_id'] = $intake['case_id'];

        // Step 3: Multi-agent comprehensive analysis
        $analysis = $this->multiAgent->analyzeCaseWithAgents($factPattern->id);
        $results['success_probability'] = $analysis['synthesis']['success_probability'];

        // Step 4: Evidence strategy
        $evidenceStrategy = $this->evidenceAnalyzer->analyzeEvidenceNeeds($factPattern->id);
        $results['evidence_strength'] = $evidenceStrategy['evidence_strength_score'];

        // Step 5: Generate legal memo
        $memo = $this->memoGenerator->generateMemo($factPattern->id, [
            'to' => 'Trial Team',
            'from' => 'Case Manager',
        ]);
        $results['memo_generated'] = true;

        // Step 6: Generate discovery requests
        $discovery = $this->discoveryGenerator->generateDiscoveryPackage($factPattern->id);
        $results['discovery_items'] = $discovery['summary']['total_interrogatories'] +
                                     $discovery['summary']['total_document_requests'];

        // Step 7: Build chronology for trial
        $chronology = $this->chronologyBuilder->buildChronology($factPattern->id);
        $results['chronology_events'] = $chronology['chronology']['total_events'];

        // Step 8: Decide whether to proceed
        $results['recommendation'] = $this->makeRecommendation($analysis, $evidenceStrategy);

        return $results;
    }

    protected function makeRecommendation(array $analysis, array $evidence): string
    {
        $probability = $analysis['synthesis']['success_probability'];
        $strengthScore = $evidence['evidence_strength_score'];

        if ($probability > 0.7 && $strengthScore > 0.7) {
            return 'PROCEED: Strong case with good evidence. File immediately.';
        } elseif ($probability > 0.5 && $strengthScore > 0.5) {
            return 'PROCEED WITH CAUTION: Case has merit but needs evidence strengthening.';
        } else {
            return 'DO NOT PROCEED: Case is weak. Consider alternative approaches.';
        }
    }
}
```

### Workflow 2: Client Consultation Automation

```php
class AutomatedConsultationPrep
{
    public function prepareConsultation(string $intakeFormText): array
    {
        // Extract facts from intake form
        $factPattern = $this->factExtractor->extract($intakeFormText, auth()->id(), ['save' => true]);

        // Generate consultation materials
        $materials = [
            // 1. Summary of client's situation
            'summary' => $factPattern->structured_facts['summary'],

            // 2. Identified legal issues
            'legal_issues' => $factPattern->getLegalIssues(),

            // 3. Evidence analysis
            'evidence_analysis' => $this->evidenceAnalyzer->analyzeEvidenceNeeds($factPattern->id),

            // 4. Preliminary chronology
            'chronology' => $this->chronologyBuilder->buildChronology($factPattern->id),

            // 5. Similar cases from history
            'similar_cases' => $this->findSimilarCases($factPattern),

            // 6. Questions to ask client
            'questions_for_client' => $this->generateClientQuestions($factPattern),

            // 7. Estimated costs
            'cost_estimate' => $this->estimateCosts($factPattern),

            // 8. Timeline estimate
            'timeline_estimate' => '12-18 months',
        ];

        return $materials;
    }

    protected function generateClientQuestions(LegalFactPattern $pattern): array
    {
        $questions = [];

        // Questions about disputed facts
        foreach ($pattern->getFact('disputed_facts', []) as $fact) {
            $questions[] = "Can you provide more details about: {$fact}?";
        }

        // Questions about missing evidence
        foreach ($pattern->getFact('evidence', []) as $evidence) {
            if ($evidence['availability'] === 'unknown') {
                $questions[] = "Do you have: {$evidence['description']}?";
            }
        }

        return $questions;
    }
}
```

---

## API Integration Examples

All these services can be exposed via API:

```php
// routes/api.php

Route::prefix('case-analysis')->middleware('api.token')->group(function () {
    // Evidence analysis
    Route::post('/evidence-strategy/{factPatternId}', [AnalysisController::class, 'evidenceStrategy']);

    // Multi-agent analysis
    Route::post('/comprehensive/{factPatternId}', [AnalysisController::class, 'comprehensiveAnalysis']);

    // Document generation
    Route::post('/memo/{factPatternId}', [DocumentController::class, 'generateMemo']);

    // Discovery
    Route::post('/discovery/{factPatternId}', [DiscoveryController::class, 'generateDiscovery']);

    // Chronology
    Route::post('/chronology/{factPatternId}', [ChronologyController::class, 'buildChronology']);
});
```

---

## Best Practices

1. **Always cache fact pattern extraction** - It's expensive
2. **Run multi-agent analysis in background jobs** for large cases
3. **Save generated documents** to avoid regenerating
4. **Use chronology for trial prep** - Judges love timelines
5. **Combine services** - They're designed to work together
6. **Monitor success probabilities** over time to improve accuracy
7. **Use discovery generator early** in case lifecycle

---

## Performance Tips

```php
// Cache intensive operations
$cacheKey = "evidence-strategy:{$factPatternId}";
$strategy = Cache::remember($cacheKey, 3600, function() use ($factPatternId) {
    return $this->evidenceAnalyzer->analyzeEvidenceNeeds($factPatternId);
});

// Queue multi-agent analysis for large cases
dispatch(new AnalyzeCaseJob($factPatternId))->onQueue('analysis');

// Batch process multiple cases
$narratives = [/* multiple narratives */];
$results = $this->factExtractor->batchExtract($narratives, $userId);
```

---

## Testing

Each service includes comprehensive tests. Example:

```php
// Test evidence analysis
$strategy = $this->evidenceAnalyzer->analyzeEvidenceNeeds($factPattern->id);
$this->assertArrayHasKey('evidence_strength_score', $strategy);
$this->assertGreaterThan(0, $strategy['evidence_strength_score']);

// Test multi-agent
$analysis = $this->multiAgent->analyzeCaseWithAgents($factPattern->id);
$this->assertEquals(4, count($analysis['agents_executed']));

// Test memo generation
$memo = $this->memoGenerator->generateMemo($factPattern->id);
$this->assertStringContainsString('MEMORANDUM', $memo['full_text']);
```

---

## Summary

These integrations provide **production-ready**, **highly specific** examples of how to leverage the FactPatternExtractor across your entire legal workflow:

- **Evidence Strategy** → Comprehensive evidence analysis
- **Multi-Agent** → Coordinated specialist analysis
- **Memo Generator** → Automated legal writing
- **Discovery Generator** → Complete discovery packages
- **Chronology Builder** → Visual timelines

All services are designed to work together seamlessly, providing end-to-end case management from intake to trial! 🎯
