<?php

namespace App\Modules\Topics\Analyzers;

use App\Models\LegalCase;
use App\Modules\Topics\TopicAnalyzer;
use Illuminate\Support\Facades\Log;

/**
 * DrugChargeAbuseDetector (Detektor Zlouporabe Optužbi za Drogu)
 *
 * Detects overcharging in drug cases - specifically charging "dealing/trafficking"
 * (trgovanje drogom) when evidence suggests personal use (posjedovanje za osobnu upotrebu).
 *
 * THE PROBLEM:
 * Croatian prosecutors routinely charge defendants with drug dealing/trafficking
 * for amounts that clearly indicate personal use. This is prosecutorial overreach
 * designed to:
 * 1. Leverage harsher sentences to force plea bargains
 * 2. Increase conviction statistics
 * 3. Justify invasive investigations
 *
 * CROATIAN LAW THRESHOLDS:
 * While Croatian law doesn't specify exact weight thresholds, case law
 * and practice generally recognize:
 *
 * Cannabis (marihuana):
 * - Personal use: Up to 30-50g typically
 * - Dealing: >50g or evidence of distribution intent
 *
 * Cocaine/Heroin (kokain/heroin):
 * - Personal use: Up to 1-2g typically
 * - Dealing: >2g or evidence of distribution intent
 *
 * MDMA/Ecstasy:
 * - Personal use: Up to 5-10 pills typically
 * - Dealing: >10 pills or evidence of distribution intent
 *
 * LEGAL FRAMEWORK:
 * - Zakon o suzbijanju zlouporabe opojnih droga (ZoSZOOD)
 * - Kazneni zakon Čl. 190 - Neovlaštena proizvodnja i promet (dealing)
 * - Kazneni zakon Čl. 173 - Omogućavanje uzimanja opojnih droga (personal use)
 * - ZKP Čl. 179 - Načelo razmjernosti
 *
 * OVERCHARGING INDICATORS:
 * 1. Small amounts charged as dealing (e.g., 30g cannabis = dealing)
 * 2. No evidence of sales (scales, baggies, large cash, phone records)
 * 3. No prior dealing history
 * 4. Personal use paraphernalia present (pipes, rolling papers)
 * 5. Amount consistent with personal consumption
 * 6. No testimony of sales or distribution
 *
 * QUESTIONS THIS ANSWERS:
 * - "How many dealing charges for <30g cannabis in 2025?"
 * - "Is Osijek worse than Zadar for drug overcharging?"
 * - "What percentage of drug cases are overcharged?"
 * - "Which prosecutors most often overcharge drug cases?"
 * - "Success rate of charge reduction motions?"
 *
 * DEFENSE STRATEGIES:
 * - Motion to reduce charges (KZ Čl. 173 instead of Čl. 190)
 * - Challenge probable cause for dealing charge
 * - Cite case law on amount thresholds
 * - Expert testimony on personal use vs dealing amounts
 * - Regional disparity arguments (Zadar vs Osijek)
 */
class DrugChargeAbuseDetector extends TopicAnalyzer
{
    protected string $topicName = 'drug_charge_severity';

    protected string $topicDescription = 'Overcharging in drug cases (dealing charges for personal use amounts)';

    protected array $legalFramework = [
        'KZ_Čl_190' => 'Neovlaštena proizvodnja i promet opojnim drogama (Dealing)',
        'KZ_Čl_173' => 'Omogućavanje uzimanja opojnih droga (Personal use)',
        'ZoSZOOD' => 'Zakon o suzbijanju zlouporabe opojnih droga',
        'ZKP_Čl_179' => 'Načelo razmjernosti',
    ];

    /**
     * Personal use thresholds by drug type (grams or pills)
     *
     * These are based on case law and practice, not statutory limits.
     * Conservative thresholds - anything above is harder to defend as personal use.
     */
    protected array $personalUseThresholds = [
        'cannabis' => ['weight_g' => 30, 'typical_range' => '20-50g'],
        'marijuana' => ['weight_g' => 30, 'typical_range' => '20-50g'],
        'marihuana' => ['weight_g' => 30, 'typical_range' => '20-50g'],
        'cocaine' => ['weight_g' => 1, 'typical_range' => '0.5-2g'],
        'kokain' => ['weight_g' => 1, 'typical_range' => '0.5-2g'],
        'heroin' => ['weight_g' => 1, 'typical_range' => '0.5-2g'],
        'ecstasy' => ['weight_pills' => 5, 'typical_range' => '3-10 pills'],
        'mdma' => ['weight_pills' => 5, 'typical_range' => '3-10 pills'],
        'amphetamine' => ['weight_g' => 2, 'typical_range' => '1-3g'],
        'methamphetamine' => ['weight_g' => 1, 'typical_range' => '0.5-2g'],
    ];

    /**
     * Analyze case for drug charge overcharging
     *
     * @param  LegalCase  $case  The legal case
     * @param  array  $drugCaseData  Drug case specific data
     * @return array Analysis result
     */
    public function analyzeCase(LegalCase $case, array $drugCaseData): array
    {
        Log::info('DrugChargeAbuseDetector: Analyzing case', [
            'case_id' => $case->id,
            'drug_type' => $drugCaseData['drug_type'] ?? 'unknown',
            'amount' => $drugCaseData['amount'] ?? 'unknown',
        ]);

        // Extract data
        $drugType = strtolower($drugCaseData['drug_type'] ?? '');
        $amount = $drugCaseData['amount'] ?? 0;
        $chargedAs = $drugCaseData['charged_as'] ?? 'unknown'; // 'dealing' or 'personal_use'
        $evidenceOfDealing = $drugCaseData['evidence_of_dealing'] ?? [];

        // Determine if amount is within personal use threshold
        $thresholdAnalysis = $this->analyzeThreshold($drugType, $amount);

        // Detect overcharging patterns
        $overchargingPatterns = $this->detectPatterns([
            'drug_type' => $drugType,
            'amount' => $amount,
            'charged_as' => $chargedAs,
            'evidence_of_dealing' => $evidenceOfDealing,
            'threshold_analysis' => $thresholdAnalysis,
        ]);

        // Calculate overcharge severity (0-100)
        $overchargeSeverity = $this->calculateOverchargeSeverity(
            $thresholdAnalysis,
            $chargedAs,
            $evidenceOfDealing
        );

        // Generate defense strategy
        $defenseStrategy = $this->generateDefenseStrategy([
            'overcharge_severity' => $overchargeSeverity,
            'threshold_analysis' => $thresholdAnalysis,
            'patterns' => $overchargingPatterns,
            'drug_type' => $drugType,
            'amount' => $amount,
        ]);

        $result = [
            'case_id' => $case->id,
            'overcharge_detected' => $overchargeSeverity >= 60,
            'overcharge_severity' => $overchargeSeverity,
            'drug_type' => $drugType,
            'amount' => $amount,
            'charged_as' => $chargedAs,
            'threshold_analysis' => $thresholdAnalysis,
            'overcharging_patterns' => $overchargingPatterns,
            'defense_strategy' => $defenseStrategy,
            'recommended_charge' => $thresholdAnalysis['personal_use_likely'] ? 'KZ Čl. 173' : 'KZ Čl. 190',
            'legal_violations' => $this->identifyLegalViolations($overchargingPatterns),
        ];

        Log::info('DrugChargeAbuseDetector: Analysis complete', [
            'case_id' => $case->id,
            'overcharge_detected' => $result['overcharge_detected'],
            'overcharge_severity' => $overchargeSeverity,
        ]);

        return $result;
    }

    /**
     * Analyze if amount is within personal use threshold
     *
     * @param  string  $drugType  Drug type
     * @param  float  $amount  Amount (grams or pills)
     * @return array Threshold analysis
     */
    protected function analyzeThreshold(string $drugType, float $amount): array
    {
        $drugTypeLower = strtolower($drugType);

        // Find matching drug type
        $threshold = null;
        foreach ($this->personalUseThresholds as $drug => $limits) {
            if (stripos($drugTypeLower, $drug) !== false || stripos($drug, $drugTypeLower) !== false) {
                $threshold = $limits;
                break;
            }
        }

        if (! $threshold) {
            return [
                'threshold_found' => false,
                'personal_use_likely' => null,
                'analysis' => 'No established threshold for this drug type',
            ];
        }

        $limit = $threshold['weight_g'] ?? $threshold['weight_pills'] ?? 0;
        $withinThreshold = $amount <= $limit;

        return [
            'threshold_found' => true,
            'threshold_amount' => $limit,
            'typical_range' => $threshold['typical_range'] ?? 'Unknown',
            'actual_amount' => $amount,
            'within_threshold' => $withinThreshold,
            'percentage_of_threshold' => $limit > 0 ? round(($amount / $limit) * 100, 1) : 0,
            'personal_use_likely' => $withinThreshold,
            'analysis' => $withinThreshold
                ? "Amount ({$amount}) is within personal use threshold ({$limit}) - likely personal use"
                : "Amount ({$amount}) exceeds personal use threshold ({$limit}) - may indicate dealing",
        ];
    }

    /**
     * Detect overcharging patterns
     *
     * @param  array  $data  Case data
     * @return array Detected patterns
     */
    protected function detectPatterns(array $data): array
    {
        $patterns = [];

        $amount = $data['amount'];
        $chargedAs = $data['charged_as'];
        $evidenceOfDealing = $data['evidence_of_dealing'] ?? [];
        $thresholdAnalysis = $data['threshold_analysis'];

        // Pattern 1: Personal use amount charged as dealing
        if ($thresholdAnalysis['personal_use_likely'] && $chargedAs === 'dealing') {
            $patterns[] = [
                'type' => 'personal_use_charged_as_dealing',
                'severity' => 85,
                'description' => 'Amount within personal use threshold charged as dealing',
                'evidence' => "Amount: {$amount}, Threshold: {$thresholdAnalysis['threshold_amount']}, Charged as: dealing",
                'legal_basis' => 'KZ Čl. 190 inappropriate - should be KZ Čl. 173',
            ];
        }

        // Pattern 2: No evidence of dealing intent
        if ($chargedAs === 'dealing' && empty($evidenceOfDealing)) {
            $patterns[] = [
                'type' => 'no_dealing_evidence',
                'severity' => 80,
                'description' => 'Dealing charge with no evidence of sales or distribution',
                'evidence' => 'No scales, baggies, large cash, phone records, or testimony of sales',
                'legal_basis' => 'Lack of probable cause for dealing charge',
            ];
        }

        // Pattern 3: Very small amount (< 50% of threshold) charged as dealing
        if (isset($thresholdAnalysis['percentage_of_threshold']) &&
            $thresholdAnalysis['percentage_of_threshold'] < 50 &&
            $chargedAs === 'dealing') {
            $patterns[] = [
                'type' => 'minimal_amount_charged_as_dealing',
                'severity' => 90,
                'description' => 'Extremely small amount charged as dealing',
                'evidence' => "Amount is only {$thresholdAnalysis['percentage_of_threshold']}% of personal use threshold",
                'legal_basis' => 'Clear overcharging - amount incompatible with dealing',
            ];
        }

        return $patterns;
    }

    /**
     * Calculate overcharge severity (0-100)
     *
     * @param  array  $thresholdAnalysis  Threshold analysis
     * @param  string  $chargedAs  What defendant is charged as
     * @param  array  $evidenceOfDealing  Evidence of dealing intent
     * @return int Severity score
     */
    protected function calculateOverchargeSeverity(
        array $thresholdAnalysis,
        string $chargedAs,
        array $evidenceOfDealing
    ): int {
        $severity = 0;

        // Not charged as dealing = no overcharge
        if ($chargedAs !== 'dealing') {
            return 0;
        }

        // Base score if amount within personal use threshold
        if ($thresholdAnalysis['personal_use_likely'] ?? false) {
            $severity = 60;
        }

        // Add points based on how far below threshold
        if (isset($thresholdAnalysis['percentage_of_threshold'])) {
            $percentage = $thresholdAnalysis['percentage_of_threshold'];

            if ($percentage < 25) {
                $severity += 30; // Extremely small amount
            } elseif ($percentage < 50) {
                $severity += 20; // Very small amount
            } elseif ($percentage < 75) {
                $severity += 10; // Small amount
            }
        }

        // Add points if no evidence of dealing
        if (empty($evidenceOfDealing)) {
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

        $severity = $analysis['overcharge_severity'];

        if ($severity >= 60) {
            $strategies[] = [
                'strategy' => 'motion_to_reduce_charges',
                'priority' => 'high',
                'title' => 'Prijedlog za promjenu kvalifikacije (KZ Čl. 173 umjesto Čl. 190)',
                'description' => 'File motion to reduce dealing charge to personal use charge',
                'legal_basis' => 'Amount within personal use threshold, no evidence of dealing intent',
                'likelihood_of_success' => $severity >= 80 ? 'high' : 'moderate',
            ];
        }

        if (isset($analysis['threshold_analysis']['analysis'])) {
            $strategies[] = [
                'strategy' => 'threshold_argument',
                'priority' => 'high',
                'title' => 'Argument temeljen na pragu za osobnu upotrebu',
                'description' => $analysis['threshold_analysis']['analysis'],
                'legal_basis' => 'Case law recognizing personal use thresholds',
            ];
        }

        $strategies[] = [
            'strategy' => 'regional_comparison',
            'priority' => 'medium',
            'title' => 'Statistički dokaz regionalnih razlika',
            'description' => 'Use regional statistics to show charging disparities',
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
     * Get statistics for drug charge overcharging
     *
     * @param  array  $criteria  Search criteria
     * @return array Statistics
     */
    public function getStatistics(array $criteria): array
    {
        // Search for drug cases
        $cases = $this->searchCases($criteria);

        // Analyze all cases
        $analysis = $this->analyzeDrugCases($cases);

        return $analysis;
    }

    /**
     * Analyze drug cases from search results
     *
     * @param  array  $searchResults  Search results from odluke.sudovi.hr
     * @return array Statistical analysis
     */
    protected function analyzeDrugCases(array $searchResults): array
    {
        $totalCases = count($searchResults['cases'] ?? []);

        if ($totalCases === 0) {
            return [
                'total_cases' => 0,
                'analysis' => 'No cases found',
                'status' => 'no_data',
            ];
        }

        $overchargedCount = 0;
        $byDrugType = [];
        $byAmountRange = [];

        foreach ($searchResults['cases'] as $case) {
            // Extract drug info using AI
            $drugInfo = $this->extractDrugInfo($case);

            if ($drugInfo) {
                // Check if overcharged
                $thresholdAnalysis = $this->analyzeThreshold(
                    $drugInfo['drug_type'],
                    $drugInfo['amount']
                );

                if ($thresholdAnalysis['personal_use_likely'] &&
                    $drugInfo['charged_as'] === 'dealing') {
                    $overchargedCount++;
                }

                // Count by drug type
                $drugType = $drugInfo['drug_type'];
                $byDrugType[$drugType] = ($byDrugType[$drugType] ?? 0) + 1;

                // Count by amount range
                $amountRange = $this->getAmountRange($drugInfo['amount']);
                $byAmountRange[$amountRange] = ($byAmountRange[$amountRange] ?? 0) + 1;
            }
        }

        $overchargePercentage = $totalCases > 0 ? round(($overchargedCount / $totalCases) * 100, 1) : 0;

        return [
            'total_cases' => $totalCases,
            'overcharged_count' => $overchargedCount,
            'overcharge_percentage' => $overchargePercentage,
            'by_drug_type' => $byDrugType,
            'by_amount_range' => $byAmountRange,
            'alarming_findings' => $this->generateAlarmingFindings($overchargePercentage, $totalCases),
            'status' => 'real_data',
        ];
    }

    /**
     * Extract drug information from case using AI
     *
     * @param  array  $case  Case data
     * @return array|null Drug information
     */
    protected function extractDrugInfo(array $case): ?array
    {
        $decisionText = $case['text'] ?? $case['content'] ?? $case['decision_text'] ?? '';

        if (empty($decisionText)) {
            Log::warning('DrugChargeAbuseDetector: No decision text found', [
                'case_id' => $case['case_id'] ?? 'unknown',
            ]);

            return null;
        }

        $prompt = <<<PROMPT
Extract drug-related information from this Croatian court decision.

Decision text:
{$decisionText}

Extract the following information:
1. Drug type (cannabis/marihuana, cocaine/kokain, heroin, ecstasy/mdma, amphetamine, etc.)
2. Amount seized (in grams or pills/tablets)
3. What the defendant was charged as:
   - "dealing" if charged with KZ Čl. 190 (Neovlaštena proizvodnja i promet)
   - "personal_use" if charged with KZ Čl. 173 (Omogućavanje uzimanja opojnih droga)
4. Evidence of dealing intent (if any):
   - Scales (vaga)
   - Baggies/packaging (vrećice)
   - Large cash amounts (novac)
   - Phone records showing sales (poruke o prodaji)
   - Multiple buyers (više kupaca)
   - Testimony of sales (svjedočenje o prodaji)

Return JSON with this structure:
{
  "drug_type": "cannabis|cocaine|heroin|ecstasy|amphetamine|other",
  "amount": <number>,
  "unit": "grams|pills",
  "charged_as": "dealing|personal_use",
  "evidence_of_dealing": ["scales", "baggies", "large_cash", "phone_records"],
  "confidence": "high|medium|low"
}

If information cannot be extracted, return:
{
  "drug_type": null,
  "amount": null,
  "charged_as": null,
  "evidence_of_dealing": [],
  "confidence": "none"
}
PROMPT;

        try {
            $response = $this->openAI->chat([
                [
                    'role' => 'system',
                    'content' => 'You are a Croatian legal document analyzer specializing in extracting structured data from court decisions about drug cases. Return valid JSON only.',
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
                Log::warning('DrugChargeAbuseDetector: Invalid JSON from AI extraction');

                return null;
            }

            // Validate extracted data
            if (empty($extracted['drug_type']) || empty($extracted['amount']) || empty($extracted['charged_as'])) {
                Log::debug('DrugChargeAbuseDetector: Incomplete extraction', [
                    'extracted' => $extracted,
                ]);

                return null;
            }

            // Normalize drug type
            $drugType = strtolower($extracted['drug_type']);

            // Normalize charge type
            $chargedAs = strtolower($extracted['charged_as']);
            if (! in_array($chargedAs, ['dealing', 'personal_use'])) {
                $chargedAs = 'unknown';
            }

            return [
                'drug_type' => $drugType,
                'amount' => (float) $extracted['amount'],
                'unit' => $extracted['unit'] ?? 'grams',
                'charged_as' => $chargedAs,
                'evidence_of_dealing' => $extracted['evidence_of_dealing'] ?? [],
                'confidence' => $extracted['confidence'] ?? 'unknown',
                'case_id' => $case['case_id'] ?? $case['case_number'] ?? 'unknown',
            ];

        } catch (\Exception $e) {
            Log::error('DrugChargeAbuseDetector: AI extraction failed', [
                'error' => $e->getMessage(),
                'case_id' => $case['case_id'] ?? 'unknown',
            ]);

            return null;
        }
    }

    /**
     * Get amount range category
     *
     * @param  float  $amount  Amount
     * @return string Range category
     */
    protected function getAmountRange(float $amount): string
    {
        if ($amount < 10) {
            return '0-10g';
        }
        if ($amount < 30) {
            return '10-30g';
        }
        if ($amount < 50) {
            return '30-50g';
        }
        if ($amount < 100) {
            return '50-100g';
        }

        return '100g+';
    }

    /**
     * Generate alarming findings
     *
     * @param  float  $overchargePercentage  Overcharge percentage
     * @param  int  $totalCases  Total cases
     * @return array Alarming findings
     */
    protected function generateAlarmingFindings(float $overchargePercentage, int $totalCases): array
    {
        $findings = [];

        if ($overchargePercentage > 50) {
            $findings[] = "Više od {$overchargePercentage}% slučajeva drogerija je prekomjerno optuženo - to je sistemski problem";
        } elseif ($overchargePercentage > 30) {
            $findings[] = "{$overchargePercentage}% slučajeva pokazuje prekomjerno optužbu (trgovanje umjesto osobne upotrebe)";
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
        $percentage1 = $stats1['overcharge_percentage'] ?? 0;
        $percentage2 = $stats2['overcharge_percentage'] ?? 0;

        $worseRegion = $percentage1 > $percentage2 ? $region1 : $region2;
        $difference = round(abs($percentage1 - $percentage2), 1);

        return [
            'worse_region' => $worseRegion,
            'overcharge_percentage_difference' => $difference,
            'significance' => $difference > 20 ? 'very_significant' : ($difference > 10 ? 'significant' : 'minor'),
            'analysis' => "{$worseRegion} pokazuje {$difference}% višu stopu prekomjernog optužba za drogu",
        ];
    }

    /**
     * Get search keywords for drug cases
     *
     * @return array Keywords
     */
    protected function getSearchKeywords(): array
    {
        return [
            'neovlaštena proizvodnja',
            'promet opojnim drogama',
            'trgovanje drogom',
            'posjedovanje droge',
            'osobna upotreba',
            'KZ 190', // Dealing
            'KZ 173', // Personal use
        ];
    }
}
