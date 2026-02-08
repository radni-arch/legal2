<?php

namespace App\Modules\Topics\Analyzers;

use App\Models\LegalCase;
use App\Modules\Topics\TopicAnalyzer;
use Illuminate\Support\Facades\Log;

/**
 * IllegalSearchDetector (Detektor Nezakonitog Pretresa)
 *
 * Detects illegal search and seizure violations - specifically violations of
 * constitutional and procedural protections in home searches, vehicle searches,
 * and personal searches.
 *
 * THE PROBLEM:
 * Croatian police and prosecutors routinely violate search and seizure protections:
 * 1. Defective warrants (vague descriptions, wrong addresses, improper justification)
 * 2. Excessive force during searches (breaking down doors, terrorizing families)
 * 3. Warrantless searches without true exigency (claiming "emergency" when none exists)
 * 4. Scope violations (searching beyond warrant authorization)
 *
 * CROATIAN LAW FRAMEWORK:
 * Constitutional protections:
 * - Ustav RH Čl. 34 - Right to privacy, home inviolability
 * - Ustav RH Čl. 35 - Protection from arbitrary searches
 *
 * Criminal Procedure protections:
 * - ZKP Čl. 220 - Warrant requirements and procedures
 * - ZKP Čl. 221 - Search execution procedures
 * - ZKP Čl. 222 - Protection against illegal evidence
 *
 * VIOLATION TYPES:
 * 1. WARRANT_DEFECT:
 *    - Vague warrant (no specific items described)
 *    - Wrong address or description
 *    - Insufficient probable cause
 *    - Stale information (old tip)
 *
 * 2. EXCESSIVE_FORCE:
 *    - Breaking down doors without announcement
 *    - Terrorizing family members
 *    - Unnecessary destruction of property
 *    - Disproportionate police presence
 *
 * 3. NO_EXIGENCY:
 *    - Warrantless search claiming "emergency"
 *    - No actual risk of evidence destruction
 *    - No actual risk of harm
 *    - Planned operation disguised as emergency
 *
 * 4. SCOPE_VIOLATION:
 *    - Searching beyond warrant authorization
 *    - Searching persons not named in warrant
 *    - Searching areas not covered by warrant
 *    - Seizing items not listed in warrant
 *
 * QUESTIONS THIS ANSWERS:
 * - "How many illegal searches in Osijek in 2025?"
 * - "Is Osijek worse than Zagreb for search violations?"
 * - "What percentage of searches have defective warrants?"
 * - "Which judges issue most defective warrants?"
 * - "Success rate of suppression motions?"
 *
 * DEFENSE STRATEGIES:
 * - Motion to suppress evidence (ZKP Čl. 222)
 * - Challenge warrant validity
 * - Cite constitutional violations (Ustav RH Čl. 34, 35)
 * - Expert testimony on proper procedures
 * - Regional disparity arguments
 */
class IllegalSearchDetector extends TopicAnalyzer
{
    protected string $topicName = 'illegal_search';

    protected string $topicDescription = 'Illegal search and seizure violations';

    protected array $legalFramework = [
        'Ustav_RH_Čl_34' => 'Pravo na privatnost i nepovredivost doma (Right to privacy and home inviolability)',
        'Ustav_RH_Čl_35' => 'Zaštita od proizvoljnih pretresa (Protection from arbitrary searches)',
        'ZKP_Čl_220' => 'Zahtjevi i postupci naloga za pretres (Warrant requirements and procedures)',
        'ZKP_Čl_221' => 'Postupci izvršenja pretresa (Search execution procedures)',
        'ZKP_Čl_222' => 'Zaštita od nezakonitih dokaza (Protection against illegal evidence)',
    ];

    /**
     * Violation types with severity thresholds
     */
    protected array $violationTypes = [
        'warrant_defect' => [
            'name' => 'Defective Warrant',
            'description' => 'Warrant lacks specificity, probable cause, or other legal requirements',
            'base_severity' => 75,
            'legal_basis' => 'ZKP Čl. 220, Ustav RH Čl. 34',
        ],
        'excessive_force' => [
            'name' => 'Excessive Force',
            'description' => 'Unnecessary force or property destruction during search execution',
            'base_severity' => 85,
            'legal_basis' => 'ZKP Čl. 221, Ustav RH Čl. 35',
        ],
        'no_exigency' => [
            'name' => 'False Exigent Circumstances',
            'description' => 'Warrantless search claiming emergency when none exists',
            'base_severity' => 90,
            'legal_basis' => 'ZKP Čl. 220, Ustav RH Čl. 34',
        ],
        'scope_violation' => [
            'name' => 'Scope Violation',
            'description' => 'Search exceeded warrant authorization',
            'base_severity' => 80,
            'legal_basis' => 'ZKP Čl. 220, ZKP Čl. 222',
        ],
    ];

    /**
     * Analyze case for illegal search violations
     *
     * @param  LegalCase  $case  The legal case
     * @param  array  $searchData  Search-specific data
     * @return array Analysis result
     */
    public function analyzeCase(LegalCase $case, array $searchData): array
    {
        Log::info('IllegalSearchDetector: Analyzing case', [
            'case_id' => $case->id,
            'has_warrant' => $searchData['has_warrant'] ?? false,
            'search_type' => $searchData['search_type'] ?? 'unknown',
        ]);

        // Extract data
        $hasWarrant = $searchData['has_warrant'] ?? false;
        $searchType = $searchData['search_type'] ?? 'home'; // home|vehicle|person
        $warrantDefects = $searchData['warrant_defects'] ?? [];
        $forceUsed = $searchData['force_used'] ?? [];
        $exigencyDetails = $searchData['exigency_details'] ?? [];
        $scopeViolations = $searchData['scope_violations'] ?? [];

        // Detect violation patterns
        $violationPatterns = $this->detectPatterns([
            'has_warrant' => $hasWarrant,
            'search_type' => $searchType,
            'warrant_defects' => $warrantDefects,
            'force_used' => $forceUsed,
            'exigency_details' => $exigencyDetails,
            'scope_violations' => $scopeViolations,
        ]);

        // Calculate violation severity (0-100)
        $violationSeverity = $this->calculateViolationSeverity($violationPatterns);

        // Generate defense strategy
        $defenseStrategy = $this->generateDefenseStrategy([
            'violation_severity' => $violationSeverity,
            'patterns' => $violationPatterns,
            'has_warrant' => $hasWarrant,
            'search_type' => $searchType,
        ]);

        $result = [
            'case_id' => $case->id,
            'violation_detected' => $violationSeverity >= 60,
            'violation_severity' => $violationSeverity,
            'search_type' => $searchType,
            'has_warrant' => $hasWarrant,
            'violation_patterns' => $violationPatterns,
            'defense_strategy' => $defenseStrategy,
            'suppression_likelihood' => $this->calculateSuppressionLikelihood($violationSeverity, $violationPatterns),
            'legal_violations' => $this->identifyLegalViolations($violationPatterns),
        ];

        Log::info('IllegalSearchDetector: Analysis complete', [
            'case_id' => $case->id,
            'violation_detected' => $result['violation_detected'],
            'violation_severity' => $violationSeverity,
        ]);

        return $result;
    }

    /**
     * Detect illegal search patterns
     *
     * @param  array  $data  Case data
     * @return array Detected patterns
     */
    protected function detectPatterns(array $data): array
    {
        $patterns = [];

        $hasWarrant = $data['has_warrant'];
        $warrantDefects = $data['warrant_defects'] ?? [];
        $forceUsed = $data['force_used'] ?? [];
        $exigencyDetails = $data['exigency_details'] ?? [];
        $scopeViolations = $data['scope_violations'] ?? [];

        // Pattern 1: Defective warrant
        if ($hasWarrant && ! empty($warrantDefects)) {
            $defectCount = count($warrantDefects);
            $severity = $this->violationTypes['warrant_defect']['base_severity'];

            // Increase severity based on number of defects
            if ($defectCount >= 3) {
                $severity = min(100, $severity + 15);
            } elseif ($defectCount >= 2) {
                $severity = min(100, $severity + 10);
            }

            $patterns[] = [
                'type' => 'warrant_defect',
                'severity' => $severity,
                'description' => 'Warrant contains '.$defectCount.' legal defect(s)',
                'evidence' => implode('; ', $warrantDefects),
                'legal_basis' => $this->violationTypes['warrant_defect']['legal_basis'],
            ];
        }

        // Pattern 2: Excessive force
        if (! empty($forceUsed)) {
            $forceCount = count($forceUsed);
            $severity = $this->violationTypes['excessive_force']['base_severity'];

            // Increase severity if multiple force incidents
            if ($forceCount >= 2) {
                $severity = min(100, $severity + 10);
            }

            $patterns[] = [
                'type' => 'excessive_force',
                'severity' => $severity,
                'description' => 'Excessive force used during search execution',
                'evidence' => implode('; ', $forceUsed),
                'legal_basis' => $this->violationTypes['excessive_force']['legal_basis'],
            ];
        }

        // Pattern 3: No exigency (warrantless search)
        if (! $hasWarrant && ! empty($exigencyDetails)) {
            $claimedExigency = $exigencyDetails['claimed_reason'] ?? 'unknown';
            $actualExigency = $exigencyDetails['actual_emergency'] ?? false;

            if (! $actualExigency) {
                $patterns[] = [
                    'type' => 'no_exigency',
                    'severity' => $this->violationTypes['no_exigency']['base_severity'],
                    'description' => 'Warrantless search with false exigent circumstances claim',
                    'evidence' => "Claimed: {$claimedExigency}, but no actual emergency existed",
                    'legal_basis' => $this->violationTypes['no_exigency']['legal_basis'],
                ];
            }
        }

        // Pattern 4: Scope violation
        if (! empty($scopeViolations)) {
            $violationCount = count($scopeViolations);
            $severity = $this->violationTypes['scope_violation']['base_severity'];

            // Increase severity if multiple scope violations
            if ($violationCount >= 2) {
                $severity = min(100, $severity + 10);
            }

            $patterns[] = [
                'type' => 'scope_violation',
                'severity' => $severity,
                'description' => 'Search exceeded warrant authorization',
                'evidence' => implode('; ', $scopeViolations),
                'legal_basis' => $this->violationTypes['scope_violation']['legal_basis'],
            ];
        }

        return $patterns;
    }

    /**
     * Calculate violation severity (0-100)
     *
     * @param  array  $violationPatterns  Detected patterns
     * @return int Severity score
     */
    protected function calculateViolationSeverity(array $violationPatterns): int
    {
        if (empty($violationPatterns)) {
            return 0;
        }

        // Use highest severity pattern
        $maxSeverity = 0;
        foreach ($violationPatterns as $pattern) {
            $maxSeverity = max($maxSeverity, $pattern['severity']);
        }

        // Add 5 points for each additional violation type
        if (count($violationPatterns) > 1) {
            $maxSeverity = min(100, $maxSeverity + (5 * (count($violationPatterns) - 1)));
        }

        return $maxSeverity;
    }

    /**
     * Calculate likelihood of successful suppression motion
     *
     * @param  int  $violationSeverity  Violation severity score
     * @param  array  $violationPatterns  Detected patterns
     * @return string Likelihood (high|moderate|low)
     */
    protected function calculateSuppressionLikelihood(int $violationSeverity, array $violationPatterns): string
    {
        // Check for no_exigency (highest success rate)
        $hasNoExigency = collect($violationPatterns)->contains('type', 'no_exigency');

        if ($hasNoExigency || $violationSeverity >= 85) {
            return 'high';
        }

        if ($violationSeverity >= 70) {
            return 'moderate';
        }

        if ($violationSeverity >= 60) {
            return 'low-moderate';
        }

        return 'low';
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
        $patterns = $analysis['patterns'];

        if ($severity >= 60) {
            $strategies[] = [
                'strategy' => 'motion_to_suppress',
                'priority' => 'high',
                'title' => 'Prijedlog za isključenje nezakonitih dokaza (ZKP Čl. 222)',
                'description' => 'File motion to suppress all evidence obtained from illegal search',
                'legal_basis' => 'ZKP Čl. 222 - Fruit of the poisonous tree doctrine',
                'likelihood_of_success' => $this->calculateSuppressionLikelihood($severity, $patterns),
            ];
        }

        // Add specific strategies for each violation type
        foreach ($patterns as $pattern) {
            switch ($pattern['type']) {
                case 'warrant_defect':
                    $strategies[] = [
                        'strategy' => 'challenge_warrant_validity',
                        'priority' => 'high',
                        'title' => 'Osporavanje valjanosti naloga (ZKP Čl. 220)',
                        'description' => 'Challenge warrant validity due to defects',
                        'legal_basis' => 'ZKP Čl. 220 - Warrant requirements',
                    ];
                    break;

                case 'excessive_force':
                    $strategies[] = [
                        'strategy' => 'excessive_force_violation',
                        'priority' => 'medium',
                        'title' => 'Kršenje postupka izvršenja (ZKP Čl. 221)',
                        'description' => 'Challenge search execution procedures',
                        'legal_basis' => 'ZKP Čl. 221, Ustav RH Čl. 35',
                    ];
                    break;

                case 'no_exigency':
                    $strategies[] = [
                        'strategy' => 'false_exigency_claim',
                        'priority' => 'high',
                        'title' => 'Lažna tvrdnja o hitnosti',
                        'description' => 'Challenge false exigent circumstances claim',
                        'legal_basis' => 'ZKP Čl. 220, Ustav RH Čl. 34 - Warrantless search exception requires true emergency',
                    ];
                    break;

                case 'scope_violation':
                    $strategies[] = [
                        'strategy' => 'scope_exceeded',
                        'priority' => 'high',
                        'title' => 'Prekoračenje ovlasti naloga',
                        'description' => 'Challenge evidence obtained beyond warrant scope',
                        'legal_basis' => 'ZKP Čl. 220, ZKP Čl. 222',
                    ];
                    break;
            }
        }

        $strategies[] = [
            'strategy' => 'constitutional_violation',
            'priority' => 'medium',
            'title' => 'Kršenje ustavnih prava',
            'description' => 'Assert constitutional violations',
            'legal_basis' => 'Ustav RH Čl. 34, 35 - Right to privacy and protection from arbitrary searches',
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
     * Get statistics for illegal searches
     *
     * @param  array  $criteria  Search criteria
     * @return array Statistics
     */
    public function getStatistics(array $criteria): array
    {
        // Search for search/seizure cases
        $cases = $this->searchCases($criteria);

        // Analyze all cases
        $analysis = $this->analyzeSearchCases($cases);

        return $analysis;
    }

    /**
     * Analyze search cases from search results
     *
     * @param  array  $searchResults  Search results from odluke.sudovi.hr
     * @return array Statistical analysis
     */
    protected function analyzeSearchCases(array $searchResults): array
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
        $bySearchType = [];

        foreach ($searchResults['cases'] as $case) {
            // Extract search info using AI
            $searchInfo = $this->extractSearchInfo($case);

            if ($searchInfo) {
                // Check if violations detected
                if (! empty($searchInfo['violations'])) {
                    $violationCount++;

                    // Count by violation type
                    foreach ($searchInfo['violations'] as $violation) {
                        $byViolationType[$violation] = ($byViolationType[$violation] ?? 0) + 1;
                    }
                }

                // Count by search type
                $searchType = $searchInfo['search_type'];
                $bySearchType[$searchType] = ($bySearchType[$searchType] ?? 0) + 1;
            }
        }

        $violationPercentage = $totalCases > 0 ? round(($violationCount / $totalCases) * 100, 1) : 0;

        return [
            'total_cases' => $totalCases,
            'violation_count' => $violationCount,
            'violation_percentage' => $violationPercentage,
            'by_violation_type' => $byViolationType,
            'by_search_type' => $bySearchType,
            'alarming_findings' => $this->generateAlarmingFindings($violationPercentage, $totalCases),
            'status' => 'real_data',
        ];
    }

    /**
     * Extract search information from case using AI
     *
     * @param  array  $case  Case data
     * @return array|null Search information
     */
    protected function extractSearchInfo(array $case): ?array
    {
        $decisionText = $case['text'] ?? $case['content'] ?? $case['decision_text'] ?? '';

        if (empty($decisionText)) {
            Log::warning('IllegalSearchDetector: No decision text found', [
                'case_id' => $case['case_id'] ?? 'unknown',
            ]);

            return null;
        }

        $prompt = <<<PROMPT
Extract search and seizure information from this Croatian court decision.

Decision text:
{$decisionText}

Extract the following information:
1. Search type (home|vehicle|person)
2. Was there a warrant? (yes|no)
3. Warrant defects (if any):
   - Vague description (nejasan opis)
   - Wrong address (pogrešna adresa)
   - Insufficient probable cause (nedovoljan osnov)
   - Stale information (zastarjele informacije)
4. Excessive force used (if any):
   - Breaking doors (razbijanje vrata)
   - Terrorizing family (zastrašivanje obitelji)
   - Property destruction (uništavanje imovine)
   - Excessive police presence (prekomjerna policijska prisutnost)
5. Exigency details (if warrantless):
   - Claimed reason (tvrdnja o hitnosti)
   - Actual emergency (stvarna hitnost)
6. Scope violations (if any):
   - Searched beyond authorization (pretraga izvan ovlasti)
   - Searched unauthorized persons (pretraga neovlaštenih osoba)
   - Searched unauthorized areas (pretraga neovlaštenih područja)
   - Seized unauthorized items (oduzimanje neovlaštenih predmeta)

Return JSON with this structure:
{
  "search_type": "home|vehicle|person",
  "has_warrant": true|false,
  "violations": ["warrant_defect", "excessive_force", "no_exigency", "scope_violation"],
  "violation_details": {
    "warrant_defects": ["vague description", "wrong address"],
    "force_used": ["breaking doors"],
    "exigency": {"claimed": "emergency", "actual": false},
    "scope_violations": ["searched beyond authorization"]
  },
  "confidence": "high|medium|low"
}

If information cannot be extracted, return:
{
  "search_type": null,
  "has_warrant": null,
  "violations": [],
  "confidence": "none"
}
PROMPT;

        try {
            $response = $this->openAI->chat([
                [
                    'role' => 'system',
                    'content' => 'You are a Croatian legal document analyzer specializing in extracting structured data from court decisions about search and seizure. Return valid JSON only.',
                ],
                ['role' => 'user', 'content' => $prompt],
            ], 'gpt-4o-mini', [
                'temperature' => 0.1,
                'max_tokens' => 400,
            ]);

            $content = $response['choices'][0]['message']['content'] ?? '';

            // Clean up markdown code blocks if present
            $content = preg_replace('/```json\s*/i', '', $content);
            $content = preg_replace('/```\s*$/i', '', $content);
            $content = trim($content);

            $extracted = json_decode($content, true);

            if (! is_array($extracted)) {
                Log::warning('IllegalSearchDetector: Invalid JSON from AI extraction');

                return null;
            }

            // Validate extracted data
            if (empty($extracted['search_type']) || ! isset($extracted['has_warrant'])) {
                Log::debug('IllegalSearchDetector: Incomplete extraction', [
                    'extracted' => $extracted,
                ]);

                return null;
            }

            return [
                'search_type' => strtolower($extracted['search_type']),
                'has_warrant' => (bool) $extracted['has_warrant'],
                'violations' => $extracted['violations'] ?? [],
                'violation_details' => $extracted['violation_details'] ?? [],
                'confidence' => $extracted['confidence'] ?? 'unknown',
                'case_id' => $case['case_id'] ?? $case['case_number'] ?? 'unknown',
            ];

        } catch (\Exception $e) {
            Log::error('IllegalSearchDetector: AI extraction failed', [
                'error' => $e->getMessage(),
                'case_id' => $case['case_id'] ?? 'unknown',
            ]);

            return null;
        }
    }

    /**
     * Generate alarming findings
     *
     * @param  float  $violationPercentage  Violation percentage
     * @param  int  $totalCases  Total cases
     * @return array Alarming findings
     */
    protected function generateAlarmingFindings(float $violationPercentage, int $totalCases): array
    {
        $findings = [];

        if ($violationPercentage > 40) {
            $findings[] = "Više od {$violationPercentage}% pretresa krši ustavna i zakonska prava - to je sistemski problem";
        } elseif ($violationPercentage > 25) {
            $findings[] = "{$violationPercentage}% slučajeva pokazuje kršenje procedura pretresa";
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
            'analysis' => "{$worseRegion} pokazuje {$difference}% višu stopu kršenja prava pri pretresima",
        ];
    }

    /**
     * Get search keywords for illegal search cases
     *
     * @return array Keywords
     */
    protected function getSearchKeywords(): array
    {
        return [
            'pretres',
            'pretres stana',
            'pretres vozila',
            'nalog za pretres',
            'nezakoniti dokazi',
            'isključenje dokaza',
            'ZKP 220',
            'ZKP 221',
            'ZKP 222',
            'Ustav RH 34',
            'Ustav RH 35',
        ];
    }
}
