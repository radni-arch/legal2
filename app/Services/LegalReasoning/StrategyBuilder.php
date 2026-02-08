<?php

namespace App\Services\LegalReasoning;

use App\Exceptions\AnalysisException;
use App\Models\CaseStrategy;
use App\Models\LegalCase;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class StrategyBuilder
{
    public function __construct(
        protected OpenAIService $openAI,
        protected OutcomePredictor $outcomePredictor,
        protected ArgumentGenerator $argumentGenerator,
        protected RiskAssessor $riskAssessor,
        protected StrategicPlanner $strategicPlanner,
        protected CitationAnalyzer $citationAnalyzer
    ) {}

    /**
     * Build comprehensive case strategy (Master Orchestrator)
     */
    public function buildCaseStrategy(string $caseId, array $objectives = []): array
    {
        $correlationId = request()->header('X-Request-ID') ?? Str::uuid()->toString();
        $startTime = microtime(true);

        Log::withContext(['correlation_id' => $correlationId]);

        Log::info('StrategyBuilder: buildCaseStrategy initiated', [
            'case_id' => $caseId,
            'objectives_count' => count($objectives),
            'user_id' => auth()->id(),
        ]);

        try {
            $case = LegalCase::with('documents')->findOrFail($caseId);

            // Phase 1: Parallel Analysis (independent operations)
            Log::info('StrategyBuilder - Phase 1: Parallel analysis');

            // Analyze case position (SWOT)
            $analysis = $this->analyzeCasePosition($case);

            // Generate arguments
            $argumentsResult = $this->argumentGenerator->generateArguments($caseId, $objectives);

            // Assess risks
            $risksResult = $this->riskAssessor->assessRisks($caseId);

            // Select precedents
            $precedents = $this->selectPrecedents($case, $argumentsResult['arguments'] ?? []);

            // Create timeline
            $timeline = $this->strategicPlanner->createActionPlan($caseId, $objectives);

            // Phase 2: Synthesis
            Log::info('StrategyBuilder - Phase 2: Strategy synthesis');

            // Compile all components
            $strategyComponents = [
                'analysis' => $analysis,
                'arguments' => $argumentsResult,
                'risks' => $risksResult,
                'precedents' => $precedents,
                'timeline' => $timeline,
            ];

            // Generate strategic recommendations
            $recommendations = $this->generateRecommendations($strategyComponents, $case);

            // LLM synthesis for holistic strategy
            $synthesizedStrategy = $this->synthesizeStrategy($strategyComponents, $case, $objectives);

            // Compile final strategy
            $finalStrategy = [
                'case_id' => $caseId,
                'version' => '1.0',
                'created_at' => now()->toDateTimeString(),
                'objectives' => $objectives,

                // Analysis
                'case_analysis' => $analysis,

                // Arguments
                'arguments' => $argumentsResult['arguments'] ?? [],
                'strongest_argument' => $argumentsResult['strongest_argument'] ?? null,

                // Risks
                'risk_assessment' => [
                    'overall_risk_level' => $risksResult['risk_level'],
                    'risk_scores' => $risksResult['risk_scores'],
                    'mitigation_strategies' => $risksResult['mitigation_strategies'],
                ],

                // Precedents
                'key_precedents' => $precedents,

                // Timeline and Action Plan
                'timeline' => $timeline,
                'action_plan' => [
                    'phases' => $timeline['phases'],
                    'total_duration_days' => $timeline['total_duration_days'],
                    'settlement_windows' => $timeline['settlement_windows'],
                    'resources_needed' => $timeline['resources_needed'],
                ],

                // Recommendations
                'strategic_recommendations' => $recommendations,

                // Synthesis
                'executive_summary' => $synthesizedStrategy,

                // Metrics
                'confidence_score' => $this->calculateConfidenceScore($strategyComponents),
            ];

            // Persist strategy to database
            $this->persistStrategy($caseId, $finalStrategy);

            $duration = (microtime(true) - $startTime) * 1000;

            Log::info('StrategyBuilder: buildCaseStrategy completed', [
                'case_id' => $caseId,
                'confidence_score' => $finalStrategy['confidence_score'],
                'arguments_count' => count($finalStrategy['arguments']),
                'timeline_duration_days' => $finalStrategy['action_plan']['total_duration_days'] ?? 0,
                'duration_ms' => round($duration, 2),
            ]);

            return $finalStrategy;

        } catch (AnalysisException $e) {
            Log::error('StrategyBuilder: buildCaseStrategy failed with AnalysisException', [
                'case_id' => $caseId,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('StrategyBuilder: Case not found', [
                'case_id' => $caseId,
                'error' => $e->getMessage(),
            ]);

            throw new AnalysisException(
                "Case not found: {$caseId}",
                AnalysisException::RESOURCE_NOT_FOUND,
                $e
            );

        } catch (\Exception $e) {
            Log::error('StrategyBuilder: buildCaseStrategy failed with unexpected exception', [
                'case_id' => $caseId,
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new AnalysisException(
                'Strategy building failed: '.$e->getMessage(),
                AnalysisException::ANALYSIS_FAILED,
                $e
            );
        }
    }

    /**
     * Analyze case position with SWOT
     */
    protected function analyzeCasePosition(LegalCase $case): array
    {
        // Get similar cases for comparison
        $prediction = $this->outcomePredictor->predictOutcome($case->id);
        $similarCases = $prediction['similar_cases'] ?? [];

        // Perform SWOT analysis
        $swot = $this->performSWOT($case, $similarCases);

        // Analyze opponent position
        $opponentAnalysis = $this->analyzeOpponentPosition($case);

        // Calculate case strength
        $caseStrength = $this->scoreCaseStrength($swot, $similarCases);

        // Calculate win probability
        $winProbability = $prediction['probability_distribution']['win'] ?? 0.5;

        return [
            'case_strength_score' => round($caseStrength, 3),
            'win_probability' => round($winProbability, 3),
            'swot_analysis' => $swot,
            'opponent_analysis' => $opponentAnalysis,
            'similar_cases_analyzed' => count($similarCases),
        ];
    }

    /**
     * Perform SWOT analysis
     */
    protected function performSWOT(LegalCase $case, array $similarCases): array
    {
        $caseContext = $this->prepareCaseContext($case);
        $similarCasesText = collect($similarCases)
            ->take(5)
            ->map(fn ($c) => '- '.($c['case_title'] ?? 'N/A').': '.($c['outcome'] ?? 'N/A'))
            ->implode("\n");

        $prompt = "Perform a comprehensive SWOT analysis for this legal case:

{$caseContext}

Similar Cases:
{$similarCasesText}

Provide a detailed SWOT analysis in JSON format:
{
  \"strengths\": [\"strength 1\", \"strength 2\", ...],
  \"weaknesses\": [\"weakness 1\", \"weakness 2\", ...],
  \"opportunities\": [\"opportunity 1\", \"opportunity 2\", ...],
  \"threats\": [\"threat 1\", \"threat 2\", ...]
}

Each category should have 3-5 items.";

        $response = $this->openAI->complete($prompt, [
            'model' => 'gpt-4o-mini',
            'max_tokens' => 1000,
            'temperature' => 0.3,
        ]);

        $content = $response['choices'][0]['message']['content'] ?? '';
        if (preg_match('/\{[\s\S]*\}/', $content, $matches)) {
            $swot = json_decode($matches[0], true);

            return $swot ?? $this->getDefaultSWOT();
        }

        return $this->getDefaultSWOT();
    }

    /**
     * Analyze opponent position
     */
    protected function analyzeOpponentPosition(LegalCase $case): array
    {
        $caseContext = $this->prepareCaseContext($case);

        $prompt = "Analyze the opposing party's position in this case:

{$caseContext}

Provide analysis in JSON format:
{
  \"opponent_strengths\": [\"List 3-5 key strengths\"],
  \"opponent_weaknesses\": [\"List 3-5 potential weaknesses\"],
  \"likely_strategy\": \"Brief description of opponent's likely approach\",
  \"vulnerability_points\": [\"List 2-3 points where opponent is vulnerable\"]
}";

        $response = $this->openAI->complete($prompt, [
            'model' => 'gpt-4o-mini',
            'max_tokens' => 800,
            'temperature' => 0.4,
        ]);

        $content = $response['choices'][0]['message']['content'] ?? '';
        if (preg_match('/\{[\s\S]*\}/', $content, $matches)) {
            return json_decode($matches[0], true) ?? [];
        }

        return [
            'opponent_strengths' => ['Analysis pending'],
            'opponent_weaknesses' => ['Analysis pending'],
            'likely_strategy' => 'Standard defense strategy',
            'vulnerability_points' => ['To be determined'],
        ];
    }

    /**
     * Score overall case strength
     */
    protected function scoreCaseStrength(array $swot, array $similarCases): float
    {
        $score = 0.5; // Baseline

        // Factor 1: Strength vs Weakness ratio (max 0.3)
        $strengthCount = count($swot['strengths'] ?? []);
        $weaknessCount = count($swot['weaknesses'] ?? []);
        if ($strengthCount + $weaknessCount > 0) {
            $ratio = $strengthCount / ($strengthCount + $weaknessCount);
            $score += ($ratio * 0.3);
        }

        // Factor 2: Similar case outcomes (max 0.25)
        if (! empty($similarCases)) {
            $favorableOutcomes = 0;
            foreach ($similarCases as $similar) {
                if (isset($similar['outcome']) && str_contains(strtolower($similar['outcome']), 'favor')) {
                    $favorableOutcomes++;
                }
            }
            $outcomeRatio = $favorableOutcomes / count($similarCases);
            $score += ($outcomeRatio * 0.25);
        }

        // Factor 3: Opportunity vs Threat ratio (max 0.25)
        $opportunityCount = count($swot['opportunities'] ?? []);
        $threatCount = count($swot['threats'] ?? []);
        if ($opportunityCount + $threatCount > 0) {
            $ratio = $opportunityCount / ($opportunityCount + $threatCount);
            $score += ($ratio * 0.25);
        }

        return min($score, 1.0);
    }

    /**
     * Select key precedents for strategy
     */
    protected function selectPrecedents(LegalCase $case, array $arguments): array
    {
        $allPrecedents = [];

        // Collect precedents from arguments
        foreach ($arguments as $argument) {
            $precedents = $argument['supporting_precedents'] ?? [];
            foreach ($precedents as $precedent) {
                $allPrecedents[$precedent['decision_id']] = $precedent;
            }
        }

        // Sort by authority score and return top 10
        usort($allPrecedents, fn ($a, $b) => ($b['authority_score'] ?? 0) <=> ($a['authority_score'] ?? 0));

        return array_slice($allPrecedents, 0, 10);
    }

    /**
     * Generate strategic recommendations
     */
    protected function generateRecommendations(array $components, LegalCase $case): array
    {
        $riskLevel = $components['risks']['risk_level'] ?? 'MEDIUM';
        $winProbability = $components['analysis']['win_probability'] ?? 0.5;
        $caseStrength = $components['analysis']['case_strength_score'] ?? 0.5;

        $recommendations = [];

        // Recommendation 1: Overall approach
        if ($riskLevel === 'HIGH') {
            $recommendations[] = [
                'category' => 'Overall Approach',
                'recommendation' => 'Consider settlement negotiations. High risk profile suggests exposure to unfavorable outcome.',
                'priority' => 'high',
            ];
        } elseif ($caseStrength >= 0.7) {
            $recommendations[] = [
                'category' => 'Overall Approach',
                'recommendation' => 'Proceed with confidence. Strong case position supports aggressive litigation strategy.',
                'priority' => 'high',
            ];
        } else {
            $recommendations[] = [
                'category' => 'Overall Approach',
                'recommendation' => 'Balanced approach. Monitor developments and maintain flexibility for settlement or litigation.',
                'priority' => 'medium',
            ];
        }

        // Recommendation 2: Settlement
        if ($winProbability < 0.6) {
            $recommendations[] = [
                'category' => 'Settlement Strategy',
                'recommendation' => 'Actively pursue settlement opportunities, especially after discovery phase.',
                'priority' => 'high',
            ];
        }

        // Recommendation 3: Risk mitigation
        $mitigationStrategies = $components['risks']['mitigation_strategies'] ?? [];
        if (! empty($mitigationStrategies)) {
            $recommendations[] = [
                'category' => 'Risk Mitigation',
                'recommendation' => 'Implement identified mitigation strategies immediately, particularly for high-severity risks.',
                'priority' => 'high',
            ];
        }

        // Recommendation 4: Resource allocation
        $estimatedCost = $components['timeline']['resources_needed']['total_estimated_cost'] ?? 0;
        if ($estimatedCost > 200000) {
            $recommendations[] = [
                'category' => 'Resource Management',
                'recommendation' => 'High litigation costs projected. Consider cost-benefit analysis and client budget constraints.',
                'priority' => 'medium',
            ];
        }

        // Recommendation 5: Evidence strengthening
        $recommendations[] = [
            'category' => 'Evidence Development',
            'recommendation' => 'Focus on strengthening key arguments during discovery. Target evidence gaps identified in risk assessment.',
            'priority' => 'high',
        ];

        return $recommendations;
    }

    /**
     * Synthesize strategy using LLM
     */
    protected function synthesizeStrategy(array $components, LegalCase $case, array $objectives): string
    {
        $caseContext = $this->prepareCaseContext($case);
        $objectivesText = ! empty($objectives) ? implode('; ', $objectives) : 'Achieve favorable outcome for client';

        $analysisText = 'Win Probability: '.($components['analysis']['win_probability'] ?? 'N/A')."\n";
        $analysisText .= 'Case Strength: '.($components['analysis']['case_strength_score'] ?? 'N/A')."\n";
        $analysisText .= 'Risk Level: '.($components['risks']['risk_level'] ?? 'N/A');

        $argumentCount = count($components['arguments']['arguments'] ?? []);
        $phaseCount = count($components['timeline']['phases'] ?? []);
        $totalDays = $components['timeline']['total_duration_days'] ?? 0;

        $prompt = "You are a senior legal strategist. Synthesize a comprehensive case strategy.

Case: {$caseContext}

Objectives: {$objectivesText}

Analysis Summary:
{$analysisText}

Key Metrics:
- {$argumentCount} legal arguments developed
- {$phaseCount} litigation phases planned
- {$totalDays} days estimated duration

Write a concise executive summary (300-400 words) that:
1. Summarizes the case position and key strengths
2. Outlines the recommended strategic approach
3. Highlights critical success factors
4. Addresses key risks and mitigation
5. Provides clear action items for the legal team

Use professional, confident language appropriate for client presentation.";

        $response = $this->openAI->complete($prompt, [
            'model' => 'gpt-4o',
            'max_tokens' => 1000,
            'temperature' => 0.4,
        ]);

        return $response['choices'][0]['message']['content'] ?? 'Executive summary pending finalization.';
    }

    /**
     * Calculate overall confidence score
     */
    protected function calculateConfidenceScore(array $components): float
    {
        $score = 0.0;

        // Factor 1: Case strength (30%)
        $caseStrength = $components['analysis']['case_strength_score'] ?? 0.5;
        $score += ($caseStrength * 0.30);

        // Factor 2: Win probability (25%)
        $winProb = $components['analysis']['win_probability'] ?? 0.5;
        $score += ($winProb * 0.25);

        // Factor 3: Inverse risk (20%)
        $riskScore = $components['risks']['risk_scores']['overall'] ?? 0.5;
        $score += ((1.0 - $riskScore) * 0.20);

        // Factor 4: Argument strength (15%)
        $strongestArg = $components['arguments']['strongest_argument'] ?? null;
        $argStrength = $strongestArg['strength_score'] ?? 0.5;
        $score += ($argStrength * 0.15);

        // Factor 5: Precedent quality (10%)
        $precedentCount = count($components['precedents'] ?? []);
        $precedentScore = min($precedentCount / 10, 1.0);
        $score += ($precedentScore * 0.10);

        return min($score, 1.0);
    }

    /**
     * Persist strategy to database
     */
    protected function persistStrategy(string $caseId, array $strategy): void
    {
        CaseStrategy::create([
            'id' => Str::ulid(),
            'case_id' => $caseId,
            'version' => $strategy['version'],
            'status' => 'active',
            'objectives' => $strategy['objectives'],
            'analysis' => $strategy['case_analysis'],
            'arguments' => $strategy['arguments'],
            'risks' => $strategy['risk_assessment'],
            'precedents' => $strategy['key_precedents'],
            'action_plan' => $strategy['action_plan'],
            'timeline' => $strategy['timeline'],
            'recommendations' => $strategy['strategic_recommendations'],
            'summary' => $strategy['executive_summary'],
            'confidence_score' => $strategy['confidence_score'],
            'metrics' => [
                'win_probability' => $strategy['case_analysis']['win_probability'],
                'risk_level' => $strategy['risk_assessment']['overall_risk_level'],
                'total_duration_days' => $strategy['action_plan']['total_duration_days'],
            ],
        ]);

        Log::info('StrategyBuilder - Strategy persisted', ['case_id' => $caseId]);
    }

    /**
     * Prepare case context for prompts
     */
    protected function prepareCaseContext(LegalCase $case): string
    {
        return sprintf(
            "Case: %s\nCourt: %s\nJurisdiction: %s\nParties: %s vs %s\nStatus: %s\nDescription: %s",
            $case->case_number ?? 'N/A',
            $case->court ?? 'N/A',
            $case->jurisdiction ?? 'N/A',
            $case->client_name ?? 'Client',
            $case->opponent_name ?? 'Opponent',
            $case->status ?? 'unknown',
            substr($case->description ?? '', 0, 500)
        );
    }

    /**
     * Get default SWOT analysis
     */
    protected function getDefaultSWOT(): array
    {
        return [
            'strengths' => ['Case analysis pending'],
            'weaknesses' => ['Case analysis pending'],
            'opportunities' => ['Case analysis pending'],
            'threats' => ['Case analysis pending'],
        ];
    }
}
