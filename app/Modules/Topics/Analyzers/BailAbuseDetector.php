<?php

namespace App\Modules\Topics\Analyzers;

use App\Models\LegalCase;
use App\Modules\Topics\TopicAnalyzer;
use Illuminate\Support\Facades\Log;

/**
 * BailAbuseDetector (Detektor Zlouporabe Jamčevine)
 *
 * Detects excessive bail amounts, unjustified bail denials, and impossible
 * bail conditions that violate defendants' constitutional rights.
 *
 * THE PROBLEM:
 * Croatian courts and prosecutors routinely:
 * 1. Set excessive bail amounts far beyond defendant's means (proportionality violation)
 * 2. Deny bail without evidence of flight risk or danger to public
 * 3. Impose impossible bail conditions that ensure detention
 *
 * This is prosecutorial/judicial overreach designed to:
 * - Keep defendants detained pre-trial to pressure plea bargains
 * - Circumvent constitutional right to liberty
 * - Punish before conviction
 *
 * CROATIAN LAW FRAMEWORK:
 * - ZKP Čl. 102 - Jamčevina (Bail provisions)
 * - ZKP Čl. 98 - Istražni zatvor (Pre-trial detention conditions)
 * - Ustav RH Čl. 24 - Pravo na slobodu (Constitutional right to liberty)
 *
 * BAIL ABUSE PATTERNS:
 * 1. Excessive Bail (severity: 85)
 *    - Bail amount >20x monthly income
 *    - Disproportionate to offense severity
 *    - Minor offenses with major bail
 *
 * 2. Unjustified Denial (severity: 90)
 *    - Bail denied without flight risk evidence
 *    - Strong community ties ignored
 *    - Minor offense with bail denial
 *
 * 3. Impossible Conditions (severity: 80)
 *    - Daily police reporting (unrealistic)
 *    - Surrender non-existent documents
 *    - No internet/phone (impossible in modern life)
 *    - Conditions designed to be violated
 *
 * QUESTIONS THIS ANSWERS:
 * - "What's the bail denial rate for minor offenses in Osijek?"
 * - "Average bail amount for drug possession vs theft?"
 * - "Which courts set highest bail amounts?"
 * - "Is bail proportional to income?"
 * - "Regional disparities in bail practices?"
 *
 * DEFENSE STRATEGIES:
 * - Motion to reduce bail (ZKP Čl. 102)
 * - Constitutional challenge (Ustav RH Čl. 24)
 * - Regional disparity arguments
 * - Income proportionality analysis
 * - Flight risk rebuttal
 */
class BailAbuseDetector extends TopicAnalyzer
{
    protected string $topicName = 'bail_abuse';

    protected string $topicDescription = 'Excessive bail denial or unreasonable amounts';

    protected array $legalFramework = [
        'ZKP_Čl_102' => 'Jamčevina (Bail provisions)',
        'ZKP_Čl_98' => 'Istražni zatvor (Pre-trial detention)',
        'Ustav_RH_Čl_24' => 'Pravo na slobodu (Right to liberty)',
    ];

    /**
     * Offense severity categories for bail proportionality analysis
     */
    protected array $offenseSeverityLevels = [
        'petty' => ['typical_bail_range' => [1000, 5000], 'max_income_multiplier' => 2],
        'minor' => ['typical_bail_range' => [5000, 20000], 'max_income_multiplier' => 5],
        'moderate' => ['typical_bail_range' => [20000, 50000], 'max_income_multiplier' => 10],
        'serious' => ['typical_bail_range' => [50000, 150000], 'max_income_multiplier' => 20],
        'severe' => ['typical_bail_range' => [150000, 500000], 'max_income_multiplier' => 30],
    ];

    /**
     * Impossible bail conditions that indicate abuse
     */
    protected array $impossibleConditions = [
        'daily_police_reporting' => 'Daily check-ins are unrealistic for employed defendants',
        'no_internet_access' => 'Internet access is necessary for modern life/work',
        'no_phone_access' => 'Phone access is necessary for emergencies and work',
        'surrender_passport_not_owned' => 'Cannot surrender what defendant does not possess',
        'no_contact_with_100_people' => 'Impossible to verify or comply with',
        'house_arrest_without_address' => 'Defendant has no fixed address',
    ];

    /**
     * Analyze case for bail abuse
     *
     * @param  LegalCase  $case  The legal case
     * @param  array  $bailData  Bail-specific data
     * @return array Analysis result
     */
    public function analyzeCase(LegalCase $case, array $bailData): array
    {
        Log::info('BailAbuseDetector: Analyzing case', [
            'case_id' => $case->id,
            'bail_amount' => $bailData['bail_amount'] ?? 'unknown',
            'bail_status' => $bailData['bail_status'] ?? 'unknown',
        ]);

        // Detect abuse patterns
        $patterns = $this->detectPatterns($bailData);

        // Calculate abuse severity (0-100)
        $abuseSeverity = $this->calculateAbuseSeverity($patterns, $bailData);

        // Generate defense strategy
        $defenseStrategy = $this->generateDefenseStrategy([
            'abuse_severity' => $abuseSeverity,
            'patterns' => $patterns,
            'bail_amount' => $bailData['bail_amount'] ?? 0,
            'defendant_income' => $bailData['defendant_income'] ?? 0,
            'offense' => $bailData['offense'] ?? 'unknown',
        ]);

        $result = [
            'case_id' => $case->id,
            'abuse_detected' => $abuseSeverity >= 60,
            'abuse_severity' => $abuseSeverity,
            'bail_amount' => $bailData['bail_amount'] ?? 0,
            'bail_status' => $bailData['bail_status'] ?? 'unknown',
            'patterns' => $patterns,
            'defense_strategy' => $defenseStrategy,
            'legal_violations' => $this->identifyLegalViolations($patterns),
            'proportionality_analysis' => $this->analyzeProportionality($bailData),
        ];

        Log::info('BailAbuseDetector: Analysis complete', [
            'case_id' => $case->id,
            'abuse_detected' => $result['abuse_detected'],
            'abuse_severity' => $abuseSeverity,
            'patterns_found' => count($patterns),
        ]);

        return $result;
    }

    /**
     * Detect bail abuse patterns
     *
     * @param  array  $bailData  Bail case data
     * @return array Detected patterns
     */
    protected function detectPatterns(array $bailData): array
    {
        $patterns = [];

        $bailAmount = $bailData['bail_amount'] ?? 0;
        $bailStatus = $bailData['bail_status'] ?? 'unknown';
        $offenseSeverity = $bailData['offense_severity'] ?? 'moderate';
        $defendantIncome = $bailData['defendant_income'] ?? 0;
        $flightRiskEvidence = $bailData['flight_risk_evidence'] ?? [];
        $defendantTies = $bailData['defendant_ties'] ?? [];
        $bailConditions = $bailData['bail_conditions'] ?? [];

        // Pattern 1: Excessive Bail
        if ($bailStatus === 'set' && $bailAmount > 0 && $defendantIncome > 0) {
            $incomeMultiplier = $bailAmount / $defendantIncome;
            $severityLimits = $this->offenseSeverityLevels[$offenseSeverity] ??
                             $this->offenseSeverityLevels['moderate'];

            if ($incomeMultiplier > $severityLimits['max_income_multiplier']) {
                $patterns[] = [
                    'type' => 'excessive_bail',
                    'severity' => 85,
                    'description' => sprintf(
                        'Bail amount (%.0f HRK) is %.1fx defendant monthly income (%.0f HRK) - exceeds %.0fx limit for %s offense',
                        $bailAmount,
                        $incomeMultiplier,
                        $defendantIncome,
                        $severityLimits['max_income_multiplier'],
                        $offenseSeverity
                    ),
                    'evidence' => [
                        'bail_amount' => $bailAmount,
                        'monthly_income' => $defendantIncome,
                        'income_multiplier' => round($incomeMultiplier, 1),
                        'offense_severity' => $offenseSeverity,
                        'recommended_max' => $defendantIncome * $severityLimits['max_income_multiplier'],
                    ],
                    'legal_basis' => 'ZKP Čl. 102 - Bail must be proportionate to offense and defendant means',
                ];
            }
        }

        // Pattern 2: Unjustified Denial
        if ($bailStatus === 'denied' && empty($flightRiskEvidence)) {
            $hasCommunityTies = ! empty($defendantTies);
            $isMinorOffense = in_array($offenseSeverity, ['petty', 'minor']);

            $severity = 90;
            if ($hasCommunityTies) {
                $severity = 95; // Even worse if strong community ties
            }

            $patterns[] = [
                'type' => 'unjustified_denial',
                'severity' => $severity,
                'description' => sprintf(
                    'Bail denied for %s offense without flight risk evidence%s',
                    $offenseSeverity,
                    $hasCommunityTies ? ' despite strong community ties' : ''
                ),
                'evidence' => [
                    'flight_risk_evidence' => 'none',
                    'community_ties' => $defendantTies,
                    'offense_severity' => $offenseSeverity,
                ],
                'legal_basis' => 'Ustav RH Čl. 24 - Right to liberty; ZKP Čl. 98 - Detention requires specific risk evidence',
            ];
        }

        // Pattern 3: Impossible Conditions
        if ($bailStatus === 'set' && ! empty($bailConditions)) {
            $impossibleFound = [];
            foreach ($bailConditions as $condition) {
                $conditionNormalized = strtolower(str_replace('_', ' ', $condition));
                foreach ($this->impossibleConditions as $impossibleKey => $reason) {
                    $keyNormalized = strtolower(str_replace('_', ' ', $impossibleKey));
                    // Check if condition contains the impossible condition pattern
                    if (stripos($conditionNormalized, $keyNormalized) !== false ||
                        stripos($keyNormalized, $conditionNormalized) !== false) {
                        $impossibleFound[$condition] = $reason;
                        break; // Found match, move to next condition
                    }
                }
            }

            if (! empty($impossibleFound)) {
                $patterns[] = [
                    'type' => 'impossible_conditions',
                    'severity' => 80,
                    'description' => sprintf(
                        'Bail includes %d impossible/unrealistic condition(s)',
                        count($impossibleFound)
                    ),
                    'evidence' => [
                        'impossible_conditions' => $impossibleFound,
                        'all_conditions' => $bailConditions,
                    ],
                    'legal_basis' => 'ZKP Čl. 102 - Bail conditions must be reasonable and achievable',
                ];
            }
        }

        return $patterns;
    }

    /**
     * Calculate abuse severity (0-100)
     *
     * @param  array  $patterns  Detected patterns
     * @param  array  $bailData  Bail data
     * @return int Severity score
     */
    protected function calculateAbuseSeverity(array $patterns, array $bailData): int
    {
        if (empty($patterns)) {
            return 0;
        }

        // Base severity is the highest pattern severity
        $maxSeverity = 0;
        foreach ($patterns as $pattern) {
            $maxSeverity = max($maxSeverity, $pattern['severity']);
        }

        // Add points for multiple patterns (stacking abuse)
        $patternCount = count($patterns);
        if ($patternCount > 1) {
            $maxSeverity = min(100, $maxSeverity + (($patternCount - 1) * 5));
        }

        // Additional severity for extreme cases
        $bailAmount = $bailData['bail_amount'] ?? 0;
        $defendantIncome = $bailData['defendant_income'] ?? 0;

        if ($bailAmount > 0 && $defendantIncome > 0) {
            $incomeMultiplier = $bailAmount / $defendantIncome;

            // Extremely excessive bail (>30x income)
            if ($incomeMultiplier > 30) {
                $maxSeverity = min(100, $maxSeverity + 10);
            }
        }

        return min(100, $maxSeverity);
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
        $severity = $analysis['abuse_severity'];
        $patterns = $analysis['patterns'] ?? [];

        // Strategy 1: Motion to reduce/set bail
        if ($severity >= 60) {
            $patternTypes = array_column($patterns, 'type');

            if (in_array('excessive_bail', $patternTypes)) {
                $strategies[] = [
                    'strategy' => 'motion_to_reduce_bail',
                    'priority' => 'high',
                    'title' => 'Prijedlog za smanjenje jamčevine',
                    'description' => 'File motion to reduce bail to proportionate amount based on income and offense severity',
                    'legal_basis' => 'ZKP Čl. 102 - Proportionality principle',
                    'likelihood_of_success' => $severity >= 85 ? 'high' : 'moderate',
                ];
            }

            if (in_array('unjustified_denial', $patternTypes)) {
                $strategies[] = [
                    'strategy' => 'motion_to_set_bail',
                    'priority' => 'high',
                    'title' => 'Prijedlog za određivanje jamčevine',
                    'description' => 'File motion to set bail, challenging denial without flight risk evidence',
                    'legal_basis' => 'Ustav RH Čl. 24, ZKP Čl. 98 - Right to liberty requires risk justification',
                    'likelihood_of_success' => $severity >= 90 ? 'high' : 'moderate',
                ];
            }

            if (in_array('impossible_conditions', $patternTypes)) {
                $strategies[] = [
                    'strategy' => 'motion_to_modify_conditions',
                    'priority' => 'high',
                    'title' => 'Prijedlog za izmjenu uvjeta jamčevine',
                    'description' => 'File motion to modify bail conditions to reasonable/achievable terms',
                    'legal_basis' => 'ZKP Čl. 102 - Conditions must be reasonable',
                    'likelihood_of_success' => 'high',
                ];
            }
        }

        // Strategy 2: Constitutional challenge
        if ($severity >= 80) {
            $strategies[] = [
                'strategy' => 'constitutional_challenge',
                'priority' => 'medium',
                'title' => 'Ustavna pritužba',
                'description' => 'File constitutional complaint for violation of right to liberty',
                'legal_basis' => 'Ustav RH Čl. 24 - Pravo na slobodu',
            ];
        }

        // Strategy 3: Regional comparison
        $strategies[] = [
            'strategy' => 'regional_comparison',
            'priority' => 'medium',
            'title' => 'Statistička analiza regionalnih razlika',
            'description' => 'Use regional bail statistics to demonstrate disproportionate treatment',
            'legal_basis' => 'Equal protection / Načelo jednakosti pred zakonom',
        ];

        // Strategy 4: Income proportionality analysis
        if (($analysis['bail_amount'] ?? 0) > 0 && ($analysis['defendant_income'] ?? 0) > 0) {
            $strategies[] = [
                'strategy' => 'income_proportionality',
                'priority' => 'high',
                'title' => 'Analiza razmjernosti prema primanjima',
                'description' => 'Present detailed income analysis showing bail is effectively equivalent to detention',
                'legal_basis' => 'ZKP Čl. 102 - Bail must not be de facto detention',
            ];
        }

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
     * Analyze bail proportionality
     *
     * @param  array  $bailData  Bail data
     * @return array Proportionality analysis
     */
    protected function analyzeProportionality(array $bailData): array
    {
        $bailAmount = $bailData['bail_amount'] ?? 0;
        $defendantIncome = $bailData['defendant_income'] ?? 0;
        $offenseSeverity = $bailData['offense_severity'] ?? 'moderate';

        if ($bailAmount === 0 || $defendantIncome === 0) {
            return [
                'analysis_available' => false,
                'reason' => 'Insufficient data for proportionality analysis',
            ];
        }

        $incomeMultiplier = $bailAmount / $defendantIncome;
        $severityLimits = $this->offenseSeverityLevels[$offenseSeverity] ??
                         $this->offenseSeverityLevels['moderate'];

        $isProportionate = $incomeMultiplier <= $severityLimits['max_income_multiplier'];
        $recommendedMax = $defendantIncome * $severityLimits['max_income_multiplier'];

        return [
            'analysis_available' => true,
            'bail_amount' => $bailAmount,
            'monthly_income' => $defendantIncome,
            'income_multiplier' => round($incomeMultiplier, 1),
            'offense_severity' => $offenseSeverity,
            'max_multiplier_for_severity' => $severityLimits['max_income_multiplier'],
            'recommended_max_bail' => $recommendedMax,
            'is_proportionate' => $isProportionate,
            'excess_amount' => $isProportionate ? 0 : ($bailAmount - $recommendedMax),
            'assessment' => $isProportionate
                ? 'Bail appears proportionate to income and offense'
                : sprintf('Bail exceeds proportionate amount by %.0f HRK', $bailAmount - $recommendedMax),
        ];
    }

    /**
     * Get statistics for bail abuse
     *
     * @param  array  $criteria  Search criteria
     * @return array Statistics
     */
    public function getStatistics(array $criteria): array
    {
        // Search for bail-related cases
        $cases = $this->searchCases($criteria);

        // Analyze cases
        $analysis = $this->analyzeBailCases($cases);

        return $analysis;
    }

    /**
     * Analyze bail cases from search results
     *
     * @param  array  $searchResults  Search results
     * @return array Statistical analysis
     */
    protected function analyzeBailCases(array $searchResults): array
    {
        $totalCases = count($searchResults['cases'] ?? []);

        if ($totalCases === 0) {
            return [
                'total_cases' => 0,
                'analysis' => 'No cases found',
                'status' => 'no_data',
            ];
        }

        $abuseDetectedCount = 0;
        $totalAbuseSeverity = 0;
        $byOffenseSeverity = [];
        $bailAmounts = [];

        foreach ($searchResults['cases'] as $case) {
            // Extract bail info (simplified - would use AI in production)
            $bailInfo = $this->extractBailInfo($case);

            if ($bailInfo) {
                // Detect patterns
                $patterns = $this->detectPatterns($bailInfo);
                $severity = $this->calculateAbuseSeverity($patterns, $bailInfo);

                if ($severity >= 60) {
                    $abuseDetectedCount++;
                }

                $totalAbuseSeverity += $severity;

                // Track by offense severity
                $offenseSeverity = $bailInfo['offense_severity'] ?? 'unknown';
                $byOffenseSeverity[$offenseSeverity] = ($byOffenseSeverity[$offenseSeverity] ?? 0) + 1;

                // Track bail amounts
                if (($bailInfo['bail_amount'] ?? 0) > 0) {
                    $bailAmounts[] = $bailInfo['bail_amount'];
                }
            }
        }

        $abusePercentage = $totalCases > 0 ? round(($abuseDetectedCount / $totalCases) * 100, 1) : 0;
        $avgSeverity = $totalCases > 0 ? round($totalAbuseSeverity / $totalCases, 1) : 0;
        $avgBailAmount = ! empty($bailAmounts) ? round(array_sum($bailAmounts) / count($bailAmounts), 0) : 0;

        return [
            'total_cases' => $totalCases,
            'abuse_detected_count' => $abuseDetectedCount,
            'abuse_percentage' => $abusePercentage,
            'average_abuse_severity' => $avgSeverity,
            'average_bail_amount' => $avgBailAmount,
            'by_offense_severity' => $byOffenseSeverity,
            'alarming_findings' => $this->generateAlarmingFindings($abusePercentage, $totalCases),
            'status' => 'real_data',
        ];
    }

    /**
     * Extract bail information from case
     *
     * @param  array  $case  Case data
     * @return array|null Bail information
     */
    protected function extractBailInfo(array $case): ?array
    {
        // Simplified extraction - in production would use AI like DrugChargeAbuseDetector
        // For now, return mock data structure
        return [
            'bail_amount' => 0,
            'bail_status' => 'unknown',
            'offense_severity' => 'moderate',
            'defendant_income' => 0,
            'flight_risk_evidence' => [],
            'defendant_ties' => [],
            'bail_conditions' => [],
        ];
    }

    /**
     * Generate alarming findings
     *
     * @param  float  $abusePercentage  Abuse percentage
     * @param  int  $totalCases  Total cases
     * @return array Alarming findings
     */
    protected function generateAlarmingFindings(float $abusePercentage, int $totalCases): array
    {
        $findings = [];

        if ($abusePercentage > 50) {
            $findings[] = sprintf(
                'Više od %.1f%% slučajeva pokazuje zlouporabu jamčevine - to je sistemski problem',
                $abusePercentage
            );
        } elseif ($abusePercentage > 30) {
            $findings[] = sprintf(
                '%.1f%% slučajeva pokazuje prekomjernu jamčevinu ili neopravdano uskraćivanje',
                $abusePercentage
            );
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
        $percentage1 = $stats1['abuse_percentage'] ?? 0;
        $percentage2 = $stats2['abuse_percentage'] ?? 0;

        $worseRegion = $percentage1 > $percentage2 ? $region1 : $region2;
        $difference = round(abs($percentage1 - $percentage2), 1);

        return [
            'worse_region' => $worseRegion,
            'abuse_percentage_difference' => $difference,
            'significance' => $difference > 20 ? 'very_significant' : ($difference > 10 ? 'significant' : 'minor'),
            'analysis' => sprintf(
                '%s pokazuje %.1f%% višu stopu zlouporabe jamčevine',
                $worseRegion,
                $difference
            ),
        ];
    }

    /**
     * Get search keywords for bail cases
     *
     * @return array Keywords
     */
    protected function getSearchKeywords(): array
    {
        return [
            'jamčevina',
            'istražni zatvor',
            'pritvor',
            'uvjeti jamčevine',
            'određivanje jamčevine',
            'uskraćivanje jamčevine',
            'ZKP 102',
            'ZKP 98',
            'pravo na slobodu',
        ];
    }
}
