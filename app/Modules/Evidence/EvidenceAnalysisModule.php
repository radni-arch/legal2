<?php

namespace App\Modules\Evidence;

use App\Models\LegalCase;
use App\Modules\Evidence\Services\AlternativeInterpretationAnalyzer;
use App\Modules\Evidence\Services\ConstitutionalViolationDetector;
use App\Modules\Evidence\Services\ContextAnalyzer;
use App\Modules\Evidence\Services\EvidenceAdmissibilityChecker;
use App\Modules\Evidence\Services\RecontextualizationService;
use App\Modules\Evidence\Services\SuppressionMotionGenerator;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Log;

/**
 * EvidenceAnalysisModule - Croatian Legal System
 *
 * Analyzes evidence for admissibility challenges under Croatian law.
 * Based on Zakon o kaznenom postupku (ZKP) - Criminal Procedure Act.
 *
 * CAPABILITIES:
 * - Identifies constitutional violations (Ustav RH)
 * - Detects procedural errors in evidence collection
 * - Finds legitimate grounds for evidence exclusion
 * - Provides alternative interpretations based on actual facts
 * - Detects prosecutor's selective presentation of evidence (NEW)
 * - Generates defense recontextualization showing full context (NEW)
 * - Calculates credibility scores for defense narratives (NEW)
 *
 * RECONTEXTUALIZATION (Sprint 3):
 * Counters prosecution's selective use of evidence by:
 * 1. Detecting 5 types of selective presentation (partial messages, cherry-picked
 *    timestamps, out-of-context media, partial statements, selective records)
 * 2. Identifying omitted context that changes interpretation
 * 3. Generating defense recontextualization based on full evidence
 * 4. Providing supporting evidence and credibility scoring
 *
 * ETHICAL USE ONLY:
 * ✅ Based on actual evidence (never fabricates context)
 * ✅ Shows full factual context prosecutor omitted
 * ✅ Provides legitimate alternative interpretations
 * ❌ NOT FOR: Fabricating evidence, distorting facts, obstructing justice
 */
class EvidenceAnalysisModule
{
    public function __construct(
        protected OpenAIService $openAI,
        protected EvidenceAdmissibilityChecker $admissibilityChecker,
        protected ConstitutionalViolationDetector $constitutionalDetector,
        protected AlternativeInterpretationAnalyzer $interpretationAnalyzer,
        protected SuppressionMotionGenerator $motionGenerator,
        protected ContextAnalyzer $contextAnalyzer,
        protected RecontextualizationService $recontextualizationService
    ) {}

    /**
     * Comprehensive evidence analysis
     *
     * @param  string  $caseId  The case ID
     * @param  array  $evidence  Evidence to analyze
     * @param  array  $options  Analysis options
     * @return array Complete evidence analysis
     */
    public function analyzeEvidence(string $caseId, array $evidence = [], array $options = []): array
    {
        Log::info('EvidenceAnalysisModule - Starting analysis', [
            'case_id' => $caseId,
            'evidence_count' => count($evidence),
        ]);

        $case = LegalCase::with('documents')->findOrFail($caseId);

        // If no specific evidence provided, extract from case
        if (empty($evidence)) {
            $evidence = $this->extractEvidenceFromCase($case);
        }

        $results = [];

        foreach ($evidence as $item) {
            // Analyze context for selective presentation
            $contextAnalysis = $this->contextAnalyzer->analyzeContext($item, $case);

            $analysis = [
                'evidence_id' => $item['id'] ?? uniqid('ev_'),
                'description' => $item['description'] ?? '',
                'type' => $item['type'] ?? 'unknown',

                // Admissibility analysis (ZKP)
                'admissibility' => $this->admissibilityChecker->check($item, $case),

                // Constitutional violations (Ustav RH)
                'constitutional_issues' => $this->constitutionalDetector->detect($item, $case),

                // Alternative interpretations
                'alternative_interpretations' => $this->interpretationAnalyzer->analyze($item, $case),

                // Context analysis (selective presentation detection)
                'context_analysis' => $contextAnalysis,

                // Recontextualization (defense narrative based on full context)
                'recontextualization' => $this->recontextualizationService->recontextualize(
                    $item,
                    $contextAnalysis,
                    $case
                ),

                // Suppression potential
                'suppression_grounds' => $this->identifySuppressionGrounds($item, $case),

                // Challenge strategy
                'challenge_strategy' => $this->generateChallengeStrategy($item, $case),
            ];

            // Calculate overall excludability score
            $analysis['excludability_score'] = $this->calculateExcludabilityScore($analysis);

            $results[] = $analysis;
        }

        // Sort by excludability (most challengeable first)
        usort($results, fn ($a, $b) => $b['excludability_score'] <=> $a['excludability_score']);

        return [
            'case_id' => $caseId,
            'total_evidence_items' => count($results),
            'highly_challengeable' => count(array_filter($results, fn ($r) => $r['excludability_score'] >= 70)),
            'evidence_analysis' => $results,
            'summary' => $this->generateSummary($results),
            'recommended_motions' => $this->recommendMotions($results, $case),
        ];
    }

    /**
     * Generate motion to suppress evidence
     *
     * @param  string  $caseId  Case ID
     * @param  array  $evidenceIds  Evidence IDs to suppress
     * @return array Generated motion
     */
    public function generateSuppressionMotion(string $caseId, array $evidenceIds): array
    {
        $case = LegalCase::findOrFail($caseId);

        return $this->motionGenerator->generate($case, $evidenceIds);
    }

    /**
     * Check specific evidence admissibility
     *
     * @param  array  $evidence  Evidence item
     * @param  string  $caseId  Case ID
     * @return array Admissibility analysis
     */
    public function checkAdmissibility(array $evidence, string $caseId): array
    {
        $case = LegalCase::findOrFail($caseId);

        return $this->admissibilityChecker->check($evidence, $case);
    }

    /**
     * Detect constitutional violations
     *
     * @param  array  $evidence  Evidence item
     * @param  string  $caseId  Case ID
     * @return array Constitutional issues
     */
    public function detectConstitutionalViolations(array $evidence, string $caseId): array
    {
        $case = LegalCase::findOrFail($caseId);

        return $this->constitutionalDetector->detect($evidence, $case);
    }

    /**
     * Get alternative interpretations
     *
     * @param  array  $evidence  Evidence item
     * @param  string  $caseId  Case ID
     * @return array Alternative interpretations
     */
    public function getAlternativeInterpretations(array $evidence, string $caseId): array
    {
        $case = LegalCase::findOrFail($caseId);

        return $this->interpretationAnalyzer->analyze($evidence, $case);
    }

    /**
     * Recontextualize evidence (show full context vs. prosecutor's selective use)
     *
     * This method counters prosecution's selective presentation of evidence by:
     * 1. Detecting selective presentation (5 types: partial messages, cherry-picked
     *    timestamps, out-of-context media, partial statements, selective records)
     * 2. Identifying omitted context that changes interpretation
     * 3. Generating defense recontextualization based on full evidence
     * 4. Calculating credibility score for defense narrative
     * 5. Providing supporting evidence and strategic recommendations
     *
     * ETHICAL FRAMEWORK:
     * - Based on ACTUAL evidence (never fabricates context)
     * - Shows full factual context prosecutor omitted
     * - Provides legitimate alternative interpretations
     * - Identifies supporting evidence for defense narrative
     *
     * @param  string  $caseId  Case ID
     * @param  array  $evidence  Evidence item to recontextualize
     * @return array Context analysis and recontextualization result
     */
    public function recontextualizeEvidence(string $caseId, array $evidence): array
    {
        Log::info('EvidenceAnalysisModule: Recontextualizing evidence', [
            'case_id' => $caseId,
            'evidence_id' => $evidence['id'] ?? 'unknown',
        ]);

        $case = LegalCase::with('documents')->findOrFail($caseId);

        // Step 1: Analyze context for selective presentation
        $contextAnalysis = $this->contextAnalyzer->analyzeContext($evidence, $case);

        // Step 2: Generate recontextualization if selective presentation detected
        $recontextualization = $this->recontextualizationService->recontextualize(
            $evidence,
            $contextAnalysis,
            $case
        );

        Log::info('EvidenceAnalysisModule: Recontextualization complete', [
            'case_id' => $caseId,
            'evidence_id' => $evidence['id'] ?? 'unknown',
            'selective_presentation_detected' => $contextAnalysis['selective_presentation']['detected'] ?? false,
            'recontextualization_needed' => $recontextualization['recontextualization_needed'] ?? false,
            'credibility_score' => $recontextualization['credibility_score'] ?? null,
        ]);

        return [
            'evidence_id' => $evidence['id'] ?? uniqid('ev_'),
            'evidence_type' => $evidence['type'] ?? 'unknown',
            'context_analysis' => $contextAnalysis,
            'recontextualization' => $recontextualization,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Extract evidence from case documents
     */
    protected function extractEvidenceFromCase(LegalCase $case): array
    {
        // Use LLM to extract evidence mentions from case description
        $prompt = <<<PROMPT
Extract all evidence items mentioned in this case:

Case: {$case->title}
Description: {$case->description}

For each evidence item, identify:
- Type (physical, testimonial, documentary, digital, expert)
- Description
- Source/origin
- How it was obtained
- Importance to prosecution

Return JSON array of evidence items.
PROMPT;

        $response = $this->openAI->chat([
            ['role' => 'system', 'content' => 'You are a Croatian legal expert extracting evidence from case files.'],
            ['role' => 'user', 'content' => $prompt],
        ], 'gpt-4o-mini', [
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.3,
        ]);

        $result = json_decode($response['choices'][0]['message']['content'], true);

        return $result['evidence'] ?? [];
    }

    /**
     * Identify suppression grounds
     */
    protected function identifySuppressionGrounds(array $evidence, LegalCase $case): array
    {
        $grounds = [];

        // Constitutional grounds
        $constitutional = $this->constitutionalDetector->detect($evidence, $case);
        if (! empty($constitutional)) {
            foreach ($constitutional as $issue) {
                $grounds[] = [
                    'type' => 'constitutional',
                    'basis' => $issue['article'] ?? 'Ustav RH',
                    'description' => $issue['violation'] ?? '',
                    'severity' => $issue['severity'] ?? 50,
                ];
            }
        }

        // Procedural grounds
        $admissibility = $this->admissibilityChecker->check($evidence, $case);
        if (! ($admissibility['admissible'] ?? true)) {
            foreach ($admissibility['issues'] ?? [] as $issue) {
                $grounds[] = [
                    'type' => 'procedural',
                    'basis' => $issue['legal_basis'] ?? 'ZKP',
                    'description' => $issue['description'] ?? '',
                    'severity' => $issue['severity'] ?? 50,
                ];
            }
        }

        return $grounds;
    }

    /**
     * Generate challenge strategy
     */
    protected function generateChallengeStrategy(array $evidence, LegalCase $case): array
    {
        $admissibility = $this->admissibilityChecker->check($evidence, $case);
        $constitutional = $this->constitutionalDetector->detect($evidence, $case);

        $strategy = [
            'primary_approach' => 'To be determined',
            'tactics' => [],
            'expected_difficulty' => 'Medium',
            'success_probability' => 50,
        ];

        // If constitutional violations exist, prioritize those
        if (! empty($constitutional)) {
            $strategy['primary_approach'] = 'Constitutional challenge';
            $strategy['tactics'][] = 'File motion citing constitutional violations';
            $strategy['success_probability'] = 70;
        }
        // Otherwise, procedural challenges
        elseif (! ($admissibility['admissible'] ?? true)) {
            $strategy['primary_approach'] = 'Procedural challenge';
            $strategy['tactics'][] = 'Challenge based on ZKP violations';
            $strategy['success_probability'] = 60;
        }
        // Otherwise, credibility/interpretation
        else {
            $strategy['primary_approach'] = 'Alternative interpretation';
            $strategy['tactics'][] = 'Present alternative factual interpretations';
            $strategy['tactics'][] = 'Challenge witness credibility';
            $strategy['success_probability'] = 40;
        }

        return $strategy;
    }

    /**
     * Calculate excludability score (0-100)
     */
    protected function calculateExcludabilityScore(array $analysis): int
    {
        $score = 0;

        // Constitutional violations = high score
        $constitutionalIssues = $analysis['constitutional_issues'] ?? [];
        $score += count($constitutionalIssues) * 25;

        // Admissibility issues
        if (! ($analysis['admissibility']['admissible'] ?? true)) {
            $score += 30;
        }

        // Suppression grounds
        $grounds = $analysis['suppression_grounds'] ?? [];
        $score += count($grounds) * 15;

        // Cap at 100
        return min(100, $score);
    }

    /**
     * Generate summary
     */
    protected function generateSummary(array $results): string
    {
        $total = count($results);
        $challengeable = count(array_filter($results, fn ($r) => $r['excludability_score'] >= 50));
        $highlyChallengeable = count(array_filter($results, fn ($r) => $r['excludability_score'] >= 70));

        $summary = "EVIDENCE ANALYSIS SUMMARY\n\n";
        $summary .= "Total Evidence Items: {$total}\n";
        $summary .= "Challengeable: {$challengeable} ({$this->percentage($challengeable, $total)}%)\n";
        $summary .= "Highly Challengeable: {$highlyChallengeable} ({$this->percentage($highlyChallengeable, $total)}%)\n\n";

        if ($highlyChallengeable > 0) {
            $summary .= "RECOMMENDATION: Strong grounds for evidence suppression motions.\n";
        } elseif ($challengeable > 0) {
            $summary .= "RECOMMENDATION: Moderate grounds for challenging evidence admissibility.\n";
        } else {
            $summary .= "RECOMMENDATION: Focus on alternative interpretations and witness credibility.\n";
        }

        return $summary;
    }

    /**
     * Recommend motions to file
     */
    protected function recommendMotions(array $results, LegalCase $case): array
    {
        $motions = [];

        // Group by suppression grounds
        $constitutional = [];
        $procedural = [];

        foreach ($results as $result) {
            if ($result['excludability_score'] >= 60) {
                foreach ($result['suppression_grounds'] ?? [] as $ground) {
                    if ($ground['type'] === 'constitutional') {
                        $constitutional[] = $result['evidence_id'];
                    } else {
                        $procedural[] = $result['evidence_id'];
                    }
                }
            }
        }

        if (! empty($constitutional)) {
            $motions[] = [
                'type' => 'Prijedlog za isključenje dokaza (constitutional)',
                'evidence_ids' => array_unique($constitutional),
                'priority' => 'high',
                'basis' => 'Ustav RH - Constitutional violations',
            ];
        }

        if (! empty($procedural)) {
            $motions[] = [
                'type' => 'Prijedlog za isključenje dokaza (procedural)',
                'evidence_ids' => array_unique($procedural),
                'priority' => 'medium',
                'basis' => 'ZKP - Procedural violations',
            ];
        }

        return $motions;
    }

    /**
     * Helper: calculate percentage
     */
    protected function percentage(int $part, int $total): int
    {
        return $total > 0 ? (int) round(($part / $total) * 100) : 0;
    }
}
