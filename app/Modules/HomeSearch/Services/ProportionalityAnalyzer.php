<?php

namespace App\Modules\HomeSearch\Services;

use App\Models\LegalCase;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Log;

/**
 * ProportionalityAnalyzer (Analitičar Razmjernosti)
 *
 * Analyzes whether a home search was proportionate to the offense severity
 * under Croatian law's proportionality principle (načelo razmjernosti).
 *
 * Legal Basis:
 * - ZKP Čl. 179 - Načelo razmjernosti
 * - Ustav RH Čl. 34 - Nepovrjedivost stana (home inviolability)
 * - ZKP Čl. 215-220 - Pretres stana (home search procedures)
 *
 * Proportionality Test (Three-Part Test):
 * 1. Legitimacy - Is the objective legitimate?
 * 2. Suitability - Is the search suitable to achieve the objective?
 * 3. Necessity - Is the search necessary (no less invasive alternatives)?
 * 4. Proportionality stricto sensu - Is harm proportionate to benefit?
 *
 * Offense Severity Categories:
 * - Criminal offenses (kaznena djela):
 *   - Serious (teška): Murder, robbery, organized crime → Search usually justified
 *   - Medium (srednje teška): Theft, fraud, assault → Search may be justified
 *   - Minor (lakša): Simple theft, minor assault → Search questionable
 * - Misdemeanors (prekršaji): Traffic, noise, minor violations → Search rarely justified
 */
class ProportionalityAnalyzer
{
    public function __construct(
        protected OpenAIService $openAI
    ) {}

    /**
     * Analyze proportionality of home search
     *
     * @param  array  $searchWarrantDetails  Search warrant details
     * @param  LegalCase  $case  Legal case
     * @return array Proportionality analysis
     */
    public function analyze(array $searchWarrantDetails, LegalCase $case): array
    {
        Log::info('ProportionalityAnalyzer: Starting proportionality analysis', [
            'case_id' => $case->id,
            'offense' => $searchWarrantDetails['offense'] ?? 'unknown',
        ]);

        // 1. Classify offense severity
        $offenseClassification = $this->classifyOffenseSeverity($searchWarrantDetails);

        // 2. Assess search invasiveness
        $searchInvasiveness = $this->assessSearchInvasiveness($searchWarrantDetails);

        // 3. Apply proportionality test
        $proportionalityTest = $this->applyProportionalityTest(
            $offenseClassification,
            $searchInvasiveness,
            $searchWarrantDetails
        );

        // 4. Calculate disproportion score (0-100, higher = more disproportionate)
        $disproportionScore = $this->calculateDisproportionScore(
            $offenseClassification,
            $searchInvasiveness,
            $proportionalityTest
        );

        // 5. Generate analysis using AI
        $aiAnalysis = $this->generateAIAnalysis($searchWarrantDetails, $offenseClassification, $searchInvasiveness, $case);

        $result = [
            'proportionate' => $disproportionScore < 50,
            'disproportion_score' => $disproportionScore,
            'disproportion_level' => $this->getDisproportionLevel($disproportionScore),
            'offense_classification' => $offenseClassification,
            'search_invasiveness' => $searchInvasiveness,
            'proportionality_test' => $proportionalityTest,
            'analysis' => $aiAnalysis,
            'legal_standard' => 'ZKP Čl. 179 - Načelo razmjernosti',
            'constitutional_standard' => 'Ustav RH Čl. 34 - Nepovrjedivost stana',
        ];

        Log::info('ProportionalityAnalyzer: Analysis complete', [
            'case_id' => $case->id,
            'proportionate' => $result['proportionate'],
            'disproportion_score' => $disproportionScore,
        ]);

        return $result;
    }

    /**
     * Classify offense severity
     *
     * @param  array  $searchWarrantDetails  Search warrant details
     * @return array Offense classification
     */
    protected function classifyOffenseSeverity(array $searchWarrantDetails): array
    {
        $offense = $searchWarrantDetails['offense'] ?? '';
        $offenseType = $searchWarrantDetails['offense_type'] ?? 'unknown'; // 'kazneno_djelo' or 'prekršaj'
        $maxPenalty = $searchWarrantDetails['max_penalty_years'] ?? 0;

        // Classify severity
        $severity = 'unknown';
        $severityScore = 0; // 0-100

        if ($offenseType === 'prekršaj' || $offenseType === 'misdemeanor') {
            $severity = 'misdemeanor';
            $severityScore = 15; // Misdemeanors are very low severity
        } elseif ($maxPenalty >= 10) {
            $severity = 'serious';
            $severityScore = 90;
        } elseif ($maxPenalty >= 5) {
            $severity = 'medium';
            $severityScore = 60;
        } elseif ($maxPenalty >= 1) {
            $severity = 'minor_criminal';
            $severityScore = 35;
        } else {
            // Try to infer from offense description
            $offense_lower = strtolower($offense);

            if (preg_match('/(ubojstvo|razbojništvo|silovanje|trgovina ljudima|organizirani)/i', $offense)) {
                $severity = 'serious';
                $severityScore = 90;
            } elseif (preg_match('/(krađa|prevara|napad|prijetnja|nanošenje)/i', $offense)) {
                $severity = 'medium';
                $severityScore = 60;
            } elseif (preg_match('/(prekršaj|prometni|buka|sitna krađa|laka tjelesna)/i', $offense)) {
                $severity = 'minor_criminal';
                $severityScore = 35;
            } else {
                $severity = 'unknown';
                $severityScore = 50; // Default to medium
            }
        }

        return [
            'offense' => $offense,
            'offense_type' => $offenseType,
            'severity' => $severity,
            'severity_score' => $severityScore,
            'max_penalty_years' => $maxPenalty,
            'justifies_home_search' => $severityScore >= 40, // Generally need at least medium severity
        ];
    }

    /**
     * Assess search invasiveness
     *
     * @param  array  $searchWarrantDetails  Search warrant details
     * @return array Search invasiveness assessment
     */
    protected function assessSearchInvasiveness(array $searchWarrantDetails): array
    {
        $invasivenessScore = 0; // 0-100

        // Factor 1: Scope of search
        $scope = $searchWarrantDetails['search_scope'] ?? 'unknown';
        if (in_array($scope, ['full_home_search', 'comprehensive', 'invasive'])) {
            $invasivenessScore += 40;
        } elseif ($scope === 'partial' || $scope === 'limited') {
            $invasivenessScore += 20;
        } elseif ($scope === 'specific_items_only') {
            $invasivenessScore += 10;
        }

        // Factor 2: Force used
        $forceUsed = $searchWarrantDetails['force_used'] ?? null;
        if ($forceUsed === 'swat' || $forceUsed === 'tactical_unit') {
            $invasivenessScore += 30;
        } elseif ($forceUsed === 'armed_officers') {
            $invasivenessScore += 20;
        } elseif ($forceUsed === 'standard_officers') {
            $invasivenessScore += 10;
        }

        // Factor 3: Time of search
        $searchTime = $searchWarrantDetails['search_time'] ?? null;
        if ($searchTime) {
            $hour = (int) date('H', strtotime($searchTime));
            if ($hour >= 22 || $hour <= 6) {
                $invasivenessScore += 20; // Night raids are more invasive
            }
        }

        // Factor 4: Duration
        $duration = $searchWarrantDetails['duration_hours'] ?? 1;
        if ($duration > 4) {
            $invasivenessScore += 10;
        }

        $invasivenessScore = min(100, $invasivenessScore);

        return [
            'invasiveness_score' => $invasivenessScore,
            'invasiveness_level' => $this->getInvasivenessLevel($invasivenessScore),
            'scope' => $scope,
            'force_used' => $forceUsed,
            'search_time' => $searchTime,
            'duration_hours' => $duration,
        ];
    }

    /**
     * Apply proportionality test (four-part test)
     *
     * @param  array  $offenseClassification  Offense classification
     * @param  array  $searchInvasiveness  Search invasiveness
     * @param  array  $searchWarrantDetails  Search warrant details
     * @return array Proportionality test results
     */
    protected function applyProportionalityTest(
        array $offenseClassification,
        array $searchInvasiveness,
        array $searchWarrantDetails
    ): array {
        $testResults = [];

        // Test 1: Legitimacy - Is the objective legitimate?
        $legitimateObjective = $offenseClassification['offense_type'] === 'kazneno_djelo' ||
                               $offenseClassification['severity_score'] >= 40;

        $testResults['legitimacy'] = [
            'passes' => $legitimateObjective,
            'analysis' => $legitimateObjective
                ? 'Objective is legitimate (criminal investigation)'
                : 'Questionable legitimacy (minor offense/misdemeanor)',
        ];

        // Test 2: Suitability - Is the search suitable to achieve the objective?
        $itemsSought = $searchWarrantDetails['items_sought'] ?? '';
        $suitable = ! empty($itemsSought); // Must specify what is sought

        $testResults['suitability'] = [
            'passes' => $suitable,
            'analysis' => $suitable
                ? 'Search suitable (specific items sought)'
                : 'Search unsuitable (vague or unspecified items)',
        ];

        // Test 3: Necessity - Is the search necessary (no less invasive alternatives)?
        $alternativesConsidered = $searchWarrantDetails['alternatives_considered'] ?? false;
        $necessary = $offenseClassification['severity_score'] >= 50 || $alternativesConsidered;

        $testResults['necessity'] = [
            'passes' => $necessary,
            'analysis' => $necessary
                ? 'Search appears necessary'
                : 'Less invasive alternatives may be available',
        ];

        // Test 4: Proportionality stricto sensu - Is harm proportionate to benefit?
        $severityScore = $offenseClassification['severity_score'];
        $invasivenessScore = $searchInvasiveness['invasiveness_score'];

        // Proportionate if invasiveness doesn't exceed severity by more than 20 points
        $proportionate = ($invasivenessScore - $severityScore) <= 20;

        $testResults['proportionality_stricto_sensu'] = [
            'passes' => $proportionate,
            'analysis' => $proportionate
                ? 'Harm proportionate to benefit'
                : sprintf('Harm disproportionate: invasiveness (%d) >> severity (%d)', $invasivenessScore, $severityScore),
            'severity_score' => $severityScore,
            'invasiveness_score' => $invasivenessScore,
            'difference' => $invasivenessScore - $severityScore,
        ];

        // Overall pass/fail
        $testResults['overall_passes'] = $testResults['legitimacy']['passes'] &&
                                         $testResults['suitability']['passes'] &&
                                         $testResults['necessity']['passes'] &&
                                         $testResults['proportionality_stricto_sensu']['passes'];

        return $testResults;
    }

    /**
     * Calculate disproportion score (0-100, higher = more disproportionate)
     *
     * @param  array  $offenseClassification  Offense classification
     * @param  array  $searchInvasiveness  Search invasiveness
     * @param  array  $proportionalityTest  Proportionality test results
     * @return int Disproportion score
     */
    protected function calculateDisproportionScore(
        array $offenseClassification,
        array $searchInvasiveness,
        array $proportionalityTest
    ): int {
        $score = 0;

        // Base score: difference between invasiveness and offense severity
        $severityScore = $offenseClassification['severity_score'];
        $invasivenessScore = $searchInvasiveness['invasiveness_score'];
        $difference = $invasivenessScore - $severityScore;

        if ($difference > 0) {
            $score += min(50, $difference); // Up to 50 points for disproportion
        }

        // Add points for failed proportionality tests
        foreach ($proportionalityTest as $test => $result) {
            if ($test !== 'overall_passes' && ! $result['passes']) {
                $score += 15; // +15 for each failed test
            }
        }

        // Special case: misdemeanor with invasive search = automatic high score
        if ($offenseClassification['severity'] === 'misdemeanor' &&
            $invasivenessScore >= 50) {
            $score = max($score, 75); // Minimum 75 for this scenario
        }

        return min(100, $score);
    }

    /**
     * Get disproportion level label
     *
     * @param  int  $score  Disproportion score
     * @return string Disproportion level
     */
    protected function getDisproportionLevel(int $score): string
    {
        if ($score >= 80) {
            return 'extreme';
        } elseif ($score >= 60) {
            return 'severe';
        } elseif ($score >= 40) {
            return 'moderate';
        } elseif ($score >= 20) {
            return 'minor';
        } else {
            return 'none';
        }
    }

    /**
     * Get invasiveness level label
     *
     * @param  int  $score  Invasiveness score
     * @return string Invasiveness level
     */
    protected function getInvasivenessLevel(int $score): string
    {
        if ($score >= 80) {
            return 'extremely_invasive';
        } elseif ($score >= 60) {
            return 'very_invasive';
        } elseif ($score >= 40) {
            return 'moderately_invasive';
        } elseif ($score >= 20) {
            return 'minimally_invasive';
        } else {
            return 'non_invasive';
        }
    }

    /**
     * Generate AI analysis of proportionality
     *
     * @param  array  $searchWarrantDetails  Search warrant details
     * @param  array  $offenseClassification  Offense classification
     * @param  array  $searchInvasiveness  Search invasiveness
     * @param  LegalCase  $case  Legal case
     * @return string AI-generated analysis
     */
    protected function generateAIAnalysis(
        array $searchWarrantDetails,
        array $offenseClassification,
        array $searchInvasiveness,
        LegalCase $case
    ): string {
        $offense = $searchWarrantDetails['offense'] ?? 'unknown';
        $severity = $offenseClassification['severity'];
        $invasiveness = $searchInvasiveness['invasiveness_level'];
        $scope = $searchInvasiveness['scope'] ?? 'unknown';

        $prompt = <<<PROMPT
Analyze the proportionality of a home search under Croatian law (ZKP Čl. 179 - Načelo razmjernosti, Ustav RH Čl. 34).

Offense: {$offense}
Offense Severity: {$severity}
Search Invasiveness: {$invasiveness}
Search Scope: {$scope}

Proportionality principle requires that investigative measures be proportionate to the severity of the offense and the degree of suspicion. Home searches are one of the most invasive measures and require strong justification.

Analyze:
1. Is a home search proportionate to this offense severity?
2. What are the constitutional concerns (Ustav RH Čl. 34)?
3. What are the ZKP concerns (Čl. 179, Čl. 215-220)?
4. What defense arguments can be made?

Provide a 2-3 paragraph analysis in Croatian or English, focusing on proportionality under Croatian law.
PROMPT;

        try {
            $response = $this->openAI->chat([
                ['role' => 'system', 'content' => 'You are a Croatian criminal defense expert analyzing proportionality of home searches under ZKP and Ustav RH. Focus on protecting constitutional rights (Čl. 34 - Nepovrjedivost stana).'],
                ['role' => 'user', 'content' => $prompt],
            ], 'gpt-4o', [
                'temperature' => 0.3,
                'max_tokens' => 500,
            ]);

            return trim($response['choices'][0]['message']['content']);

        } catch (\Exception $e) {
            Log::error('ProportionalityAnalyzer: AI analysis failed', [
                'error' => $e->getMessage(),
            ]);

            // Fallback to template
            return "Pretres doma za prekršaj '{$offense}' ({$severity}) podigao je ozbiljna pitanja razmjernosti prema ZKP Čl. 179. ".
                   "Opseg pretresa ({$invasiveness}) ne odgovara težini djela. ".
                   'Ovo može predstavljati povredu Ustava RH Čl. 34 (nepovrjedivost stana).';
        }
    }
}
