<?php

namespace App\Modules\Defence\Services;

use App\Models\LegalCase;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Log;

/**
 * DefenseRecommendationService
 *
 * Generates specific, actionable recommendations for improving
 * the accused's legal position.
 */
class DefenseRecommendationService
{
    public function __construct(
        protected OpenAIService $openAI
    ) {}

    /**
     * Generate comprehensive defense recommendations
     */
    public function generateRecommendations(LegalCase $case, array $context = []): array
    {
        Log::info('DefenseRecommendationService - Generating recommendations', [
            'case_id' => $case->id,
        ]);

        $recommendations = [];

        // Immediate actions
        $recommendations = array_merge($recommendations, $this->generateImmediateActions($case, $context));

        // Evidence gathering recommendations
        $recommendations = array_merge($recommendations, $this->generateEvidenceRecommendations($case, $context));

        // Witness recommendations
        $recommendations = array_merge($recommendations, $this->generateWitnessRecommendations($case, $context));

        // Legal motion recommendations
        $recommendations = array_merge($recommendations, $this->generateMotionRecommendations($case, $context));

        // Negotiation recommendations
        $recommendations = array_merge($recommendations, $this->generateNegotiationRecommendations($case, $context));

        // Expert recommendations
        $recommendations = array_merge($recommendations, $this->generateExpertRecommendations($case, $context));

        // Prioritize and score recommendations
        return $this->prioritizeRecommendations($recommendations);
    }

    /**
     * Generate immediate action recommendations
     */
    protected function generateImmediateActions(LegalCase $case, array $context): array
    {
        return [
            [
                'category' => 'immediate',
                'action' => 'Preserve all potential evidence',
                'priority' => 'urgent',
                'impact' => 'High',
                'timeline' => 'immediate',
                'resources' => ['Investigator', 'Document preservation'],
                'rationale' => 'Evidence can be lost or destroyed if not secured immediately',
            ],
            [
                'category' => 'immediate',
                'action' => 'Interview the accused thoroughly',
                'priority' => 'urgent',
                'impact' => 'High',
                'timeline' => 'immediate',
                'resources' => ['Defense attorney time'],
                'rationale' => 'Understanding the accused\'s full account is critical for strategy',
            ],
            [
                'category' => 'immediate',
                'action' => 'Review all prosecution discovery',
                'priority' => 'urgent',
                'impact' => 'High',
                'timeline' => 'immediate',
                'resources' => ['Legal team'],
                'rationale' => 'Identify weaknesses and plan defense strategy',
            ],
        ];
    }

    /**
     * Generate evidence-related recommendations
     */
    protected function generateEvidenceRecommendations(LegalCase $case, array $context): array
    {
        $recommendations = [];

        // Check for prosecution weaknesses to exploit
        $prosecutionWeaknesses = $context['prosecution_weaknesses'] ?? [];

        foreach ($prosecutionWeaknesses as $weakness) {
            if (($weakness['severity'] ?? 0) >= 70) {
                $recommendations[] = [
                    'category' => 'evidence',
                    'action' => 'Challenge: '.($weakness['description'] ?? 'Prosecution weakness'),
                    'priority' => 'high',
                    'impact' => 'High',
                    'timeline' => 'short_term',
                    'resources' => ['Legal research', 'Motion practice'],
                    'rationale' => $weakness['exploitation_strategy'] ?? 'Significant weakness in prosecution case',
                ];
            }
        }

        return $recommendations;
    }

    /**
     * Generate witness-related recommendations
     */
    protected function generateWitnessRecommendations(LegalCase $case, array $context): array
    {
        return [
            [
                'category' => 'witnesses',
                'action' => 'Identify and interview alibi witnesses',
                'priority' => 'high',
                'impact' => 'High',
                'timeline' => 'short_term',
                'resources' => ['Investigator', 'Attorney time'],
                'rationale' => 'Alibi evidence can completely exonerate the accused',
            ],
            [
                'category' => 'witnesses',
                'action' => 'Prepare character witnesses',
                'priority' => 'medium',
                'impact' => 'Medium',
                'timeline' => 'medium_term',
                'resources' => ['Witness preparation'],
                'rationale' => 'Character evidence supports mitigation and credibility',
            ],
        ];
    }

    /**
     * Generate legal motion recommendations
     */
    protected function generateMotionRecommendations(LegalCase $case, array $context): array
    {
        return [
            [
                'category' => 'motions',
                'action' => 'File motion to suppress illegally obtained evidence',
                'priority' => 'high',
                'impact' => 'Very High',
                'timeline' => 'short_term',
                'resources' => ['Legal research', 'Brief writing'],
                'rationale' => 'Constitutional violations can result in evidence exclusion',
            ],
            [
                'category' => 'motions',
                'action' => 'File motion to dismiss for lack of probable cause',
                'priority' => 'medium',
                'impact' => 'Very High',
                'timeline' => 'short_term',
                'resources' => ['Legal research', 'Brief writing'],
                'rationale' => 'If successful, case is dismissed entirely',
            ],
        ];
    }

    /**
     * Generate negotiation recommendations
     */
    protected function generateNegotiationRecommendations(LegalCase $case, array $context): array
    {
        $defenseStrengths = $context['defense_strengths'] ?? [];
        $mitigatingFactors = $context['mitigating_factors'] ?? [];

        $leverage = [
            'prosecution_weaknesses' => count($context['prosecution_weaknesses'] ?? []),
            'defense_strengths' => count($defenseStrengths['strong_points'] ?? []),
            'mitigating_factors' => count($mitigatingFactors),
        ];

        $hasLeverage = array_sum($leverage) >= 5;

        return [
            [
                'category' => 'negotiation',
                'action' => 'Initiate plea discussions with prosecution',
                'priority' => $hasLeverage ? 'high' : 'medium',
                'impact' => 'High',
                'timeline' => 'short_term',
                'resources' => ['Attorney negotiation time'],
                'rationale' => $hasLeverage
                    ? 'Strong leverage for favorable plea terms'
                    : 'Explore options for reduced charges or sentencing',
            ],
        ];
    }

    /**
     * Generate expert recommendations
     */
    protected function generateExpertRecommendations(LegalCase $case, array $context): array
    {
        return [
            [
                'category' => 'experts',
                'action' => 'Retain forensic expert to challenge prosecution evidence',
                'priority' => 'medium',
                'impact' => 'High',
                'timeline' => 'medium_term',
                'resources' => ['Expert fees', 'Expert time'],
                'rationale' => 'Expert testimony can undermine prosecution\'s scientific evidence',
            ],
        ];
    }

    /**
     * Prioritize recommendations by impact and urgency
     */
    protected function prioritizeRecommendations(array $recommendations): array
    {
        // Score each recommendation
        foreach ($recommendations as &$rec) {
            $score = 0;

            // Priority scoring
            $score += match ($rec['priority']) {
                'urgent' => 100,
                'high' => 75,
                'medium' => 50,
                'low' => 25,
                default => 0,
            };

            // Impact scoring
            $score += match ($rec['impact']) {
                'Very High' => 50,
                'High' => 35,
                'Medium' => 20,
                'Low' => 10,
                default => 0,
            };

            $rec['score'] = $score;
        }

        // Sort by score descending
        usort($recommendations, fn ($a, $b) => $b['score'] <=> $a['score']);

        return $recommendations;
    }
}
