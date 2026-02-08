<?php

namespace App\Modules\Misconduct\Services;

use App\Models\LegalCase;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Log;

/**
 * MisconductDetector
 *
 * Detects specific instances of prosecutorial misconduct in Croatian criminal proceedings.
 *
 * Misconduct Types Detected:
 * 1. Fabricated Probable Cause - Vague/false arrest warrants
 * 2. Hidden Evidence - Brady violations (withheld exculpatory evidence)
 * 3. Backdated Documents - Document timestamp manipulation
 * 4. Rights Violations - No lawyer access, coerced statements
 * 5. Prosecutor Threats/Lying - Threats to defendants/witnesses
 * 6. Misdemeanor Pretexting - Charging misdemeanor to investigate felony
 *
 * Ethical Framework:
 * ✅ Detects actual misconduct through evidence analysis
 * ✅ Based on Croatian legal standards (ZKP, Ustav RH)
 * ❌ Does not fabricate misconduct
 * ❌ Does not create false accusations
 */
class MisconductDetector
{
    public function __construct(
        protected OpenAIService $openAI
    ) {}

    /**
     * Detect all types of prosecutorial misconduct in a case
     *
     * @param  LegalCase  $case  The case to analyze
     * @param  array  $options  Detection options
     * @return array Array of misconduct violations
     */
    public function detect(LegalCase $case, array $options = []): array
    {
        Log::info('MisconductDetector: Starting detection', [
            'case_id' => $case->id,
            'options' => $options,
        ]);

        $violations = [];

        // Check for fabricated probable cause
        $violations = array_merge($violations, $this->detectFabricatedProbableCause($case));

        // Check for hidden/withheld evidence (Brady violations)
        $violations = array_merge($violations, $this->detectHiddenEvidence($case));

        // Check for backdated documents
        $violations = array_merge($violations, $this->detectBackdatedDocuments($case));

        // Check for rights violations (no lawyer, coerced statements)
        $violations = array_merge($violations, $this->detectRightsViolations($case));

        // Check for prosecutor threats/lying
        $violations = array_merge($violations, $this->detectThreatsOrLying($case));

        // Check for misdemeanor pretexting (charging misdemeanor to investigate felony)
        $violations = array_merge($violations, $this->detectMisdemeanorPretexting($case));

        Log::info('MisconductDetector: Detection complete', [
            'case_id' => $case->id,
            'violations_found' => count($violations),
            'types' => array_column($violations, 'type'),
        ]);

        return $violations;
    }

    /**
     * Detect fabricated probable cause in arrest/search warrants
     *
     * Indicators:
     * - Vague, conclusory statements without specific facts
     * - Generic descriptions ("suspicious behavior")
     * - Lack of corroboration
     * - Actual evidence contradicts warrant claims
     *
     * Legal Basis: ZKP Članak 9, Ustav RH Članak 32
     */
    protected function detectFabricatedProbableCause(LegalCase $case): array
    {
        $violations = [];

        // Analyze warrant documents
        $warrants = $case->documents->filter(fn ($d) => stripos($d->category ?? '', 'warrant') !== false ||
            stripos($d->category ?? '', 'nalog') !== false ||
            stripos($d->type ?? '', 'warrant') !== false ||
            stripos($d->type ?? '', 'nalog') !== false
        );

        foreach ($warrants as $warrant) {
            $prompt = <<<PROMPT
Analyze this arrest/search warrant for fabricated or insufficient probable cause under Croatian law.

Warrant Details:
{$warrant->content}

Case Context:
{$case->description}

Evaluate:
1. Does the warrant contain specific, articulable facts?
2. Are descriptions vague or conclusory ("suspicious behavior", "looked nervous")?
3. Is there corroboration for informant tips?
4. Do specific facts establish probable cause, or just suspicion?

Red Flags:
- Generic descriptions without specifics
- Reliance on uncorroborated anonymous tips
- Conclusory statements ("I believe defendant committed crime")
- Circular reasoning
- Facts that don't support the claimed offense

Croatian Standard: ZKP Članak 9 requires lawful evidence collection. Ustav RH Članak 32 protects freedom of movement - arrests require specific, articulable facts.

Respond with JSON:
{
    "fabricated": true/false,
    "confidence": 0-100,
    "issues": ["specific issue 1", "specific issue 2"],
    "reasoning": "detailed explanation"
}
PROMPT;

            try {
                $response = $this->openAI->chat([
                    ['role' => 'system', 'content' => 'You are a Croatian criminal defense expert analyzing probable cause.'],
                    ['role' => 'user', 'content' => $prompt],
                ], 'gpt-4o', [
                    'response_format' => ['type' => 'json_object'],
                    'temperature' => 0.2,
                ]);

                $result = json_decode($response['choices'][0]['message']['content'], true);
            } catch (\Exception $e) {
                Log::error('MisconductDetector: OpenAI API error in fabricated probable cause detection', [
                    'case_id' => $case->id,
                    'warrant_id' => $warrant->id,
                    'error' => $e->getMessage(),
                ]);

                // Skip this warrant and continue
                continue;
            }

            if (($result['fabricated'] ?? false) && ($result['confidence'] ?? 0) >= 60) {
                $violations[] = [
                    'type' => 'fabricated_probable_cause',
                    'description' => 'Arrest/search warrant lacks sufficient probable cause. '.
                                    implode('; ', $result['issues'] ?? []),
                    'legal_basis' => 'ZKP Članak 9 (Zakonitost), Ustav RH Članak 32 (Sloboda kretanja)',
                    'croatian_citation' => 'ZKP Čl. 9, Ustav RH Čl. 32',
                    'severity' => 90,
                    'remedy' => 'Evidence suppression, case dismissal',
                    'mandates_dismissal' => ($result['confidence'] ?? 0) >= 85,
                    'evidence' => [
                        'warrant_id' => $warrant->id,
                        'issues' => $result['issues'] ?? [],
                        'reasoning' => $result['reasoning'] ?? '',
                        'confidence' => $result['confidence'] ?? 0,
                    ],
                    'timestamp' => now()->toIso8601String(),
                    'prosecutor' => $case->prosecutor ?? 'Unknown',
                ];
            }
        }

        return $violations;
    }

    /**
     * Detect hidden/withheld evidence (Brady violations)
     *
     * Brady v. Maryland violations - prosecutor duty to disclose exculpatory evidence
     *
     * Indicators:
     * - Late disclosure (days before trial)
     * - Evidence disclosed only after defense request
     * - Evidence not disclosed at all (discovered later)
     * - Exculpatory evidence in prosecutor's files not shared
     *
     * Legal Basis: ZKP Članak 292, Ustav RH Članak 29
     */
    protected function detectHiddenEvidence(LegalCase $case): array
    {
        $violations = [];

        // Analyze disclosure timeline and evidence
        $prompt = <<<PROMPT
Analyze this case for Brady violations (hidden exculpatory evidence) under Croatian law.

Case Details:
Title: {$case->title}
Description: {$case->description}
Status: {$case->status}

Documents: {$case->documents->count()} documents
Evidence: {$case->evidence->count()} evidence items

Evaluate:
1. Was all evidence disclosed timely (ZKP requires prompt disclosure)?
2. Are there gaps in evidence numbering/timeline suggesting omissions?
3. Were defense requests for evidence denied or ignored?
4. Is there evidence of late disclosure (disclosed days before trial)?
5. Are there references to evidence that was never provided?

Brady Standard: Prosecutor must disclose all exculpatory evidence that is material to guilt or punishment.

Croatian Law: ZKP Članak 292 - Prosecution must disclose all evidence. Ustav RH Članak 29 - Right to fair trial includes access to evidence.

Respond with JSON:
{
    "hidden_evidence_detected": true/false,
    "confidence": 0-100,
    "violations": [
        {
            "type": "late_disclosure|non_disclosure|selective_disclosure",
            "description": "specific description",
            "evidence_affected": "what evidence"
        }
    ],
    "reasoning": "explanation"
}
PROMPT;

        $response = $this->openAI->chat([
            ['role' => 'system', 'content' => 'You are a Croatian criminal defense expert analyzing Brady violations.'],
            ['role' => 'user', 'content' => $prompt],
        ], 'gpt-4o', [
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.2,
        ]);

        $result = json_decode($response['choices'][0]['message']['content'], true);

        if (($result['hidden_evidence_detected'] ?? false) && ($result['confidence'] ?? 0) >= 60) {
            foreach (($result['violations'] ?? []) as $violation) {
                $violations[] = [
                    'type' => 'hidden_evidence',
                    'description' => 'Brady violation: '.($violation['description'] ?? 'Exculpatory evidence withheld'),
                    'legal_basis' => 'ZKP Članak 292 (Uvjeti dopuštenosti dokaza), Ustav RH Članak 29 (Pravo na pravično suđenje)',
                    'croatian_citation' => 'ZKP Čl. 292, Ustav RH Čl. 29',
                    'severity' => 95,
                    'remedy' => 'Case dismissal, retrial with all evidence',
                    'mandates_dismissal' => true,
                    'evidence' => [
                        'violation_type' => $violation['type'] ?? 'non_disclosure',
                        'evidence_affected' => $violation['evidence_affected'] ?? 'Unknown',
                        'reasoning' => $result['reasoning'] ?? '',
                        'confidence' => $result['confidence'] ?? 0,
                    ],
                    'timestamp' => now()->toIso8601String(),
                    'prosecutor' => $case->prosecutor ?? 'Unknown',
                ];
            }
        }

        return $violations;
    }

    /**
     * Detect backdated documents
     *
     * Indicators:
     * - Document header date doesn't match file metadata
     * - Chronological inconsistencies (document references events after its date)
     * - Multiple versions with different dates
     * - Dates that don't align with case timeline
     *
     * Legal Basis: ZKP Članak 11
     */
    protected function detectBackdatedDocuments(LegalCase $case): array
    {
        $violations = [];

        foreach ($case->documents as $document) {
            // Check for metadata inconsistencies
            $createdAt = $document->created_at;
            $documentDate = $document->document_date ?? $document->created_at;

            // Check if document claims to be dated before it was created (with 2 day tolerance)
            // Use copy() to avoid mutating the original Carbon instance
            $threshold = $createdAt->copy()->subDays(2);

            if ($documentDate < $threshold) {
                $discrepancyDays = $createdAt->diffInDays($documentDate, false);

                $violations[] = [
                    'type' => 'backdated_document',
                    'description' => "Document '{$document->title}' dated {$documentDate->format('Y-m-d')} but file created {$createdAt->format('Y-m-d')}. Discrepancy: {$discrepancyDays} days. Possible backdating.",
                    'legal_basis' => 'ZKP Članak 11 (Isključenje nezakonitih dokaza)',
                    'croatian_citation' => 'ZKP Čl. 11',
                    'severity' => 85,
                    'remedy' => 'Document suppression, evidence exclusion',
                    'mandates_dismissal' => false,
                    'evidence' => [
                        'document_id' => $document->id,
                        'document_title' => $document->title,
                        'claimed_date' => $documentDate->toIso8601String(),
                        'actual_created_at' => $createdAt->toIso8601String(),
                        'discrepancy_days' => abs($discrepancyDays),
                    ],
                    'timestamp' => now()->toIso8601String(),
                    'prosecutor' => $case->prosecutor ?? 'Unknown',
                ];
            }
        }

        // Use AI to detect chronological inconsistencies in document content
        if ($case->documents->count() > 0) {
            $documentsText = $case->documents->map(fn ($d) => "Document: {$d->title} (Date: {$d->document_date})\nContent: ".substr($d->content ?? '', 0, 500)
            )->join("\n\n");

            $prompt = <<<PROMPT
Analyze these documents for chronological inconsistencies suggesting backdating.

Documents:
{$documentsText}

Case Timeline:
{$case->description}

Look for:
1. Documents that reference events occurring after their claimed date
2. Inconsistent date sequences (warrant dated after arrest)
3. Documents that contradict earlier documents
4. Suspiciously precise dates/times (suggesting fabrication)

Respond with JSON:
{
    "inconsistencies_found": true/false,
    "confidence": 0-100,
    "issues": [
        {
            "document": "document name",
            "issue": "specific inconsistency",
            "severity": "high|medium|low"
        }
    ]
}
PROMPT;

            $response = $this->openAI->chat([
                ['role' => 'system', 'content' => 'You are a forensic document analyst examining chronological consistency.'],
                ['role' => 'user', 'content' => $prompt],
            ], 'gpt-4o-mini', [
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.2,
            ]);

            $result = json_decode($response['choices'][0]['message']['content'], true);

            if (($result['inconsistencies_found'] ?? false) && ($result['confidence'] ?? 0) >= 70) {
                foreach (($result['issues'] ?? []) as $issue) {
                    if (($issue['severity'] ?? '') === 'high') {
                        $violations[] = [
                            'type' => 'backdated_document',
                            'description' => "Chronological inconsistency: {$issue['issue']}",
                            'legal_basis' => 'ZKP Članak 11 (Isključenje nezakonitih dokaza)',
                            'croatian_citation' => 'ZKP Čl. 11',
                            'severity' => 85,
                            'remedy' => 'Document suppression',
                            'mandates_dismissal' => false,
                            'evidence' => [
                                'document' => $issue['document'] ?? 'Unknown',
                                'issue' => $issue['issue'] ?? '',
                                'confidence' => $result['confidence'] ?? 0,
                            ],
                            'timestamp' => now()->toIso8601String(),
                            'prosecutor' => $case->prosecutor ?? 'Unknown',
                        ];
                    }
                }
            }
        }

        return $violations;
    }

    /**
     * Detect rights violations
     *
     * Violations:
     * - No lawyer access during interrogation
     * - Defendant not informed of rights
     * - Coerced statements
     * - Interrogation despite request for lawyer
     *
     * Legal Basis: Ustav RH Članak 29(3), ZKP Članak 236, 237
     */
    protected function detectRightsViolations(LegalCase $case): array
    {
        $violations = [];

        // Check evidence for statements/interrogations
        $statements = $case->evidence->filter(fn ($e) => stripos($e->type ?? '', 'statement') !== false ||
            stripos($e->type ?? '', 'testimonial') !== false ||
            stripos($e->type ?? '', 'interrogation') !== false ||
            stripos($e->description ?? '', 'izjava') !== false
        );

        foreach ($statements as $statement) {
            $issues = [];

            // Check if lawyer was present
            if (! isset($statement->lawyer_present) || ! $statement->lawyer_present) {
                $issues[] = 'No lawyer present during interrogation (violation of Ustav RH Čl. 29(3))';
            }

            // Check if rights were read
            if (! isset($statement->rights_warned) || ! $statement->rights_warned) {
                $issues[] = 'Defendant not informed of rights (violation of ZKP Čl. 236)';
            }

            // Check for coercion indicators
            $description = strtolower($statement->description ?? '');
            $coercionKeywords = ['prisilj', 'torture', 'mučenje', 'threat', 'prijetnja', 'intimidation'];

            foreach ($coercionKeywords as $keyword) {
                if (stripos($description, $keyword) !== false) {
                    $issues[] = "Evidence of coercion: {$keyword} (violation of Ustav RH Čl. 23)";
                }
            }

            if (! empty($issues)) {
                $violations[] = [
                    'type' => 'rights_violation',
                    'description' => 'Rights violations during interrogation: '.implode('; ', $issues),
                    'legal_basis' => 'Ustav RH Članak 29(3) (Pravo na obranu), ZKP Članak 236, 237',
                    'croatian_citation' => 'Ustav RH Čl. 29(3), ZKP Čl. 236-237',
                    'severity' => 90,
                    'remedy' => 'Statement suppression, case dismissal if statement is critical',
                    'mandates_dismissal' => count($issues) >= 2, // Multiple violations = dismissal
                    'evidence' => [
                        'statement_id' => $statement->id,
                        'issues' => $issues,
                        'lawyer_present' => $statement->lawyer_present ?? false,
                        'rights_warned' => $statement->rights_warned ?? false,
                    ],
                    'timestamp' => now()->toIso8601String(),
                    'prosecutor' => $case->prosecutor ?? 'Unknown',
                    'police_unit' => $statement->police_unit ?? 'Unknown',
                ];
            }
        }

        return $violations;
    }

    /**
     * Detect prosecutor threats or lying
     *
     * Indicators:
     * - Threats to defendant/witnesses
     * - False statements about evidence
     * - Misrepresentation of law
     * - Intimidation tactics
     *
     * Legal Basis: Ustav RH Članak 23, 29
     */
    protected function detectThreatsOrLying(LegalCase $case): array
    {
        $violations = [];

        // Analyze case documents and evidence for threats/misrepresentations
        $allText = $case->documents->map(fn ($d) => $d->content)->join("\n\n");

        if (strlen($allText) < 100) {
            return []; // Not enough content to analyze
        }

        $prompt = <<<PROMPT
Analyze this case for prosecutorial threats or lying.

Case Documents:
{$allText}

Look for:
1. Threats to defendant or witnesses ("cooperate or face maximum sentence")
2. False statements about evidence
3. Misrepresentation of law or penalties
4. Intimidation tactics
5. Promises that can't be kept
6. Lying about evidence (claiming evidence exists when it doesn't)

Respond with JSON:
{
    "violations_found": true/false,
    "confidence": 0-100,
    "violations": [
        {
            "type": "threat|lying|intimidation",
            "description": "specific description",
            "severity": "high|medium|low",
            "quote": "relevant quote from documents"
        }
    ]
}
PROMPT;

        $response = $this->openAI->chat([
            ['role' => 'system', 'content' => 'You are a legal ethics expert analyzing prosecutor conduct.'],
            ['role' => 'user', 'content' => $prompt],
        ], 'gpt-4o', [
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.2,
        ]);

        $result = json_decode($response['choices'][0]['message']['content'], true);

        if (($result['violations_found'] ?? false) && ($result['confidence'] ?? 0) >= 65) {
            foreach (($result['violations'] ?? []) as $violation) {
                $violations[] = [
                    'type' => 'prosecutor_threats_lying',
                    'description' => ($violation['type'] ?? 'Misconduct').': '.($violation['description'] ?? ''),
                    'legal_basis' => 'Ustav RH Članak 23 (Zabrana mučenja), Članak 29 (Pravo na pravično suđenje)',
                    'croatian_citation' => 'Ustav RH Čl. 23, 29',
                    'severity' => 95,
                    'remedy' => 'Case dismissal, disciplinary action against prosecutor',
                    'mandates_dismissal' => ($violation['severity'] ?? '') === 'high',
                    'evidence' => [
                        'violation_type' => $violation['type'] ?? 'unknown',
                        'quote' => $violation['quote'] ?? '',
                        'confidence' => $result['confidence'] ?? 0,
                    ],
                    'timestamp' => now()->toIso8601String(),
                    'prosecutor' => $case->prosecutor ?? 'Unknown',
                ];
            }
        }

        return $violations;
    }

    /**
     * Detect misdemeanor pretexting
     *
     * Pretexting: Charging minor misdemeanor as pretext to investigate serious felony
     *
     * Indicators:
     * - Minor charge (misdemeanor) but extensive felony investigation
     * - Misdemeanor charge dropped after felony evidence collected
     * - Disproportionate investigative tactics for minor offense
     *
     * Legal Basis: ZKP Članak 9
     */
    protected function detectMisdemeanorPretexting(LegalCase $case): array
    {
        $violations = [];

        $prompt = <<<PROMPT
Analyze this case for misdemeanor pretexting (using minor charge as pretext to investigate serious crime).

Case: {$case->title}
Description: {$case->description}
Status: {$case->status}

Indicators of Pretexting:
1. Minor charge (misdemeanor) but extensive investigation
2. Investigative tactics disproportionate to charge (e.g., search warrant for minor violation)
3. Misdemeanor charge dropped after evidence for felony collected
4. Timing suggests pretext (minor charge filed right before major search)

Croatian Law: ZKP Članak 9 requires lawful evidence collection. Cannot use minor charge as pretext to investigate unrelated serious crime.

Respond with JSON:
{
    "pretexting_detected": true/false,
    "confidence": 0-100,
    "initial_charge": "the minor charge",
    "actual_target": "what they were really investigating",
    "reasoning": "explanation"
}
PROMPT;

        $response = $this->openAI->chat([
            ['role' => 'system', 'content' => 'You are a Croatian criminal procedure expert analyzing pretextual charges.'],
            ['role' => 'user', 'content' => $prompt],
        ], 'gpt-4o-mini', [
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.2,
        ]);

        $result = json_decode($response['choices'][0]['message']['content'], true);

        if (($result['pretexting_detected'] ?? false) && ($result['confidence'] ?? 0) >= 65) {
            $violations[] = [
                'type' => 'misdemeanor_pretexting',
                'description' => "Misdemeanor pretexting: Minor charge '{$result['initial_charge']}' used as pretext to investigate '{$result['actual_target']}'",
                'legal_basis' => 'ZKP Članak 9 (Zakonitost dokaznih radnji)',
                'croatian_citation' => 'ZKP Čl. 9',
                'severity' => 75,
                'remedy' => 'Evidence suppression (evidence collected under pretext)',
                'mandates_dismissal' => false,
                'evidence' => [
                    'initial_charge' => $result['initial_charge'] ?? 'Unknown',
                    'actual_target' => $result['actual_target'] ?? 'Unknown',
                    'reasoning' => $result['reasoning'] ?? '',
                    'confidence' => $result['confidence'] ?? 0,
                ],
                'timestamp' => now()->toIso8601String(),
                'prosecutor' => $case->prosecutor ?? 'Unknown',
            ];
        }

        return $violations;
    }
}
