<?php

namespace App\Services\LegalReasoning;

use App\Exceptions\LegalReasoningException;
use App\Models\LegalCase;
use App\Services\OpenAIService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class StrategicPlanner
{
    public function __construct(
        protected OpenAIService $openAI,
        protected DurationEstimator $durationEstimator
    ) {}

    /**
     * Create comprehensive phased action plan for case
     */
    public function createActionPlan(string $caseId, array $objectives = []): array
    {
        $startTime = microtime(true);

        Log::info('Starting strategic action plan creation', [
            'case_id' => $caseId,
            'objectives_count' => count($objectives),
        ]);

        try {
            // Phase 1: Load case
            $caseLoadStart = microtime(true);
            try {
                $case = LegalCase::with('documents')->findOrFail($caseId);
                $caseLoadDuration = microtime(true) - $caseLoadStart;

                Log::debug('Case loaded', [
                    'case_id' => $caseId,
                    'document_count' => $case->documents->count(),
                    'duration_ms' => round($caseLoadDuration * 1000, 2),
                ]);
            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
                $caseLoadDuration = microtime(true) - $caseLoadStart;
                Log::error('Case not found', [
                    'case_id' => $caseId,
                    'duration_ms' => round($caseLoadDuration * 1000, 2),
                ]);
                throw new LegalReasoningException(
                    "Case not found: {$caseId}",
                    LegalReasoningException::CASE_NOT_FOUND,
                    $e
                );
            } catch (\Throwable $e) {
                $caseLoadDuration = microtime(true) - $caseLoadStart;
                Log::error('Case loading failed', [
                    'case_id' => $caseId,
                    'error' => $e->getMessage(),
                    'duration_ms' => round($caseLoadDuration * 1000, 2),
                ]);
                throw new LegalReasoningException(
                    "Failed to load case: {$e->getMessage()}",
                    LegalReasoningException::STRATEGIC_PLANNING_FAILED,
                    $e
                );
            }

            $phases = [];
            $cumulativeDays = 0;
            $startDate = $case->filing_date ? Carbon::parse($case->filing_date) : now();

            // Phase 1: Discovery & Investigation
            $discoveryPhase = [
                'phase_number' => 1,
                'name' => 'Discovery & Investigation',
                'actions' => $this->planDiscovery($case, $objectives),
                'duration_days' => $this->estimatePhaseDuration('discovery', $case),
                'deliverables' => $this->defineDeliverables('discovery'),
                'start_date' => $startDate->copy()->addDays($cumulativeDays)->toDateString(),
            ];
            $cumulativeDays += $discoveryPhase['duration_days'];
            $discoveryPhase['end_date'] = $startDate->copy()->addDays($cumulativeDays)->toDateString();
            $phases[] = $discoveryPhase;

            // Phase 2: Motions & Hearings
            $motionsPhase = [
                'phase_number' => 2,
                'name' => 'Motions & Hearings',
                'actions' => $this->planMotions($case, $objectives),
                'duration_days' => $this->estimatePhaseDuration('motions', $case),
                'deliverables' => $this->defineDeliverables('motions'),
                'start_date' => $startDate->copy()->addDays($cumulativeDays)->toDateString(),
            ];
            $cumulativeDays += $motionsPhase['duration_days'];
            $motionsPhase['end_date'] = $startDate->copy()->addDays($cumulativeDays)->toDateString();
            $phases[] = $motionsPhase;

            // Phase 3: Trial Preparation (conditional)
            if ($this->requiresTrial($case)) {
                $trialPrepPhase = [
                    'phase_number' => 3,
                    'name' => 'Trial Preparation',
                    'actions' => $this->planTrialPrep($case, $objectives),
                    'duration_days' => $this->estimatePhaseDuration('trial_prep', $case),
                    'deliverables' => $this->defineDeliverables('trial_prep'),
                    'start_date' => $startDate->copy()->addDays($cumulativeDays)->toDateString(),
                ];
                $cumulativeDays += $trialPrepPhase['duration_days'];
                $trialPrepPhase['end_date'] = $startDate->copy()->addDays($cumulativeDays)->toDateString();
                $phases[] = $trialPrepPhase;

                // Phase 4: Trial
                $trialPhase = [
                    'phase_number' => 4,
                    'name' => 'Trial',
                    'actions' => $this->planTrial($case),
                    'duration_days' => $this->estimatePhaseDuration('trial', $case),
                    'deliverables' => $this->defineDeliverables('trial'),
                    'start_date' => $startDate->copy()->addDays($cumulativeDays)->toDateString(),
                ];
                $cumulativeDays += $trialPhase['duration_days'];
                $trialPhase['end_date'] = $startDate->copy()->addDays($cumulativeDays)->toDateString();
                $phases[] = $trialPhase;
            }

            // Phase 5: Post-Decision
            $postDecisionPhase = [
                'phase_number' => count($phases) + 1,
                'name' => 'Post-Decision & Enforcement',
                'actions' => $this->planPostDecision($case),
                'duration_days' => $this->estimatePhaseDuration('post_decision', $case),
                'deliverables' => $this->defineDeliverables('post_decision'),
                'start_date' => $startDate->copy()->addDays($cumulativeDays)->toDateString(),
            ];
            $cumulativeDays += $postDecisionPhase['duration_days'];
            $postDecisionPhase['end_date'] = $startDate->copy()->addDays($cumulativeDays)->toDateString();
            $phases[] = $postDecisionPhase;

            // Identify settlement windows
            $settlementWindows = $this->identifySettlementOpportunities($case, $phases);

            // Extract milestones
            $milestones = $this->extractMilestones($phases);

            // Calculate resource requirements
            $resourcesNeeded = $this->calculateResourceRequirements($phases, $case);

            $result = [
                'case_id' => $caseId,
                'phases' => $phases,
                'total_phases' => count($phases),
                'total_duration_days' => $cumulativeDays,
                'estimated_completion_date' => $startDate->copy()->addDays($cumulativeDays)->toDateString(),
                'milestones' => $milestones,
                'settlement_windows' => $settlementWindows,
                'resources_needed' => $resourcesNeeded,
                'requires_trial' => $this->requiresTrial($case),
            ];

            $totalDuration = microtime(true) - $startTime;
            Log::info('Strategic action plan created successfully', [
                'case_id' => $caseId,
                'total_phases' => count($phases),
                'total_duration_days' => $cumulativeDays,
                'milestones_count' => count($milestones),
                'settlement_windows' => count($settlementWindows),
                'requires_trial' => $result['requires_trial'],
                'total_duration_ms' => round($totalDuration * 1000, 2),
            ]);

            return $result;
        } catch (LegalReasoningException $e) {
            $totalDuration = microtime(true) - $startTime;
            Log::error('Strategic plan creation failed', [
                'case_id' => $caseId,
                'error_code' => $e->getCode(),
                'error' => $e->getMessage(),
                'total_duration_ms' => round($totalDuration * 1000, 2),
            ]);
            throw $e;
        } catch (\Throwable $e) {
            $totalDuration = microtime(true) - $startTime;
            Log::error('Strategic plan creation failed with unexpected error', [
                'case_id' => $caseId,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'total_duration_ms' => round($totalDuration * 1000, 2),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new LegalReasoningException(
                "Unexpected error creating strategic plan: {$e->getMessage()}",
                LegalReasoningException::STRATEGIC_PLANNING_FAILED,
                $e
            );
        }
    }

    /**
     * Plan discovery phase actions
     */
    protected function planDiscovery(LegalCase $case, array $objectives): array
    {
        return [
            ['action' => 'Initial client interview and fact gathering', 'priority' => 'high', 'estimated_hours' => 8],
            ['action' => 'Identify and preserve relevant documents', 'priority' => 'high', 'estimated_hours' => 16],
            ['action' => 'Draft and serve discovery requests (interrogatories, RFPs, RFAs)', 'priority' => 'high', 'estimated_hours' => 20],
            ['action' => 'Conduct depositions of key witnesses', 'priority' => 'high', 'estimated_hours' => 40],
            ['action' => 'Engage expert witnesses if needed', 'priority' => 'medium', 'estimated_hours' => 16],
            ['action' => 'Review and analyze opponent\'s discovery responses', 'priority' => 'high', 'estimated_hours' => 24],
            ['action' => 'Prepare responses to opponent\'s discovery requests', 'priority' => 'high', 'estimated_hours' => 20],
            ['action' => 'Compile evidence database', 'priority' => 'medium', 'estimated_hours' => 12],
        ];
    }

    /**
     * Plan motion practice phase actions
     */
    protected function planMotions(LegalCase $case, array $objectives): array
    {
        $actions = [
            ['action' => 'Evaluate potential dispositive motions', 'priority' => 'high', 'estimated_hours' => 12],
            ['action' => 'Draft motion for summary judgment if appropriate', 'priority' => 'high', 'estimated_hours' => 32],
            ['action' => 'Prepare responses to opponent motions', 'priority' => 'high', 'estimated_hours' => 24],
            ['action' => 'File motions in limine to exclude prejudicial evidence', 'priority' => 'medium', 'estimated_hours' => 16],
            ['action' => 'Attend motion hearings', 'priority' => 'high', 'estimated_hours' => 8],
            ['action' => 'Analyze court rulings and adjust strategy', 'priority' => 'high', 'estimated_hours' => 8],
        ];

        // Add case-specific motions
        if ($case->documents->count() > 20) {
            $actions[] = ['action' => 'File motion to compel additional discovery', 'priority' => 'medium', 'estimated_hours' => 16];
        }

        return $actions;
    }

    /**
     * Plan trial preparation phase actions
     */
    protected function planTrialPrep(LegalCase $case, array $objectives): array
    {
        return [
            ['action' => 'Develop trial strategy and theory of the case', 'priority' => 'high', 'estimated_hours' => 20],
            ['action' => 'Prepare witness examination outlines', 'priority' => 'high', 'estimated_hours' => 32],
            ['action' => 'Organize exhibits and prepare exhibit list', 'priority' => 'high', 'estimated_hours' => 24],
            ['action' => 'Prepare opening and closing statements', 'priority' => 'high', 'estimated_hours' => 24],
            ['action' => 'Conduct witness preparation sessions', 'priority' => 'high', 'estimated_hours' => 40],
            ['action' => 'Prepare trial briefs and jury instructions', 'priority' => 'high', 'estimated_hours' => 32],
            ['action' => 'Create demonstrative aids and presentations', 'priority' => 'medium', 'estimated_hours' => 20],
            ['action' => 'Finalize expert witness testimony', 'priority' => 'high', 'estimated_hours' => 16],
            ['action' => 'Submit pretrial motions and trial memoranda', 'priority' => 'high', 'estimated_hours' => 24],
            ['action' => 'Attend pretrial conference', 'priority' => 'high', 'estimated_hours' => 4],
        ];
    }

    /**
     * Plan trial phase actions
     */
    protected function planTrial(LegalCase $case): array
    {
        return [
            ['action' => 'Jury selection (if jury trial)', 'priority' => 'high', 'estimated_hours' => 16],
            ['action' => 'Present opening statements', 'priority' => 'high', 'estimated_hours' => 4],
            ['action' => 'Direct examination of witnesses', 'priority' => 'high', 'estimated_hours' => 40],
            ['action' => 'Cross-examination of opponent witnesses', 'priority' => 'high', 'estimated_hours' => 32],
            ['action' => 'Present documentary evidence', 'priority' => 'high', 'estimated_hours' => 16],
            ['action' => 'Handle objections and evidentiary issues', 'priority' => 'high', 'estimated_hours' => 12],
            ['action' => 'Present closing arguments', 'priority' => 'high', 'estimated_hours' => 6],
            ['action' => 'Jury deliberation and verdict', 'priority' => 'high', 'estimated_hours' => 8],
        ];
    }

    /**
     * Plan post-decision phase actions
     */
    protected function planPostDecision(LegalCase $case): array
    {
        return [
            ['action' => 'Review court decision and verdict', 'priority' => 'high', 'estimated_hours' => 8],
            ['action' => 'Analyze grounds for appeal if unfavorable', 'priority' => 'high', 'estimated_hours' => 16],
            ['action' => 'File post-trial motions if warranted', 'priority' => 'medium', 'estimated_hours' => 20],
            ['action' => 'Prepare and file notice of appeal if needed', 'priority' => 'medium', 'estimated_hours' => 12],
            ['action' => 'Enforcement of judgment if favorable', 'priority' => 'high', 'estimated_hours' => 16],
            ['action' => 'Client communication and case closure', 'priority' => 'high', 'estimated_hours' => 4],
        ];
    }

    /**
     * Estimate duration for a phase
     */
    protected function estimatePhaseDuration(string $phase, LegalCase $case): int
    {
        $complexity = $case->documents->count() > 20 ? 1.5 : 1.0;

        $baseDurations = [
            'discovery' => 120,      // 4 months
            'motions' => 60,         // 2 months
            'trial_prep' => 90,      // 3 months
            'trial' => 30,           // 1 month
            'post_decision' => 45,   // 1.5 months
        ];

        $baseDuration = $baseDurations[$phase] ?? 60;

        return (int) ($baseDuration * $complexity);
    }

    /**
     * Define deliverables for each phase
     */
    protected function defineDeliverables(string $phase): array
    {
        return match ($phase) {
            'discovery' => [
                'Discovery request documents',
                'Deposition transcripts',
                'Evidence database',
                'Expert reports',
                'Witness list',
            ],
            'motions' => [
                'Motion for summary judgment',
                'Motions in limine',
                'Opposition briefs',
                'Motion hearing transcripts',
                'Court orders on motions',
            ],
            'trial_prep' => [
                'Trial brief',
                'Witness examination outlines',
                'Exhibit list and exhibits',
                'Jury instructions',
                'Opening/closing statement outlines',
                'Pretrial memorandum',
            ],
            'trial' => [
                'Trial transcripts',
                'Admitted exhibits',
                'Jury verdict or court decision',
                'Post-trial motion briefs',
            ],
            'post_decision' => [
                'Decision analysis memorandum',
                'Notice of appeal (if applicable)',
                'Post-trial motions',
                'Enforcement documents',
                'Final client report',
            ],
            default => ['Phase deliverables to be determined'],
        };
    }

    /**
     * Determine if case requires trial
     */
    protected function requiresTrial(LegalCase $case): bool
    {
        // Simple heuristic: complex cases with many documents likely need trial
        $docCount = $case->documents->count();
        $isComplex = $docCount > 15;

        // Check case description for settlement indicators
        $description = strtolower($case->description ?? '');
        $hasSettlementIndicators = str_contains($description, 'settlement') ||
                                   str_contains($description, 'agree') ||
                                   str_contains($description, 'mediation');

        // Require trial if complex and no settlement indicators
        return $isComplex && ! $hasSettlementIndicators;
    }

    /**
     * Identify settlement opportunities
     */
    protected function identifySettlementOpportunities(LegalCase $case, array $phases): array
    {
        $windows = [];

        // Early settlement (after discovery)
        $windows[] = [
            'window' => 'Early Settlement - Post-Discovery',
            'timing' => 'After Phase 1 (Discovery)',
            'rationale' => 'Both sides have information to evaluate case strength. Early resolution saves costs.',
            'recommended' => true,
        ];

        // Pre-trial settlement
        $windows[] = [
            'window' => 'Pre-Trial Settlement',
            'timing' => 'After Phase 2 (Motions)',
            'rationale' => 'Court rulings on motions clarify legal issues. Trial costs still avoidable.',
            'recommended' => true,
        ];

        // During trial
        if ($this->requiresTrial($case)) {
            $windows[] = [
                'window' => 'Mid-Trial Settlement',
                'timing' => 'During Phase 4 (Trial)',
                'rationale' => 'Evidence presentation may shift party positions. Judge may encourage settlement.',
                'recommended' => false,
            ];
        }

        return $windows;
    }

    /**
     * Extract milestones from phases
     */
    protected function extractMilestones(array $phases): array
    {
        $milestones = [];

        foreach ($phases as $phase) {
            $milestones[] = [
                'name' => "Complete {$phase['name']}",
                'target_date' => $phase['end_date'],
                'phase' => $phase['phase_number'],
            ];
        }

        return $milestones;
    }

    /**
     * Calculate resource requirements
     */
    protected function calculateResourceRequirements(array $phases, LegalCase $case): array
    {
        $totalHours = 0;
        $actionCounts = [];

        foreach ($phases as $phase) {
            foreach ($phase['actions'] as $action) {
                $totalHours += $action['estimated_hours'] ?? 0;
            }
            $actionCounts[$phase['name']] = count($phase['actions']);
        }

        // Estimate costs
        $hourlyRate = 300; // $300/hour assumption
        $estimatedCost = $totalHours * $hourlyRate;

        // Expert witnesses
        $needsExperts = $case->documents->count() > 20;

        return [
            'total_attorney_hours' => $totalHours,
            'estimated_legal_fees' => $estimatedCost,
            'actions_by_phase' => $actionCounts,
            'expert_witnesses_needed' => $needsExperts,
            'estimated_expert_costs' => $needsExperts ? 50000 : 0,
            'support_staff_hours' => (int) ($totalHours * 0.3), // Support staff = 30% of attorney hours
            'total_estimated_cost' => $estimatedCost + ($needsExperts ? 50000 : 0),
        ];
    }
}
