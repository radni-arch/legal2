<?php

namespace App\Modules\Misconduct;

use App\Models\LegalCase;
use App\Modules\Misconduct\Services\AppealBuilder;
use App\Modules\Misconduct\Services\ComplaintGenerator;
use App\Modules\Misconduct\Services\DismissalMotionGenerator;
use App\Modules\Misconduct\Services\MisconductDetector;
use App\Modules\Misconduct\Services\MisconductPatternAnalyzer;
use Illuminate\Support\Facades\Log;

/**
 * Prosecutorial Misconduct Module
 *
 * Detects and analyzes prosecutorial misconduct in Croatian criminal proceedings.
 *
 * Types of misconduct detected:
 * - Fabricated probable cause
 * - Hidden evidence (Brady violations)
 * - Backdated documents
 * - Rights violations (no lawyer access, coerced statements)
 * - Prosecutor threats/lying
 * - Misdemeanor pretexting
 *
 * Ethical Framework:
 * ✅ Detects actual misconduct through pattern analysis
 * ✅ Based on evidence and legal standards
 * ❌ Does not fabricate misconduct
 * ❌ Does not create false accusations
 */
class ProsecutorialMisconductModule
{
    public function __construct(
        protected MisconductDetector $detector,
        protected MisconductPatternAnalyzer $patternAnalyzer,
        protected DismissalMotionGenerator $dismissalGenerator,
        protected ComplaintGenerator $complaintGenerator,
        protected AppealBuilder $appealBuilder
    ) {}

    /**
     * Analyze case for prosecutorial misconduct
     *
     * @param  string  $caseId  The ID of the legal case to analyze
     * @param  array  $options  Optional analysis options
     * @return array Comprehensive misconduct analysis
     */
    public function analyzeMisconduct(string $caseId, array $options = []): array
    {
        Log::info('ProsecutorialMisconductModule: Starting misconduct analysis', [
            'case_id' => $caseId,
            'options' => $options,
        ]);

        $case = LegalCase::with(['documents', 'evidence'])->findOrFail($caseId);

        // Detect specific instances of misconduct
        $misconductInstances = $this->detector->detect($case, $options);

        Log::info('ProsecutorialMisconductModule: Detection complete', [
            'case_id' => $caseId,
            'violations_found' => count($misconductInstances),
        ]);

        // Identify patterns across the case
        $patterns = $this->patternAnalyzer->analyzePatterns($misconductInstances, $case);

        // Calculate severity score
        $severityScore = $this->calculateSeverityScore($misconductInstances);

        $result = [
            'case_id' => $caseId,
            'misconduct_detected' => ! empty($misconductInstances),
            'total_violations' => count($misconductInstances),
            'severity_score' => $severityScore,
            'severity_level' => $this->getSeverityLevel($severityScore),
            'instances' => $misconductInstances,
            'violations' => $misconductInstances, // Alias for API consistency
            'summary' => [
                'total_violations' => count($misconductInstances),
                'severity_score' => $severityScore,
                'severity_level' => $this->getSeverityLevel($severityScore),
                'misconduct_detected' => ! empty($misconductInstances),
                'dismissal_warranted' => ! empty($this->identifyDismissalGrounds($misconductInstances)),
            ],
            'patterns' => $patterns,
            'recommended_actions' => $this->recommendActions($misconductInstances, $patterns),
            'dismissal_grounds' => $this->identifyDismissalGrounds($misconductInstances),
            'analysis_timestamp' => now()->toIso8601String(),
        ];

        Log::info('ProsecutorialMisconductModule: Analysis complete', [
            'case_id' => $caseId,
            'severity_score' => $severityScore,
            'dismissal_warranted' => ! empty($result['dismissal_grounds']),
        ]);

        return $result;
    }

    /**
     * Calculate severity score based on violations
     *
     * Score calculation:
     * - Average severity of all violations
     * - Bonus multiplier for multiple violations (10% per violation)
     * - Capped at 100
     *
     * @param  array  $instances  Array of misconduct instances
     * @return int Severity score (0-100)
     */
    protected function calculateSeverityScore(array $instances): int
    {
        if (empty($instances)) {
            return 0;
        }

        // Extract severity values with null safety
        $severities = array_filter(array_column($instances, 'severity'), fn ($s) => is_numeric($s));

        if (empty($severities)) {
            Log::warning('ProsecutorialMisconductModule: No valid severity values found', [
                'instances_count' => count($instances),
            ]);

            return 0;
        }

        $totalSeverity = array_sum($severities);
        $avgSeverity = $totalSeverity / count($severities);

        // Bonus for multiple violations (indicates pattern)
        $multiplier = 1 + (count($instances) * 0.1);

        return min(100, (int) ($avgSeverity * $multiplier));
    }

    /**
     * Get severity level label from score
     *
     * @param  int  $score  Severity score (0-100)
     * @return string Severity level
     */
    protected function getSeverityLevel(int $score): string
    {
        if ($score >= 90) {
            return 'critical';
        } elseif ($score >= 75) {
            return 'high';
        } elseif ($score >= 50) {
            return 'medium';
        } elseif ($score > 0) {
            return 'low';
        } else {
            return 'none';
        }
    }

    /**
     * Recommend actions based on detected misconduct
     *
     * Action priorities:
     * - urgent: Immediate action required (severity >= 80)
     * - high: Action should be taken soon (pattern violations)
     * - medium: Consider action (moderate violations)
     *
     * @param  array  $instances  Misconduct instances
     * @param  array  $patterns  Pattern analysis results
     * @return array Recommended actions
     */
    protected function recommendActions(array $instances, array $patterns): array
    {
        $actions = [];

        // High severity = dismissal motion
        if ($this->calculateSeverityScore($instances) >= 80) {
            $actions[] = [
                'action' => 'file_dismissal_motion',
                'priority' => 'urgent',
                'description' => 'File motion to dismiss based on egregious prosecutorial misconduct',
                'legal_basis' => 'ZKP Članak 175, 177 - Obustava postupka',
                'next_steps' => [
                    'Generate dismissal motion',
                    'File with court within 8 days',
                    'Request immediate hearing',
                ],
            ];
        }

        // Pattern of rights violations = complaint to judicial council
        if (isset($patterns['rights_violations_pattern']['total_rights_violations']) &&
            $patterns['rights_violations_pattern']['total_rights_violations'] >= 2) {
            $actions[] = [
                'action' => 'file_judicial_complaint',
                'priority' => 'high',
                'description' => 'File complaint with Državno sudbeno vijeće (Judicial Council)',
                'legal_basis' => 'Zakon o Državnom sudbenom vijeću',
                'next_steps' => [
                    'Prepare formal complaint',
                    'Attach evidence of violations',
                    'Submit to Judicial Council',
                ],
            ];
        }

        // Hidden evidence (Brady) = complaint to State Attorney
        $bradyViolations = array_filter($instances, fn ($i) => $i['type'] === 'hidden_evidence');
        if (count($bradyViolations) >= 1) {
            $actions[] = [
                'action' => 'file_state_attorney_complaint',
                'priority' => 'high',
                'description' => 'File complaint with Glavni državni odvjetnik (Chief State Attorney)',
                'legal_basis' => 'Zakon o državnom odvjetništvu',
                'next_steps' => [
                    'Document all hidden evidence',
                    'Prepare formal complaint',
                    'Request disciplinary investigation',
                ],
            ];
        }

        // Systemic issues = consider civil rights lawsuit
        if (isset($patterns['systemic_issues']) && ! empty($patterns['systemic_issues'])) {
            $actions[] = [
                'action' => 'prepare_civil_lawsuit',
                'priority' => 'medium',
                'description' => 'Prepare civil rights lawsuit for damages',
                'legal_basis' => 'Zakon o obveznim odnosima - Naknada štete',
                'next_steps' => [
                    'Document all violations and damages',
                    'Consult civil attorney',
                    'File after criminal case resolution',
                ],
            ];
        }

        // Multiple violations = build appeal grounds
        if (count($instances) >= 3) {
            $actions[] = [
                'action' => 'prepare_appeal',
                'priority' => 'medium',
                'description' => 'Build appeal on prosecutorial misconduct grounds',
                'legal_basis' => 'ZKP Članak 378 - Žalbeni razlozi',
                'next_steps' => [
                    'Document all violations',
                    'Prepare appeal brief',
                    'File within 15 days of judgment',
                ],
            ];
        }

        return $actions;
    }

    /**
     * Identify grounds for case dismissal
     *
     * Dismissal warranted when:
     * - Severity >= 85 (severe violations)
     * - Instance explicitly mandates dismissal
     *
     * @param  array  $instances  Misconduct instances
     * @return array Dismissal grounds
     */
    protected function identifyDismissalGrounds(array $instances): array
    {
        $grounds = [];

        foreach ($instances as $instance) {
            if (($instance['severity'] ?? 0) >= 85 || ($instance['mandates_dismissal'] ?? false)) {
                $grounds[] = [
                    'type' => $instance['type'],
                    'legal_basis' => $instance['legal_basis'],
                    'description' => $instance['description'],
                    'remedy' => 'Case dismissal',
                    'supporting_evidence' => $instance['evidence'] ?? [],
                    'croatian_citation' => $instance['croatian_citation'] ?? '',
                ];
            }
        }

        return $grounds;
    }

    /**
     * Get summary statistics for misconduct analysis
     *
     * @param  string  $caseId  Case ID
     * @return array Summary statistics
     */
    public function getSummary(string $caseId): array
    {
        $analysis = $this->analyzeMisconduct($caseId);

        return [
            'case_id' => $caseId,
            'misconduct_detected' => $analysis['misconduct_detected'],
            'total_violations' => $analysis['total_violations'],
            'severity_score' => $analysis['severity_score'],
            'dismissal_warranted' => ! empty($analysis['dismissal_grounds']),
            'urgent_actions' => count(array_filter(
                $analysis['recommended_actions'],
                fn ($a) => $a['priority'] === 'urgent'
            )),
            'violation_types' => array_count_values(array_column($analysis['instances'], 'type')),
        ];
    }

    /**
     * Generate dismissal motion based on misconduct
     *
     * @param  string  $caseId  Case ID
     * @return array Dismissal motion details
     */
    public function generateDismissalMotion(string $caseId): array
    {
        Log::info('ProsecutorialMisconductModule: Generating dismissal motion', [
            'case_id' => $caseId,
        ]);

        // Analyze case first
        $analysis = $this->analyzeMisconduct($caseId);

        // Get the case
        $case = LegalCase::findOrFail($caseId);

        // Generate dismissal motion
        $result = $this->dismissalGenerator->generate($case, $analysis['instances']);

        Log::info('ProsecutorialMisconductModule: Dismissal motion generated', [
            'case_id' => $caseId,
            'dismissal_warranted' => $result['dismissal_warranted'] ?? false,
        ]);

        return $result;
    }

    /**
     * Generate complaint to State Attorney, Judicial Council, or Police
     *
     * @param  string  $caseId  Case ID
     * @param  string  $complaintType  Type of complaint
     * @return array Complaint details
     */
    public function generateComplaint(string $caseId, string $complaintType): array
    {
        Log::info('ProsecutorialMisconductModule: Generating complaint', [
            'case_id' => $caseId,
            'complaint_type' => $complaintType,
        ]);

        // Analyze case first
        $analysis = $this->analyzeMisconduct($caseId);

        // Get the case
        $case = LegalCase::findOrFail($caseId);

        // Generate complaint
        $result = $this->complaintGenerator->generateComplaint(
            $case,
            $analysis['instances'],
            $complaintType
        );

        Log::info('ProsecutorialMisconductModule: Complaint generated', [
            'case_id' => $caseId,
            'complaint_type' => $complaintType,
            'complaint_warranted' => $result['complaint_warranted'] ?? false,
        ]);

        return $result;
    }

    /**
     * Build appeal based on misconduct
     *
     * @param  string  $caseId  Case ID
     * @param  string  $appealType  Type of appeal
     * @return array Appeal details
     */
    public function buildAppeal(string $caseId, string $appealType): array
    {
        Log::info('ProsecutorialMisconductModule: Building appeal', [
            'case_id' => $caseId,
            'appeal_type' => $appealType,
        ]);

        // Analyze case first
        $analysis = $this->analyzeMisconduct($caseId);

        // Get the case
        $case = LegalCase::findOrFail($caseId);

        // Build appeal
        $result = $this->appealBuilder->buildAppeal(
            $case,
            $analysis['instances'],
            $appealType
        );

        Log::info('ProsecutorialMisconductModule: Appeal built', [
            'case_id' => $caseId,
            'appeal_type' => $appealType,
            'appeal_warranted' => $result['appeal_warranted'] ?? false,
        ]);

        return $result;
    }
}
