<?php

namespace App\Services\LegalReasoning;

use App\Exceptions\AnalysisException;
use App\Models\CourtDecision;
use App\Models\Law;
use App\Models\LegalCase;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RiskAssessor
{
    public function __construct(
        protected OpenAIService $openAI,
        protected OutcomePredictor $outcomePredictor,
        protected CitationAnalyzer $citationAnalyzer
    ) {}

    /**
     * Comprehensive risk assessment for a case
     */
    public function assessRisks(string $caseId): array
    {
        $correlationId = request()->header('X-Request-ID') ?? Str::uuid()->toString();
        $startTime = microtime(true);

        Log::withContext(['correlation_id' => $correlationId]);

        Log::info('RiskAssessor: assessRisks initiated', [
            'case_id' => $caseId,
            'user_id' => auth()->id(),
        ]);

        try {
            $case = LegalCase::with('documents')->findOrFail($caseId);

            // Legal risks
            $legalRisks = [
                'weak_precedents' => $this->identifyWeakPrecedents($case),
                'contradictory_authority' => $this->findContradictoryAuthority($case),
                'jurisdictional_issues' => $this->checkJurisdictionalRisks($case),
                'procedural_pitfalls' => $this->identifyProceduralRisks($case),
            ];

            // Evidentiary risks
            $evidentiaryRisks = [
                'missing_evidence' => $this->identifyEvidenceGaps($case),
                'weak_evidence' => $this->assessEvidenceStrength($case),
                'admissibility_concerns' => $this->checkAdmissibility($case),
            ];

            // Strategic risks
            $strategicRisks = [
                'opponent_strengths' => $this->analyzeOpponentStrengths($case),
                'settlement_leverage' => $this->assessSettlementLeverage($case),
                'cost_benefit' => $this->analyzeCostBenefit($case),
            ];

            // Calculate risk scores
            $legalScore = $this->calculateCategoryScore($legalRisks);
            $evidentiaryScore = $this->calculateCategoryScore($evidentiaryRisks);
            $strategicScore = $this->calculateCategoryScore($strategicRisks);

            // Overall risk score (weighted average)
            $overallScore = $this->calculateOverallRisk([
                'legal' => $legalScore,
                'evidentiary' => $evidentiaryScore,
                'strategic' => $strategicScore,
            ]);

            $riskLevel = $this->categorizeRisk($overallScore);

            // Generate mitigation strategies
            $allRisks = array_merge($legalRisks, $evidentiaryRisks, $strategicRisks);
            $mitigationStrategies = $this->generateMitigationStrategies($allRisks, $case);

            $result = [
                'case_id' => $caseId,
                'legal_risks' => $legalRisks,
                'evidentiary_risks' => $evidentiaryRisks,
                'strategic_risks' => $strategicRisks,
                'risk_scores' => [
                    'legal' => round($legalScore, 3),
                    'evidentiary' => round($evidentiaryScore, 3),
                    'strategic' => round($strategicScore, 3),
                    'overall' => round($overallScore, 3),
                ],
                'risk_level' => $riskLevel,
                'mitigation_strategies' => $mitigationStrategies,
                'risk_summary' => $this->generateRiskSummary($riskLevel, $overallScore),
            ];

            $duration = (microtime(true) - $startTime) * 1000;

            Log::info('RiskAssessor: assessRisks completed', [
                'case_id' => $caseId,
                'risk_level' => $riskLevel,
                'overall_score' => round($overallScore, 3),
                'mitigation_strategies_count' => count($mitigationStrategies),
                'duration_ms' => round($duration, 2),
            ]);

            return $result;

        } catch (AnalysisException $e) {
            Log::error('RiskAssessor: assessRisks failed with AnalysisException', [
                'case_id' => $caseId,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('RiskAssessor: Case not found', [
                'case_id' => $caseId,
                'error' => $e->getMessage(),
            ]);

            throw new AnalysisException(
                "Case not found: {$caseId}",
                AnalysisException::RESOURCE_NOT_FOUND,
                $e
            );

        } catch (\Exception $e) {
            Log::error('RiskAssessor: assessRisks failed with unexpected exception', [
                'case_id' => $caseId,
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new AnalysisException(
                'Risk assessment failed: '.$e->getMessage(),
                AnalysisException::ANALYSIS_FAILED,
                $e
            );
        }
    }

    /**
     * Identify weak or unfavorable precedents
     */
    protected function identifyWeakPrecedents(LegalCase $case): array
    {
        $keywords = $this->extractCaseKeywords($case);

        $precedents = CourtDecision::query()
            ->where(function ($query) use ($keywords) {
                foreach ($keywords as $keyword) {
                    $query->orWhere('title', 'ILIKE', "%{$keyword}%")
                        ->orWhere('summary', 'ILIKE', "%{$keyword}%");
                }
            })
            ->orderByDesc('decision_date')
            ->limit(20)
            ->get();

        $weakPrecedents = [];
        foreach ($precedents as $precedent) {
            $analysis = $this->citationAnalyzer->analyzeAuthority($precedent->id);

            // Consider precedent weak if low authority or potentially unfavorable
            if ($analysis['authority_score'] < 0.3) {
                $weakPrecedents[] = [
                    'decision_id' => $precedent->id,
                    'case_title' => $precedent->title,
                    'authority_score' => $analysis['authority_score'],
                    'concern' => 'Low authority score - may not be persuasive',
                    'severity' => 'medium',
                ];
            }
        }

        return array_slice($weakPrecedents, 0, 5);
    }

    /**
     * Find contradictory legal authority
     */
    protected function findContradictoryAuthority(LegalCase $case): array
    {
        $keywords = $this->extractCaseKeywords($case);

        // Search for laws with conflicting provisions
        $laws = Law::query()
            ->where(function ($query) use ($keywords) {
                foreach ($keywords as $keyword) {
                    $query->orWhere('text', 'ILIKE', "%{$keyword}%");
                }
            })
            ->where('status', 'active')
            ->limit(10)
            ->get();

        // Use LLM to identify contradictions
        if ($laws->isEmpty()) {
            return [];
        }

        $lawsText = $laws->map(fn ($law) => "{$law->title}: {$law->text}")->implode("\n\n");
        $caseContext = $this->prepareCaseContext($case);

        $prompt = "Analyze these laws for potential contradictions relevant to this case:

Case: {$caseContext}

Laws:
{$lawsText}

Identify any contradictions, ambiguities, or conflicting interpretations that could pose risks.
Return as JSON array (max 3):
[
  {
    \"law1\": \"First law title\",
    \"law2\": \"Second law title\",
    \"contradiction\": \"Description of conflict\",
    \"severity\": \"high|medium|low\"
  }
]";

        $response = $this->openAI->complete($prompt, [
            'model' => 'gpt-4o-mini',
            'max_tokens' => 800,
            'temperature' => 0.3,
        ]);

        $content = $response['choices'][0]['message']['content'] ?? '';
        if (preg_match('/\[[\s\S]*\]/', $content, $matches)) {
            return json_decode($matches[0], true) ?? [];
        }

        return [];
    }

    /**
     * Check for jurisdictional risks
     */
    protected function checkJurisdictionalRisks(LegalCase $case): array
    {
        $risks = [];

        // Check if jurisdiction is appropriate
        if (empty($case->jurisdiction)) {
            $risks[] = [
                'issue' => 'Missing jurisdiction specification',
                'severity' => 'high',
                'impact' => 'Case may be dismissed for lack of jurisdiction',
            ];
        }

        // Check if court has proper authority
        if (! empty($case->court)) {
            $courtLevel = $this->getCourtLevel($case->court);
            if ($courtLevel < 2) {
                $risks[] = [
                    'issue' => 'Lower court may lack jurisdiction for complex matters',
                    'severity' => 'medium',
                    'impact' => 'Consider filing in higher court',
                ];
            }
        }

        // Check for multi-jurisdictional complications
        $description = strtolower($case->description ?? '');
        if (str_contains($description, 'cross-border') || str_contains($description, 'international')) {
            $risks[] = [
                'issue' => 'Multi-jurisdictional complications detected',
                'severity' => 'high',
                'impact' => 'May require coordination across jurisdictions',
            ];
        }

        return $risks;
    }

    /**
     * Identify procedural risks
     */
    protected function identifyProceduralRisks(LegalCase $case): array
    {
        $risks = [];

        // Check filing date and statutes of limitations
        if ($case->filing_date) {
            $monthsSinceFiling = now()->diffInMonths($case->filing_date);
            if ($monthsSinceFiling > 24) {
                $risks[] = [
                    'issue' => 'Extended case duration may trigger procedural challenges',
                    'severity' => 'medium',
                    'impact' => 'Monitor for laches or procedural time limits',
                ];
            }
        }

        // Check document completeness
        $docCount = $case->documents->count();
        if ($docCount < 3) {
            $risks[] = [
                'issue' => 'Limited documentation',
                'severity' => 'medium',
                'impact' => 'May face challenges establishing facts',
            ];
        }

        // Check status
        if ($case->status === 'pending') {
            $risks[] = [
                'issue' => 'Case status indicates unresolved procedural matters',
                'severity' => 'low',
                'impact' => 'Ensure all procedural requirements are met',
            ];
        }

        return $risks;
    }

    /**
     * Identify evidence gaps
     */
    protected function identifyEvidenceGaps(LegalCase $case): array
    {
        $caseContext = $this->prepareCaseContext($case);
        $docCount = $case->documents->count();

        $prompt = "Analyze this case for potential evidence gaps:

{$caseContext}

Documents available: {$docCount}

Identify 3-5 critical evidence gaps that could weaken the case. Return as JSON:
[
  {
    \"gap\": \"Description of missing evidence\",
    \"importance\": \"critical|important|moderate\",
    \"impact\": \"Potential impact on case\"
  }
]";

        $response = $this->openAI->complete($prompt, [
            'model' => 'gpt-4o-mini',
            'max_tokens' => 800,
            'temperature' => 0.3,
        ]);

        $content = $response['choices'][0]['message']['content'] ?? '';
        if (preg_match('/\[[\s\S]*\]/', $content, $matches)) {
            return json_decode($matches[0], true) ?? [];
        }

        return [];
    }

    /**
     * Assess evidence strength
     */
    protected function assessEvidenceStrength(LegalCase $case): array
    {
        $docCount = $case->documents->count();

        $strength = match (true) {
            $docCount >= 20 => ['score' => 0.9, 'assessment' => 'Strong', 'severity' => 'low'],
            $docCount >= 10 => ['score' => 0.7, 'assessment' => 'Moderate', 'severity' => 'medium'],
            $docCount >= 5 => ['score' => 0.5, 'assessment' => 'Adequate', 'severity' => 'medium'],
            default => ['score' => 0.3, 'assessment' => 'Weak', 'severity' => 'high'],
        };

        return [
            'document_count' => $docCount,
            'strength_score' => $strength['score'],
            'assessment' => $strength['assessment'],
            'severity' => $strength['severity'],
            'concern' => $strength['severity'] === 'high' ? 'Insufficient evidence to support claims' : null,
        ];
    }

    /**
     * Check admissibility concerns
     */
    protected function checkAdmissibility(LegalCase $case): array
    {
        // Basic admissibility checks
        $concerns = [];

        $docCount = $case->documents->count();
        if ($docCount > 0 && $docCount < 3) {
            $concerns[] = [
                'issue' => 'Limited documentation may face admissibility challenges',
                'severity' => 'medium',
                'recommendation' => 'Ensure all documents are properly authenticated',
            ];
        }

        // Check for potential hearsay issues (simplified)
        $description = strtolower($case->description ?? '');
        if (str_contains($description, 'testimony') || str_contains($description, 'statement')) {
            $concerns[] = [
                'issue' => 'Potential hearsay concerns with testimonial evidence',
                'severity' => 'medium',
                'recommendation' => 'Prepare hearsay exceptions or direct testimony',
            ];
        }

        return $concerns;
    }

    /**
     * Analyze opponent strengths
     */
    protected function analyzeOpponentStrengths(LegalCase $case): array
    {
        $caseContext = $this->prepareCaseContext($case);

        $prompt = "You are representing the opposing party. Analyze their potential strengths:

{$caseContext}

Identify 3-5 key strengths the opponent might leverage. Return as JSON:
[
  {
    \"strength\": \"Description of opponent's advantage\",
    \"severity\": \"high|medium|low\",
    \"counter_strategy\": \"Brief suggestion to address this strength\"
  }
]";

        $response = $this->openAI->complete($prompt, [
            'model' => 'gpt-4o-mini',
            'max_tokens' => 1000,
            'temperature' => 0.4,
        ]);

        $content = $response['choices'][0]['message']['content'] ?? '';
        if (preg_match('/\[[\s\S]*\]/', $content, $matches)) {
            return json_decode($matches[0], true) ?? [];
        }

        return [];
    }

    /**
     * Assess settlement leverage
     */
    protected function assessSettlementLeverage(LegalCase $case): array
    {
        // Use outcome predictor to estimate win probability
        $prediction = $this->outcomePredictor->predictOutcome($case->id);
        $winProbability = $prediction['probability_distribution']['win'] ?? 0.5;

        $docStrength = $case->documents->count() >= 10 ? 0.3 : 0.1;
        $leverage = ($winProbability * 0.7) + $docStrength;

        return [
            'leverage_score' => round($leverage, 3),
            'win_probability' => round($winProbability, 3),
            'assessment' => match (true) {
                $leverage >= 0.7 => 'Strong - Favorable settlement position',
                $leverage >= 0.5 => 'Moderate - Balanced negotiating position',
                default => 'Weak - Consider settlement if terms favorable',
            },
            'recommendation' => $leverage < 0.5
                ? 'Explore settlement options to mitigate risk'
                : 'Strong position to negotiate favorable terms',
        ];
    }

    /**
     * Analyze cost-benefit
     */
    protected function analyzeCostBenefit(LegalCase $case): array
    {
        // Estimate litigation duration
        $monthsSinceFiling = $case->filing_date ? now()->diffInMonths($case->filing_date) : 0;
        $estimatedMonthsRemaining = max(12 - $monthsSinceFiling, 6);

        // Simple cost model
        $estimatedCost = $estimatedMonthsRemaining * 5000; // $5k/month estimate

        // Potential outcome value (placeholder - would need case-specific data)
        $potentialValue = 100000; // Placeholder

        $costBenefitRatio = $potentialValue / max($estimatedCost, 1);

        return [
            'estimated_months_remaining' => $estimatedMonthsRemaining,
            'estimated_cost' => $estimatedCost,
            'potential_value' => $potentialValue,
            'cost_benefit_ratio' => round($costBenefitRatio, 2),
            'assessment' => match (true) {
                $costBenefitRatio >= 5 => 'Favorable - Strong economic case for litigation',
                $costBenefitRatio >= 2 => 'Moderate - Acceptable cost-benefit profile',
                default => 'Unfavorable - Costs may exceed benefits',
            },
            'risk' => $costBenefitRatio < 2 ? 'high' : 'low',
        ];
    }

    /**
     * Calculate category risk score
     */
    protected function calculateCategoryScore(array $categoryRisks): float
    {
        if (empty($categoryRisks)) {
            return 0.0;
        }

        $totalSeverity = 0;
        $count = 0;

        foreach ($categoryRisks as $riskArray) {
            if (is_array($riskArray)) {
                foreach ($riskArray as $risk) {
                    if (is_array($risk) && isset($risk['severity'])) {
                        $severityScore = match ($risk['severity']) {
                            'critical', 'high' => 0.9,
                            'medium', 'important' => 0.6,
                            'low', 'moderate' => 0.3,
                            default => 0.5,
                        };
                        $totalSeverity += $severityScore;
                        $count++;
                    }
                }
            }
        }

        return $count > 0 ? $totalSeverity / $count : 0.0;
    }

    /**
     * Calculate overall risk score
     */
    protected function calculateOverallRisk(array $categoryScores): float
    {
        // Weighted average: Legal 40%, Evidentiary 35%, Strategic 25%
        $legal = $categoryScores['legal'] ?? 0;
        $evidentiary = $categoryScores['evidentiary'] ?? 0;
        $strategic = $categoryScores['strategic'] ?? 0;

        return ($legal * 0.40) + ($evidentiary * 0.35) + ($strategic * 0.25);
    }

    /**
     * Categorize risk level
     */
    protected function categorizeRisk(float $score): string
    {
        return match (true) {
            $score >= 0.7 => 'HIGH',
            $score >= 0.4 => 'MEDIUM',
            default => 'LOW',
        };
    }

    /**
     * Generate mitigation strategies
     */
    protected function generateMitigationStrategies(array $allRisks, LegalCase $case): array
    {
        $strategies = [];

        // Collect all high-severity risks
        $highRisks = [];
        foreach ($allRisks as $category => $risks) {
            if (is_array($risks)) {
                foreach ($risks as $risk) {
                    if (is_array($risk) && ($risk['severity'] ?? '') === 'high') {
                        $highRisks[] = $risk;
                    }
                }
            }
        }

        // Generate strategies for top 5 high-severity risks
        foreach (array_slice($highRisks, 0, 5) as $risk) {
            $riskText = $risk['issue'] ?? $risk['gap'] ?? $risk['concern'] ?? 'Unknown risk';

            $prompt = "Risk: {$riskText}

Provide a brief, actionable mitigation strategy (1-2 sentences).";

            $response = $this->openAI->complete($prompt, [
                'model' => 'gpt-4o-mini',
                'max_tokens' => 150,
                'temperature' => 0.3,
            ]);

            $strategies[] = [
                'risk' => $riskText,
                'strategy' => $response['choices'][0]['message']['content'] ?? 'Review with legal team.',
            ];
        }

        return $strategies;
    }

    /**
     * Generate risk summary
     */
    protected function generateRiskSummary(string $riskLevel, float $score): string
    {
        $scorePercent = round($score * 100);

        return match ($riskLevel) {
            'HIGH' => "High risk case ({$scorePercent}%). Significant challenges identified. Recommend thorough risk mitigation and consider settlement.",
            'MEDIUM' => "Moderate risk case ({$scorePercent}%). Manageable challenges with proper preparation. Proceed with caution.",
            'LOW' => "Low risk case ({$scorePercent}%). Favorable position with limited challenges. Proceed with confidence.",
            default => "Risk assessment complete ({$scorePercent}%).",
        };
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
     * Extract keywords from case
     */
    protected function extractCaseKeywords(LegalCase $case): array
    {
        $text = ($case->title ?? '').' '.($case->description ?? '');
        $stopWords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by'];

        $words = preg_split('/\s+/', strtolower($text));
        $keywords = array_filter($words, function ($word) use ($stopWords) {
            return strlen($word) > 3 && ! in_array($word, $stopWords);
        });

        return array_unique(array_values($keywords));
    }

    /**
     * Get court hierarchy level
     */
    protected function getCourtLevel(string $court): int
    {
        $court = strtolower($court);

        if (str_contains($court, 'supreme') || str_contains($court, 'constitutional')) {
            return 5;
        } elseif (str_contains($court, 'appeal') || str_contains($court, 'appellate')) {
            return 4;
        } elseif (str_contains($court, 'high') || str_contains($court, 'regional')) {
            return 3;
        } elseif (str_contains($court, 'district') || str_contains($court, 'county')) {
            return 2;
        }

        return 1;
    }
}
