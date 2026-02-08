<?php

namespace App\Services;

use App\Models\LegalCase;
use App\Models\LegalFactPattern;
use App\Models\User;
use App\Services\LegalReasoning\FactPatternExtractor;
use App\Services\LegalReasoning\FeatureExtractor;
use App\Services\LegalReasoning\OutcomePredictor;
use App\Services\LegalReasoning\RiskAssessor;
use App\Services\LegalReasoning\StrategyBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Case Intake Service
 *
 * Orchestrates the complete case intake workflow:
 * 1. Extract facts from client narrative
 * 2. Create case record
 * 3. Find similar cases and precedents
 * 4. Generate preliminary analysis and recommendations
 * 5. Assess risks and predict outcomes
 *
 * This service demonstrates how to integrate FactPatternExtractor
 * with the broader legal reasoning system.
 */
class CaseIntakeService
{
    public function __construct(
        protected FactPatternExtractor $factExtractor,
        protected FeatureExtractor $featureExtractor,
        protected OutcomePredictor $outcomePredictor,
        protected RiskAssessor $riskAssessor,
        protected StrategyBuilder $strategyBuilder,
        protected DecisionSearchService $decisionSearch
    ) {}

    /**
     * Process new case intake with comprehensive analysis
     *
     * @param  array  $intakeData  Contains:
     *                             - narrative: Client's description of the situation
     *                             - client_name: Client's name
     *                             - opponent_name: Opposing party (if known)
     *                             - objectives: Array of desired outcomes
     *                             - user_id: User/attorney ID
     * @return array Complete intake analysis
     */
    public function processIntake(array $intakeData): array
    {
        $startTime = microtime(true);
        $userId = $intakeData['user_id'];

        Log::info('CaseIntakeService - Starting intake', [
            'user_id' => $userId,
            'narrative_length' => strlen($intakeData['narrative']),
        ]);

        DB::beginTransaction();

        try {
            // Step 1: Extract fact pattern from narrative
            $factPattern = $this->extractFactPattern($intakeData['narrative'], $userId);

            // Step 2: Create case record
            $case = $this->createCaseRecord($factPattern, $intakeData);

            // Step 3: Find similar cases (precedents)
            $similarCases = $this->findSimilarCases($factPattern);

            // Step 4: Find relevant court decisions
            $relevantDecisions = $this->findRelevantDecisions($factPattern);

            // Step 5: Generate preliminary risk assessment
            $riskAssessment = $this->assessRisks($case, $factPattern);

            // Step 6: Predict outcome
            $outcomePrediction = $this->predictOutcome($case, $factPattern);

            // Step 7: Generate preliminary strategy
            $preliminaryStrategy = $this->generatePreliminaryStrategy(
                $case,
                $factPattern,
                $intakeData['objectives'] ?? []
            );

            // Step 8: Identify next steps
            $nextSteps = $this->identifyNextSteps($factPattern, $riskAssessment);

            DB::commit();

            $result = [
                'success' => true,
                'case_id' => $case->id,
                'fact_pattern_id' => $factPattern->id,
                'analysis' => [
                    'legal_area' => $factPattern->legal_area,
                    'extraction_confidence' => $factPattern->extraction_confidence,
                    'parties' => $factPattern->getParties(),
                    'legal_issues' => $factPattern->getLegalIssues(),
                    'events' => $factPattern->getEvents(),
                    'similar_cases_count' => count($similarCases),
                    'similar_cases' => $similarCases,
                    'relevant_decisions_count' => count($relevantDecisions),
                    'relevant_decisions' => array_slice($relevantDecisions, 0, 5), // Top 5
                    'risk_assessment' => $riskAssessment,
                    'outcome_prediction' => $outcomePrediction,
                    'preliminary_strategy' => $preliminaryStrategy,
                    'next_steps' => $nextSteps,
                    'flags' => $this->generateFlags($factPattern, $riskAssessment),
                ],
                'performance' => [
                    'total_time' => round(microtime(true) - $startTime, 3),
                ],
            ];

            Log::info('CaseIntakeService - Completed', [
                'case_id' => $case->id,
                'total_time' => $result['performance']['total_time'],
            ]);

            return $result;

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('CaseIntakeService - Failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Extract fact pattern from narrative
     */
    protected function extractFactPattern(string $narrative, int $userId): LegalFactPattern
    {
        $factPattern = $this->factExtractor->extract($narrative, $userId, [
            'save' => true,
            'use_cache' => false, // Always fresh for new cases
        ]);

        if (! ($factPattern instanceof LegalFactPattern)) {
            throw new \Exception('Failed to extract and save fact pattern');
        }

        if ($factPattern->hasLowConfidence()) {
            Log::warning('CaseIntakeService - Low confidence extraction', [
                'fact_pattern_id' => $factPattern->id,
                'confidence' => $factPattern->extraction_confidence,
            ]);
        }

        return $factPattern;
    }

    /**
     * Create case record from fact pattern
     */
    protected function createCaseRecord(LegalFactPattern $factPattern, array $intakeData): LegalCase
    {
        $structuredFacts = $factPattern->structured_facts;

        // Extract case metadata from facts
        $parties = $structuredFacts['parties'] ?? [];
        $plaintiff = collect($parties)->firstWhere('role', 'plaintiff');
        $defendant = collect($parties)->firstWhere('role', 'defendant');

        $summary = $structuredFacts['summary'] ?? '';
        $proceduralPosture = $structuredFacts['procedural_posture'] ?? [];

        $case = LegalCase::create([
            'id' => Str::ulid(),
            'case_number' => $this->generateCaseNumber(),
            'title' => $this->generateCaseTitle($factPattern),
            'client_name' => $intakeData['client_name'] ?? $plaintiff['name'] ?? 'Unknown Client',
            'opponent_name' => $intakeData['opponent_name'] ?? $defendant['name'] ?? 'Unknown Opponent',
            'court' => $proceduralPosture['court'] ?? null,
            'jurisdiction' => $proceduralPosture['jurisdiction'] ?? null,
            'filing_date' => now(),
            'status' => 'intake',
            'tags' => [$factPattern->legal_area],
            'description' => $summary,
        ]);

        Log::info('CaseIntakeService - Case created', [
            'case_id' => $case->id,
            'case_number' => $case->case_number,
        ]);

        return $case;
    }

    /**
     * Find similar cases from user's history
     */
    protected function findSimilarCases(LegalFactPattern $factPattern): array
    {
        // Get all fact patterns in same legal area
        $candidates = $this->factExtractor->searchByLegalArea(
            $factPattern->legal_area,
            100
        )->where('user_id', $factPattern->user_id);

        $similar = [];
        foreach ($candidates as $candidate) {
            if ($candidate->id === $factPattern->id) {
                continue;
            }

            $similarity = $this->factExtractor->comparePatterns($factPattern, $candidate);

            if ($similarity['overall_similarity'] >= 0.6) {
                $similar[] = [
                    'fact_pattern_id' => $candidate->id,
                    'similarity_score' => $similarity['overall_similarity'],
                    'legal_area' => $candidate->legal_area,
                    'confidence' => $candidate->extraction_confidence,
                    'created_at' => $candidate->created_at->toISOString(),
                ];
            }
        }

        // Sort by similarity
        usort($similar, fn ($a, $b) => $b['similarity_score'] <=> $a['similarity_score']);

        return array_slice($similar, 0, 10); // Top 10
    }

    /**
     * Find relevant court decisions
     */
    protected function findRelevantDecisions(LegalFactPattern $factPattern): array
    {
        $structuredFacts = $factPattern->structured_facts;
        $legalIssues = $structuredFacts['legal_issues'] ?? [];

        if (empty($legalIssues)) {
            return [];
        }

        // Build search query from legal issues
        $searchTerms = [];
        foreach ($legalIssues as $issue) {
            if (isset($issue['issue'])) {
                $searchTerms[] = $issue['issue'];
            }
        }

        if (empty($searchTerms)) {
            return [];
        }

        $query = implode(' ', $searchTerms);

        try {
            $results = $this->decisionSearch->search($query, [
                'limit' => 10,
                'use_vector' => true,
            ]);

            return $results['results'] ?? [];
        } catch (\Exception $e) {
            Log::warning('CaseIntakeService - Decision search failed', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Assess risks for the case
     */
    protected function assessRisks(LegalCase $case, LegalFactPattern $factPattern): array
    {
        $structuredFacts = $factPattern->structured_facts;

        $risks = [
            'overall_risk_level' => 'medium', // Default
            'risk_factors' => [],
            'strengths' => [],
            'weaknesses' => [],
        ];

        // Low confidence extraction is a risk
        if ($factPattern->hasLowConfidence()) {
            $risks['risk_factors'][] = [
                'type' => 'procedural',
                'description' => 'Incomplete fact pattern - requires additional client interview',
                'severity' => 'medium',
            ];
        }

        // Analyze disputed facts
        $disputedFacts = $structuredFacts['disputed_facts'] ?? [];
        if (count($disputedFacts) > 5) {
            $risks['risk_factors'][] = [
                'type' => 'evidentiary',
                'description' => 'High number of disputed facts - may require extensive discovery',
                'severity' => 'medium',
            ];
        }

        // Analyze favorable vs unfavorable facts
        $favorableFacts = $structuredFacts['facts_favorable_to_plaintiff'] ?? [];
        $unfavorableFacts = $structuredFacts['facts_favorable_to_defendant'] ?? [];

        if (count($favorableFacts) > count($unfavorableFacts)) {
            $risks['strengths'][] = 'Strong factual position with more favorable facts';
        } else {
            $risks['weaknesses'][] = 'Opposing party may have stronger factual position';
        }

        // Evidence assessment
        $evidence = $structuredFacts['evidence'] ?? [];
        $strongEvidence = collect($evidence)->where('strength', 'strong')->count();
        $weakEvidence = collect($evidence)->where('strength', 'weak')->count();

        if ($strongEvidence > 2) {
            $risks['strengths'][] = 'Multiple strong pieces of evidence available';
        }

        if ($weakEvidence > $strongEvidence) {
            $risks['risk_factors'][] = [
                'type' => 'evidentiary',
                'description' => 'Evidence may be weak - consider additional investigation',
                'severity' => 'high',
            ];
        }

        // Calculate overall risk level
        $riskFactorCount = count($risks['risk_factors']);
        $strengthCount = count($risks['strengths']);

        if ($riskFactorCount > 3 || ($riskFactorCount > 0 && $strengthCount === 0)) {
            $risks['overall_risk_level'] = 'high';
        } elseif ($riskFactorCount === 0 && $strengthCount > 2) {
            $risks['overall_risk_level'] = 'low';
        }

        return $risks;
    }

    /**
     * Predict case outcome
     */
    protected function predictOutcome(LegalCase $case, LegalFactPattern $factPattern): array
    {
        // Use structured facts to make simple prediction
        $structuredFacts = $factPattern->structured_facts;

        $favorableFacts = count($structuredFacts['facts_favorable_to_plaintiff'] ?? []);
        $unfavorableFacts = count($structuredFacts['facts_favorable_to_defendant'] ?? []);
        $evidence = $structuredFacts['evidence'] ?? [];
        $strongEvidence = collect($evidence)->where('strength', 'strong')->count();

        // Simple scoring algorithm
        $score = 0.5; // Neutral

        if ($favorableFacts > $unfavorableFacts) {
            $score += 0.2;
        } elseif ($unfavorableFacts > $favorableFacts) {
            $score -= 0.2;
        }

        if ($strongEvidence >= 3) {
            $score += 0.15;
        } elseif ($strongEvidence === 0) {
            $score -= 0.15;
        }

        if ($factPattern->hasHighConfidence()) {
            // High confidence extraction suggests clear facts
            $score += 0.05;
        }

        // Normalize to 0-1
        $score = max(0, min(1, $score));

        return [
            'success_probability' => round($score, 2),
            'confidence' => $factPattern->extraction_confidence,
            'factors' => [
                'favorable_facts_count' => $favorableFacts,
                'unfavorable_facts_count' => $unfavorableFacts,
                'strong_evidence_count' => $strongEvidence,
            ],
            'interpretation' => $this->interpretPrediction($score),
        ];
    }

    /**
     * Generate preliminary strategy
     */
    protected function generatePreliminaryStrategy(
        LegalCase $case,
        LegalFactPattern $factPattern,
        array $objectives
    ): array {
        $structuredFacts = $factPattern->structured_facts;
        $legalIssues = $structuredFacts['legal_issues'] ?? [];

        return [
            'legal_area' => $factPattern->legal_area,
            'primary_issues' => array_map(fn ($i) => $i['issue'] ?? 'Unknown', $legalIssues),
            'recommended_approach' => $this->recommendApproach($factPattern),
            'objectives' => $objectives,
            'key_arguments' => $this->identifyKeyArguments($structuredFacts),
            'discovery_needs' => $this->identifyDiscoveryNeeds($structuredFacts),
        ];
    }

    /**
     * Identify next steps
     */
    protected function identifyNextSteps(LegalFactPattern $factPattern, array $riskAssessment): array
    {
        $steps = [];

        // Always start with client interview if low confidence
        if ($factPattern->hasLowConfidence()) {
            $steps[] = [
                'priority' => 'high',
                'action' => 'Conduct detailed client interview',
                'reason' => 'Incomplete fact pattern - need additional information',
                'deadline' => 'Within 3 days',
            ];
        }

        $structuredFacts = $factPattern->structured_facts;

        // Evidence gathering
        $evidence = $structuredFacts['evidence'] ?? [];
        $needsDiscovery = collect($evidence)->where('availability', 'needs_discovery')->count();

        if ($needsDiscovery > 0) {
            $steps[] = [
                'priority' => 'high',
                'action' => 'Initiate discovery process',
                'reason' => "{$needsDiscovery} pieces of evidence require discovery",
                'deadline' => 'Within 30 days',
            ];
        }

        // High risk cases need strategy session
        if ($riskAssessment['overall_risk_level'] === 'high') {
            $steps[] = [
                'priority' => 'high',
                'action' => 'Schedule strategy session',
                'reason' => 'High-risk case requires comprehensive planning',
                'deadline' => 'Within 7 days',
            ];
        }

        // Legal research
        $legalIssues = $structuredFacts['legal_issues'] ?? [];
        if (count($legalIssues) > 0) {
            $steps[] = [
                'priority' => 'medium',
                'action' => 'Conduct legal research',
                'reason' => 'Research '.count($legalIssues).' identified legal issues',
                'deadline' => 'Within 14 days',
            ];
        }

        // Default: initial case review
        if (empty($steps)) {
            $steps[] = [
                'priority' => 'medium',
                'action' => 'Complete initial case review',
                'reason' => 'Standard intake procedure',
                'deadline' => 'Within 7 days',
            ];
        }

        return $steps;
    }

    /**
     * Generate flags for attention
     */
    protected function generateFlags(LegalFactPattern $factPattern, array $riskAssessment): array
    {
        $flags = [];

        if ($factPattern->hasLowConfidence()) {
            $flags[] = [
                'type' => 'warning',
                'message' => 'Low extraction confidence - manual review recommended',
            ];
        }

        if ($riskAssessment['overall_risk_level'] === 'high') {
            $flags[] = [
                'type' => 'alert',
                'message' => 'High-risk case - senior attorney review required',
            ];
        }

        $structuredFacts = $factPattern->structured_facts;
        $deadlines = $structuredFacts['procedural_posture']['deadlines'] ?? [];

        if (! empty($deadlines)) {
            $flags[] = [
                'type' => 'urgent',
                'message' => 'Case has existing deadlines - immediate calendar review needed',
            ];
        }

        return $flags;
    }

    /**
     * Helper methods
     */
    protected function generateCaseNumber(): string
    {
        return 'CASE-'.now()->format('Y').'-'.strtoupper(Str::random(6));
    }

    protected function generateCaseTitle(LegalFactPattern $factPattern): string
    {
        $structuredFacts = $factPattern->structured_facts;
        $summary = $structuredFacts['summary'] ?? '';

        if (! empty($summary) && strlen($summary) < 100) {
            return $summary;
        }

        $legalIssues = $structuredFacts['legal_issues'] ?? [];
        if (! empty($legalIssues) && isset($legalIssues[0]['issue'])) {
            return ucfirst($factPattern->legal_area).' - '.$legalIssues[0]['issue'];
        }

        return ucfirst($factPattern->legal_area).' Matter';
    }

    protected function interpretPrediction(float $score): string
    {
        if ($score >= 0.7) {
            return 'Strong likelihood of success';
        } elseif ($score >= 0.6) {
            return 'Moderate to good chance of success';
        } elseif ($score >= 0.4) {
            return 'Uncertain outcome - could go either way';
        } elseif ($score >= 0.3) {
            return 'Challenging case - low to moderate success probability';
        } else {
            return 'Difficult case - consider settlement options';
        }
    }

    protected function recommendApproach(LegalFactPattern $factPattern): string
    {
        $structuredFacts = $factPattern->structured_facts;
        $proceduralPosture = $structuredFacts['procedural_posture'] ?? [];
        $stage = $proceduralPosture['stage'] ?? 'pre-filing';

        $approaches = [
            'pre-filing' => 'Gather evidence, conduct legal research, attempt negotiation before filing',
            'filed' => 'Respond to initial pleadings, begin discovery planning',
            'discovery' => 'Execute discovery plan, identify key evidence, prepare for depositions',
            'trial' => 'Finalize trial strategy, prepare witnesses, draft trial briefs',
            'appeal' => 'Review trial record, identify appealable issues, prepare appellate briefs',
        ];

        return $approaches[$stage] ?? 'Assess current procedural status and develop appropriate strategy';
    }

    protected function identifyKeyArguments(array $structuredFacts): array
    {
        $arguments = [];

        $favorableFacts = $structuredFacts['facts_favorable_to_plaintiff'] ?? [];
        foreach (array_slice($favorableFacts, 0, 3) as $fact) {
            $arguments[] = $fact;
        }

        return $arguments;
    }

    protected function identifyDiscoveryNeeds(array $structuredFacts): array
    {
        $needs = [];

        $evidence = $structuredFacts['evidence'] ?? [];
        foreach ($evidence as $item) {
            if (($item['availability'] ?? '') === 'needs_discovery') {
                $needs[] = $item['description'] ?? 'Unknown evidence';
            }
        }

        return $needs;
    }
}
