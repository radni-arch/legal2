<?php

namespace App\Modules\Evidence\Services;

use App\Models\LegalCase;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Log;

/**
 * RecontextualizationService
 *
 * Generates defense recontextualization showing full context of evidence
 * to counter prosecutor's selective presentation.
 *
 * ETHICAL FRAMEWORK:
 * ✅ Based on ACTUAL evidence and omitted context
 * ✅ Shows full factual context prosecutor omitted
 * ✅ Provides legitimate alternative interpretations
 * ✅ Identifies supporting evidence for defense narrative
 * ✅ Calculates credibility based on objective factors
 * ❌ Does NOT fabricate context
 * ❌ Does NOT distort clear facts
 * ❌ Does NOT create false evidence
 * ❌ Does NOT mislead about what evidence shows
 *
 * Purpose:
 * When prosecution selectively presents evidence (e.g., showing only incriminating
 * excerpt of SMS conversation), this service restores full context and generates
 * legitimate defense interpretation based on complete evidence.
 *
 * Credibility Scoring (0-100):
 * - 50: Base score (neutral starting point)
 * - +20: Prosecutor omitted significant context
 * - +15: Supporting evidence for defense recontextualization
 * - +15: Objective support (metadata, timestamps, documents)
 * - Maximum: 100 (highly credible defense narrative)
 *
 * Legal Basis (Croatian Law):
 * - ZKP Članak 9 - Objektivnost (prosecution must present all relevant context)
 * - ZKP Članak 331 - Slobodna ocjena dokaza (court evaluates evidence in full context)
 * - Ustav RH Članak 29 - Pravo na pravično suđenje (fair trial requires complete picture)
 */
class RecontextualizationService
{
    public function __construct(
        protected OpenAIService $openAI
    ) {}

    /**
     * Generate defense recontextualization based on context analysis
     *
     * @param  array  $evidence  Evidence item
     * @param  array  $contextAnalysis  Context analysis from ContextAnalyzer
     * @param  LegalCase  $case  The legal case
     * @return array Recontextualization result
     */
    public function recontextualize(array $evidence, array $contextAnalysis, LegalCase $case): array
    {
        Log::info('RecontextualizationService: Starting recontextualization', [
            'evidence_id' => $evidence['id'] ?? 'unknown',
            'case_id' => $case->id,
        ]);

        // Check if selective presentation was detected
        $selectivePresentation = $contextAnalysis['selective_presentation'] ?? [];
        $detected = $selectivePresentation['detected'] ?? false;

        if (! $detected) {
            Log::info('RecontextualizationService: No selective presentation detected, no recontextualization needed', [
                'evidence_id' => $evidence['id'] ?? 'unknown',
            ]);

            return [
                'recontextualization_needed' => false,
                'reason' => 'No selective presentation detected by prosecution',
                'evidence_id' => $evidence['id'] ?? 'unknown',
            ];
        }

        // Extract prosecution narrative
        $prosecutionNarrative = $this->extractProsecutionNarrative($evidence, $contextAnalysis);

        // Generate defense recontextualization
        $defenseRecontextualization = $this->generateDefenseRecontextualization(
            $evidence,
            $contextAnalysis,
            $case
        );

        // Highlight key differences
        $keyDifferences = $this->highlightKeyDifferences($contextAnalysis, $prosecutionNarrative, $defenseRecontextualization);

        // Identify supporting evidence
        $supportingEvidence = $this->identifySupportingEvidence($contextAnalysis, $case);

        // Calculate credibility score
        $credibilityScore = $this->calculateCredibilityScore($contextAnalysis, $supportingEvidence);

        $result = [
            'recontextualization_needed' => true,
            'evidence_id' => $evidence['id'] ?? 'unknown',
            'evidence_type' => $evidence['type'] ?? 'unknown',
            'selective_presentation_type' => $selectivePresentation['type'] ?? 'unknown',
            'selective_presentation_severity' => $selectivePresentation['severity'] ?? 0,
            'prosecution_narrative' => $prosecutionNarrative,
            'defense_recontextualization' => $defenseRecontextualization,
            'key_differences' => $keyDifferences,
            'supporting_evidence' => $supportingEvidence,
            'credibility_score' => $credibilityScore,
            'credibility_level' => $this->getCredibilityLevel($credibilityScore),
            'recommended_use' => $this->getRecommendedUse($credibilityScore),
            'generated_at' => now()->toIso8601String(),
        ];

        Log::info('RecontextualizationService: Recontextualization complete', [
            'evidence_id' => $evidence['id'] ?? 'unknown',
            'credibility_score' => $credibilityScore,
            'credibility_level' => $result['credibility_level'],
        ]);

        return $result;
    }

    /**
     * Extract prosecution's narrative from evidence and context analysis
     *
     * @param  array  $evidence  Evidence item
     * @param  array  $contextAnalysis  Context analysis
     * @return array Prosecution narrative
     */
    protected function extractProsecutionNarrative(array $evidence, array $contextAnalysis): array
    {
        $selectivePresentation = $contextAnalysis['selective_presentation'] ?? [];
        $prosecutionPresentation = $contextAnalysis['prosecution_presentation'] ?? [];

        return [
            'summary' => $prosecutionPresentation['description'] ?? $evidence['prosecution_description'] ?? $evidence['description'] ?? '',
            'what_they_showed' => $selectivePresentation['what_prosecutor_showed'] ?? '',
            'their_interpretation' => $prosecutionPresentation['interpretation'] ?? '',
            'emphasis' => $prosecutionPresentation['emphasis'] ?? '',
            'selective_presentation_type' => $selectivePresentation['type'] ?? 'unknown',
        ];
    }

    /**
     * Generate defense recontextualization using AI
     *
     * This method generates a LEGITIMATE defense narrative based on:
     * - Full evidence context (not just prosecutor's excerpt)
     * - Omitted context that changes interpretation
     * - Alternative interpretation based on actual facts
     *
     * ETHICAL CONSTRAINTS:
     * - Must be based on actual evidence
     * - Cannot fabricate context
     * - Cannot distort clear facts
     * - Must show genuine alternative interpretation
     *
     * @param  array  $evidence  Evidence item
     * @param  array  $contextAnalysis  Context analysis
     * @param  LegalCase  $case  The legal case
     * @return array Defense recontextualization
     */
    protected function generateDefenseRecontextualization(
        array $evidence,
        array $contextAnalysis,
        LegalCase $case
    ): array {
        Log::info('RecontextualizationService: Generating defense recontextualization', [
            'evidence_id' => $evidence['id'] ?? 'unknown',
        ]);

        $selectivePresentation = $contextAnalysis['selective_presentation'] ?? [];
        $omittedContext = $contextAnalysis['omitted_context'] ?? [];
        $fullContext = $contextAnalysis['full_context'] ?? [];

        $evidenceDesc = $evidence['description'] ?? '';
        $whatProsecutorShowed = $selectivePresentation['what_prosecutor_showed'] ?? '';
        $whatProsecutorOmitted = $selectivePresentation['what_prosecutor_omitted'] ?? '';
        $whyOmissionMatters = $selectivePresentation['why_omission_matters'] ?? '';
        $fullContent = $fullContext['full_content'] ?? '';

        // Build comprehensive prompt for AI
        $prompt = $this->buildRecontextualizationPrompt(
            $evidenceDesc,
            $whatProsecutorShowed,
            $whatProsecutorOmitted,
            $whyOmissionMatters,
            $fullContent,
            $omittedContext
        );

        try {
            $response = $this->openAI->chat([
                ['role' => 'system', 'content' => 'You are a Croatian defense attorney providing LEGITIMATE recontextualization based on actual evidence. You restore full context that prosecution omitted. You NEVER fabricate facts. You only provide alternative interpretations based on evidence that actually exists in the case file.'],
                ['role' => 'user', 'content' => $prompt],
            ], 'gpt-4o', [
                'temperature' => 0.4,
                'response_format' => ['type' => 'json_object'],
                'max_tokens' => 1000,
            ]);

            $recontextualization = json_decode($response['choices'][0]['message']['content'], true);

            if (! is_array($recontextualization)) {
                Log::warning('RecontextualizationService: Invalid JSON response from OpenAI');

                return $this->getTemplateRecontextualization($whatProsecutorShowed, $whatProsecutorOmitted, $whyOmissionMatters);
            }

            Log::info('RecontextualizationService: Defense recontextualization generated', [
                'evidence_id' => $evidence['id'] ?? 'unknown',
                'narrative_length' => strlen($recontextualization['narrative'] ?? ''),
            ]);

            return [
                'narrative' => $recontextualization['narrative'] ?? '',
                'key_points' => $recontextualization['key_points'] ?? [],
                'alternative_interpretation' => $recontextualization['alternative_interpretation'] ?? '',
                'supporting_facts' => $recontextualization['supporting_facts'] ?? [],
                'croatian_legal_basis' => $recontextualization['croatian_legal_basis'] ?? 'ZKP Članak 9 - Objektivnost',
                'fair_trial_argument' => $recontextualization['fair_trial_argument'] ?? '',
            ];

        } catch (\Exception $e) {
            Log::error('RecontextualizationService: OpenAI API error', [
                'evidence_id' => $evidence['id'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            // Return template-based recontextualization as fallback
            return $this->getTemplateRecontextualization($whatProsecutorShowed, $whatProsecutorOmitted, $whyOmissionMatters);
        }
    }

    /**
     * Build AI prompt for recontextualization generation
     *
     * @param  string  $evidenceDesc  Evidence description
     * @param  string  $whatShowed  What prosecutor showed
     * @param  string  $whatOmitted  What prosecutor omitted
     * @param  string  $whyMatters  Why omission matters
     * @param  string  $fullContent  Full evidence content
     * @param  array  $omittedContext  Omitted context details
     * @return string AI prompt
     */
    protected function buildRecontextualizationPrompt(
        string $evidenceDesc,
        string $whatShowed,
        string $whatOmitted,
        string $whyMatters,
        string $fullContent,
        array $omittedContext
    ): string {
        $omissionsDetails = '';
        if (! empty($omittedContext['omissions']) && is_array($omittedContext['omissions'])) {
            // Limit to first 5 omissions to prevent excessively long prompts
            $limitedOmissions = array_slice($omittedContext['omissions'], 0, 5);
            foreach ($limitedOmissions as $i => $omission) {
                $num = $i + 1;
                $omissionsDetails .= "Omission #{$num}:\n";
                $omissionsDetails .= '- Omitted Fact: '.($omission['omitted_fact'] ?? '')."\n";
                $omissionsDetails .= '- Where in Evidence: '.($omission['where_in_full_evidence'] ?? '')."\n";
                $omissionsDetails .= '- How It Changes Interpretation: '.($omission['how_it_changes_interpretation'] ?? '')."\n\n";
            }
            if (count($omittedContext['omissions']) > 5) {
                $omissionsDetails .= '(Showing top 5 of '.count($omittedContext['omissions'])." omissions)\n";
            }
        }

        return <<<PROMPT
Generate a LEGITIMATE defense recontextualization of this evidence for Croatian criminal defense.

Evidence Description:
{$evidenceDesc}

Prosecution's Selective Presentation:
{$whatShowed}

What Prosecution Omitted:
{$whatOmitted}

Why Omission Matters:
{$whyMatters}

Full Available Evidence:
{$fullContent}

Detailed Omissions:
{$omissionsDetails}

Generate defense recontextualization that:

1. **Shows FULL Context** - Present complete picture, not just prosecutor's selective excerpt
2. **Explains Omitted Context** - Clearly state what prosecutor left out and why it matters
3. **Provides Alternative Interpretation** - Offer legitimate defense interpretation based on full evidence
4. **Based on ACTUAL Evidence** - Every fact must come from provided evidence (NO FABRICATION)
5. **Fair Trial Argument** - Explain how selective presentation violates defendant's rights

CRITICAL ETHICAL CONSTRAINTS:
- Must be based on actual evidence provided above
- Cannot fabricate context that doesn't exist in evidence
- Cannot distort clear, unambiguous facts
- Must acknowledge prosecution's evidence while showing full context
- Alternative interpretation must be genuinely plausible given full evidence

Croatian Legal Framework:
- ZKP Članak 9 - Prosecution must present both incriminating and exculpatory evidence
- ZKP Članak 331 - Court must evaluate evidence in full context
- Ustav RH Članak 29 - Right to fair trial requires complete presentation

Return JSON:
{
    "narrative": "2-3 paragraph defense narrative showing full context (in Croatian or English)",
    "key_points": [
        "Key point 1 from full context",
        "Key point 2 showing what was omitted",
        "Key point 3 alternative interpretation"
    ],
    "alternative_interpretation": "One sentence summary of defense view",
    "supporting_facts": [
        "Specific fact from full evidence supporting defense view",
        "Another fact prosecutor omitted"
    ],
    "croatian_legal_basis": "Relevant ZKP and Ustav RH articles",
    "fair_trial_argument": "How selective presentation violates fair trial rights"
}

IMPORTANT: Base EVERY claim on actual evidence provided above. Do not invent facts.
PROMPT;
    }

    /**
     * Highlight key differences between prosecution and defense narratives
     *
     * @param  array  $contextAnalysis  Context analysis
     * @param  array  $prosecutionNarrative  Prosecution narrative
     * @param  array  $defenseRecontextualization  Defense recontextualization
     * @return array Key differences
     */
    protected function highlightKeyDifferences(
        array $contextAnalysis,
        array $prosecutionNarrative,
        array $defenseRecontextualization
    ): array {
        $differences = [];

        // Difference 1: What was shown vs. omitted
        $selectivePresentation = $contextAnalysis['selective_presentation'] ?? [];
        if (! empty($selectivePresentation['what_prosecutor_showed']) &&
            ! empty($selectivePresentation['what_prosecutor_omitted'])) {
            $differences[] = [
                'aspect' => 'Evidence Scope',
                'prosecution' => $selectivePresentation['what_prosecutor_showed'],
                'defense' => 'Full evidence including: '.$selectivePresentation['what_prosecutor_omitted'],
                'significance' => $selectivePresentation['why_omission_matters'] ?? '',
            ];
        }

        // Difference 2: Interpretation
        if (! empty($prosecutionNarrative['their_interpretation']) &&
            ! empty($defenseRecontextualization['alternative_interpretation'])) {
            $differences[] = [
                'aspect' => 'Interpretation',
                'prosecution' => $prosecutionNarrative['their_interpretation'],
                'defense' => $defenseRecontextualization['alternative_interpretation'],
                'significance' => 'Different interpretations based on complete vs. selective context',
            ];
        }

        // Difference 3: Omitted facts
        $omittedContext = $contextAnalysis['omitted_context'] ?? [];
        if (! empty($omittedContext['omissions'])) {
            foreach ($omittedContext['omissions'] as $i => $omission) {
                if ($i >= 3) {
                    break;
                } // Limit to top 3 omissions

                $differences[] = [
                    'aspect' => 'Omitted Fact #'.($i + 1),
                    'prosecution' => 'Not presented',
                    'defense' => $omission['omitted_fact'] ?? '',
                    'significance' => $omission['how_it_changes_interpretation'] ?? '',
                ];
            }
        }

        return $differences;
    }

    /**
     * Identify supporting evidence for defense recontextualization
     *
     * @param  array  $contextAnalysis  Context analysis
     * @param  LegalCase  $case  The legal case
     * @return array Supporting evidence
     */
    protected function identifySupportingEvidence(array $contextAnalysis, LegalCase $case): array
    {
        $supporting = [];

        // 1. Full evidence content
        $fullContext = $contextAnalysis['full_context'] ?? [];
        if (! empty($fullContext['full_content'])) {
            $supporting[] = [
                'type' => 'full_evidence',
                'description' => 'Complete evidence content',
                'relevance' => 'Shows full context prosecutor omitted',
                'source' => 'Full evidence file',
            ];
        }

        // 2. Metadata (timestamps, locations, etc.)
        if (! empty($fullContext['metadata'])) {
            $supporting[] = [
                'type' => 'metadata',
                'description' => 'Evidence metadata (timestamps, locations, etc.)',
                'relevance' => 'Provides objective context for interpretation',
                'source' => 'Evidence metadata',
                'details' => $fullContext['metadata'],
            ];
        }

        // 3. Surrounding evidence
        if (! empty($fullContext['surrounding_evidence'])) {
            $supporting[] = [
                'type' => 'surrounding_evidence',
                'description' => 'Evidence collected around same time',
                'relevance' => 'Provides timeline context',
                'source' => 'Case evidence collection',
                'count' => count($fullContext['surrounding_evidence']),
            ];
        }

        // 4. Related documents
        if (! empty($fullContext['related_documents'])) {
            $supporting[] = [
                'type' => 'related_documents',
                'description' => 'Case documents referencing this evidence',
                'relevance' => 'Cross-references supporting defense interpretation',
                'source' => 'Case file documents',
                'count' => count($fullContext['related_documents']),
            ];
        }

        // 5. Omissions with supporting quotes
        $omittedContext = $contextAnalysis['omitted_context'] ?? [];
        if (! empty($omittedContext['omissions'])) {
            foreach ($omittedContext['omissions'] as $i => $omission) {
                if (! empty($omission['where_in_full_evidence'])) {
                    $supporting[] = [
                        'type' => 'omitted_context',
                        'description' => 'Context prosecutor omitted',
                        'relevance' => $omission['how_it_changes_interpretation'] ?? '',
                        'source' => 'Full evidence',
                        'quote' => $omission['where_in_full_evidence'],
                        'exculpatory_value' => $omission['exculpatory_value'] ?? 0,
                    ];
                }
            }
        }

        return $supporting;
    }

    /**
     * Calculate credibility score for defense recontextualization (0-100)
     *
     * Higher score = more likely to create reasonable doubt
     *
     * Scoring:
     * - 50: Base score (neutral starting point)
     * - +20: Prosecutor omitted significant context
     * - +15: Supporting evidence for defense recontextualization
     * - +15: Objective support (metadata, timestamps, documents)
     * - Maximum: 100
     *
     * @param  array  $contextAnalysis  Context analysis
     * @param  array  $supportingEvidence  Supporting evidence
     * @return int Credibility score (0-100)
     */
    protected function calculateCredibilityScore(array $contextAnalysis, array $supportingEvidence): int
    {
        $score = 50; // Base score (neutral starting point)

        // +20 if prosecutor omitted significant context
        $selectivePresentation = $contextAnalysis['selective_presentation'] ?? [];
        if (! empty($selectivePresentation['why_omission_matters'])) {
            $score += 20;
            Log::debug('RecontextualizationService: +20 for significant omission');
        }

        // +15 if we have supporting evidence for defense recontextualization
        $omittedContext = $contextAnalysis['omitted_context'] ?? [];
        if (! empty($omittedContext['omissions'])) {
            $score += 15;
            Log::debug('RecontextualizationService: +15 for omitted context evidence');
        }

        // +15 if recontextualization is based on objective evidence (metadata, timestamps)
        if ($this->hasObjectiveSupport($contextAnalysis, $supportingEvidence)) {
            $score += 15;
            Log::debug('RecontextualizationService: +15 for objective support');
        }

        // Bonus: +5 if high exculpatory value
        $totalExculpatory = $omittedContext['total_exculpatory_value'] ?? 0;
        if ($totalExculpatory >= 150) {
            $score += 5;
            Log::debug('RecontextualizationService: +5 for high exculpatory value');
        }

        // Bonus: +5 if multiple types of supporting evidence
        if (is_array($supportingEvidence) && count($supportingEvidence) >= 3) {
            $score += 5;
            Log::debug('RecontextualizationService: +5 for multiple evidence types');
        }

        return min(100, $score);
    }

    /**
     * Check if recontextualization has objective support
     *
     * Objective support includes:
     * - Metadata (timestamps, GPS, EXIF data)
     * - Timestamps proving timeline
     * - Documents cross-referencing
     * - Physical evidence corroboration
     *
     * @param  array  $contextAnalysis  Context analysis
     * @param  array  $supportingEvidence  Supporting evidence
     * @return bool Has objective support
     */
    protected function hasObjectiveSupport(array $contextAnalysis, array $supportingEvidence): bool
    {
        // Check for metadata
        $fullContext = $contextAnalysis['full_context'] ?? [];
        if (! empty($fullContext['metadata'])) {
            return true;
        }

        // Check for timestamps
        if (! empty($fullContext['timestamps'])) {
            return true;
        }

        // Check supporting evidence types
        if (is_array($supportingEvidence)) {
            foreach ($supportingEvidence as $support) {
                $type = $support['type'] ?? '';
                if (in_array($type, ['metadata', 'surrounding_evidence', 'related_documents'])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get credibility level label from score
     *
     * @param  int  $score  Credibility score (0-100)
     * @return string Credibility level
     */
    protected function getCredibilityLevel(int $score): string
    {
        if ($score >= 85) {
            return 'very_high';
        } elseif ($score >= 70) {
            return 'high';
        } elseif ($score >= 55) {
            return 'moderate';
        } elseif ($score >= 40) {
            return 'low';
        } else {
            return 'very_low';
        }
    }

    /**
     * Get recommended use based on credibility score
     *
     * @param  int  $score  Credibility score
     * @return string Recommendation
     */
    protected function getRecommendedUse(int $score): string
    {
        if ($score >= 85) {
            return 'Strong defense argument - use prominently in trial and appeals';
        } elseif ($score >= 70) {
            return 'Solid recontextualization - use in defense strategy and closing arguments';
        } elseif ($score >= 55) {
            return 'Moderate strength - use as supporting argument alongside other evidence';
        } elseif ($score >= 40) {
            return 'Weak recontextualization - consider using only if no stronger arguments available';
        } else {
            return 'Very weak - not recommended for use without additional supporting evidence';
        }
    }

    /**
     * Get template-based recontextualization (fallback when AI fails)
     *
     * @param  string  $whatShowed  What prosecutor showed
     * @param  string  $whatOmitted  What prosecutor omitted
     * @param  string  $whyMatters  Why omission matters
     * @return array Template recontextualization
     */
    protected function getTemplateRecontextualization(
        string $whatShowed,
        string $whatOmitted,
        string $whyMatters
    ): array {
        $narrative = <<<NARRATIVE
Tužiteljstvo je selektivno prezentiralo dokaz, pokazujući samo: {$whatShowed}

Međutim, cjeloviti dokaz sadrži i: {$whatOmitted}

Ovaj izostavljeni kontekst je bitan jer: {$whyMatters}

Sukladno ZKP Članku 9, državno odvjetništvo dužno je voditi računa ne samo o okolnostima koje terete okrivljenika, već i o onima koje ga oslobađaju. Selektivnom prezentacijom dokaza, tužiteljstvo krši načelo objektivnosti i pravo okrivljenika na pravično suđenje (Ustav RH Članak 29).
NARRATIVE;

        return [
            'narrative' => $narrative,
            'key_points' => [
                "Tužiteljstvo pokazalo: {$whatShowed}",
                "Tužiteljstvo izostavilo: {$whatOmitted}",
                'Potpuni kontekst mijenja interpretaciju',
            ],
            'alternative_interpretation' => 'Cjeloviti dokaz pruža drukčiju interpretaciju od selektivne prezentacije tužiteljstva',
            'supporting_facts' => [
                $whatOmitted,
            ],
            'croatian_legal_basis' => 'ZKP Članak 9 - Objektivnost, ZKP Članak 331 - Slobodna ocjena dokaza',
            'fair_trial_argument' => 'Selektivna prezentacija dokaza krši pravo na pravično suđenje (Ustav RH Članak 29)',
        ];
    }
}
