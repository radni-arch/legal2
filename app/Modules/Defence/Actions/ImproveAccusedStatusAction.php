<?php

namespace App\Modules\Defence\Actions;

use App\Models\LegalCase;
use App\Modules\Defence\Services\DefenseRecommendationService;
use App\Modules\Defence\Services\DefenseStrategyAnalyzer;
use App\Services\LegalReasoning\ArgumentGenerator;
use App\Services\LegalReasoning\RiskAssessor;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Log;

/**
 * ImproveAccusedStatusAction
 *
 * Comprehensive action to analyze and improve the legal position
 * of the accused through strategic defense planning.
 */
class ImproveAccusedStatusAction
{
    public function __construct(
        protected OpenAIService $openAI,
        protected DefenseStrategyAnalyzer $strategyAnalyzer,
        protected DefenseRecommendationService $recommendationService,
        protected ArgumentGenerator $argumentGenerator,
        protected RiskAssessor $riskAssessor
    ) {}

    /**
     * Execute the status improvement analysis
     *
     * @param  string  $caseId  The case ID
     * @param  array  $options  Additional options
     * @return array Comprehensive analysis and recommendations
     */
    public function execute(string $caseId, array $options = []): array
    {
        $startTime = microtime(true);

        Log::info('ImproveAccusedStatusAction - Starting execution', [
            'case_id' => $caseId,
        ]);

        try {
            $case = LegalCase::with('documents')->findOrFail($caseId);

            // Phase 1: Current Status Assessment
            $currentStatus = $this->assessCurrentStatus($case);

            // Phase 2: Identify Weaknesses in Prosecution
            $prosecutionWeaknesses = $this->strategyAnalyzer->findProsecutionWeaknesses($case);

            // Phase 3: Identify Defense Strengths
            $defenseStrengths = $this->strategyAnalyzer->assessDefenseStrength($case);

            // Phase 4: Find Mitigating Factors
            $mitigatingFactors = $this->strategyAnalyzer->identifyMitigatingFactors($case);

            // Phase 5: Generate Defense Arguments
            $defenseArguments = $this->generateDefenseArguments($case, $prosecutionWeaknesses);

            // Phase 6: Risk Assessment
            $riskAnalysis = $this->riskAssessor->assessRisks($caseId);

            // Phase 7: Strategic Recommendations
            $recommendations = $this->recommendationService->generateRecommendations($case, [
                'prosecution_weaknesses' => $prosecutionWeaknesses,
                'defense_strengths' => $defenseStrengths,
                'mitigating_factors' => $mitigatingFactors,
            ]);

            // Phase 8: Action Plan
            $actionPlan = $this->createActionPlan($case, $recommendations);

            // Phase 9: Status Improvement Score
            $improvementScore = $this->calculateImprovementPotential(
                $currentStatus,
                $defenseStrengths,
                $mitigatingFactors,
                $prosecutionWeaknesses
            );

            $executionTime = round(microtime(true) - $startTime, 4);

            $result = [
                'case_id' => $caseId,
                'execution_time' => $executionTime,
                'timestamp' => now()->toIso8601String(),

                // Current Assessment
                'current_status' => $currentStatus,

                // Defense Analysis
                'defense_strengths' => $defenseStrengths,
                'mitigating_factors' => $mitigatingFactors,
                'defense_arguments' => $defenseArguments,

                // Prosecution Analysis
                'prosecution_weaknesses' => $prosecutionWeaknesses,
                'exploitable_points' => $this->identifyExploitablePoints($prosecutionWeaknesses),

                // Risk & Strategy
                'risk_analysis' => $riskAnalysis,
                'recommendations' => $recommendations,
                'action_plan' => $actionPlan,

                // Improvement Potential
                'improvement_score' => $improvementScore,
                'status_upgrade_potential' => $this->determineStatusUpgradePotential($improvementScore),

                // Summary
                'executive_summary' => $this->generateExecutiveSummary(
                    $currentStatus,
                    $defenseStrengths,
                    $prosecutionWeaknesses,
                    $improvementScore
                ),
            ];

            Log::info('ImproveAccusedStatusAction - Execution completed', [
                'case_id' => $caseId,
                'improvement_score' => $improvementScore['overall_score'],
                'execution_time' => $executionTime,
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error('ImproveAccusedStatusAction - Execution failed', [
                'case_id' => $caseId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Assess the current legal status of the accused
     */
    protected function assessCurrentStatus(LegalCase $case): array
    {
        $prompt = <<<PROMPT
Analyze the current legal status of the accused in this case:

Case Details:
{$case->title}
{$case->description}

Assess:
1. Current charges and their severity
2. Evidence strength against the accused (0-100)
3. Legal position strength (0-100)
4. Likely outcome if no defense action is taken
5. Immediate concerns and vulnerabilities

Respond in JSON format.
PROMPT;

        $response = $this->openAI->chat([
            ['role' => 'system', 'content' => 'You are an expert defense attorney analyzing a case.'],
            ['role' => 'user', 'content' => $prompt],
        ], 'gpt-4o-mini', [
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.3,
        ]);

        $assessment = json_decode($response['choices'][0]['message']['content'], true);

        return [
            'charges' => $assessment['charges'] ?? [],
            'evidence_strength_against' => $assessment['evidence_strength'] ?? 0,
            'current_legal_position' => $assessment['legal_position_strength'] ?? 0,
            'likely_outcome_baseline' => $assessment['likely_outcome'] ?? 'Unknown',
            'immediate_concerns' => $assessment['immediate_concerns'] ?? [],
            'vulnerabilities' => $assessment['vulnerabilities'] ?? [],
        ];
    }

    /**
     * Generate defense arguments focused on improving status
     */
    protected function generateDefenseArguments(LegalCase $case, array $prosecutionWeaknesses): array
    {
        $objectives = [
            'Challenge prosecution evidence',
            'Establish reasonable doubt',
            'Present alternative narratives',
            'Highlight procedural errors',
        ];

        $argumentsResult = $this->argumentGenerator->generateArguments($case->id, $objectives);

        // Enhance with prosecution weakness exploitation
        foreach ($argumentsResult['arguments'] as &$argument) {
            $argument['prosecution_weaknesses_addressed'] = $this->mapWeaknessesToArgument(
                $argument,
                $prosecutionWeaknesses
            );
        }

        return $argumentsResult['arguments'];
    }

    /**
     * Identify exploitable points in prosecution's case
     */
    protected function identifyExploitablePoints(array $prosecutionWeaknesses): array
    {
        $exploitable = [];

        foreach ($prosecutionWeaknesses as $weakness) {
            if (($weakness['severity'] ?? 0) >= 70) {
                $exploitable[] = [
                    'weakness' => $weakness['description'] ?? $weakness,
                    'severity' => $weakness['severity'] ?? 0,
                    'exploitation_strategy' => $weakness['exploitation_strategy'] ?? 'To be determined',
                    'impact_potential' => $weakness['impact'] ?? 'Medium',
                ];
            }
        }

        return $exploitable;
    }

    /**
     * Create an actionable plan for improving the accused's status
     */
    protected function createActionPlan(LegalCase $case, array $recommendations): array
    {
        $plan = [
            'immediate_actions' => [],
            'short_term_actions' => [],
            'long_term_actions' => [],
            'timeline' => [],
        ];

        // Categorize recommendations by urgency
        foreach ($recommendations as $rec) {
            $priority = $rec['priority'] ?? 'medium';
            $timeline = $rec['timeline'] ?? 'short_term';

            $action = [
                'action' => $rec['action'] ?? $rec['recommendation'] ?? 'Action required',
                'priority' => $priority,
                'expected_impact' => $rec['impact'] ?? 'Medium',
                'resources_needed' => $rec['resources'] ?? [],
                'deadline' => $rec['deadline'] ?? null,
            ];

            if ($priority === 'urgent' || $timeline === 'immediate') {
                $plan['immediate_actions'][] = $action;
            } elseif ($timeline === 'short_term') {
                $plan['short_term_actions'][] = $action;
            } else {
                $plan['long_term_actions'][] = $action;
            }
        }

        return $plan;
    }

    /**
     * Calculate the potential for status improvement
     */
    protected function calculateImprovementPotential(
        array $currentStatus,
        array $defenseStrengths,
        array $mitigatingFactors,
        array $prosecutionWeaknesses
    ): array {
        $baseScore = $currentStatus['current_legal_position'] ?? 0;

        // Add points for defense strengths
        $defensePoints = count($defenseStrengths['strong_points'] ?? []) * 5;

        // Add points for mitigating factors
        $mitigatingPoints = count($mitigatingFactors) * 3;

        // Add points for prosecution weaknesses
        $weaknessPoints = count($prosecutionWeaknesses) * 4;

        // Calculate improvement potential (0-100)
        $improvementPotential = min(100, $baseScore + $defensePoints + $mitigatingPoints + $weaknessPoints);

        // Calculate realistic improved score
        $realisticImprovedScore = min(100, $baseScore + ($improvementPotential - $baseScore) * 0.7);

        return [
            'baseline_score' => $baseScore,
            'defense_contribution' => $defensePoints,
            'mitigation_contribution' => $mitigatingPoints,
            'prosecution_weakness_contribution' => $weaknessPoints,
            'maximum_potential_score' => $improvementPotential,
            'realistic_improved_score' => round($realisticImprovedScore, 1),
            'overall_score' => round($realisticImprovedScore, 1),
            'improvement_range' => [
                'best_case' => round($improvementPotential, 1),
                'likely_case' => round($realisticImprovedScore, 1),
                'worst_case' => $baseScore,
            ],
        ];
    }

    /**
     * Determine potential for status upgrade (e.g., dismissal, reduced charges)
     */
    protected function determineStatusUpgradePotential(array $improvementScore): array
    {
        $score = $improvementScore['overall_score'];

        $potential = [];

        if ($score >= 80) {
            $potential[] = [
                'outcome' => 'Case Dismissal',
                'probability' => 'High',
                'requirements' => 'Strong procedural challenges or evidence suppression',
            ];
        }

        if ($score >= 65) {
            $potential[] = [
                'outcome' => 'Charge Reduction',
                'probability' => 'Medium-High',
                'requirements' => 'Successful plea negotiation with demonstrated weaknesses',
            ];
        }

        if ($score >= 50) {
            $potential[] = [
                'outcome' => 'Favorable Plea Deal',
                'probability' => 'Medium',
                'requirements' => 'Leverage prosecution weaknesses in negotiation',
            ];
        }

        $potential[] = [
            'outcome' => 'Improved Trial Position',
            'probability' => 'High',
            'requirements' => 'Effective use of identified defense strategies',
        ];

        return $potential;
    }

    /**
     * Generate executive summary
     */
    protected function generateExecutiveSummary(
        array $currentStatus,
        array $defenseStrengths,
        array $prosecutionWeaknesses,
        array $improvementScore
    ): string {
        $baseScore = $currentStatus['current_legal_position'];
        $improvedScore = $improvementScore['overall_score'];
        $improvement = $improvedScore - $baseScore;

        $summary = "DEFENSE STATUS IMPROVEMENT ANALYSIS\n\n";
        $summary .= "Current Position: {$baseScore}/100\n";
        $summary .= "Improved Position (Potential): {$improvedScore}/100\n";
        $summary .= "Improvement Potential: +{$improvement} points\n\n";

        $summary .= "KEY FINDINGS:\n";
        $summary .= '- '.count($defenseStrengths['strong_points'] ?? [])." strong defense points identified\n";
        $summary .= '- '.count($prosecutionWeaknesses)." prosecution weaknesses found\n";
        $summary .= '- '.($improvementScore['mitigation_contribution'] / 3)." mitigating factors available\n\n";

        if ($improvedScore >= 70) {
            $summary .= "RECOMMENDATION: Strong defense position. Pursue aggressive defense strategy.\n";
        } elseif ($improvedScore >= 50) {
            $summary .= "RECOMMENDATION: Moderate defense position. Consider negotiated settlement with leverage.\n";
        } else {
            $summary .= "RECOMMENDATION: Challenging position. Focus on damage mitigation and plea negotiations.\n";
        }

        return $summary;
    }

    /**
     * Map prosecution weaknesses to defense arguments
     */
    protected function mapWeaknessesToArgument(array $argument, array $prosecutionWeaknesses): array
    {
        $mapped = [];

        foreach ($prosecutionWeaknesses as $weakness) {
            // Simple keyword matching (can be enhanced with NLP)
            $weaknessText = is_array($weakness) ? ($weakness['description'] ?? '') : $weakness;
            $argumentText = $argument['argument'] ?? '';

            if (stripos($argumentText, $weaknessText) !== false) {
                $mapped[] = $weakness;
            }
        }

        return $mapped;
    }
}
