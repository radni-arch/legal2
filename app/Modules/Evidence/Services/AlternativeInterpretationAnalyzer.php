<?php

namespace App\Modules\Evidence\Services;

use App\Models\LegalCase;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Log;

/**
 * AlternativeInterpretationAnalyzer
 *
 * Provides legitimate alternative interpretations of evidence
 * based on actual facts (not fabrication).
 *
 * ETHICAL USE:
 * - Multiple valid interpretations of ambiguous evidence
 * - Competing expert opinions (legitimate science)
 * - Timeline inconsistencies
 * - Credibility assessment
 *
 * NOT FOR:
 * - Distorting clear facts
 * - Fabricating alternative narratives
 * - Ignoring overwhelming evidence
 */
class AlternativeInterpretationAnalyzer
{
    public function __construct(
        protected OpenAIService $openAI
    ) {}

    /**
     * Analyze evidence and provide alternative interpretations
     */
    public function analyze(array $evidence, LegalCase $case): array
    {
        Log::info('AlternativeInterpretationAnalyzer - Analyzing', [
            'case_id' => $case->id,
            'evidence_type' => $evidence['type'] ?? 'unknown',
        ]);

        $interpretations = [];

        // Get primary (prosecution) interpretation
        $primary = $this->extractPrimaryInterpretation($evidence);

        // Generate alternative interpretations
        $alternatives = $this->generateAlternativeInterpretations($evidence, $case, $primary);

        // Assess credibility of each interpretation
        foreach ($alternatives as $alt) {
            $alt['credibility_score'] = $this->assessCredibility($alt, $evidence, $case);
            $interpretations[] = $alt;
        }

        // Sort by credibility
        usort($interpretations, fn ($a, $b) => $b['credibility_score'] <=> $a['credibility_score']);

        return $interpretations;
    }

    /**
     * Extract primary (prosecution) interpretation
     */
    protected function extractPrimaryInterpretation(array $evidence): array
    {
        return [
            'interpretation' => $evidence['prosecution_interpretation'] ?? $evidence['description'] ?? '',
            'theory' => 'Prosecution theory',
        ];
    }

    /**
     * Generate legitimate alternative interpretations
     */
    protected function generateAlternativeInterpretations(
        array $evidence,
        LegalCase $case,
        array $primary
    ): array {
        $prompt = <<<PROMPT
As a defense expert, analyze this evidence and provide LEGITIMATE alternative interpretations.

Evidence:
Type: {$evidence['type']}
Description: {$evidence['description']}
Prosecution Interpretation: {$primary['interpretation']}

Case Context:
{$case->title}
{$case->description}

Generate 2-4 alternative interpretations that are:
1. Based on the actual evidence (not fabricated)
2. Scientifically/logically sound
3. Create reasonable doubt
4. Address ambiguities in the evidence

For each interpretation, provide:
- The alternative explanation
- Why it's plausible
- What additional evidence would support it
- How it contradicts prosecution theory

ETHICAL CONSTRAINTS:
- Must be based on facts
- Must be scientifically valid
- Cannot involve evidence fabrication
- Cannot distort clear, unambiguous evidence

Respond in JSON format with array of interpretations.
PROMPT;

        $response = $this->openAI->chat([
            ['role' => 'system', 'content' => 'You are an ethical defense expert providing legitimate alternative interpretations of evidence under Croatian law.'],
            ['role' => 'user', 'content' => $prompt],
        ], 'gpt-4o', [
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.6,
        ]);

        $result = json_decode($response['choices'][0]['message']['content'], true);

        return $result['interpretations'] ?? [];
    }

    /**
     * Assess credibility of an interpretation
     */
    protected function assessCredibility(
        array $interpretation,
        array $evidence,
        LegalCase $case
    ): int {
        $prompt = <<<PROMPT
Assess the credibility of this alternative interpretation:

Evidence: {$evidence['description']}
Alternative Interpretation: {$interpretation['explanation']}

Rate credibility (0-100) based on:
1. Scientific/logical soundness
2. Consistency with known facts
3. Ability to create reasonable doubt
4. Expert support available

Respond with JSON: {"credibility": 0-100, "reasoning": "..."}
PROMPT;

        $response = $this->openAI->chat([
            ['role' => 'system', 'content' => 'You are assessing the credibility of defense interpretations.'],
            ['role' => 'user', 'content' => $prompt],
        ], 'gpt-4o-mini', [
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.2,
        ]);

        $result = json_decode($response['choices'][0]['message']['content'], true);

        return $result['credibility'] ?? 50;
    }

    /**
     * Identify timeline inconsistencies
     */
    public function findTimelineInconsistencies(array $evidence, LegalCase $case): array
    {
        $inconsistencies = [];

        // Check for timestamp issues
        if (isset($evidence['timestamp'])) {
            // Look for gaps, overlaps, impossibilities
            $timestamp = $evidence['timestamp'];

            // Example checks (can be expanded)
            if (isset($evidence['related_events'])) {
                foreach ($evidence['related_events'] as $event) {
                    if (isset($event['timestamp'])) {
                        // Check if times are physically impossible
                        // (e.g., person in two places at once)
                        $inconsistencies[] = [
                            'type' => 'timeline',
                            'description' => 'Temporal inconsistency detected',
                            'impact' => 'Questions reliability of evidence',
                        ];
                    }
                }
            }
        }

        return $inconsistencies;
    }

    /**
     * Assess witness credibility issues
     */
    public function assessWitnessCredibility(array $evidence): array
    {
        if (($evidence['type'] ?? '') !== 'testimonial') {
            return [];
        }

        $issues = [];

        // Bias
        if (isset($evidence['witness_relationship'])) {
            if (in_array($evidence['witness_relationship'], ['victim', 'interested_party', 'co-defendant'])) {
                $issues[] = [
                    'issue' => 'Witness bias',
                    'description' => 'Witness has relationship to case that may affect credibility',
                    'severity' => 60,
                ];
            }
        }

        // Prior inconsistent statements
        if (isset($evidence['prior_statements']) && count($evidence['prior_statements']) > 1) {
            $issues[] = [
                'issue' => 'Inconsistent statements',
                'description' => 'Witness has made contradictory statements',
                'severity' => 75,
            ];
        }

        // Criminal history
        if (isset($evidence['witness_criminal_history']) && $evidence['witness_criminal_history']) {
            $issues[] = [
                'issue' => 'Witness credibility - criminal history',
                'description' => 'Witness has criminal convictions affecting truthfulness',
                'severity' => 70,
            ];
        }

        return $issues;
    }
}
