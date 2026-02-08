<?php

namespace App\Modules\Misconduct\Services;

use App\Models\LegalCase;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Log;

/**
 * ComplaintGenerator
 *
 * Generates formal complaints for prosecutorial, judicial, and police misconduct
 * in Croatian criminal proceedings.
 *
 * Complaint Types:
 * 1. State Attorney Complaint (Prigovor na rad državnog odvjetnika)
 *    - For prosecutor misconduct
 *    - Submit to: Glavni državni odvjetnik (Chief State Attorney)
 *    - Legal Basis: Zakon o državnom odvjetništvu
 *
 * 2. Judicial Council Complaint (Prigovor Državnom sudbenom vijeću)
 *    - For judge/prosecutor misconduct (disciplinary)
 *    - Submit to: Državno sudbeno vijeće (Judicial Council)
 *    - Legal Basis: Zakon o Državnom sudbenom vijeću
 *
 * 3. Police Internal Affairs Complaint (Prigovor unutarnjoj kontroli policije)
 *    - For police misconduct
 *    - Submit to: Police Internal Affairs division
 *    - Legal Basis: Zakon o policiji
 *
 * All complaints follow formal Croatian administrative format.
 */
class ComplaintGenerator
{
    public function __construct(
        protected OpenAIService $openAI
    ) {}

    /**
     * Generate complaint based on type
     *
     * @param  LegalCase  $case  The case
     * @param  array  $misconductInstances  Detected misconduct violations
     * @param  string  $complaintType  Type: state_attorney|judicial_council|police_internal_affairs
     * @return array Complaint details
     *
     * @throws \InvalidArgumentException If unknown complaint type
     */
    public function generateComplaint(
        LegalCase $case,
        array $misconductInstances,
        string $complaintType = 'police_internal_affairs'
    ): array {
        Log::info('ComplaintGenerator: Starting complaint generation', [
            'case_id' => $case->id,
            'complaint_type' => $complaintType,
            'instances_count' => count($misconductInstances),
        ]);

        switch ($complaintType) {
            case 'state_attorney':
                return $this->generateStateAttorneyComplaint($case, $misconductInstances);

            case 'judicial_council':
                return $this->generateJudicialCouncilComplaint($case, $misconductInstances);

            case 'police_internal_affairs':
                return $this->generatePoliceComplaint($case, $misconductInstances);

            default:
                throw new \InvalidArgumentException("Unknown complaint type: {$complaintType}. Valid types: state_attorney, judicial_council, police_internal_affairs");
        }
    }

    /**
     * Generate complaint to Chief State Attorney
     *
     * Format: "PRIGOVOR NA RAD DRŽAVNOG ODVJETNIKA"
     * Submit to: Glavni državni odvjetnik Republike Hrvatske
     * Legal basis: Zakon o državnom odvjetništvu
     *
     * @param  LegalCase  $case  The case
     * @param  array  $instances  Misconduct instances
     * @return array Complaint details
     */
    protected function generateStateAttorneyComplaint(LegalCase $case, array $instances): array
    {
        // Filter for prosecutor-related violations
        $prosecutorViolations = $this->filterProsecutorViolations($instances);

        if (empty($prosecutorViolations)) {
            return [
                'complaint_warranted' => false,
                'reason' => 'No prosecutor-related violations found',
            ];
        }

        Log::info('ComplaintGenerator: Generating State Attorney complaint', [
            'case_id' => $case->id,
            'violations_count' => count($prosecutorViolations),
        ]);

        $complaintText = $this->generateStateAttorneyComplaintText($case, $prosecutorViolations);

        return [
            'complaint_warranted' => true,
            'complaint_type' => 'state_attorney',
            'complaint_title' => 'PRIGOVOR NA RAD DRŽAVNOG ODVJETNIKA',
            'complaint_text' => $complaintText,
            'violations' => $prosecutorViolations,
            'violations_summary' => $this->summarizeViolations($prosecutorViolations),
            'legal_authorities' => [
                'primary' => 'Zakon o državnom odvjetništvu (NN 76/09, 153/09, 116/10, 145/10, 57/11, 130/11, 72/13, 148/13, 33/15, 82/15)',
                'related' => ['ZKP', 'Ustav RH Članak 29'],
            ],
            'submission_info' => [
                'submit_to' => 'Glavni državni odvjetnik Republike Hrvatske',
                'address' => 'Gajeva 30a, 10000 Zagreb',
                'method' => 'Registered mail or in person',
                'deadline' => 'No statutory deadline, but timely filing recommended',
                'fee' => 'No filing fee',
            ],
            'required_attachments' => $this->getRequiredAttachments($prosecutorViolations),
            'expected_outcome' => 'Disciplinary investigation of prosecutor; possible sanctions',
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Generate State Attorney complaint text
     *
     * @param  LegalCase  $case  The case
     * @param  array  $violations  Prosecutor violations
     * @return string Croatian complaint text
     */
    protected function generateStateAttorneyComplaintText(LegalCase $case, array $violations): string
    {
        $violationsFormatted = $this->formatViolationsForComplaint($violations);

        $prompt = <<<PROMPT
Generate a formal complaint to the Chief State Attorney of Croatia regarding prosecutor misconduct.

Case Information:
Title: {$case->title}
Case Number: {$case->case_number}
Court: {$case->court}
Prosecutor: {$case->prosecutor}

Violations:
{$violationsFormatted}

Generate "PRIGOVOR NA RAD DRŽAVNOG ODVJETNIKA" with structure:

1. NASLOV (Title):
   PRIGOVOR NA RAD DRŽAVNOG ODVJETNIKA

2. PRIMATELJ (Recipient):
   Glavni državni odvjetnik Republike Hrvatske
   Gajeva 30a
   10000 Zagreb

3. PODNOSITELJ PRIGOVORA (Complainant):
   [Defense attorney name]
   Branitelj okrivljenika u predmetu: {$case->case_number}

4. PRAVNA OSNOVA (Legal Basis):
   Zakon o državnom odvjetništvu
   ZKP (Zakon o kaznenom postupku)
   Ustav RH Članak 29

5. PREDMET PRIGOVORA (Subject of Complaint):
   Brief description of prosecutor's misconduct

6. ČINJENIČNO STANJE (Facts):
   Detailed description of each violation:
   - What the prosecutor did
   - When it occurred
   - Evidence of misconduct
   - How it violated Croatian law

7. POVREDE PROPISA (Legal Violations):
   Explain which laws/regulations were violated:
   - ZKP provisions
   - Ustav RH provisions
   - Professional ethics rules

8. ZAHTJEV (Request):
   "Molim Glavni državni odvjetnik da:
   1. Pokrene disciplinski postupak protiv državnog odvjetnika [name]
   2. Ispita navedene povrede
   3. Poduzme odgovarajuće mjere
   4. Obavijesti podnositelja o ishodu postupka"

9. PRILOZI (Attachments):
   List of attached evidence

10. POTPIS I DATUM (Signature and Date):
    [Location], [date]
    Podnositelj prigovora:
    [Name]

Use formal Croatian administrative language. Be factual, professional, and precise.
PROMPT;

        try {
            $response = $this->openAI->chat([
                ['role' => 'system', 'content' => 'You are a Croatian attorney drafting a formal administrative complaint. Use proper Croatian legal and administrative terminology.'],
                ['role' => 'user', 'content' => $prompt],
            ], 'gpt-4o', [
                'temperature' => 0.3,
                'max_tokens' => 2500,
            ]);

            return $response['choices'][0]['message']['content'];

        } catch (\Exception $e) {
            Log::error('ComplaintGenerator: OpenAI API error (State Attorney)', [
                'case_id' => $case->id,
                'error' => $e->getMessage(),
            ]);

            return $this->getStateAttorneyTemplate($case, $violations);
        }
    }

    /**
     * Generate complaint to Judicial Council
     *
     * Format: "PRIGOVOR DRŽAVNOM SUDBENOM VIJEĆU"
     * Submit to: Državno sudbeno vijeće
     * Legal basis: Zakon o Državnom sudbenom vijeću
     *
     * @param  LegalCase  $case  The case
     * @param  array  $instances  Misconduct instances
     * @return array Complaint details
     */
    protected function generateJudicialCouncilComplaint(LegalCase $case, array $instances): array
    {
        // Filter for rights violations and serious misconduct
        $seriousViolations = array_filter($instances, fn ($v) => ($v['type'] ?? '') === 'rights_violation' ||
            ($v['severity'] ?? 0) >= 90
        );

        if (empty($seriousViolations)) {
            return [
                'complaint_warranted' => false,
                'reason' => 'No violations serious enough for Judicial Council complaint',
            ];
        }

        Log::info('ComplaintGenerator: Generating Judicial Council complaint', [
            'case_id' => $case->id,
            'violations_count' => count($seriousViolations),
        ]);

        $complaintText = $this->generateJudicialCouncilComplaintText($case, $seriousViolations);

        return [
            'complaint_warranted' => true,
            'complaint_type' => 'judicial_council',
            'complaint_title' => 'PRIGOVOR DRŽAVNOM SUDBENOM VIJEĆU',
            'complaint_text' => $complaintText,
            'violations' => $seriousViolations,
            'violations_summary' => $this->summarizeViolations($seriousViolations),
            'legal_authorities' => [
                'primary' => 'Zakon o Državnom sudbenom vijeću (NN 116/10, 57/11, 130/11, 13/13, 28/13, 82/15)',
                'related' => ['Ustav RH Članak 29', 'ZKP'],
            ],
            'submission_info' => [
                'submit_to' => 'Državno sudbeno vijeće',
                'address' => 'Trg Nikole Šubića Zrinskog 3, 10000 Zagreb',
                'method' => 'Registered mail or electronic submission',
                'deadline' => 'No statutory deadline for disciplinary complaints',
                'fee' => 'No filing fee',
            ],
            'required_attachments' => $this->getRequiredAttachments($seriousViolations),
            'expected_outcome' => 'Disciplinary investigation; possible sanctions against judge/prosecutor',
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Generate Judicial Council complaint text
     *
     * @param  LegalCase  $case  The case
     * @param  array  $violations  Serious violations
     * @return string Croatian complaint text
     */
    protected function generateJudicialCouncilComplaintText(LegalCase $case, array $violations): string
    {
        $violationsFormatted = $this->formatViolationsForComplaint($violations);

        $prompt = <<<PROMPT
Generate a formal complaint to the Croatian Judicial Council regarding serious misconduct.

Case Information:
Title: {$case->title}
Case Number: {$case->case_number}
Court: {$case->court}
Prosecutor: {$case->prosecutor}

Violations:
{$violationsFormatted}

Generate "PRIGOVOR DRŽAVNOM SUDBENOM VIJEĆU" with structure:

1. NASLOV (Title):
   PRIGOVOR DRŽAVNOM SUDBENOM VIJEĆU

2. PRIMATELJ (Recipient):
   Državno sudbeno vijeće
   Trg Nikole Šubića Zrinskog 3
   10000 Zagreb

3. PODNOSITELJ (Complainant):
   [Defense attorney name]
   u predmetu: {$case->case_number}

4. PRAVNA OSNOVA (Legal Basis):
   - Zakon o Državnom sudbenom vijeću
   - Ustav RH Članak 29
   - ZKP

5. PREDMET PRIGOVORA (Subject):
   Disciplinska prijava protiv [prosecutor/judge name]

6. OPIS POVREDA (Description of Violations):
   Detailed description of each violation:
   - Constitutional rights violations
   - Procedural violations
   - Professional ethics violations

7. PRAVNA ANALIZA (Legal Analysis):
   Explain how violations breach:
   - Constitutional provisions (Ustav RH)
   - Procedural law (ZKP)
   - Professional standards

8. ZAHTJEV (Request):
   "Molim Državno sudbeno vijeće da:
   1. Pokrene disciplinski postupak
   2. Ispita navedene povrede profesionalnih dužnosti
   3. Izrekne odgovarajuće disciplinske mjere
   4. Obavijesti podnositelja o ishodu"

9. PRILOZI (Attachments):
   List evidence

10. POTPIS I DATUM (Signature and Date):
    [Location], [date]
    [Name]

Use formal Croatian legal language. Emphasize seriousness and pattern of violations if applicable.
PROMPT;

        try {
            $response = $this->openAI->chat([
                ['role' => 'system', 'content' => 'You are a Croatian attorney drafting a formal disciplinary complaint to the Judicial Council. Use proper Croatian legal terminology.'],
                ['role' => 'user', 'content' => $prompt],
            ], 'gpt-4o', [
                'temperature' => 0.3,
                'max_tokens' => 2500,
            ]);

            return $response['choices'][0]['message']['content'];

        } catch (\Exception $e) {
            Log::error('ComplaintGenerator: OpenAI API error (Judicial Council)', [
                'case_id' => $case->id,
                'error' => $e->getMessage(),
            ]);

            return $this->getJudicialCouncilTemplate($case, $violations);
        }
    }

    /**
     * Generate complaint to Police Internal Affairs
     *
     * Format: "PRIGOVOR UNUTARNJOJ KONTROLI POLICIJE"
     * Submit to: Police Internal Affairs division
     * Legal basis: Zakon o policiji
     *
     * @param  LegalCase  $case  The case
     * @param  array  $instances  Misconduct instances
     * @return array Complaint details
     */
    protected function generatePoliceComplaint(LegalCase $case, array $instances): array
    {
        // Filter for police-related violations
        $policeViolations = array_filter($instances, fn ($v) => isset($v['police_unit']) ||
            stripos($v['description'] ?? '', 'police') !== false ||
            stripos($v['description'] ?? '', 'policij') !== false
        );

        if (empty($policeViolations)) {
            return [
                'complaint_warranted' => false,
                'reason' => 'No police-related violations found',
            ];
        }

        Log::info('ComplaintGenerator: Generating Police Internal Affairs complaint', [
            'case_id' => $case->id,
            'violations_count' => count($policeViolations),
        ]);

        $complaintText = $this->generatePoliceComplaintText($case, $policeViolations);

        $policeUnits = array_unique(array_filter(array_column($policeViolations, 'police_unit')));

        return [
            'complaint_warranted' => true,
            'complaint_type' => 'police_internal_affairs',
            'complaint_title' => 'PRIGOVOR UNUTARNJOJ KONTROLI POLICIJE',
            'complaint_text' => $complaintText,
            'violations' => $policeViolations,
            'violations_summary' => $this->summarizeViolations($policeViolations),
            'police_units_involved' => $policeUnits,
            'legal_authorities' => [
                'primary' => 'Zakon o policiji (NN 34/11, 130/12, 89/14, 151/14, 33/15, 121/16, 66/19, 68/22)',
                'related' => ['Ustav RH Članak 29', 'ZKP', 'Kodeks policijske etike'],
            ],
            'submission_info' => [
                'submit_to' => 'Odjel unutarnje kontrole Ministarstva unutarnjih poslova',
                'address' => 'Savska cesta 39, 10000 Zagreb',
                'method' => 'Registered mail, in person, or online',
                'deadline' => 'File within reasonable time after discovery',
                'fee' => 'No filing fee',
            ],
            'required_attachments' => $this->getRequiredAttachments($policeViolations),
            'expected_outcome' => 'Internal investigation; possible disciplinary action against police officers',
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Generate Police Internal Affairs complaint text
     *
     * @param  LegalCase  $case  The case
     * @param  array  $violations  Police violations
     * @return string Croatian complaint text
     */
    protected function generatePoliceComplaintText(LegalCase $case, array $violations): string
    {
        $violationsFormatted = $this->formatViolationsForComplaint($violations);
        $policeUnits = array_unique(array_filter(array_column($violations, 'police_unit')));
        $policeUnitsText = ! empty($policeUnits) ? implode(', ', $policeUnits) : 'Unknown';

        $prompt = <<<PROMPT
Generate a formal complaint to Croatian Police Internal Affairs regarding police misconduct.

Case Information:
Title: {$case->title}
Case Number: {$case->case_number}
Police Units Involved: {$policeUnitsText}

Violations:
{$violationsFormatted}

Generate "PRIGOVOR UNUTARNJOJ KONTROLI POLICIJE" with structure:

1. NASLOV (Title):
   PRIGOVOR UNUTARNJOJ KONTROLI POLICIJE

2. PRIMATELJ (Recipient):
   Odjel unutarnje kontrole
   Ministarstvo unutarnjih poslova Republike Hrvatske
   Savska cesta 39
   10000 Zagreb

3. PODNOSITELJ (Complainant):
   [Name]
   u predmetu: {$case->case_number}

4. PRAVNA OSNOVA (Legal Basis):
   - Zakon o policiji
   - Kodeks policijske etike
   - Ustav RH Članak 29

5. PREDMET PRIGOVORA (Subject):
   Prijava nezakonitog postupanja policijskih službenika

6. OPIS DOGAĐAJA (Description of Events):
   Detailed chronological description:
   - When and where incidents occurred
   - Which police officers/units involved
   - What violations occurred
   - Evidence of misconduct

7. POVREDE ZAKONA I ETIKE (Legal and Ethical Violations):
   Explain violations of:
   - Zakon o policiji provisions
   - Kodeks policijske etike
   - Constitutional rights (Ustav RH Čl. 29)

8. ZAHTJEV (Request):
   "Molim Odjel unutarnje kontrole da:
   1. Pokrene unutarnju istragu
   2. Ispita postupanje policijskih službenika [names/units]
   3. Poduzme odgovarajuće disciplinske mjere
   4. Obavijesti podnositelja o rezultatima istrage"

9. PRILOZI (Attachments):
   List evidence

10. POTPIS I DATUM (Signature and Date):
    [Location], [date]
    [Name]

Use formal Croatian administrative language. Be specific about police units and officers if known.
PROMPT;

        try {
            $response = $this->openAI->chat([
                ['role' => 'system', 'content' => 'You are a Croatian attorney drafting a formal complaint to Police Internal Affairs. Use proper Croatian administrative and legal terminology.'],
                ['role' => 'user', 'content' => $prompt],
            ], 'gpt-4o', [
                'temperature' => 0.3,
                'max_tokens' => 2500,
            ]);

            return $response['choices'][0]['message']['content'];

        } catch (\Exception $e) {
            Log::error('ComplaintGenerator: OpenAI API error (Police)', [
                'case_id' => $case->id,
                'error' => $e->getMessage(),
            ]);

            return $this->getPoliceComplaintTemplate($case, $violations);
        }
    }

    /**
     * Filter for prosecutor-related violations
     *
     * @param  array  $instances  All violations
     * @return array Prosecutor violations
     */
    protected function filterProsecutorViolations(array $instances): array
    {
        return array_filter($instances, fn ($v) => in_array($v['type'] ?? '', [
            'hidden_evidence',
            'fabricated_probable_cause',
            'prosecutor_threats_lying',
            'misdemeanor_pretexting',
        ])
        );
    }

    /**
     * Format violations for complaint text
     *
     * @param  array  $violations  Violations
     * @return string Formatted violations
     */
    protected function formatViolationsForComplaint(array $violations): string
    {
        $formatted = [];

        foreach ($violations as $i => $violation) {
            $num = $i + 1;
            $type = $violation['type'] ?? 'unknown';
            $description = $violation['description'] ?? 'No description';
            $severity = $violation['severity'] ?? 0;
            $legalBasis = $violation['legal_basis'] ?? 'Unknown';

            $formatted[] = <<<VIOLATION
Violation #{$num}:
Type: {$type}
Severity: {$severity}/100
Description: {$description}
Legal Basis Violated: {$legalBasis}
VIOLATION;
        }

        return implode("\n\n", $formatted);
    }

    /**
     * Summarize violations
     *
     * @param  array  $violations  Violations
     * @return array Summary
     */
    protected function summarizeViolations(array $violations): array
    {
        return [
            'total_violations' => count($violations),
            'violation_types' => array_count_values(array_column($violations, 'type')),
            'average_severity' => round(array_sum(array_column($violations, 'severity')) / count($violations), 1),
            'max_severity' => max(array_column($violations, 'severity')),
        ];
    }

    /**
     * Get required attachments
     *
     * @param  array  $violations  Violations
     * @return array Attachments list
     */
    protected function getRequiredAttachments(array $violations): array
    {
        $attachments = [
            'Copy of indictment/charges',
            'Relevant court documents',
        ];

        foreach ($violations as $violation) {
            if (isset($violation['evidence']['document_id'])) {
                $attachments[] = "Evidence: Document #{$violation['evidence']['document_id']}";
            }

            if (isset($violation['evidence']['warrant_id'])) {
                $attachments[] = "Evidence: Warrant #{$violation['evidence']['warrant_id']}";
            }

            if (isset($violation['evidence']['statement_id'])) {
                $attachments[] = "Evidence: Statement #{$violation['evidence']['statement_id']}";
            }
        }

        return array_unique($attachments);
    }

    /**
     * Get State Attorney complaint template (fallback)
     *
     * @param  LegalCase  $case  Case
     * @param  array  $violations  Violations
     * @return string Template
     */
    protected function getStateAttorneyTemplate(LegalCase $case, array $violations): string
    {
        $today = now()->format('d.m.Y');
        $violationsList = '';
        foreach ($violations as $i => $v) {
            $num = $i + 1;
            $violationsList .= "{$num}. {$v['type']}: {$v['description']}\n";
        }

        return <<<TEMPLATE
PRIGOVOR NA RAD DRŽAVNOG ODVJETNIKA

Glavni državni odvjetnik Republike Hrvatske
Gajeva 30a
10000 Zagreb

Podnositelj prigovora: Branitelj okrivljenika
U predmetu: {$case->case_number}

PRAVNA OSNOVA:
Zakon o državnom odvjetništvu

PREDMET PRIGOVORA:
Nezakonito postupanje državnog odvjetnika {$case->prosecutor}

POVREDE:
{$violationsList}

ZAHTJEV:
Molim Glavni državni odvjetnik da pokrene disciplinski postupak i poduzme odgovarajuće mjere.

{$today}
Podnositelj prigovora
TEMPLATE;
    }

    /**
     * Get Judicial Council complaint template (fallback)
     *
     * @param  LegalCase  $case  Case
     * @param  array  $violations  Violations
     * @return string Template
     */
    protected function getJudicialCouncilTemplate(LegalCase $case, array $violations): string
    {
        $today = now()->format('d.m.Y');
        $violationsList = '';
        foreach ($violations as $i => $v) {
            $num = $i + 1;
            $violationsList .= "{$num}. {$v['type']}: {$v['description']}\n";
        }

        return <<<TEMPLATE
PRIGOVOR DRŽAVNOM SUDBENOM VIJEĆU

Državno sudbeno vijeće
Trg Nikole Šubića Zrinskog 3
10000 Zagreb

Podnositelj: [Ime]
U predmetu: {$case->case_number}

PRAVNA OSNOVA:
Zakon o Državnom sudbenom vijeću

PREDMET:
Disciplinska prijava

POVREDE:
{$violationsList}

ZAHTJEV:
Molim Državno sudbeno vijeće da pokrene disciplinski postupak.

{$today}
Podnositelj
TEMPLATE;
    }

    /**
     * Get Police complaint template (fallback)
     *
     * @param  LegalCase  $case  Case
     * @param  array  $violations  Violations
     * @return string Template
     */
    protected function getPoliceComplaintTemplate(LegalCase $case, array $violations): string
    {
        $today = now()->format('d.m.Y');
        $violationsList = '';
        foreach ($violations as $i => $v) {
            $num = $i + 1;
            $violationsList .= "{$num}. {$v['type']}: {$v['description']}\n";
        }

        return <<<TEMPLATE
PRIGOVOR UNUTARNJOJ KONTROLI POLICIJE

Odjel unutarnje kontrole
Ministarstvo unutarnjih poslova Republike Hrvatske
Savska cesta 39
10000 Zagreb

Podnositelj: [Ime]
U predmetu: {$case->case_number}

PRAVNA OSNOVA:
Zakon o policiji

PREDMET:
Prijava nezakonitog postupanja policijskih službenika

POVREDE:
{$violationsList}

ZAHTJEV:
Molim Odjel unutarnje kontrole da pokrene unutarnju istragu.

{$today}
Podnositelj
TEMPLATE;
    }
}
