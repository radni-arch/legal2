<?php

namespace App\Modules\Topics\Analyzers;

use App\Models\LegalCase;
use App\Modules\Topics\TopicAnalyzer;
use Illuminate\Support\Facades\Log;

/**
 * DisproportionateSentencingDetector (Detektor Neproporcionalne Kazne)
 *
 * Detects disproportionate sentencing violations - specifically when sentences
 * are outliers compared to similar cases, fail to consider mitigating factors,
 * improperly emphasize aggravating factors, or show comparative injustice.
 *
 * THE PROBLEM:
 * Croatian judges sometimes impose sentences that are disproportionately harsh
 * compared to similar cases, failing to properly consider mitigating circumstances
 * or over-emphasizing aggravating factors. This leads to:
 * 1. Inconsistent application of sentencing guidelines
 * 2. Regional disparities in punishment severity
 * 3. Violation of proportionality principles
 * 4. Unfair treatment of similarly situated defendants
 *
 * CROATIAN LAW FRAMEWORK:
 * The Croatian Criminal Code (Kazneni zakon - KZ) establishes clear principles
 * for sentencing:
 *
 * KZ Članak 45 - General rules for determining sentence:
 * - Court determines sentence within statutory limits
 * - Must consider purpose of punishment and gravity of offense
 * - Must consider mitigating and aggravating circumstances
 * - Sentence must be proportionate to offense severity
 *
 * KZ Članak 46 - Circumstances affecting sentence:
 * - Mitigating: minimal culpability, provocation, restitution, cooperation,
 *   first offense, family circumstances, remorse
 * - Aggravating: premeditation, cruelty, vulnerable victim, abuse of position,
 *   prior convictions, organized crime involvement
 *
 * ZKP Članak 528 - Appellate review of sentences:
 * - Sentences can be appealed as disproportionate
 * - Courts must provide reasoning for sentence imposed
 * - Comparison to similar cases is relevant
 *
 * VIOLATION TYPES:
 * 1. sentence_outlier - Sentence significantly exceeds norm for similar cases
 * 2. no_mitigating_consideration - Mitigating factors ignored or dismissed
 * 3. improper_aggravation - Aggravating factors improperly applied or overstated
 * 4. comparative_injustice - Similar defendants receive vastly different sentences
 *
 * DETECTION INDICATORS:
 * - Sentence in top 10% for offense type
 * - Mitigating factors present but not reflected in sentence
 * - Aggravating factors improperly weighted
 * - Sentence >50% higher than regional average for same offense
 * - No justification for deviation from sentencing norms
 *
 * QUESTIONS THIS ANSWERS:
 * - "Is this sentence an outlier for this offense?"
 * - "Were mitigating factors properly considered?"
 * - "Are sentences in Osijek harsher than Zadar for same crimes?"
 * - "What percentage of sentences are disproportionate?"
 * - "Which judges impose harshest sentences?"
 *
 * DEFENSE STRATEGIES:
 * - Appeal sentence as disproportionate (ZKP Čl. 528)
 * - Statistical comparison to similar cases
 * - Argument based on ignored mitigating factors
 * - Regional disparity analysis
 * - Expert testimony on sentencing norms
 */
class DisproportionateSentencingDetector extends TopicAnalyzer
{
    protected string $topicName = 'disproportionate_sentencing';

    protected string $topicDescription = 'Disproportionate sentencing violations';

    protected array $legalFramework = [
        'KZ_Čl_45' => 'Opća pravila za odmjeravanje kazne (General rules for determining sentence)',
        'KZ_Čl_46' => 'Okolnosti koje utječu na odmjeravanje kazne (Circumstances affecting sentence)',
        'ZKP_Čl_528' => 'Žalbeno preispitivanje kazne (Appellate review of sentences)',
    ];

    /**
     * Baseline sentence ranges by offense type (months)
     *
     * These are typical ranges based on Croatian case law.
     * Used to detect outliers.
     */
    protected array $sentenceBaselines = [
        'theft' => ['min' => 3, 'max' => 12, 'average' => 6],
        'krađa' => ['min' => 3, 'max' => 12, 'average' => 6],
        'assault' => ['min' => 6, 'max' => 18, 'average' => 10],
        'napad' => ['min' => 6, 'max' => 18, 'average' => 10],
        'fraud' => ['min' => 6, 'max' => 24, 'average' => 12],
        'prijevara' => ['min' => 6, 'max' => 24, 'average' => 12],
        'drug_possession' => ['min' => 3, 'max' => 12, 'average' => 6],
        'posjedovanje_droge' => ['min' => 3, 'max' => 12, 'average' => 6],
        'burglary' => ['min' => 12, 'max' => 36, 'average' => 18],
        'provalna_krađa' => ['min' => 12, 'max' => 36, 'average' => 18],
    ];

    /**
     * Analyze case for disproportionate sentencing
     *
     * @param  LegalCase  $case  The legal case
     * @param  array  $sentencingData  Sentencing specific data
     * @return array Analysis result
     */
    public function analyzeCase(LegalCase $case, array $sentencingData): array
    {
        Log::info('DisproportionateSentencingDetector: Analyzing case', [
            'case_id' => $case->id,
            'offense_type' => $sentencingData['offense_type'] ?? 'unknown',
            'sentence_months' => $sentencingData['sentence_months'] ?? 'unknown',
        ]);

        // Extract data
        $offenseType = strtolower($sentencingData['offense_type'] ?? '');
        $sentenceMonths = $sentencingData['sentence_months'] ?? 0;
        $mitigatingFactors = $sentencingData['mitigating_factors'] ?? [];
        $aggravatingFactors = $sentencingData['aggravating_factors'] ?? [];
        $regionalAverage = $sentencingData['regional_average'] ?? null;

        // Determine baseline for offense
        $baselineAnalysis = $this->analyzeBaseline($offenseType, $sentenceMonths);

        // Detect violation patterns
        $violationPatterns = $this->detectPatterns([
            'offense_type' => $offenseType,
            'sentence_months' => $sentenceMonths,
            'mitigating_factors' => $mitigatingFactors,
            'aggravating_factors' => $aggravatingFactors,
            'baseline_analysis' => $baselineAnalysis,
            'regional_average' => $regionalAverage,
        ]);

        // Calculate disproportionality severity (0-100)
        $severity = $this->calculateDisproportionalitySeverity(
            $baselineAnalysis,
            $mitigatingFactors,
            $aggravatingFactors,
            $regionalAverage,
            $sentenceMonths
        );

        // Generate defense strategy
        $defenseStrategy = $this->generateDefenseStrategy([
            'severity' => $severity,
            'baseline_analysis' => $baselineAnalysis,
            'patterns' => $violationPatterns,
            'offense_type' => $offenseType,
            'sentence_months' => $sentenceMonths,
            'mitigating_factors' => $mitigatingFactors,
        ]);

        $result = [
            'case_id' => $case->id,
            'disproportionate_detected' => $severity >= 60,
            'severity' => $severity,
            'offense_type' => $offenseType,
            'sentence_months' => $sentenceMonths,
            'baseline_analysis' => $baselineAnalysis,
            'violation_patterns' => $violationPatterns,
            'defense_strategy' => $defenseStrategy,
            'recommended_sentence_range' => $this->getRecommendedSentenceRange(
                $offenseType,
                $mitigatingFactors,
                $aggravatingFactors
            ),
            'legal_violations' => $this->identifyLegalViolations($violationPatterns),
        ];

        Log::info('DisproportionateSentencingDetector: Analysis complete', [
            'case_id' => $case->id,
            'disproportionate_detected' => $result['disproportionate_detected'],
            'severity' => $severity,
        ]);

        return $result;
    }

    /**
     * Analyze if sentence is within baseline for offense type
     *
     * @param  string  $offenseType  Offense type
     * @param  int  $sentenceMonths  Actual sentence in months
     * @return array Baseline analysis
     */
    protected function analyzeBaseline(string $offenseType, int $sentenceMonths): array
    {
        $offenseTypeLower = strtolower($offenseType);

        // Find matching offense type
        $baseline = null;
        foreach ($this->sentenceBaselines as $offense => $range) {
            if (stripos($offenseTypeLower, $offense) !== false || stripos($offense, $offenseTypeLower) !== false) {
                $baseline = $range;
                break;
            }
        }

        if (! $baseline) {
            return [
                'baseline_found' => false,
                'is_outlier' => null,
                'analysis' => 'No established baseline for this offense type',
            ];
        }

        $average = $baseline['average'];
        $max = $baseline['max'];
        $min = $baseline['min'];

        $isOutlier = $sentenceMonths > $max;
        $percentageOfAverage = $average > 0 ? round(($sentenceMonths / $average) * 100, 1) : 0;
        $deviationFromAverage = $sentenceMonths - $average;

        return [
            'baseline_found' => true,
            'baseline_min' => $min,
            'baseline_max' => $max,
            'baseline_average' => $average,
            'actual_sentence' => $sentenceMonths,
            'is_outlier' => $isOutlier,
            'percentage_of_average' => $percentageOfAverage,
            'deviation_from_average' => $deviationFromAverage,
            'analysis' => $isOutlier
                ? "Sentence ({$sentenceMonths} months) exceeds typical maximum ({$max} months) - likely disproportionate"
                : "Sentence ({$sentenceMonths} months) is within typical range ({$min}-{$max} months)",
        ];
    }

    /**
     * Detect disproportionate sentencing patterns
     *
     * @param  array  $data  Case data
     * @return array Detected patterns
     */
    protected function detectPatterns(array $data): array
    {
        $patterns = [];

        $sentenceMonths = $data['sentence_months'];
        $mitigatingFactors = $data['mitigating_factors'] ?? [];
        $aggravatingFactors = $data['aggravating_factors'] ?? [];
        $baselineAnalysis = $data['baseline_analysis'];
        $regionalAverage = $data['regional_average'];

        // Pattern 1: Sentence outlier (exceeds baseline maximum)
        if ($baselineAnalysis['is_outlier'] ?? false) {
            $patterns[] = [
                'type' => 'sentence_outlier',
                'severity' => 85,
                'description' => 'Sentence significantly exceeds typical maximum for this offense',
                'evidence' => "Sentence: {$sentenceMonths} months, Baseline max: {$baselineAnalysis['baseline_max']} months",
                'legal_basis' => 'KZ Čl. 45 - Violation of proportionality principle',
            ];
        }

        // Pattern 2: No mitigating factor consideration
        if (! empty($mitigatingFactors) && ($baselineAnalysis['percentage_of_average'] ?? 0) >= 100) {
            $patterns[] = [
                'type' => 'no_mitigating_consideration',
                'severity' => 80,
                'description' => 'Mitigating factors present but not reflected in sentence',
                'evidence' => 'Mitigating factors: '.implode(', ', $mitigatingFactors).
                    ' - Sentence at or above average despite mitigation',
                'legal_basis' => 'KZ Čl. 46 - Failure to properly consider mitigating circumstances',
            ];
        }

        // Pattern 3: Improper aggravation
        if (empty($aggravatingFactors) && ($baselineAnalysis['percentage_of_average'] ?? 0) > 150) {
            $patterns[] = [
                'type' => 'improper_aggravation',
                'severity' => 90,
                'description' => 'Sentence far above average with no aggravating factors',
                'evidence' => "No aggravating factors present, yet sentence is {$baselineAnalysis['percentage_of_average']}% of average",
                'legal_basis' => 'KZ Čl. 46 - Improper application of aggravating circumstances',
            ];
        }

        // Pattern 4: Comparative injustice (regional disparity)
        if ($regionalAverage !== null && $sentenceMonths > ($regionalAverage * 1.5)) {
            $patterns[] = [
                'type' => 'comparative_injustice',
                'severity' => 85,
                'description' => 'Sentence significantly exceeds regional average for same offense',
                'evidence' => "Sentence: {$sentenceMonths} months, Regional average: {$regionalAverage} months",
                'legal_basis' => 'ZKP Čl. 528 - Comparative sentencing disparities',
            ];
        }

        return $patterns;
    }

    /**
     * Calculate disproportionality severity (0-100)
     *
     * @param  array  $baselineAnalysis  Baseline analysis
     * @param  array  $mitigatingFactors  Mitigating factors
     * @param  array  $aggravatingFactors  Aggravating factors
     * @param  int|null  $regionalAverage  Regional average sentence
     * @param  int  $sentenceMonths  Actual sentence
     * @return int Severity score
     */
    protected function calculateDisproportionalitySeverity(
        array $baselineAnalysis,
        array $mitigatingFactors,
        array $aggravatingFactors,
        ?int $regionalAverage,
        int $sentenceMonths
    ): int {
        $severity = 0;

        // Base score if outlier
        if ($baselineAnalysis['is_outlier'] ?? false) {
            $severity = 60;
        }

        // Add points based on deviation percentage
        if (isset($baselineAnalysis['percentage_of_average'])) {
            $percentage = $baselineAnalysis['percentage_of_average'];

            if ($percentage > 200) {
                $severity += 30; // More than double average
            } elseif ($percentage > 150) {
                $severity += 20; // 50-100% above average
            } elseif ($percentage > 125) {
                $severity += 10; // 25-50% above average
            }
        }

        // Add points if mitigating factors ignored
        if (! empty($mitigatingFactors) && ($baselineAnalysis['percentage_of_average'] ?? 0) >= 100) {
            $severity += 10;
        }

        // Add points if no aggravating factors but high sentence
        if (empty($aggravatingFactors) && ($baselineAnalysis['percentage_of_average'] ?? 0) > 150) {
            $severity += 10;
        }

        // Add points for regional disparity
        if ($regionalAverage !== null && $sentenceMonths > ($regionalAverage * 1.5)) {
            $severity += 10;
        }

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

        $severity = $analysis['severity'];

        if ($severity >= 60) {
            $strategies[] = [
                'strategy' => 'appeal_sentence',
                'priority' => 'high',
                'title' => 'Žalba zbog neproporcionalne kazne (Appeal disproportionate sentence)',
                'description' => 'File appeal arguing sentence is disproportionate to offense',
                'legal_basis' => 'ZKP Čl. 528 - Appellate review of disproportionate sentences',
                'likelihood_of_success' => $severity >= 80 ? 'high' : 'moderate',
            ];
        }

        if (! empty($analysis['mitigating_factors'])) {
            $strategies[] = [
                'strategy' => 'mitigating_factors_argument',
                'priority' => 'high',
                'title' => 'Argument o zanemarenim olakotnim okolnostima',
                'description' => 'Argue that mitigating factors were improperly ignored or underweighted',
                'legal_basis' => 'KZ Čl. 46 - Court must consider all mitigating circumstances',
            ];
        }

        if (isset($analysis['baseline_analysis']['analysis'])) {
            $strategies[] = [
                'strategy' => 'comparative_analysis',
                'priority' => 'high',
                'title' => 'Statistička usporedba sa sličnim slučajevima',
                'description' => $analysis['baseline_analysis']['analysis'],
                'legal_basis' => 'Comparative sentencing analysis from similar cases',
            ];
        }

        $strategies[] = [
            'strategy' => 'regional_disparity',
            'priority' => 'medium',
            'title' => 'Argument regionalnih razlika',
            'description' => 'Use regional statistics to show sentencing disparities',
            'legal_basis' => 'Equal protection / Načelo jednakosti pred zakonom',
        ];

        return $strategies;
    }

    /**
     * Get recommended sentence range
     *
     * @param  string  $offenseType  Offense type
     * @param  array  $mitigatingFactors  Mitigating factors
     * @param  array  $aggravatingFactors  Aggravating factors
     * @return array Recommended sentence range
     */
    protected function getRecommendedSentenceRange(
        string $offenseType,
        array $mitigatingFactors,
        array $aggravatingFactors
    ): array {
        $offenseTypeLower = strtolower($offenseType);

        // Find baseline
        $baseline = null;
        foreach ($this->sentenceBaselines as $offense => $range) {
            if (stripos($offenseTypeLower, $offense) !== false || stripos($offense, $offenseTypeLower) !== false) {
                $baseline = $range;
                break;
            }
        }

        if (! $baseline) {
            return [
                'min' => null,
                'max' => null,
                'recommended' => null,
                'reasoning' => 'No baseline available for this offense type',
            ];
        }

        $min = $baseline['min'];
        $max = $baseline['max'];
        $average = $baseline['average'];

        // Adjust for mitigating factors
        if (! empty($mitigatingFactors)) {
            $adjustment = count($mitigatingFactors) * 0.1; // 10% reduction per factor
            $average = max($min, $average * (1 - $adjustment));
        }

        // Adjust for aggravating factors
        if (! empty($aggravatingFactors)) {
            $adjustment = count($aggravatingFactors) * 0.15; // 15% increase per factor
            $average = min($max, $average * (1 + $adjustment));
        }

        return [
            'min' => $min,
            'max' => $max,
            'recommended' => round($average),
            'reasoning' => $this->getRecommendationReasoning($mitigatingFactors, $aggravatingFactors),
        ];
    }

    /**
     * Get recommendation reasoning
     *
     * @param  array  $mitigatingFactors  Mitigating factors
     * @param  array  $aggravatingFactors  Aggravating factors
     * @return string Reasoning
     */
    protected function getRecommendationReasoning(array $mitigatingFactors, array $aggravatingFactors): string
    {
        $parts = ['Based on typical sentencing for this offense'];

        if (! empty($mitigatingFactors)) {
            $parts[] = 'reduced for '.count($mitigatingFactors).' mitigating factor(s): '.
                implode(', ', $mitigatingFactors);
        }

        if (! empty($aggravatingFactors)) {
            $parts[] = 'increased for '.count($aggravatingFactors).' aggravating factor(s): '.
                implode(', ', $aggravatingFactors);
        }

        return implode('; ', $parts);
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
     * Get statistics for disproportionate sentencing
     *
     * @param  array  $criteria  Search criteria
     * @return array Statistics
     */
    public function getStatistics(array $criteria): array
    {
        // Search for sentencing cases
        $cases = $this->searchCases($criteria);

        // Analyze all cases
        $analysis = $this->analyzeSentencingCases($cases);

        return $analysis;
    }

    /**
     * Analyze sentencing cases from search results
     *
     * @param  array  $searchResults  Search results from odluke.sudovi.hr
     * @return array Statistical analysis
     */
    protected function analyzeSentencingCases(array $searchResults): array
    {
        $totalCases = count($searchResults['cases'] ?? []);

        if ($totalCases === 0) {
            return [
                'total_cases' => 0,
                'analysis' => 'No cases found',
                'status' => 'no_data',
            ];
        }

        $disproportionateCount = 0;
        $byOffenseType = [];
        $bySeverityLevel = [
            'low' => 0,
            'moderate' => 0,
            'high' => 0,
            'severe' => 0,
        ];

        foreach ($searchResults['cases'] as $case) {
            // Extract sentencing info using AI
            $sentencingInfo = $this->extractSentencingInfo($case);

            if ($sentencingInfo) {
                // Check if disproportionate
                $baselineAnalysis = $this->analyzeBaseline(
                    $sentencingInfo['offense_type'],
                    $sentencingInfo['sentence_months']
                );

                $severity = $this->calculateDisproportionalitySeverity(
                    $baselineAnalysis,
                    $sentencingInfo['mitigating_factors'] ?? [],
                    $sentencingInfo['aggravating_factors'] ?? [],
                    null,
                    $sentencingInfo['sentence_months']
                );

                if ($severity >= 60) {
                    $disproportionateCount++;
                }

                // Categorize by severity
                if ($severity >= 85) {
                    $bySeverityLevel['severe']++;
                } elseif ($severity >= 70) {
                    $bySeverityLevel['high']++;
                } elseif ($severity >= 60) {
                    $bySeverityLevel['moderate']++;
                } else {
                    $bySeverityLevel['low']++;
                }

                // Count by offense type
                $offenseType = $sentencingInfo['offense_type'];
                $byOffenseType[$offenseType] = ($byOffenseType[$offenseType] ?? 0) + 1;
            }
        }

        $disproportionatePercentage = $totalCases > 0 ? round(($disproportionateCount / $totalCases) * 100, 1) : 0;

        return [
            'total_cases' => $totalCases,
            'disproportionate_count' => $disproportionateCount,
            'disproportionate_percentage' => $disproportionatePercentage,
            'by_offense_type' => $byOffenseType,
            'by_severity_level' => $bySeverityLevel,
            'alarming_findings' => $this->generateAlarmingFindings($disproportionatePercentage, $totalCases),
            'status' => 'real_data',
        ];
    }

    /**
     * Extract sentencing information from case using AI
     *
     * @param  array  $case  Case data
     * @return array|null Sentencing information
     */
    protected function extractSentencingInfo(array $case): ?array
    {
        $decisionText = $case['text'] ?? $case['content'] ?? $case['decision_text'] ?? '';

        if (empty($decisionText)) {
            Log::warning('DisproportionateSentencingDetector: No decision text found', [
                'case_id' => $case['case_id'] ?? 'unknown',
            ]);

            return null;
        }

        $prompt = <<<PROMPT
Extract sentencing information from this Croatian court decision.

Decision text:
{$decisionText}

Extract the following information:
1. Offense type (theft/krađa, assault/napad, fraud/prijevara, drug possession, burglary, etc.)
2. Sentence imposed (in months of imprisonment)
3. Mitigating factors mentioned:
   - First offense (prvo kazneno djelo)
   - Remorse (žaljenje, kajanje)
   - Cooperation (suradnja)
   - Restitution (naknada štete)
   - Family circumstances (obiteljske okolnosti)
   - Provocation (provokacija)
4. Aggravating factors mentioned:
   - Prior convictions (prethodne osude)
   - Premeditation (predumišljaj)
   - Cruelty (okrutnost)
   - Vulnerable victim (ranjiva žrtva)
   - Abuse of position (zloupotreba položaja)

Return JSON with this structure:
{
  "offense_type": "theft|assault|fraud|drug_possession|burglary|other",
  "sentence_months": <number>,
  "mitigating_factors": ["first_offense", "remorse", "cooperation"],
  "aggravating_factors": ["prior_convictions", "premeditation"],
  "confidence": "high|medium|low"
}

If information cannot be extracted, return:
{
  "offense_type": null,
  "sentence_months": null,
  "mitigating_factors": [],
  "aggravating_factors": [],
  "confidence": "none"
}
PROMPT;

        try {
            $response = $this->openAI->chat([
                [
                    'role' => 'system',
                    'content' => 'You are a Croatian legal document analyzer specializing in extracting structured data from court decisions about sentencing. Return valid JSON only.',
                ],
                ['role' => 'user', 'content' => $prompt],
            ], 'gpt-4o-mini', [
                'temperature' => 0.1, // Very low temperature for factual extraction
                'max_tokens' => 300,
            ]);

            $content = $response['choices'][0]['message']['content'] ?? '';

            // Clean up markdown code blocks if present
            $content = preg_replace('/```json\s*/i', '', $content);
            $content = preg_replace('/```\s*$/i', '', $content);
            $content = trim($content);

            $extracted = json_decode($content, true);

            if (! is_array($extracted)) {
                Log::warning('DisproportionateSentencingDetector: Invalid JSON from AI extraction');

                return null;
            }

            // Validate extracted data
            if (empty($extracted['offense_type']) || empty($extracted['sentence_months'])) {
                Log::debug('DisproportionateSentencingDetector: Incomplete extraction', [
                    'extracted' => $extracted,
                ]);

                return null;
            }

            return [
                'offense_type' => strtolower($extracted['offense_type']),
                'sentence_months' => (int) $extracted['sentence_months'],
                'mitigating_factors' => $extracted['mitigating_factors'] ?? [],
                'aggravating_factors' => $extracted['aggravating_factors'] ?? [],
                'confidence' => $extracted['confidence'] ?? 'unknown',
                'case_id' => $case['case_id'] ?? $case['case_number'] ?? 'unknown',
            ];

        } catch (\Exception $e) {
            Log::error('DisproportionateSentencingDetector: AI extraction failed', [
                'error' => $e->getMessage(),
                'case_id' => $case['case_id'] ?? 'unknown',
            ]);

            return null;
        }
    }

    /**
     * Generate alarming findings
     *
     * @param  float  $disproportionatePercentage  Disproportionate percentage
     * @param  int  $totalCases  Total cases
     * @return array Alarming findings
     */
    protected function generateAlarmingFindings(float $disproportionatePercentage, int $totalCases): array
    {
        $findings = [];

        if ($disproportionatePercentage > 50) {
            $findings[] = "Više od {$disproportionatePercentage}% kazni je neproporcionalno - to je sistemski problem";
        } elseif ($disproportionatePercentage > 30) {
            $findings[] = "{$disproportionatePercentage}% slučajeva pokazuje neproporcionalne kazne";
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
        $percentage1 = $stats1['disproportionate_percentage'] ?? 0;
        $percentage2 = $stats2['disproportionate_percentage'] ?? 0;

        $worseRegion = $percentage1 > $percentage2 ? $region1 : $region2;
        $difference = round(abs($percentage1 - $percentage2), 1);

        return [
            'worse_region' => $worseRegion,
            'disproportionate_percentage_difference' => $difference,
            'significance' => $difference > 20 ? 'very_significant' : ($difference > 10 ? 'significant' : 'minor'),
            'analysis' => "{$worseRegion} pokazuje {$difference}% višu stopu neproporcionalne kazne",
        ];
    }

    /**
     * Get search keywords for sentencing cases
     *
     * @return array Keywords
     */
    protected function getSearchKeywords(): array
    {
        return [
            'odmjeravanje kazne',
            'kazna zatvora',
            'olakotne okolnosti',
            'otegotne okolnosti',
            'neproporcionalna kazna',
            'KZ 45',
            'KZ 46',
        ];
    }
}
