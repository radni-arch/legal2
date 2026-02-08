<?php

namespace App\Modules\HomeSearch\Services;

use App\Models\LegalCase;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Log;

/**
 * HomeSearchAbuseDetector (Detektor Zlouporabe Pregleda Doma)
 *
 * Detects disproportionate use of home search warrants for minor offenses.
 * Under Croatian law, home searches must be proportionate to the severity
 * of the alleged crime (načelo razmjernosti - ZKP Čl. 179).
 *
 * PROBLEM:
 * Prosecutors and police often use minor offenses (prekršaji) as pretext
 * for full-scale home invasions ("pretres doma") with little justification.
 * Examples:
 * - Minor drug possession → full apartment search with SWAT team
 * - Traffic violation → home search for "evidence"
 * - Misdemeanor complaint → invasive property search
 *
 * LEGAL FRAMEWORK (Croatian Law):
 * - Ustav RH Čl. 34 - Nepovrjedivost stana (Inviolability of home)
 * - ZKP Čl. 179 - Načelo razmjernosti (Proportionality principle)
 * - ZKP Čl. 215-220 - Pretres stana (Home search procedures)
 * - ZKP Čl. 221 - Pretres osobe (Body search)
 * - Prekršajni zakon - Minor offenses (generally NOT justifying home search)
 *
 * DETECTION CRITERIA:
 * 1. Offense Severity Mismatch - Minor offense vs. invasive search
 * 2. Disproportionate Force - SWAT team for misdemeanor
 * 3. Weak Justification - Vague or pretextual reasoning
 * 4. Pattern of Abuse - Same judge/prosecutor issuing excessive warrants
 * 5. Geographic Targeting - Specific neighborhoods targeted
 * 6. Rights Violations - Search exceeded warrant scope
 *
 * DEFENSE STRATEGIES:
 * - Motion to suppress evidence (ZKP Čl. 10, St. 2)
 * - Constitutional challenge (Ustav RH Čl. 34)
 * - Statistical evidence of pattern
 * - Complaint to Državno odvjetništvo
 * - Ustavna tužba (Constitutional Court complaint)
 *
 * ETHICAL USE ONLY:
 * ✅ Identify disproportionate searches
 * ✅ Document patterns of abuse
 * ✅ Support legitimate defense challenges
 * ✅ Protect constitutional rights
 * ❌ NOT FOR: Obstructing legitimate investigations
 */
class HomeSearchAbuseDetector
{
    public function __construct(
        protected OpenAIService $openAI,
        protected ProportionalityAnalyzer $proportionalityAnalyzer,
        protected StatisticalAnalyzer $statisticalAnalyzer
    ) {}

    /**
     * Detect home search abuse in a case
     *
     * @param  LegalCase  $case  The legal case
     * @param  array  $searchWarrantDetails  Home search warrant details
     * @return array Abuse detection result
     */
    public function detectAbuse(LegalCase $case, array $searchWarrantDetails): array
    {
        Log::info('HomeSearchAbuseDetector: Starting abuse detection', [
            'case_id' => $case->id,
            'offense' => $searchWarrantDetails['offense'] ?? 'unknown',
        ]);

        // 1. Analyze proportionality of search to offense
        $proportionalityAnalysis = $this->proportionalityAnalyzer->analyze(
            $searchWarrantDetails,
            $case
        );

        // 2. Detect specific abuse patterns
        $abusePatterns = $this->detectAbusePatterns($searchWarrantDetails, $case);

        // 3. Calculate abuse severity score (0-100)
        $abuseSeverity = $this->calculateAbuseSeverity($proportionalityAnalysis, $abusePatterns);

        // 4. Generate defense strategy
        $defenseStrategy = $this->generateDefenseStrategy(
            $proportionalityAnalysis,
            $abusePatterns,
            $abuseSeverity,
            $case
        );

        // 5. Identify legal violations
        $legalViolations = $this->identifyLegalViolations($proportionalityAnalysis, $abusePatterns);

        $result = [
            'case_id' => $case->id,
            'abuse_detected' => $abuseSeverity >= 60,
            'abuse_severity' => $abuseSeverity,
            'abuse_level' => $this->getAbuseLevelLabel($abuseSeverity),
            'proportionality_analysis' => $proportionalityAnalysis,
            'abuse_patterns' => $abusePatterns,
            'legal_violations' => $legalViolations,
            'defense_strategy' => $defenseStrategy,
            'suppression_grounds' => $this->getSuppressionGrounds($legalViolations, $proportionalityAnalysis),
            'recommended_actions' => $this->getRecommendedActions($abuseSeverity, $abusePatterns),
            'analyzed_at' => now()->toIso8601String(),
        ];

        Log::info('HomeSearchAbuseDetector: Detection complete', [
            'case_id' => $case->id,
            'abuse_detected' => $result['abuse_detected'],
            'abuse_severity' => $abuseSeverity,
            'patterns_found' => count($abusePatterns),
        ]);

        return $result;
    }

    /**
     * Detect specific abuse patterns
     *
     * @param  array  $searchWarrantDetails  Search warrant details
     * @param  LegalCase  $case  The legal case
     * @return array Detected abuse patterns
     */
    protected function detectAbusePatterns(array $searchWarrantDetails, LegalCase $case): array
    {
        $patterns = [];

        // Pattern 1: Minor offense with invasive search
        $offenseSeverity = $searchWarrantDetails['offense_severity'] ?? 'unknown';
        $searchScope = $searchWarrantDetails['search_scope'] ?? 'unknown';

        if (in_array($offenseSeverity, ['misdemeanor', 'minor', 'petty']) &&
            in_array($searchScope, ['full_home_search', 'invasive', 'comprehensive'])) {
            $patterns[] = [
                'type' => 'minor_offense_invasive_search',
                'severity' => 85,
                'description' => 'Minor offense used as pretext for full-scale home search',
                'evidence' => "Offense: {$offenseSeverity}, Search scope: {$searchScope}",
                'legal_basis' => 'ZKP Čl. 179 - Načelo razmjernosti (violated)',
                'constitutional_violation' => 'Ustav RH Čl. 34 - Nepovrjedivost stana',
            ];
        }

        // Pattern 2: Disproportionate force
        $forceUsed = $searchWarrantDetails['force_used'] ?? null;
        if ($forceUsed && in_array($forceUsed, ['swat', 'tactical_unit', 'armed_officers'])) {
            $patterns[] = [
                'type' => 'disproportionate_force',
                'severity' => 80,
                'description' => 'Excessive force used for minor offense investigation',
                'evidence' => "Force used: {$forceUsed} for offense: {$offenseSeverity}",
                'legal_basis' => 'ZKP Čl. 179 - Razmjernost (violated)',
                'constitutional_violation' => 'Ustav RH Čl. 34 - Proporcionalna zaštita',
            ];
        }

        // Pattern 3: Weak or vague justification
        $justification = $searchWarrantDetails['warrant_justification'] ?? '';
        if (strlen($justification) < 100 ||
            stripos($justification, 'sumnja') !== false && stripos($justification, 'dokaz') === false) {
            $patterns[] = [
                'type' => 'weak_justification',
                'severity' => 70,
                'description' => 'Search warrant based on vague suspicion without concrete evidence',
                'evidence' => 'Justification: "'.substr($justification, 0, 100).'..."',
                'legal_basis' => 'ZKP Čl. 215 - Pretres samo uz osnovanu sumnju',
                'constitutional_violation' => 'Ustav RH Čl. 34 - Zaštita od proizvoljnog upada',
            ];
        }

        // Pattern 4: Search exceeded warrant scope
        $scopeExceeded = $searchWarrantDetails['scope_exceeded'] ?? false;
        if ($scopeExceeded) {
            $patterns[] = [
                'type' => 'exceeded_warrant_scope',
                'severity' => 90,
                'description' => 'Search exceeded the scope specified in warrant',
                'evidence' => $searchWarrantDetails['scope_exceeded_details'] ?? 'Search went beyond warrant authorization',
                'legal_basis' => 'ZKP Čl. 217 - Pretres samo u opsegu naloga',
                'constitutional_violation' => 'Ustav RH Čl. 34 - Nepovrjedivost stana',
            ];
        }

        // Pattern 5: No judge approval (prosecutor-only warrant)
        $approvedBy = $searchWarrantDetails['approved_by'] ?? 'unknown';
        $urgentCircumstances = $searchWarrantDetails['urgent_circumstances'] ?? false;

        if ($approvedBy === 'prosecutor' && ! $urgentCircumstances) {
            $patterns[] = [
                'type' => 'no_judicial_approval',
                'severity' => 95,
                'description' => 'Home search ordered by prosecutor without judicial approval',
                'evidence' => 'Warrant approved by prosecutor only, no urgent circumstances documented',
                'legal_basis' => 'ZKP Čl. 215, St. 1 - Nalog suda za pretres',
                'constitutional_violation' => 'Ustav RH Čl. 34 - Sudska kontrola',
            ];
        }

        // Pattern 6: Time of search abuse (night raids for minor offenses)
        $searchTime = $searchWarrantDetails['search_time'] ?? null;
        if ($searchTime) {
            $hour = (int) date('H', strtotime($searchTime));
            if (($hour >= 22 || $hour <= 6) && in_array($offenseSeverity, ['misdemeanor', 'minor'])) {
                $patterns[] = [
                    'type' => 'night_raid_minor_offense',
                    'severity' => 75,
                    'description' => 'Night-time raid for minor offense without justification',
                    'evidence' => "Search conducted at {$searchTime} for {$offenseSeverity}",
                    'legal_basis' => 'ZKP Čl. 218 - Pretres danju (noćni pretres samo iznimno)',
                    'constitutional_violation' => 'Ustav RH Čl. 34 - Zaštita privatnosti',
                ];
            }
        }

        // Pattern 7: Pretextual search (actual target vs. stated target)
        $statedPurpose = $searchWarrantDetails['stated_purpose'] ?? '';
        $actualTarget = $searchWarrantDetails['actual_target'] ?? '';
        if ($statedPurpose && $actualTarget && $statedPurpose !== $actualTarget) {
            $patterns[] = [
                'type' => 'pretextual_search',
                'severity' => 85,
                'description' => 'Search warrant used as pretext to investigate unrelated matters',
                'evidence' => "Stated: '{$statedPurpose}', Actual: '{$actualTarget}'",
                'legal_basis' => 'ZKP Čl. 215 - Pretres samo za navedenu svrhu',
                'constitutional_violation' => 'Ustav RH Čl. 34 - Zabrana zlouporabe ovlaštenja',
            ];
        }

        return $patterns;
    }

    /**
     * Calculate abuse severity score (0-100)
     *
     * @param  array  $proportionalityAnalysis  Proportionality analysis
     * @param  array  $abusePatterns  Detected abuse patterns
     * @return int Abuse severity score
     */
    protected function calculateAbuseSeverity(array $proportionalityAnalysis, array $abusePatterns): int
    {
        // Base score from proportionality analysis
        $baseScore = 0;
        $proportionate = $proportionalityAnalysis['proportionate'] ?? true;

        if (! $proportionate) {
            $baseScore = 50; // Starts at 50 if disproportionate

            // Add points for disproportion severity
            $disproportionScore = $proportionalityAnalysis['disproportion_score'] ?? 0;
            $baseScore += ($disproportionScore / 100) * 20; // Up to +20 points
        }

        // Add points for each abuse pattern detected
        $patternScore = 0;
        if (! empty($abusePatterns)) {
            $totalPatternSeverity = array_sum(array_column($abusePatterns, 'severity'));
            $avgPatternSeverity = $totalPatternSeverity / count($abusePatterns);
            $patternScore = min(50, ($avgPatternSeverity / 100) * 50); // Up to +50 points (increased from 40)

            // If patterns exist but proportionality says it's proportionate,
            // still give base score based on pattern severity
            if ($proportionate && ! empty($abusePatterns)) {
                $maxPatternSeverity = max(array_column($abusePatterns, 'severity'));
                $baseScore = ($maxPatternSeverity / 100) * 30; // Up to +30 base from severe patterns
            }
        }

        // Bonus for multiple patterns (systemic abuse indicator)
        $multiplePatternBonus = 0;
        if (count($abusePatterns) >= 3) {
            $multiplePatternBonus = 15; // +15 for 3+ patterns (increased from 10)
        } elseif (count($abusePatterns) >= 2) {
            $multiplePatternBonus = 7; // +7 for 2 patterns (increased from 5)
        }

        $totalScore = $baseScore + $patternScore + $multiplePatternBonus;

        Log::debug('HomeSearchAbuseDetector: Severity calculation', [
            'base_score' => $baseScore,
            'pattern_score' => $patternScore,
            'multiple_pattern_bonus' => $multiplePatternBonus,
            'total_score' => $totalScore,
        ]);

        return min(100, (int) $totalScore);
    }

    /**
     * Get abuse level label from severity score
     *
     * @param  int  $severity  Severity score (0-100)
     * @return string Abuse level label
     */
    protected function getAbuseLevelLabel(int $severity): string
    {
        if ($severity >= 90) {
            return 'extreme_abuse';
        } elseif ($severity >= 75) {
            return 'severe_abuse';
        } elseif ($severity >= 60) {
            return 'moderate_abuse';
        } elseif ($severity >= 40) {
            return 'questionable';
        } else {
            return 'likely_proportionate';
        }
    }

    /**
     * Identify legal violations
     *
     * @param  array  $proportionalityAnalysis  Proportionality analysis
     * @param  array  $abusePatterns  Abuse patterns
     * @return array Legal violations
     */
    protected function identifyLegalViolations(array $proportionalityAnalysis, array $abusePatterns): array
    {
        $violations = [];

        // Extract violations from abuse patterns
        foreach ($abusePatterns as $pattern) {
            $violations[] = [
                'type' => $pattern['type'],
                'severity' => $pattern['severity'],
                'zkp_violation' => $pattern['legal_basis'],
                'constitutional_violation' => $pattern['constitutional_violation'],
                'description' => $pattern['description'],
                'evidence' => $pattern['evidence'],
            ];
        }

        // Add proportionality violation if applicable
        if (! ($proportionalityAnalysis['proportionate'] ?? true)) {
            $violations[] = [
                'type' => 'proportionality_violation',
                'severity' => 80,
                'zkp_violation' => 'ZKP Čl. 179 - Načelo razmjernosti',
                'constitutional_violation' => 'Ustav RH Čl. 34 - Proporcionalna zaštita',
                'description' => 'Search disproportionate to offense severity',
                'evidence' => $proportionalityAnalysis['analysis'] ?? 'See proportionality analysis',
            ];
        }

        // Sort by severity (highest first)
        usort($violations, fn ($a, $b) => $b['severity'] <=> $a['severity']);

        return $violations;
    }

    /**
     * Generate defense strategy
     *
     * @param  array  $proportionalityAnalysis  Proportionality analysis
     * @param  array  $abusePatterns  Abuse patterns
     * @param  int  $abuseSeverity  Abuse severity score
     * @param  LegalCase  $case  Legal case
     * @return array Defense strategy
     */
    protected function generateDefenseStrategy(
        array $proportionalityAnalysis,
        array $abusePatterns,
        int $abuseSeverity,
        LegalCase $case
    ): array {
        $strategies = [];

        // Strategy 1: Evidence suppression motion
        if ($abuseSeverity >= 60) {
            $strategies[] = [
                'strategy' => 'motion_to_suppress',
                'priority' => 'high',
                'title' => 'Prijedlog za isključenje dokaza (ZKP Čl. 10, St. 2)',
                'description' => 'File motion to suppress all evidence obtained from disproportionate search',
                'legal_basis' => 'ZKP Čl. 10, St. 2 - Zabrana uporabe protuzakonito pribavljenih dokaza',
                'likelihood_of_success' => $this->estimateSuppressionLikelihood($abuseSeverity),
            ];
        }

        // Strategy 2: Constitutional complaint
        if ($abuseSeverity >= 75) {
            $strategies[] = [
                'strategy' => 'constitutional_complaint',
                'priority' => 'high',
                'title' => 'Ustavna tužba (Ustavni sud RH)',
                'description' => 'File constitutional complaint for violation of home inviolability',
                'legal_basis' => 'Ustav RH Čl. 34 - Nepovrjedivost stana',
                'likelihood_of_success' => 'moderate_to_high',
            ];
        }

        // Strategy 3: Complaint to State Attorney
        if (count($abusePatterns) >= 2) {
            $strategies[] = [
                'strategy' => 'prosecutorial_complaint',
                'priority' => 'medium',
                'title' => 'Prijava Državnom odvjetništvu',
                'description' => 'File complaint about prosecutorial misconduct',
                'legal_basis' => 'Zakon o Državnom odvjetništvu Čl. 13',
                'likelihood_of_success' => 'moderate',
            ];
        }

        // Strategy 4: Statistical evidence of pattern
        $strategies[] = [
            'strategy' => 'statistical_evidence',
            'priority' => 'medium',
            'title' => 'Statistički dokazi obrasca zlouporabe',
            'description' => 'Use statistical analysis to show pattern of abuse',
            'legal_basis' => 'ZKP Čl. 9 - Objektivnost, ZKP Čl. 331 - Slobodna ocjena dokaza',
            'likelihood_of_success' => 'supportive',
        ];

        // Strategy 5: Damages claim
        if ($abuseSeverity >= 80) {
            $strategies[] = [
                'strategy' => 'damages_claim',
                'priority' => 'low',
                'title' => 'Zahtjev za naknadu štete',
                'description' => 'File civil claim for damages from unlawful search',
                'legal_basis' => 'Zakon o obveznim odnosima - Odgovornost za štetu',
                'likelihood_of_success' => 'moderate',
            ];
        }

        return $strategies;
    }

    /**
     * Estimate likelihood of successful evidence suppression
     *
     * @param  int  $abuseSeverity  Abuse severity score
     * @return string Likelihood estimate
     */
    protected function estimateSuppressionLikelihood(int $abuseSeverity): string
    {
        if ($abuseSeverity >= 90) {
            return 'very_high';
        } elseif ($abuseSeverity >= 75) {
            return 'high';
        } elseif ($abuseSeverity >= 60) {
            return 'moderate';
        } else {
            return 'low';
        }
    }

    /**
     * Get suppression grounds
     *
     * @param  array  $legalViolations  Legal violations
     * @param  array  $proportionalityAnalysis  Proportionality analysis
     * @return array Suppression grounds
     */
    protected function getSuppressionGrounds(array $legalViolations, array $proportionalityAnalysis): array
    {
        $grounds = [];

        foreach ($legalViolations as $violation) {
            $grounds[] = [
                'ground' => $violation['type'],
                'legal_basis' => $violation['zkp_violation'].' + '.$violation['constitutional_violation'],
                'argument' => $violation['description'],
                'evidence' => $violation['evidence'],
            ];
        }

        return $grounds;
    }

    /**
     * Get recommended actions
     *
     * @param  int  $abuseSeverity  Abuse severity score
     * @param  array  $abusePatterns  Abuse patterns
     * @return array Recommended actions
     */
    protected function getRecommendedActions(int $abuseSeverity, array $abusePatterns): array
    {
        $actions = [];

        if ($abuseSeverity >= 60) {
            $actions[] = [
                'action' => 'file_suppression_motion',
                'urgency' => 'immediate',
                'description' => 'File motion to suppress evidence within 8 days of arraignment',
                'deadline' => 'ZKP Čl. 10, St. 2 - Prijedlog za isključenje dokaza',
            ];
        }

        if ($abuseSeverity >= 75) {
            $actions[] = [
                'action' => 'file_constitutional_complaint',
                'urgency' => 'high',
                'description' => 'Prepare and file constitutional complaint (ustavna tužba)',
                'deadline' => '30 dana od konačne odluke - Ustavni zakon o Ustavnom sudu',
            ];
        }

        if (count($abusePatterns) >= 2) {
            $actions[] = [
                'action' => 'collect_statistical_evidence',
                'urgency' => 'medium',
                'description' => 'Gather statistical evidence of search warrant abuse patterns',
                'deadline' => 'Before filing suppression motion',
            ];
        }

        $actions[] = [
            'action' => 'document_violations',
            'urgency' => 'immediate',
            'description' => 'Document all violations and gather witness testimony',
            'deadline' => 'As soon as possible',
        ];

        return $actions;
    }
}
