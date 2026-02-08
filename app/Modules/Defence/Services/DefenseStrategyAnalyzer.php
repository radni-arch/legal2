<?php

namespace App\Modules\Defence\Services;

use App\Models\LegalCase;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Log;

/**
 * DefenseStrategyAnalyzer
 *
 * Analyzes defense strategies and identifies opportunities
 * for improving the accused's legal position.
 */
class DefenseStrategyAnalyzer
{
    public function __construct(
        protected OpenAIService $openAI
    ) {}

    /**
     * Comprehensive defense strategy analysis
     */
    public function analyze(LegalCase $case): array
    {
        Log::info('DefenseStrategyAnalyzer - Starting analysis', ['case_id' => $case->id]);

        return [
            'defense_strengths' => $this->assessDefenseStrength($case),
            'prosecution_weaknesses' => $this->findProsecutionWeaknesses($case),
            'mitigating_factors' => $this->identifyMitigatingFactors($case),
            'strategic_options' => $this->identifyStrategicOptions($case),
            'recommended_approach' => $this->recommendApproach($case),
        ];
    }

    /**
     * Find weaknesses in the prosecution's case
     */
    public function findProsecutionWeaknesses(LegalCase $case): array
    {
        $prompt = <<<PROMPT
As a defense attorney, analyze the prosecution's case and identify ALL weaknesses:

Case: {$case->title}
Description: {$case->description}

Identify:
1. Evidentiary weaknesses (missing evidence, chain of custody issues, etc.)
2. Procedural errors (violations of rights, improper search/seizure, etc.)
3. Witness credibility issues
4. Burden of proof challenges
5. Constitutional violations
6. Statute of limitations issues
7. Factual inconsistencies

For each weakness, provide:
- Description of the weakness
- Severity (0-100, where 100 = case-destroying)
- Exploitability (how easily can defense use this)
- Potential impact on case outcome
- Suggested exploitation strategy

Respond in JSON format with an array of weaknesses.
PROMPT;

        $response = $this->openAI->chat([
            ['role' => 'system', 'content' => 'You are an expert defense attorney skilled at finding weaknesses in prosecution cases.'],
            ['role' => 'user', 'content' => $prompt],
        ], 'gpt-4o', [
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.4,
        ]);

        $result = json_decode($response['choices'][0]['message']['content'], true);

        return $result['weaknesses'] ?? [];
    }

    /**
     * Assess defense strengths
     */
    public function assessDefenseStrength(LegalCase $case): array
    {
        $prompt = <<<PROMPT
Assess the defense's strengths in this case:

Case: {$case->title}
Description: {$case->description}

Identify:
1. Strong alibi or exculpatory evidence
2. Witness testimony supporting defense
3. Expert testimony opportunities
4. Constitutional defenses
5. Affirmative defenses available
6. Character evidence in favor
7. Procedural advantages

For each strength, provide:
- Description
- Strength score (0-100)
- How it can be leveraged
- Supporting evidence needed

Respond in JSON format.
PROMPT;

        $response = $this->openAI->chat([
            ['role' => 'system', 'content' => 'You are an expert defense attorney evaluating defense strengths.'],
            ['role' => 'user', 'content' => $prompt],
        ], 'gpt-4o-mini', [
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.3,
        ]);

        $result = json_decode($response['choices'][0]['message']['content'], true);

        return [
            'overall_strength' => $result['overall_strength'] ?? 0,
            'strong_points' => $result['strong_points'] ?? [],
            'available_defenses' => $result['available_defenses'] ?? [],
            'evidence_support' => $result['evidence_support'] ?? [],
        ];
    }

    /**
     * Identify mitigating factors
     */
    public function identifyMitigatingFactors(LegalCase $case): array
    {
        $prompt = <<<PROMPT
Identify ALL mitigating factors that could reduce culpability or sentencing:

Case: {$case->title}
Description: {$case->description}

Consider:
1. Defendant's background (first-time offender, good character, etc.)
2. Circumstances of the offense (provocation, diminished capacity, etc.)
3. Post-offense conduct (cooperation, remorse, restitution, etc.)
4. Personal circumstances (family obligations, employment, health, etc.)
5. Comparative culpability (role in offense vs. co-defendants)
6. Social factors (abuse history, mental health, addiction, etc.)

For each factor, provide:
- Description
- Impact level (how much it could reduce penalties)
- Evidence/documentation needed
- How to present it effectively

Respond in JSON format with an array of mitigating factors.
PROMPT;

        $response = $this->openAI->chat([
            ['role' => 'system', 'content' => 'You are an expert defense attorney skilled at identifying mitigating factors.'],
            ['role' => 'user', 'content' => $prompt],
        ], 'gpt-4o-mini', [
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.3,
        ]);

        $result = json_decode($response['choices'][0]['message']['content'], true);

        return $result['mitigating_factors'] ?? [];
    }

    /**
     * Identify strategic options
     */
    protected function identifyStrategicOptions(LegalCase $case): array
    {
        return [
            [
                'strategy' => 'Aggressive Motion Practice',
                'description' => 'File motions to dismiss, suppress evidence, or exclude testimony',
                'suitability' => 'High if prosecution weaknesses are significant',
                'risk' => 'Medium - may antagonize prosecution',
            ],
            [
                'strategy' => 'Plea Negotiation',
                'description' => 'Negotiate reduced charges or sentencing in exchange for guilty plea',
                'suitability' => 'Medium to High depending on evidence strength',
                'risk' => 'Low - preserves certainty, but accepts conviction',
            ],
            [
                'strategy' => 'Trial by Jury',
                'description' => 'Pursue full trial with jury',
                'suitability' => 'High if strong defense case or sympathetic facts',
                'risk' => 'High - uncertain outcome, possible maximum penalty',
            ],
            [
                'strategy' => 'Alternative Dispute Resolution',
                'description' => 'Mediation, diversion programs, or restorative justice',
                'suitability' => 'Medium if available and prosecution agrees',
                'risk' => 'Low - may avoid conviction entirely',
            ],
        ];
    }

    /**
     * Recommend overall approach
     */
    protected function recommendApproach(LegalCase $case): array
    {
        // This would integrate all analyses to recommend the best approach
        return [
            'primary_strategy' => 'To be determined based on full analysis',
            'backup_strategy' => 'Negotiated plea if primary strategy fails',
            'key_priorities' => [
                'Challenge weakest prosecution evidence',
                'Present strongest mitigating factors',
                'Preserve appellate issues',
            ],
        ];
    }
}
