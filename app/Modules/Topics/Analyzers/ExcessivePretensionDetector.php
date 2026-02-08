<?php

namespace App\Modules\Topics\Analyzers;

use App\Models\LegalCase;
use App\Modules\Topics\TopicAnalyzer;
use Illuminate\Support\Facades\Log;

/**
 * ExcessivePretensionDetector (Detektor Prekomjernog Pritvaranja)
 *
 * Detects excessive pretrial detention violations - illegally prolonged detention,
 * lack of justification, procedural violations, and disproportionate measures.
 *
 * THE PROBLEM:
 * Croatian prosecutors and courts routinely detain defendants in pretrial detention
 * (pritvor) for excessive periods without proper justification or review. This violates:
 * 1. Constitutional rights (Ustav RH Čl. 24)
 * 2. ECHR Article 5 (right to liberty)
 * 3. ZKP procedural requirements
 * 4. Proportionality principles
 *
 * CROATIAN LAW FRAMEWORK:
 * ZKP Članak 122 - Grounds for pretrial detention:
 * - Flight risk (opasnost od bijega)
 * - Risk of repeating offense (opasnost od ponavljanja)
 * - Risk of destroying evidence (opasnost od uništavanja dokaza)
 * - Risk to public order (opasnost za javni red)
 *
 * ZKP Članak 123 - Duration limits:
 * - General crimes: Max 6 months investigation, 1 year total before first instance
 * - Serious crimes (8+ years): Max 12 months investigation, 2 years total
 * - Exceptional cases: Supreme Court can extend
 * - Must be reviewed every month (mjesečna kontrola)
 *
 * Ustav RH Članak 24:
 * - Right to liberty and security
 * - Detention only when legally prescribed
 * - Right to judicial review of detention
 *
 * ECHR Article 5:
 * - Right to liberty
 * - Detention must be "reasonable time"
 * - Automatic periodic review required
 *
 * VIOLATION PATTERNS:
 * 1. Excessive Duration - Detention beyond statutory limits
 * 2. No Justification - Generic reasoning without specific facts
 * 3. Procedural Violations - Missing monthly reviews, improper hearings
 * 4. Proportionality Violation - Detention disproportionate to offense severity
 *
 * QUESTIONS THIS ANSWERS:
 * - "How many defendants detained beyond 6 months for minor crimes?"
 * - "Is Osijek worse than Zadar for pretrial detention abuse?"
 * - "What percentage of detention orders lack proper justification?"
 * - "Which judges most often violate detention time limits?"
 * - "Success rate of release motions?"
 *
 * DEFENSE STRATEGIES:
 * - Motion for immediate release (Zahtjev za ukidanje pritvora)
 * - ECHR violation arguments
 * - Challenge justification grounds
 * - Cite regional disparity statistics
 * - Constitutional Court complaint
 */
class ExcessivePretensionDetector extends TopicAnalyzer
{
    protected string $topicName = 'excessive_pretrial_detention';

    protected string $topicDescription = 'Excessive pretrial detention violations';

    protected array $legalFramework = [
        'ZKP_Čl_122' => 'Razlozi pritvora (Grounds for pretrial detention)',
        'ZKP_Čl_123' => 'Trajanje pritvora (Duration of detention)',
        'Ustav_RH_Čl_24' => 'Pravo na osobnu slobodu (Right to liberty)',
        'ECHR_Article_5' => 'Right to liberty and security',
    ];

    /**
     * Duration limits by offense severity (in months)
     *
     * Based on ZKP Čl. 123
     */
    protected array $durationLimits = [
        'general_investigation' => 6,  // General crimes - investigation phase
        'general_total' => 12,          // General crimes - total before first instance
        'serious_investigation' => 12,  // Serious crimes (8+ years) - investigation
        'serious_total' => 24,          // Serious crimes - total before first instance
        'review_interval' => 1,         // Monthly review required
    ];

    /**
     * Analyze case for excessive pretrial detention
     *
     * @param  LegalCase  $case  The legal case
     * @param  array  $detentionData  Detention-specific data
     * @return array Analysis result
     */
    public function analyzeCase(LegalCase $case, array $detentionData): array
    {
        Log::info('ExcessivePretensionDetector: Analyzing case', [
            'case_id' => $case->id,
            'detention_months' => $detentionData['detention_months'] ?? 'unknown',
            'offense_severity' => $detentionData['offense_severity'] ?? 'unknown',
        ]);

        // Extract data
        $detentionMonths = $detentionData['detention_months'] ?? 0;
        $offenseSeverity = $detentionData['offense_severity'] ?? 'general'; // 'general' or 'serious'
        $justificationProvided = $detentionData['justification_provided'] ?? false;
        $justificationText = $detentionData['justification_text'] ?? '';
        $monthlyReviewsConducted = $detentionData['monthly_reviews_conducted'] ?? 0;
        $detentionGrounds = $detentionData['detention_grounds'] ?? []; // flight_risk, repeat_risk, evidence_destruction, public_order
        $offenseMaxPenalty = $detentionData['offense_max_penalty_years'] ?? 0;

        // Determine applicable limit
        $applicableLimit = $this->getApplicableLimit($offenseSeverity);

        // Analyze duration compliance
        $durationAnalysis = $this->analyzeDuration($detentionMonths, $applicableLimit, $offenseSeverity);

        // Detect violation patterns
        $violationPatterns = $this->detectPatterns([
            'detention_months' => $detentionMonths,
            'applicable_limit' => $applicableLimit,
            'justification_provided' => $justificationProvided,
            'justification_text' => $justificationText,
            'monthly_reviews_conducted' => $monthlyReviewsConducted,
            'detention_grounds' => $detentionGrounds,
            'offense_max_penalty' => $offenseMaxPenalty,
            'offense_severity' => $offenseSeverity,
            'duration_analysis' => $durationAnalysis,
        ]);

        // Calculate violation severity (0-100)
        $violationSeverity = $this->calculateViolationSeverity(
            $durationAnalysis,
            $justificationProvided,
            $monthlyReviewsConducted,
            $detentionMonths,
            $violationPatterns
        );

        // Generate defense strategy
        $defenseStrategy = $this->generateDefenseStrategy([
            'violation_severity' => $violationSeverity,
            'duration_analysis' => $durationAnalysis,
            'patterns' => $violationPatterns,
            'detention_months' => $detentionMonths,
            'justification_provided' => $justificationProvided,
        ]);

        $result = [
            'case_id' => $case->id,
            'violation_detected' => $violationSeverity >= 60,
            'violation_severity' => $violationSeverity,
            'detention_months' => $detentionMonths,
            'applicable_limit_months' => $applicableLimit,
            'offense_severity' => $offenseSeverity,
            'duration_analysis' => $durationAnalysis,
            'violation_patterns' => $violationPatterns,
            'defense_strategy' => $defenseStrategy,
            'recommended_action' => $violationSeverity >= 80 ? 'immediate_release' : 'release_motion',
            'legal_violations' => $this->identifyLegalViolations($violationPatterns),
        ];

        Log::info('ExcessivePretensionDetector: Analysis complete', [
            'case_id' => $case->id,
            'violation_detected' => $result['violation_detected'],
            'violation_severity' => $violationSeverity,
        ]);

        return $result;
    }

    /**
     * Get applicable detention limit based on offense severity
     *
     * @param  string  $offenseSeverity  Offense severity ('general' or 'serious')
     * @return int Limit in months
     */
    protected function getApplicableLimit(string $offenseSeverity): int
    {
        return $offenseSeverity === 'serious'
            ? $this->durationLimits['serious_total']
            : $this->durationLimits['general_total'];
    }

    /**
     * Analyze detention duration compliance
     *
     * @param  int  $detentionMonths  Actual detention in months
     * @param  int  $applicableLimit  Applicable legal limit
     * @param  string  $offenseSeverity  Offense severity
     * @return array Duration analysis
     */
    protected function analyzeDuration(int $detentionMonths, int $applicableLimit, string $offenseSeverity): array
    {
        $exceeds = $detentionMonths > $applicableLimit;
        $percentageOfLimit = $applicableLimit > 0 ? round(($detentionMonths / $applicableLimit) * 100, 1) : 0;
        $excessMonths = max(0, $detentionMonths - $applicableLimit);

        return [
            'detention_months' => $detentionMonths,
            'applicable_limit' => $applicableLimit,
            'exceeds_limit' => $exceeds,
            'excess_months' => $excessMonths,
            'percentage_of_limit' => $percentageOfLimit,
            'offense_severity' => $offenseSeverity,
            'analysis' => $exceeds
                ? "Detention ({$detentionMonths} months) exceeds legal limit ({$applicableLimit} months) by {$excessMonths} months - ILLEGAL"
                : "Detention ({$detentionMonths} months) within legal limit ({$applicableLimit} months)",
        ];
    }

    /**
     * Detect violation patterns
     *
     * @param  array  $data  Case data
     * @return array Detected patterns
     */
    protected function detectPatterns(array $data): array
    {
        $patterns = [];

        $detentionMonths = $data['detention_months'];
        $applicableLimit = $data['applicable_limit'];
        $justificationProvided = $data['justification_provided'];
        $justificationText = $data['justification_text'];
        $monthlyReviewsConducted = $data['monthly_reviews_conducted'];
        $detentionGrounds = $data['detention_grounds'];
        $offenseMaxPenalty = $data['offense_max_penalty'];
        $durationAnalysis = $data['duration_analysis'];

        // Pattern 1: Excessive Duration - Beyond statutory limits
        if ($durationAnalysis['exceeds_limit']) {
            $excessMonths = $durationAnalysis['excess_months'];
            $severity = min(100, 70 + ($excessMonths * 5)); // +5 per month over limit

            $patterns[] = [
                'type' => 'excessive_duration',
                'severity' => $severity,
                'description' => "Detention exceeds legal limit by {$excessMonths} months",
                'evidence' => "Detained: {$detentionMonths} months, Limit: {$applicableLimit} months, Excess: {$excessMonths} months",
                'legal_basis' => 'ZKP Čl. 123 violation - illegal detention beyond statutory limit',
            ];
        }

        // Pattern 2: No Justification - Missing or generic reasoning
        if (! $justificationProvided || strlen($justificationText) < 50) {
            $patterns[] = [
                'type' => 'no_justification',
                'severity' => 85,
                'description' => 'Detention order lacks proper justification or reasoning',
                'evidence' => $justificationProvided
                    ? "Generic justification provided (only {length($justificationText)} characters)"
                    : 'No justification provided in detention order',
                'legal_basis' => 'ZKP Čl. 122 violation - detention must be specifically justified',
            ];
        }

        // Pattern 3: Procedural Violations - Missing monthly reviews
        $expectedReviews = max(0, $detentionMonths - 1); // First month doesn't need review
        if ($monthlyReviewsConducted < $expectedReviews) {
            $missedReviews = $expectedReviews - $monthlyReviewsConducted;
            $severity = min(95, 75 + ($missedReviews * 5)); // +5 per missed review

            $patterns[] = [
                'type' => 'procedural_violations',
                'severity' => $severity,
                'description' => "Missing {$missedReviews} mandatory monthly reviews",
                'evidence' => "Detained: {$detentionMonths} months, Reviews required: {$expectedReviews}, Reviews conducted: {$monthlyReviewsConducted}",
                'legal_basis' => 'ZKP Čl. 123 violation - mandatory monthly review not conducted',
            ];
        }

        // Pattern 4: Proportionality Violation - Detention disproportionate to offense
        if ($offenseMaxPenalty > 0 && $detentionMonths >= $offenseMaxPenalty * 12 * 0.5) {
            // Detention >= 50% of maximum possible sentence
            $percentage = round(($detentionMonths / ($offenseMaxPenalty * 12)) * 100, 1);

            $patterns[] = [
                'type' => 'proportionality_violation',
                'severity' => 90,
                'description' => 'Detention disproportionate to offense severity',
                'evidence' => "Detained: {$detentionMonths} months ({$percentage}% of max sentence {$offenseMaxPenalty} years)",
                'legal_basis' => 'Ustav RH Čl. 24 + ECHR Article 5 - detention must be proportionate',
            ];
        }

        return $patterns;
    }

    /**
     * Calculate violation severity (0-100)
     *
     * @param  array  $durationAnalysis  Duration analysis
     * @param  bool  $justificationProvided  Whether justification provided
     * @param  int  $monthlyReviewsConducted  Number of reviews
     * @param  int  $detentionMonths  Total detention months
     * @param  array  $violationPatterns  Detected violation patterns
     * @return int Severity score
     */
    protected function calculateViolationSeverity(
        array $durationAnalysis,
        bool $justificationProvided,
        int $monthlyReviewsConducted,
        int $detentionMonths,
        array $violationPatterns
    ): int {
        $severity = 0;

        // Base score if exceeds limit
        if ($durationAnalysis['exceeds_limit']) {
            $severity = 60;

            // Add points based on how much over limit
            $excessMonths = $durationAnalysis['excess_months'];
            if ($excessMonths >= 6) {
                $severity += 30; // Severely over limit
            } elseif ($excessMonths >= 3) {
                $severity += 20; // Significantly over limit
            } else {
                $severity += 10; // Moderately over limit
            }
        }

        // Add points if no justification
        if (! $justificationProvided) {
            $severity += 15;
        }

        // Add points for missed reviews
        $expectedReviews = max(0, $detentionMonths - 1);
        $missedReviews = max(0, $expectedReviews - $monthlyReviewsConducted);
        if ($missedReviews > 0) {
            $severity += min(10, $missedReviews * 2); // +2 per missed review, max 10
        }

        // Cap at 100
        return min(100, $severity);
    }

    /**
     * Generate defense strategy
     *
     * @param  array  $analysis  Case analysis
     * @return array Defense strategies
     */
    protected function generateDefenseStrategy(array $analysis): array
    {
        $strategies = [];

        $severity = $analysis['violation_severity'];
        $durationAnalysis = $analysis['duration_analysis'];

        // Strategy 1: Immediate Release Motion
        if ($severity >= 80 || $durationAnalysis['exceeds_limit']) {
            $strategies[] = [
                'strategy' => 'immediate_release_motion',
                'priority' => 'critical',
                'title' => 'Zahtjev za hitno ukidanje pritvora (Immediate Release Motion)',
                'description' => 'File emergency motion for immediate release due to illegal detention',
                'legal_basis' => 'ZKP Čl. 123 exceeded - detention beyond statutory limit',
                'likelihood_of_success' => $severity >= 90 ? 'very_high' : 'high',
            ];
        }

        // Strategy 2: Standard Release Motion
        if ($severity >= 60 && $severity < 80) {
            $strategies[] = [
                'strategy' => 'release_motion',
                'priority' => 'high',
                'title' => 'Zahtjev za ukidanje pritvora (Release Motion)',
                'description' => 'File motion to terminate pretrial detention',
                'legal_basis' => $durationAnalysis['analysis'],
                'likelihood_of_success' => 'moderate',
            ];
        }

        // Strategy 3: ECHR Violation Argument
        if ($severity >= 70) {
            $strategies[] = [
                'strategy' => 'echr_violation',
                'priority' => 'high',
                'title' => 'ECHR Article 5 violation argument',
                'description' => 'Argue violation of European Convention on Human Rights',
                'legal_basis' => 'ECHR Article 5 - right to liberty violated by excessive detention',
            ];
        }

        // Strategy 4: Constitutional Court Complaint
        if ($durationAnalysis['exceeds_limit']) {
            $strategies[] = [
                'strategy' => 'constitutional_complaint',
                'priority' => 'medium',
                'title' => 'Ustavna tužba (Constitutional Court Complaint)',
                'description' => 'File complaint with Constitutional Court for violation of Ustav RH Čl. 24',
                'legal_basis' => 'Ustav RH Čl. 24 - right to liberty and security violated',
            ];
        }

        // Strategy 5: Regional Comparison
        $strategies[] = [
            'strategy' => 'regional_comparison',
            'priority' => 'medium',
            'title' => 'Statistički dokaz regionalnih razlika (Regional Disparity Evidence)',
            'description' => 'Use regional statistics to show detention abuse patterns',
            'legal_basis' => 'Equal protection / Načelo jednakosti pred zakonom',
        ];

        return $strategies;
    }

    /**
     * Identify legal violations
     *
     * @param  array  $patterns  Detected patterns
     * @return array Legal violations
     */
    protected function identifyLegalViolations(array $patterns): array
    {
        $violations = [];

        foreach ($patterns as $pattern) {
            $violations[] = [
                'pattern_type' => $pattern['type'],
                'severity' => $pattern['severity'],
                'legal_violation' => $pattern['legal_basis'],
                'description' => $pattern['description'],
            ];
        }

        return $violations;
    }

    /**
     * Get statistics for pretrial detention violations
     *
     * @param  array  $criteria  Search criteria
     * @return array Statistics
     */
    public function getStatistics(array $criteria): array
    {
        // Search for detention cases
        $cases = $this->searchCases($criteria);

        // Analyze all cases
        $analysis = $this->analyzeDetentionCases($cases);

        return $analysis;
    }

    /**
     * Analyze detention cases from search results
     *
     * @param  array  $searchResults  Search results from odluke.sudovi.hr
     * @return array Statistical analysis
     */
    protected function analyzeDetentionCases(array $searchResults): array
    {
        $totalCases = count($searchResults['cases'] ?? []);

        if ($totalCases === 0) {
            return [
                'total_cases' => 0,
                'analysis' => 'No cases found',
                'status' => 'no_data',
            ];
        }

        $violationCount = 0;
        $byViolationType = [];
        $byDurationRange = [];
        $averageDetentionMonths = 0;
        $totalDetentionMonths = 0;

        foreach ($searchResults['cases'] as $case) {
            // Extract detention info using AI
            $detentionInfo = $this->extractDetentionInfo($case);

            if ($detentionInfo) {
                $detentionMonths = $detentionInfo['detention_months'];
                $totalDetentionMonths += $detentionMonths;

                // Check if violation exists
                $applicableLimit = $this->getApplicableLimit($detentionInfo['offense_severity']);
                if ($detentionMonths > $applicableLimit ||
                    ! $detentionInfo['justification_provided'] ||
                    $detentionInfo['monthly_reviews_conducted'] < ($detentionMonths - 1)) {
                    $violationCount++;
                }

                // Count by duration range
                $durationRange = $this->getDurationRange($detentionMonths);
                $byDurationRange[$durationRange] = ($byDurationRange[$durationRange] ?? 0) + 1;

                // Count violation types (simplified)
                if ($detentionMonths > $applicableLimit) {
                    $byViolationType['excessive_duration'] = ($byViolationType['excessive_duration'] ?? 0) + 1;
                }
                if (! $detentionInfo['justification_provided']) {
                    $byViolationType['no_justification'] = ($byViolationType['no_justification'] ?? 0) + 1;
                }
            }
        }

        $averageDetentionMonths = $totalCases > 0 ? round($totalDetentionMonths / $totalCases, 1) : 0;
        $violationPercentage = $totalCases > 0 ? round(($violationCount / $totalCases) * 100, 1) : 0;

        return [
            'total_cases' => $totalCases,
            'violation_count' => $violationCount,
            'violation_percentage' => $violationPercentage,
            'average_detention_months' => $averageDetentionMonths,
            'by_violation_type' => $byViolationType,
            'by_duration_range' => $byDurationRange,
            'alarming_findings' => $this->generateAlarmingFindings($violationPercentage, $averageDetentionMonths),
            'status' => 'real_data',
        ];
    }

    /**
     * Extract detention information from case using AI
     *
     * @param  array  $case  Case data
     * @return array|null Detention information
     */
    protected function extractDetentionInfo(array $case): ?array
    {
        $decisionText = $case['text'] ?? $case['content'] ?? $case['decision_text'] ?? '';

        if (empty($decisionText)) {
            Log::warning('ExcessivePretensionDetector: No decision text found', [
                'case_id' => $case['case_id'] ?? 'unknown',
            ]);

            return null;
        }

        $prompt = <<<PROMPT
Extract pretrial detention (pritvor) information from this Croatian court decision.

Decision text:
{$decisionText}

Extract the following information:
1. Duration of pretrial detention in months (broj mjeseci u pritvoru)
2. Offense severity:
   - "serious" if max penalty >= 8 years (teža kaznena djela)
   - "general" if max penalty < 8 years
3. Was justification provided? (da li je obrazloženje dano?)
   - true if specific reasoning provided
   - false if generic/missing
4. Number of monthly reviews conducted (broj provedenih mjesečnih kontrola)
5. Maximum penalty for offense in years (maksimalna kazna u godinama)

Return JSON with this structure:
{
  "detention_months": <number>,
  "offense_severity": "general|serious",
  "justification_provided": true|false,
  "monthly_reviews_conducted": <number>,
  "offense_max_penalty_years": <number>,
  "confidence": "high|medium|low"
}

If information cannot be extracted, return:
{
  "detention_months": null,
  "offense_severity": null,
  "justification_provided": null,
  "monthly_reviews_conducted": null,
  "offense_max_penalty_years": null,
  "confidence": "none"
}
PROMPT;

        try {
            $response = $this->openAI->chat([
                [
                    'role' => 'system',
                    'content' => 'You are a Croatian legal document analyzer specializing in extracting structured data from court decisions about pretrial detention. Return valid JSON only.',
                ],
                ['role' => 'user', 'content' => $prompt],
            ], 'gpt-4o-mini', [
                'temperature' => 0.1,
                'max_tokens' => 300,
            ]);

            $content = $response['choices'][0]['message']['content'] ?? '';

            // Clean up markdown code blocks if present
            $content = preg_replace('/```json\s*/i', '', $content);
            $content = preg_replace('/```\s*$/i', '', $content);
            $content = trim($content);

            $extracted = json_decode($content, true);

            if (! is_array($extracted)) {
                Log::warning('ExcessivePretensionDetector: Invalid JSON from AI extraction');

                return null;
            }

            // Validate extracted data
            if (empty($extracted['detention_months']) || empty($extracted['offense_severity'])) {
                Log::debug('ExcessivePretensionDetector: Incomplete extraction', [
                    'extracted' => $extracted,
                ]);

                return null;
            }

            return [
                'detention_months' => (int) $extracted['detention_months'],
                'offense_severity' => $extracted['offense_severity'],
                'justification_provided' => $extracted['justification_provided'] ?? false,
                'monthly_reviews_conducted' => (int) ($extracted['monthly_reviews_conducted'] ?? 0),
                'offense_max_penalty_years' => (int) ($extracted['offense_max_penalty_years'] ?? 0),
                'confidence' => $extracted['confidence'] ?? 'unknown',
                'case_id' => $case['case_id'] ?? $case['case_number'] ?? 'unknown',
            ];

        } catch (\Exception $e) {
            Log::error('ExcessivePretensionDetector: AI extraction failed', [
                'error' => $e->getMessage(),
                'case_id' => $case['case_id'] ?? 'unknown',
            ]);

            return null;
        }
    }

    /**
     * Get duration range category
     *
     * @param  int  $months  Detention months
     * @return string Range category
     */
    protected function getDurationRange(int $months): string
    {
        if ($months <= 3) {
            return '0-3 months';
        }
        if ($months <= 6) {
            return '3-6 months';
        }
        if ($months <= 12) {
            return '6-12 months';
        }
        if ($months <= 24) {
            return '12-24 months';
        }

        return '24+ months';
    }

    /**
     * Generate alarming findings
     *
     * @param  float  $violationPercentage  Violation percentage
     * @param  float  $averageDetentionMonths  Average detention months
     * @return array Alarming findings
     */
    protected function generateAlarmingFindings(float $violationPercentage, float $averageDetentionMonths): array
    {
        $findings = [];

        if ($violationPercentage > 50) {
            $findings[] = "Više od {$violationPercentage}% slučajeva pritvora krši zakon - to je sistemski problem";
        } elseif ($violationPercentage > 30) {
            $findings[] = "{$violationPercentage}% slučajeva pokazuje kršenje zakona o pritvoru";
        } elseif ($violationPercentage > 20) {
            $findings[] = "{$violationPercentage}% slučajeva pritvora pokazuje zabrinjavajuću razinu kršenja zakona";
        }

        if ($averageDetentionMonths > 12) {
            $findings[] = "Prosječno trajanje pritvora ({$averageDetentionMonths} mjeseci) premašuje standardne limite";
        } elseif ($averageDetentionMonths > 8) {
            $findings[] = "Prosječno trajanje pritvora ({$averageDetentionMonths} mjeseci) je zabrinjavajuće visoko";
        }

        return $findings;
    }

    /**
     * Determine which region is worse
     *
     * @param  array  $stats1  Region 1 statistics
     * @param  array  $stats2  Region 2 statistics
     * @param  string  $region1  Region 1 name
     * @param  string  $region2  Region 2 name
     * @return array Worse region determination
     */
    protected function determineWorseRegion(
        array $stats1,
        array $stats2,
        string $region1,
        string $region2
    ): array {
        $percentage1 = $stats1['violation_percentage'] ?? 0;
        $percentage2 = $stats2['violation_percentage'] ?? 0;

        $worseRegion = $percentage1 > $percentage2 ? $region1 : $region2;
        $difference = round(abs($percentage1 - $percentage2), 1);

        return [
            'worse_region' => $worseRegion,
            'violation_percentage_difference' => $difference,
            'significance' => $difference > 20 ? 'very_significant' : ($difference > 10 ? 'significant' : 'minor'),
            'analysis' => "{$worseRegion} pokazuje {$difference}% višu stopu kršenja zakona o pritvoru",
        ];
    }

    /**
     * Get search keywords for detention cases
     *
     * @return array Keywords
     */
    protected function getSearchKeywords(): array
    {
        return [
            'pritvor',
            'pritvaranje',
            'mjera pritvora',
            'ukidanje pritvora',
            'produljenje pritvora',
            'ZKP 122',
            'ZKP 123',
            'osobna sloboda',
        ];
    }
}
