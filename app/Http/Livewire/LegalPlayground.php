<?php

namespace App\Http\Livewire;

use App\Models\Evidence;
use App\Models\LegalCase;
use App\Modules\Evidence\Services\EvidenceAnalysisService;
use App\Modules\Evidence\Services\RecontextualizationService;
use App\Modules\HomeSearch\Services\HomeSearchAbuseDetector;
use App\Modules\Misconduct\Services\ComplaintGenerator;
use App\Modules\Misconduct\Services\DismissalMotionGenerator;
use App\Modules\Misconduct\Services\MisconductDetector;
use App\Modules\Topics\Analyzers\DrugChargeAbuseDetector;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

/**
 * LegalPlayground - Comprehensive Testing Interface
 *
 * Unified playground for testing all legal defense modules:
 * - Evidence Analysis
 * - Evidence Recontextualization
 * - Prosecutorial Misconduct Detection
 * - Topic Framework (Drug Charges, Home Searches)
 * - Legal Reasoning
 * - Defense Strategy Generation
 *
 * Follows TextractManager styling for consistency.
 */
class LegalPlayground extends Component
{
    // Active module
    public $activeModule = 'evidence'; // evidence, recontextualize, misconduct, topics, concepts, case_analysis

    // Case selection - stored as string to support Livewire serialization
    public $selectedCaseId = '';

    public $cases = [];

    /**
     * Livewire property casts to handle ULID serialization
     */
    protected function casts(): array
    {
        return [
            'selectedCaseId' => 'string',
        ];
    }

    // Evidence Analysis
    public $evidenceType = 'communication';

    public $evidenceDescription = '';

    public $evidenceAnalysisResult = null;

    // Evidence Recontextualization
    public $prosecutionEvidence = '';

    public $fullContent = '';

    public $recontextResult = null;

    // Misconduct Detection
    public $misconductType = 'fabricated_probable_cause';

    public $misconductDetails = '';

    public $misconductResult = null;

    public $dismissalMotion = null;

    public $complaint = null;

    // Topic Framework
    public $selectedTopic = 'drug_charge_severity';

    public $drugType = 'cannabis';

    public $amount = 30;

    public $chargedAs = 'dealing';

    public $evidenceOfDealing = [];

    public $topicResult = null;

    // Regional Comparison
    public $region1 = 'Osijek';

    public $region2 = 'Zadar';

    public $comparisonYear = 2025;

    public $comparisonResult = null;

    // Legal Concept Analysis
    public $conceptQuery = '';

    public $conceptOperation = 'define';

    public $conceptResult = null;

    // Case Analysis
    public $caseAnalysisOperation = 'strength';

    public $caseAnalysisResult = null;

    // UI State
    public $loading = false;

    public $errorMessage = null;

    public $successMessage = null;

    // Error Recovery State
    public $showRetryButton = false;

    public $lastFailedAction = null;

    public $isProcessingRequest = false;

    // Options
    public $evidenceTypes = [
        'communication' => 'Communication (SMS/Email/Chat)',
        'timestamp' => 'Timestamp/Timeline',
        'media' => 'Media (Photos/Videos)',
        'witness' => 'Witness Statement',
        'financial' => 'Financial Records',
    ];

    public $misconductTypes = [
        'fabricated_probable_cause' => 'Fabricated Probable Cause',
        'hidden_evidence' => 'Hidden Evidence (Brady Violation)',
        'backdated_documents' => 'Backdated Documents',
        'rights_violations' => 'Constitutional Rights Violations',
        'prosecutor_threats' => 'Prosecutor Threats/Lying',
        'misdemeanor_pretexting' => 'Misdemeanor Pretexting',
    ];

    public $topics = [
        'drug_charge_severity' => 'Drug Charge Overcharging',
        'home_search_abuse' => 'Home Search Abuse',
    ];

    public $drugTypes = [
        'cannabis' => 'Cannabis (30g)',
        'cocaine' => 'Cocaine (1g)',
        'heroin' => 'Heroin (1g)',
        'ecstasy' => 'Ecstasy (5 pills)',
    ];

    public $chargeTypes = [
        'dealing' => 'Dealing (KZ Čl. 190)',
        'personal_use' => 'Personal Use (KZ Čl. 173)',
    ];

    public $evidenceOptions = [
        'scales' => 'Scales',
        'baggies' => 'Baggies',
        'large_cash' => 'Large Cash',
        'phone_records' => 'Phone Records',
    ];

    public $regions = [
        'Osijek', 'Zagreb', 'Split', 'Rijeka', 'Zadar',
        'Pula', 'Dubrovnik', 'Slavonski Brod', 'Karlovac', 'Varaždin',
    ];

    public $conceptOperations = [
        'define' => 'Define Concept (Get AI definition)',
        'related' => 'Find Related Concepts',
        'precedents' => 'Find Precedent Cases',
        'doctrine' => 'Analyze Doctrine Origin',
    ];

    public $caseAnalysisOperations = [
        'strength' => 'Case Strength Analysis',
        'risk' => 'Risk Assessment',
        'timeline' => 'Timeline Visualization',
        'evidence' => 'Evidence Quality Assessment',
    ];

    public function mount()
    {
        $this->loadCases();

        // Set default selected case if cases exist
        if (! empty($this->cases)) {
            $this->selectedCaseId = $this->cases[0]['id'];
        }
    }

    public function loadCases()
    {
        try {
            // Only select fields needed for dropdown to avoid loading unnecessary data
            $this->cases = LegalCase::select(['id', 'case_number', 'title'])
                ->orderBy('created_at', 'desc')
                ->take(50)
                ->get()
                ->map(fn ($case) => [
                    'id' => (string) $case->id, // Cast ULID to string for Livewire serialization
                    'label' => "{$case->case_number} - ".\Illuminate\Support\Str::limit($case->title ?? 'Untitled', 50),
                ])
                ->toArray();
        } catch (\Exception $e) {
            Log::error('LegalPlayground: Failed to load cases', ['error' => $e->getMessage()]);
            $this->cases = [];
            $this->errorMessage = 'Failed to load cases. Please refresh the page.';
        }
    }

    /**
     * Get statistics for the current drug charge analysis
     */
    public function getStatistics()
    {
        $this->loading = true;
        $this->resetMessages();

        try {
            $detector = app(DrugChargeAbuseDetector::class);

            $stats = $detector->getStatistics([
                'year' => date('Y'),
                'drug_type' => $this->drugType,
            ]);

            $this->successMessage = 'Statistics loaded successfully!';

            return $stats;

        } catch (\Exception $e) {
            $this->errorMessage = 'Failed to load statistics: '.$e->getMessage();
            Log::error('LegalPlayground: Statistics failed', ['error' => $e->getMessage()]);

            return null;
        } finally {
            $this->loading = false;
        }
    }

    public function setModule($module)
    {
        $this->activeModule = $module;
        $this->resetMessages();
    }

    // Evidence Analysis
    public function analyzeEvidence()
    {
        // Check for concurrent requests
        if ($this->isProcessingRequest) {
            $this->errorMessage = 'Request in progress. Please wait for current analysis to complete.';

            return;
        }

        // Validate input
        $this->validate([
            'selectedCaseId' => 'required|exists:cases,id',
            'evidenceDescription' => 'required|string|min:1|max:100000',
            'evidenceType' => 'required|in:'.implode(',', array_keys($this->evidenceTypes)),
        ], [
            'evidenceDescription.required' => 'Evidence text is required',
            'evidenceDescription.max' => 'Evidence text must not exceed 100,000 characters',
        ]);

        $this->isProcessingRequest = true;
        $this->loading = true;
        $this->resetMessages();
        $this->evidenceAnalysisResult = null;
        $this->showRetryButton = false;

        try {
            $case = LegalCase::findOrFail($this->selectedCaseId);
            $service = app(EvidenceAnalysisService::class);

            $this->evidenceAnalysisResult = $service->analyzeEvidence($case, [
                'type' => $this->evidenceType,
                'description' => $this->evidenceDescription,
            ]);

            $this->successMessage = 'Analysis complete';
            $this->lastFailedAction = null;
            Log::info('LegalPlayground: Evidence analyzed', ['case_id' => $this->selectedCaseId]);

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            // Network error
            $this->errorMessage = 'Network error. Please check your connection and try again.';
            $this->showRetryButton = true;
            $this->lastFailedAction = 'analyzeEvidence';
            Log::error('LegalPlayground: Network error during analysis', ['error' => $e->getMessage()]);

        } catch (\Illuminate\Http\Client\RequestException $e) {
            // Check for rate limiting (429) or other HTTP errors
            if ($e->response && $e->response->status() === 429) {
                $this->errorMessage = 'Rate limit exceeded. Please wait a moment and try again.';
                $this->showRetryButton = true;
                $this->lastFailedAction = 'analyzeEvidence';
                Log::warning('LegalPlayground: Rate limit hit', ['error' => $e->getMessage()]);
            } elseif ($e->response && in_array($e->response->status(), [401, 419])) {
                // Session expired or unauthenticated
                $this->errorMessage = 'Session expired. Please log in again.';
                $this->showRetryButton = false;
                $this->lastFailedAction = null;
                Log::warning('LegalPlayground: Session expired', ['status' => $e->response->status()]);
            } else {
                $this->errorMessage = 'Analysis failed: '.$e->getMessage();
                $this->showRetryButton = true;
                $this->lastFailedAction = 'analyzeEvidence';
                Log::error('LegalPlayground: HTTP error during analysis', ['error' => $e->getMessage()]);
            }

        } catch (\Illuminate\Auth\AuthenticationException $e) {
            // Session expired or user logged out
            $this->errorMessage = 'Session expired. Please log in again.';
            $this->showRetryButton = false;
            $this->lastFailedAction = null;
            Log::warning('LegalPlayground: Authentication failed', ['error' => $e->getMessage()]);

        } catch (\Exception $e) {
            $this->errorMessage = 'Analysis failed: '.$e->getMessage();
            $this->showRetryButton = true;
            $this->lastFailedAction = 'analyzeEvidence';
            Log::error('LegalPlayground: Evidence analysis failed', ['error' => $e->getMessage()]);

        } finally {
            $this->loading = false;
            $this->isProcessingRequest = false;
        }
    }

    /**
     * Retry the last failed action
     */
    public function retry()
    {
        if (! $this->lastFailedAction) {
            $this->errorMessage = 'No action to retry';

            return;
        }

        $action = $this->lastFailedAction;
        $this->lastFailedAction = null;
        $this->showRetryButton = false;
        $this->resetMessages();

        // Call the failed action again
        $this->$action();
    }

    // Evidence Recontextualization
    public function recontextualizeEvidence()
    {
        $this->validate([
            'selectedCaseId' => 'required|exists:cases,id',
            'prosecutionEvidence' => 'required|string|min:10|max:5000',
            'fullContent' => 'required|string|min:10|max:10000',
        ]);

        $this->loading = true;
        $this->resetMessages();
        $this->recontextResult = null;

        try {
            $case = LegalCase::findOrFail($this->selectedCaseId);
            $service = app(RecontextualizationService::class);

            // Build evidence array for recontextualization
            $evidence = [
                'type' => 'communication',
                'prosecution_presentation' => $this->prosecutionEvidence,
            ];

            $contextAnalysis = [
                'full_context' => $this->fullContent,
            ];

            $this->recontextResult = $service->recontextualize($evidence, $contextAnalysis, $case);

            $this->successMessage = 'Evidence recontextualized successfully!';
            Log::info('LegalPlayground: Evidence recontextualized', ['case_id' => $this->selectedCaseId]);

        } catch (\Exception $e) {
            $this->errorMessage = 'Recontextualization failed: '.$e->getMessage();
            Log::error('LegalPlayground: Recontextualization failed', ['error' => $e->getMessage()]);
        } finally {
            $this->loading = false;
        }
    }

    // Misconduct Detection
    public function detectMisconduct()
    {
        $this->validate([
            'selectedCaseId' => 'required|exists:cases,id',
            'misconductDetails' => 'required|string|min:10|max:5000',
            'misconductType' => 'required|in:'.implode(',', array_keys($this->misconductTypes)),
        ]);

        $this->loading = true;
        $this->resetMessages();
        $this->misconductResult = null;

        try {
            $case = LegalCase::findOrFail($this->selectedCaseId);
            $detector = app(MisconductDetector::class);

            $this->misconductResult = $detector->detect($case, [
                'type' => $this->misconductType,
                'details' => $this->misconductDetails,
            ]);

            $this->successMessage = 'Misconduct detected and analyzed!';
            Log::info('LegalPlayground: Misconduct detected', ['case_id' => $this->selectedCaseId]);

        } catch (\Exception $e) {
            $this->errorMessage = 'Misconduct detection failed: '.$e->getMessage();
            Log::error('LegalPlayground: Misconduct detection failed', ['error' => $e->getMessage()]);
        } finally {
            $this->loading = false;
        }
    }

    public function generateDismissalMotion()
    {
        if (empty($this->misconductResult)) {
            $this->errorMessage = 'Please detect misconduct first before generating a dismissal motion.';

            return;
        }

        $this->loading = true;
        $this->resetMessages();
        $this->dismissalMotion = null;

        try {
            $case = LegalCase::findOrFail($this->selectedCaseId);
            $generator = app(DismissalMotionGenerator::class);

            $this->dismissalMotion = $generator->generate($case, $this->misconductResult);

            $this->successMessage = 'Dismissal motion generated!';
            Log::info('LegalPlayground: Dismissal motion generated', ['case_id' => $this->selectedCaseId]);

        } catch (\Exception $e) {
            $this->errorMessage = 'Motion generation failed: '.$e->getMessage();
            Log::error('LegalPlayground: Motion generation failed', ['error' => $e->getMessage()]);
        } finally {
            $this->loading = false;
        }
    }

    public function generateComplaint()
    {
        if (empty($this->misconductResult)) {
            $this->errorMessage = 'Please detect misconduct first before generating a complaint.';

            return;
        }

        $this->loading = true;
        $this->resetMessages();
        $this->complaint = null;

        try {
            $case = LegalCase::findOrFail($this->selectedCaseId);
            $generator = app(ComplaintGenerator::class);

            $this->complaint = $generator->generateComplaint($case, $this->misconductResult);

            $this->successMessage = 'Complaint generated!';
            Log::info('LegalPlayground: Complaint generated', ['case_id' => $this->selectedCaseId]);

        } catch (\Exception $e) {
            $this->errorMessage = 'Complaint generation failed: '.$e->getMessage();
            Log::error('LegalPlayground: Complaint generation failed', ['error' => $e->getMessage()]);
        } finally {
            $this->loading = false;
        }
    }

    // Topic Framework
    public function analyzeTopic()
    {
        $this->validate([
            'selectedCaseId' => 'required|exists:cases,id',
            'amount' => 'required|numeric|min:0|max:100000',
            'drugType' => 'required_if:selectedTopic,drug_charge_severity|in:'.implode(',', array_keys($this->drugTypes)),
            'chargedAs' => 'required_if:selectedTopic,drug_charge_severity|in:'.implode(',', array_keys($this->chargeTypes)),
        ]);

        $this->loading = true;
        $this->resetMessages();
        $this->topicResult = null;

        try {
            $case = LegalCase::findOrFail($this->selectedCaseId);

            if ($this->selectedTopic === 'drug_charge_severity') {
                $detector = app(DrugChargeAbuseDetector::class);

                $this->topicResult = $detector->analyzeCase($case, [
                    'drug_type' => $this->drugType,
                    'amount' => (float) $this->amount,
                    'charged_as' => $this->chargedAs,
                    'evidence_of_dealing' => is_array($this->evidenceOfDealing) ? $this->evidenceOfDealing : [],
                ]);
            } elseif ($this->selectedTopic === 'home_search_abuse') {
                $detector = app(HomeSearchAbuseDetector::class);

                $this->topicResult = $detector->detectAbuse($case, [
                    'search_conducted' => true,
                    'warrant_quality' => 'questionable',
                ]);
            }

            $this->successMessage = 'Topic analyzed successfully!';
            Log::info('LegalPlayground: Topic analyzed', ['topic' => $this->selectedTopic]);

        } catch (\Exception $e) {
            $this->errorMessage = 'Topic analysis failed: '.$e->getMessage();
            Log::error('LegalPlayground: Topic analysis failed', ['error' => $e->getMessage()]);
        } finally {
            $this->loading = false;
        }
    }

    public function compareRegions()
    {
        $this->validate([
            'region1' => 'required|in:'.implode(',', $this->regions),
            'region2' => 'required|in:'.implode(',', $this->regions),
            'comparisonYear' => 'required|integer|min:2020|max:2030',
        ]);

        if ($this->region1 === $this->region2) {
            $this->errorMessage = 'Please select two different regions to compare.';

            return;
        }

        $this->loading = true;
        $this->resetMessages();
        $this->comparisonResult = null;

        try {
            if ($this->selectedTopic === 'drug_charge_severity') {
                $detector = app(DrugChargeAbuseDetector::class);
                $this->comparisonResult = $detector->compareRegions(
                    $this->region1,
                    $this->region2,
                    $this->comparisonYear
                );
            }

            $this->successMessage = 'Regions compared successfully!';
            Log::info('LegalPlayground: Regions compared', [
                'region1' => $this->region1,
                'region2' => $this->region2,
            ]);

        } catch (\Exception $e) {
            $this->errorMessage = 'Regional comparison failed: '.$e->getMessage();
            Log::error('LegalPlayground: Regional comparison failed', ['error' => $e->getMessage()]);
        } finally {
            $this->loading = false;
        }
    }

    // Legal Concept Analysis
    public function analyzeConcept()
    {
        $this->validate([
            'conceptQuery' => 'required|string|min:3|max:200',
            'conceptOperation' => 'required|in:'.implode(',', array_keys($this->conceptOperations)),
        ], [
            'conceptQuery.required' => 'Please enter a legal concept to analyze',
            'conceptQuery.min' => 'Concept must be at least 3 characters',
        ]);

        $this->loading = true;
        $this->resetMessages();
        $this->conceptResult = null;

        try {
            $lawSearchService = app(\App\Services\LawSearchService::class);

            $this->conceptResult = $lawSearchService->analyzeConcept(
                $this->conceptQuery,
                [
                    'operation' => $this->conceptOperation,
                    'jurisdiction' => 'HR',
                ]
            );

            $this->successMessage = 'Concept analyzed successfully!';
            Log::info('LegalPlayground: Concept analyzed', [
                'concept' => $this->conceptQuery,
                'operation' => $this->conceptOperation,
            ]);

        } catch (\Exception $e) {
            $this->errorMessage = 'Concept analysis failed: '.$e->getMessage();
            Log::error('LegalPlayground: Concept analysis failed', [
                'concept' => $this->conceptQuery,
                'error' => $e->getMessage(),
            ]);
        } finally {
            $this->loading = false;
        }
    }

    // Case Analysis
    public function analyzeCase()
    {
        $this->validate([
            'selectedCaseId' => 'required|exists:cases,id',
            'caseAnalysisOperation' => 'required|in:'.implode(',', array_keys($this->caseAnalysisOperations)),
        ], [
            'selectedCaseId.required' => 'Please select a case to analyze',
            'selectedCaseId.exists' => 'Selected case does not exist',
        ]);

        $this->loading = true;
        $this->resetMessages();
        $this->caseAnalysisResult = null;

        try {
            $caseSearchService = app(\App\Services\CaseSearchService::class);

            $this->caseAnalysisResult = $caseSearchService->analyzeCase(
                $this->selectedCaseId,
                [
                    'analysis_type' => $this->caseAnalysisOperation,
                ]
            );

            $this->successMessage = 'Analysis complete';
            Log::info('LegalPlayground: Case analyzed', [
                'case_id' => $this->selectedCaseId,
                'operation' => $this->caseAnalysisOperation,
            ]);

        } catch (\Exception $e) {
            $this->errorMessage = 'Case analysis failed: '.$e->getMessage();
            Log::error('LegalPlayground: Case analysis failed', [
                'case_id' => $this->selectedCaseId,
                'operation' => $this->caseAnalysisOperation,
                'error' => $e->getMessage(),
            ]);
        } finally {
            $this->loading = false;
        }
    }

    // Reset functions
    public function resetMessages()
    {
        $this->errorMessage = null;
        $this->successMessage = null;
    }

    public function resetResults()
    {
        $this->evidenceAnalysisResult = null;
        $this->recontextResult = null;
        $this->misconductResult = null;
        $this->dismissalMotion = null;
        $this->complaint = null;
        $this->topicResult = null;
        $this->comparisonResult = null;
        $this->conceptResult = null;
        $this->caseAnalysisResult = null;
        $this->resetMessages();
    }

    public function render()
    {
        return view('livewire.legal-playground');
    }
}
