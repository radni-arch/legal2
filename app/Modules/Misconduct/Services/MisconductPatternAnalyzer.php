<?php

namespace App\Modules\Misconduct\Services;

use App\Models\LegalCase;
use Illuminate\Support\Facades\Log;

/**
 * MisconductPatternAnalyzer
 *
 * Identifies patterns of prosecutorial misconduct across multiple violations.
 *
 * Pattern Types:
 * 1. Repeated Violations - Same type occurring multiple times
 * 2. Escalating Severity - Violations becoming more severe over time
 * 3. Rights Violation Patterns - Multiple rights violations indicating systemic abuse
 * 4. Evidence Suppression Patterns - Pattern of hiding/manipulating evidence
 * 5. Systemic Issues - Same actors (prosecutor/police) across multiple violations
 *
 * Purpose: Distinguish isolated misconduct from systematic abuse of process
 */
class MisconductPatternAnalyzer
{
    /**
     * Analyze patterns across multiple misconduct instances
     *
     * @param  array  $instances  Array of misconduct violations from MisconductDetector
     * @param  LegalCase  $case  The case being analyzed
     * @return array Pattern analysis results
     */
    public function analyzePatterns(array $instances, LegalCase $case): array
    {
        if (empty($instances)) {
            Log::info('MisconductPatternAnalyzer: No violations to analyze');

            return [
                'repeated_violations' => [],
                'escalating_severity' => false,
                'rights_violations_pattern' => [],
                'evidence_suppression_pattern' => [],
                'systemic_issues' => [],
                'pattern_severity' => 0,
            ];
        }

        Log::info('MisconductPatternAnalyzer: Analyzing patterns', [
            'case_id' => $case->id,
            'violation_count' => count($instances),
        ]);

        $patterns = [
            'repeated_violations' => $this->findRepeatedViolations($instances),
            'escalating_severity' => $this->detectEscalatingSeverity($instances),
            'rights_violations_pattern' => $this->analyzeRightsViolationPattern($instances),
            'evidence_suppression_pattern' => $this->analyzeEvidenceSuppressionPattern($instances),
            'systemic_issues' => $this->identifySystemicIssues($instances, $case),
        ];

        // Calculate overall pattern severity
        $patterns['pattern_severity'] = $this->calculatePatternSeverity($patterns, $instances);

        // Add pattern summary
        $patterns['summary'] = $this->generatePatternSummary($patterns, $instances);

        Log::info('MisconductPatternAnalyzer: Analysis complete', [
            'case_id' => $case->id,
            'pattern_severity' => $patterns['pattern_severity'],
            'systemic' => ! empty($patterns['systemic_issues']),
        ]);

        return $patterns;
    }

    /**
     * Find repeated violation types
     *
     * Repeated violations indicate pattern of behavior, not isolated incident
     *
     * @param  array  $instances  Misconduct violations
     * @return array Violation types that occurred 2+ times with counts
     */
    protected function findRepeatedViolations(array $instances): array
    {
        $types = array_column($instances, 'type');
        $counts = array_count_values($types);

        // Filter to only violations that occurred 2+ times
        $repeated = array_filter($counts, fn ($count) => $count >= 2);

        // Add details for each repeated violation
        $details = [];
        foreach ($repeated as $type => $count) {
            $violations = array_filter($instances, fn ($i) => ($i['type'] ?? '') === $type);

            $details[$type] = [
                'count' => $count,
                'type' => $type,
                'violations' => array_values($violations),
                'avg_severity' => round(array_sum(array_column($violations, 'severity')) / $count, 1),
                'pattern_significance' => $this->assessRepetitionSignificance($count),
            ];
        }

        return $details;
    }

    /**
     * Assess significance of violation repetition
     *
     * @param  int  $count  Number of times violation occurred
     * @return string Significance level
     */
    protected function assessRepetitionSignificance(int $count): string
    {
        if ($count >= 4) {
            return 'critical'; // 4+ same violations = critical pattern
        } elseif ($count >= 3) {
            return 'high'; // 3 same violations = high concern
        } else {
            return 'moderate'; // 2 violations = moderate concern
        }
    }

    /**
     * Detect escalating severity over time
     *
     * If violations are becoming more severe, indicates prosecutor becoming
     * more aggressive/reckless over time
     *
     * @param  array  $instances  Misconduct violations
     * @return bool True if severity is escalating
     */
    protected function detectEscalatingSeverity(array $instances): bool
    {
        if (count($instances) < 2) {
            return false; // Need at least 2 violations to detect escalation
        }

        // Sort by timestamp
        usort($instances, function ($a, $b) {
            $timeA = strtotime($a['timestamp'] ?? 'now');
            $timeB = strtotime($b['timestamp'] ?? 'now');

            return $timeA <=> $timeB;
        });

        $severities = array_column($instances, 'severity');

        // Compare first half to second half
        $midpoint = (int) floor(count($severities) / 2);
        $firstHalf = array_slice($severities, 0, $midpoint);
        $secondHalf = array_slice($severities, $midpoint);

        $avgFirst = array_sum($firstHalf) / count($firstHalf);
        $avgSecond = array_sum($secondHalf) / count($secondHalf);

        // Escalating if second half is at least 10 points more severe on average
        return ($avgSecond - $avgFirst) >= 10;
    }

    /**
     * Analyze rights violation patterns
     *
     * Multiple rights violations indicate systemic disregard for defendant rights
     *
     * @param  array  $instances  Misconduct violations
     * @return array Rights violation pattern analysis
     */
    protected function analyzeRightsViolationPattern(array $instances): array
    {
        // Filter to rights violations only
        $rightsViolations = array_filter($instances, fn ($i) => ($i['type'] ?? '') === 'rights_violation' ||
            stripos($i['description'] ?? '', 'rights') !== false ||
            stripos($i['description'] ?? '', 'lawyer') !== false
        );

        if (empty($rightsViolations)) {
            return [];
        }

        // Analyze specific rights that were violated
        $violatedRights = [];
        foreach ($rightsViolations as $violation) {
            $description = $violation['description'] ?? '';

            if (stripos($description, 'no lawyer') !== false || stripos($description, 'lawyer') !== false) {
                $violatedRights['right_to_counsel'] = ($violatedRights['right_to_counsel'] ?? 0) + 1;
            }

            if (stripos($description, 'rights not') !== false || stripos($description, 'not informed') !== false) {
                $violatedRights['right_to_information'] = ($violatedRights['right_to_information'] ?? 0) + 1;
            }

            if (stripos($description, 'coercion') !== false || stripos($description, 'threat') !== false) {
                $violatedRights['freedom_from_coercion'] = ($violatedRights['freedom_from_coercion'] ?? 0) + 1;
            }
        }

        return [
            'total_rights_violations' => count($rightsViolations),
            'violated_rights' => $violatedRights,
            'severity' => count($rightsViolations) >= 3 ? 'critical' : (count($rightsViolations) >= 2 ? 'high' : 'moderate'),
            'legal_basis' => 'Ustav RH Članak 29 - Pravo na obranu',
            'recommendation' => count($rightsViolations) >= 2
                ? 'File complaint with Judicial Council - pattern of rights violations'
                : 'Monitor for additional violations',
        ];
    }

    /**
     * Analyze evidence suppression patterns
     *
     * Multiple instances of hidden/manipulated evidence indicate systematic
     * suppression of exculpatory evidence
     *
     * @param  array  $instances  Misconduct violations
     * @return array Evidence suppression pattern analysis
     */
    protected function analyzeEvidenceSuppressionPattern(array $instances): array
    {
        // Filter to evidence-related violations
        $evidenceViolations = array_filter($instances, fn ($i) => ($i['type'] ?? '') === 'hidden_evidence' ||
            ($i['type'] ?? '') === 'backdated_document' ||
            stripos($i['description'] ?? '', 'evidence') !== false ||
            stripos($i['description'] ?? '', 'Brady') !== false
        );

        if (empty($evidenceViolations)) {
            return [];
        }

        // Categorize types of evidence suppression
        $suppressionTypes = [];
        foreach ($evidenceViolations as $violation) {
            $type = $violation['type'];

            if ($type === 'hidden_evidence') {
                $suppressionTypes['brady_violations'] = ($suppressionTypes['brady_violations'] ?? 0) + 1;
            } elseif ($type === 'backdated_document') {
                $suppressionTypes['document_manipulation'] = ($suppressionTypes['document_manipulation'] ?? 0) + 1;
            }
        }

        return [
            'total_evidence_violations' => count($evidenceViolations),
            'suppression_types' => $suppressionTypes,
            'severity' => count($evidenceViolations) >= 2 ? 'critical' : 'high',
            'legal_basis' => 'ZKP Članak 292 - Uvjeti dopuštenosti dokaza',
            'recommendation' => count($evidenceViolations) >= 1
                ? 'File dismissal motion - Brady violations mandate dismissal'
                : 'Challenge evidence admissibility',
        ];
    }

    /**
     * Identify systemic issues
     *
     * If multiple violations involve same prosecutor/police unit, indicates
     * systemic problem, not isolated misconduct
     *
     * @param  array  $instances  Misconduct violations
     * @param  LegalCase  $case  The case being analyzed
     * @return array Systemic issues identified
     */
    protected function identifySystemicIssues(array $instances, LegalCase $case): array
    {
        if (count($instances) < 2) {
            return []; // Need multiple violations to identify systemic issues
        }

        $issues = [];

        // Analyze prosecutor patterns
        $prosecutors = array_filter(array_column($instances, 'prosecutor'));
        if (! empty($prosecutors)) {
            $prosecutorCounts = array_count_values($prosecutors);

            foreach ($prosecutorCounts as $prosecutor => $count) {
                if ($count >= 2 && $prosecutor !== 'Unknown') {
                    $prosecutorViolations = array_filter($instances, fn ($i) => ($i['prosecutor'] ?? '') === $prosecutor);

                    $issues[] = [
                        'type' => 'prosecutor_pattern',
                        'actor' => $prosecutor,
                        'violation_count' => $count,
                        'violation_types' => array_unique(array_column($prosecutorViolations, 'type')),
                        'severity' => 'critical',
                        'description' => "Prosecutor '{$prosecutor}' involved in {$count} separate violations. Indicates systemic misconduct, not isolated error.",
                        'recommendation' => 'File complaint with Glavni državni odvjetnik (Chief State Attorney) - pattern of misconduct by prosecutor',
                        'legal_action' => 'disciplinary_complaint',
                    ];
                }
            }
        }

        // Analyze police unit patterns
        $policeUnits = array_filter(array_column($instances, 'police_unit'));
        if (! empty($policeUnits)) {
            $policeCounts = array_count_values($policeUnits);

            foreach ($policeCounts as $unit => $count) {
                if ($count >= 2 && $unit !== 'Unknown') {
                    $unitViolations = array_filter($instances, fn ($i) => ($i['police_unit'] ?? '') === $unit);

                    $issues[] = [
                        'type' => 'police_unit_pattern',
                        'actor' => $unit,
                        'violation_count' => $count,
                        'violation_types' => array_unique(array_column($unitViolations, 'type')),
                        'severity' => 'high',
                        'description' => "Police unit '{$unit}' involved in {$count} separate violations. Indicates institutional problem.",
                        'recommendation' => 'File complaint with Police Internal Affairs - systemic issues within unit',
                        'legal_action' => 'internal_affairs_complaint',
                    ];
                }
            }
        }

        // Analyze coordinated violations (prosecutor + police)
        if (! empty($prosecutors) && ! empty($policeUnits)) {
            $issues[] = [
                'type' => 'coordinated_misconduct',
                'actors' => [
                    'prosecutors' => array_unique($prosecutors),
                    'police_units' => array_unique($policeUnits),
                ],
                'violation_count' => count($instances),
                'severity' => 'critical',
                'description' => 'Multiple violations involving both prosecution and police. Indicates coordinated misconduct.',
                'recommendation' => 'File complaints with both State Attorney and Police Internal Affairs. Consider civil rights lawsuit.',
                'legal_action' => 'multiple_complaints_plus_civil',
            ];
        }

        return $issues;
    }

    /**
     * Calculate overall pattern severity
     *
     * @param  array  $patterns  Pattern analysis results
     * @param  array  $instances  Original violations
     * @return int Pattern severity score (0-100)
     */
    protected function calculatePatternSeverity(array $patterns, array $instances): int
    {
        $score = 0;

        // Base score: average individual violation severity
        $avgSeverity = array_sum(array_column($instances, 'severity')) / count($instances);
        $score += $avgSeverity * 0.4; // 40% weight

        // Repeated violations bonus (+10 per repeated type)
        $score += count($patterns['repeated_violations']) * 10;

        // Escalating severity bonus (+15)
        if ($patterns['escalating_severity']) {
            $score += 15;
        }

        // Rights violations pattern bonus (+20 if critical)
        if (! empty($patterns['rights_violations_pattern'])) {
            $rightsPattern = $patterns['rights_violations_pattern'];
            if (($rightsPattern['severity'] ?? '') === 'critical') {
                $score += 20;
            } elseif (($rightsPattern['severity'] ?? '') === 'high') {
                $score += 10;
            }
        }

        // Evidence suppression pattern bonus (+20 if present)
        if (! empty($patterns['evidence_suppression_pattern'])) {
            $score += 20;
        }

        // Systemic issues bonus (+25 if present)
        if (! empty($patterns['systemic_issues'])) {
            $score += 25;
        }

        return min(100, (int) round($score));
    }

    /**
     * Generate human-readable pattern summary
     *
     * @param  array  $patterns  Pattern analysis results
     * @param  array  $instances  Original violations
     * @return string Pattern summary
     */
    protected function generatePatternSummary(array $patterns, array $instances): string
    {
        $summary = [];

        // Repeated violations
        if (! empty($patterns['repeated_violations'])) {
            $repeated = [];
            foreach ($patterns['repeated_violations'] as $type => $data) {
                $repeated[] = "{$type} ({$data['count']}x)";
            }
            $summary[] = 'Repeated violations: '.implode(', ', $repeated);
        }

        // Escalating severity
        if ($patterns['escalating_severity']) {
            $summary[] = 'Violations escalating in severity over time';
        }

        // Rights violations
        if (! empty($patterns['rights_violations_pattern'])) {
            $count = $patterns['rights_violations_pattern']['total_rights_violations'];
            $summary[] = "Pattern of {$count} rights violations";
        }

        // Evidence suppression
        if (! empty($patterns['evidence_suppression_pattern'])) {
            $count = $patterns['evidence_suppression_pattern']['total_evidence_violations'];
            $summary[] = "Pattern of {$count} evidence suppression violations";
        }

        // Systemic issues
        if (! empty($patterns['systemic_issues'])) {
            $summary[] = 'Systemic issues detected ('.count($patterns['systemic_issues']).' patterns)';
        }

        if (empty($summary)) {
            return 'No significant patterns detected - violations appear isolated';
        }

        return implode('. ', $summary).'.';
    }

    /**
     * Get pattern-based recommendations
     *
     * @param  array  $patterns  Pattern analysis results
     * @return array Recommendations based on patterns
     */
    public function getRecommendations(array $patterns): array
    {
        $recommendations = [];

        // Systemic issues = multiple complaints
        if (! empty($patterns['systemic_issues'])) {
            foreach ($patterns['systemic_issues'] as $issue) {
                $recommendations[] = [
                    'action' => $issue['legal_action'] ?? 'complaint',
                    'priority' => 'urgent',
                    'description' => $issue['recommendation'] ?? 'File complaint',
                ];
            }
        }

        // Evidence suppression = dismissal
        if (! empty($patterns['evidence_suppression_pattern'])) {
            $recommendations[] = [
                'action' => 'file_dismissal_motion',
                'priority' => 'urgent',
                'description' => $patterns['evidence_suppression_pattern']['recommendation'] ?? 'File dismissal motion',
            ];
        }

        // Rights violations = judicial complaint
        if (! empty($patterns['rights_violations_pattern'])) {
            if (($patterns['rights_violations_pattern']['severity'] ?? '') === 'critical') {
                $recommendations[] = [
                    'action' => 'file_judicial_complaint',
                    'priority' => 'high',
                    'description' => $patterns['rights_violations_pattern']['recommendation'] ?? 'File judicial complaint',
                ];
            }
        }

        return $recommendations;
    }
}
