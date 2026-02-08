<?php

namespace App\Modules\Misconduct\Services;

use App\Models\LegalCase;
use App\Services\OpenAIService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * AppealBuilder
 *
 * Builds appeals based on prosecutorial misconduct in Croatian criminal proceedings.
 *
 * Croatian Appeal Types:
 * 1. Žalba - Appeal to higher court (15-day deadline)
 * 2. Zahtjev za zaštitu zakonitosti - Supreme Court protection of legality
 * 3. Ustavna tužba - Constitutional Court complaint (30-day deadline)
 *
 * Legal Basis:
 * - ZKP Članak 378 - Žalbeni razlozi (grounds for appeal)
 * - ZKP Članak 379 - Rokovi za žalbu (appeal deadlines)
 * - Zakon o Ustavnom sudu - Constitutional Court Act
 * - Ustav RH Članak 29 - Pravo na pravično suđenje
 *
 * Croatian Appeal Format:
 * 1. Naziv suda (Court name)
 * 2. Žalitelj (Appellant)
 * 3. Pobijana odluka (Challenged decision)
 * 4. Žalbeni razlozi (Grounds for appeal)
 * 5. Zahtjev (Request - overturn, dismiss, retrial)
 */
class AppealBuilder
{
    public function __construct(
        protected OpenAIService $openAI
    ) {}

    /**
     * Build appeal based on misconduct instances
     *
     * @param  LegalCase  $case  The case for which to build appeal
     * @param  array  $misconductInstances  Detected misconduct violations
     * @param  string  $appealType  Type of appeal (zalba, zastita_zakonitosti, ustavna_tuzba)
     * @return array Appeal details or appeal_warranted = false
     */
    public function buildAppeal(
        LegalCase $case,
        array $misconductInstances,
        string $appealType = 'zalba'
    ): array {
        Log::info('AppealBuilder: Starting appeal generation', [
            'case_id' => $case->id,
            'appeal_type' => $appealType,
            'total_instances' => count($misconductInstances),
        ]);

        // Route to specific appeal type generator
        return match ($appealType) {
            'zalba' => $this->generateZalba($case, $misconductInstances),
            'zastita_zakonitosti' => $this->generateZastitaZakonitosti($case, $misconductInstances),
            'ustavna_tuzba' => $this->generateUstavnaTuzba($case, $misconductInstances),
            default => throw new \InvalidArgumentException("Unknown appeal type: {$appealType}"),
        };
    }

    /**
     * Generate standard appeal (Žalba) to higher court
     *
     * @param  LegalCase  $case  The case
     * @param  array  $instances  Misconduct instances
     * @return array Appeal details
     */
    protected function generateZalba(LegalCase $case, array $instances): array
    {
        Log::info('AppealBuilder: Generating žalba', ['case_id' => $case->id]);

        // Filter for appeal-worthy violations (severity >= 70)
        $appealGrounds = $this->identifyAppealGrounds($instances, 70);

        if (empty($appealGrounds)) {
            Log::info('AppealBuilder: No appeal-worthy violations found', [
                'case_id' => $case->id,
            ]);

            return [
                'appeal_warranted' => false,
                'reason' => 'No violations with severity >= 70',
                'highest_severity' => ! empty($instances)
                    ? max(array_column($instances, 'severity'))
                    : 0,
            ];
        }

        // Calculate filing deadline (15 days from judgment)
        $deadline = $this->calculateDeadline($case, 15);

        // Generate appeal text in Croatian
        $appealText = $this->generateAppealText($case, $appealGrounds, 'zalba');

        // Gather legal authorities
        $legalAuthorities = $this->gatherAppealAuthorities($appealGrounds, 'zalba');

        $result = [
            'appeal_warranted' => true,
            'appeal_type' => 'zalba',
            'appeal_title' => 'ŽALBA',
            'court' => $this->getAppealCourt($case, 'zalba'),
            'grounds' => $appealGrounds,
            'grounds_summary' => $this->summarizeGrounds($appealGrounds),
            'appeal_text' => $appealText,
            'legal_authorities' => $legalAuthorities,
            'filing_info' => [
                'deadline' => $deadline,
                'deadline_days' => 15,
                'submit_to' => $this->getAppealCourt($case, 'zalba'),
                'copies_required' => 3,
                'filing_fee' => 'No filing fee for criminal appeals',
            ],
            'requested_relief' => $this->determineRequestedRelief($appealGrounds),
            'generated_at' => now()->toIso8601String(),
        ];

        Log::info('AppealBuilder: Žalba generated successfully', [
            'case_id' => $case->id,
            'deadline' => $deadline,
        ]);

        return $result;
    }

    /**
     * Generate Supreme Court protection of legality request
     *
     * @param  LegalCase  $case  The case
     * @param  array  $instances  Misconduct instances
     * @return array Appeal details
     */
    protected function generateZastitaZakonitosti(LegalCase $case, array $instances): array
    {
        Log::info('AppealBuilder: Generating zaštita zakonitosti', ['case_id' => $case->id]);

        // Filter for severe violations warranting Supreme Court review (severity >= 85)
        $appealGrounds = $this->identifyAppealGrounds($instances, 85);

        if (empty($appealGrounds)) {
            Log::info('AppealBuilder: No Supreme Court grounds found', [
                'case_id' => $case->id,
            ]);

            return [
                'appeal_warranted' => false,
                'reason' => 'No violations with severity >= 85 for Supreme Court review',
                'highest_severity' => ! empty($instances)
                    ? max(array_column($instances, 'severity'))
                    : 0,
            ];
        }

        // No statutory deadline for zaštita zakonitosti
        $deadline = 'No statutory deadline, but timely filing recommended';

        // Generate appeal text
        $appealText = $this->generateAppealText($case, $appealGrounds, 'zastita_zakonitosti');

        // Gather legal authorities
        $legalAuthorities = $this->gatherAppealAuthorities($appealGrounds, 'zastita_zakonitosti');

        $result = [
            'appeal_warranted' => true,
            'appeal_type' => 'zastita_zakonitosti',
            'appeal_title' => 'ZAHTJEV ZA ZAŠTITU ZAKONITOSTI',
            'court' => 'Vrhovni sud Republike Hrvatske',
            'grounds' => $appealGrounds,
            'grounds_summary' => $this->summarizeGrounds($appealGrounds),
            'appeal_text' => $appealText,
            'legal_authorities' => $legalAuthorities,
            'filing_info' => [
                'deadline' => $deadline,
                'deadline_days' => null,
                'submit_to' => 'Vrhovni sud Republike Hrvatske',
                'address' => 'Trg Nikole Šubića Zrinskog 3, 10000 Zagreb',
                'copies_required' => 3,
                'filing_fee' => 'No filing fee',
                'note' => 'Only Glavni državni odvjetnik can file zaštitu zakonitosti',
            ],
            'requested_relief' => 'Annulment of final judgment due to serious legal violations',
            'generated_at' => now()->toIso8601String(),
        ];

        Log::info('AppealBuilder: Zaštita zakonitosti generated successfully', [
            'case_id' => $case->id,
        ]);

        return $result;
    }

    /**
     * Generate Constitutional Court complaint (Ustavna tužba)
     *
     * @param  LegalCase  $case  The case
     * @param  array  $instances  Misconduct instances
     * @return array Appeal details
     */
    protected function generateUstavnaTuzba(LegalCase $case, array $instances): array
    {
        Log::info('AppealBuilder: Generating ustavna tužba', ['case_id' => $case->id]);

        // Filter for constitutional rights violations (severity >= 75)
        $appealGrounds = $this->identifyAppealGrounds($instances, 75);

        if (empty($appealGrounds)) {
            Log::info('AppealBuilder: No constitutional grounds found', [
                'case_id' => $case->id,
            ]);

            return [
                'appeal_warranted' => false,
                'reason' => 'No violations with severity >= 75 for constitutional complaint',
                'highest_severity' => ! empty($instances)
                    ? max(array_column($instances, 'severity'))
                    : 0,
            ];
        }

        // Calculate filing deadline (30 days after exhausting all remedies)
        $deadline = $this->calculateDeadline($case, 30);

        // Generate appeal text
        $appealText = $this->generateAppealText($case, $appealGrounds, 'ustavna_tuzba');

        // Gather legal authorities
        $legalAuthorities = $this->gatherAppealAuthorities($appealGrounds, 'ustavna_tuzba');

        $result = [
            'appeal_warranted' => true,
            'appeal_type' => 'ustavna_tuzba',
            'appeal_title' => 'USTAVNA TUŽBA',
            'court' => 'Ustavni sud Republike Hrvatske',
            'grounds' => $appealGrounds,
            'grounds_summary' => $this->summarizeGrounds($appealGrounds),
            'appeal_text' => $appealText,
            'legal_authorities' => $legalAuthorities,
            'filing_info' => [
                'deadline' => $deadline,
                'deadline_days' => 30,
                'submit_to' => 'Ustavni sud Republike Hrvatske',
                'address' => 'Trg svetog Marka 4, 10000 Zagreb',
                'copies_required' => 3,
                'filing_fee' => 'No filing fee',
                'requirement' => 'Must exhaust all ordinary legal remedies first',
            ],
            'constitutional_rights_violated' => $this->identifyConstitutionalViolations($appealGrounds),
            'requested_relief' => 'Annulment of judgment due to constitutional rights violations',
            'generated_at' => now()->toIso8601String(),
        ];

        Log::info('AppealBuilder: Ustavna tužba generated successfully', [
            'case_id' => $case->id,
            'deadline' => $deadline,
        ]);

        return $result;
    }

    /**
     * Generate formal Croatian appeal text
     *
     * @param  LegalCase  $case  The case
     * @param  array  $grounds  Appeal grounds
     * @param  string  $appealType  Type of appeal
     * @return string Croatian appeal text
     */
    protected function generateAppealText(LegalCase $case, array $grounds, string $appealType): string
    {
        $groundsFormatted = $this->formatGrounds($grounds);

        $appealTitle = match ($appealType) {
            'zalba' => 'ŽALBA',
            'zastita_zakonitosti' => 'ZAHTJEV ZA ZAŠTITU ZAKONITOSTI',
            'ustavna_tuzba' => 'USTAVNA TUŽBA',
        };

        $courtName = match ($appealType) {
            'zalba' => $this->getAppealCourt($case, 'zalba'),
            'zastita_zakonitosti' => 'Vrhovni sud Republike Hrvatske',
            'ustavna_tuzba' => 'Ustavni sud Republike Hrvatske',
        };

        $legalBasis = match ($appealType) {
            'zalba' => 'ZKP Članak 378, 379 - Žalbeni razlozi i rokovi',
            'zastita_zakonitosti' => 'ZKP Članak 469 - Zahtjev za zaštitu zakonitosti',
            'ustavna_tuzba' => 'Zakon o Ustavnom sudu RH - Ustavna tužba',
        };

        $prompt = <<<PROMPT
Generate a formal Croatian {$appealTitle} based on prosecutorial misconduct.

Case Information:
Title: {$case->title}
Description: {$case->description}
Court: {$case->court}
Case Number: {$case->case_number}
Prosecutor: {$case->prosecutor}

Grounds for Appeal:
{$groundsFormatted}

Generate "{$appealTitle}" with the following structure:

1. NAZIV SUDA (Court Name):
   {$courtName}
   Broj predmeta: [case number]

2. ŽALITELJ / PODNOSITELJ (Appellant):
   Okrivljenik: [defendant name]
   Branitelj: [defense attorney]

3. POBIJANA ODLUKA (Challenged Decision):
   Describe the judgment being appealed
   Include date and case number

4. ŽALBENI RAZLOZI / OSNOVA TUŽBE (Grounds for Appeal):
   Legal Basis: {$legalBasis}

   Detail each ground for appeal based on prosecutorial misconduct:
   - Describe the specific misconduct
   - Explain how it violated procedural law or constitutional rights
   - Reference ZKP and Ustav RH provisions
   - Explain impact on case outcome

5. ZAHTJEV (Request):
   Based on appeal type:
   - Žalba: "Molim poštovani sud da preinači presudu..." (overturn judgment)
   - Zaštita zakonitosti: "Molim poštovani sud da poništi pravomoćnu presudu..." (annul final judgment)
   - Ustavna tužba: "Molim poštovani sud da utvrdi povredu ustavnih prava..." (find constitutional violation)

6. POTPIS I DATUM (Signature and Date):
   [Location], [date]
   Branitelj okrivljenika
   [Defense attorney name]

Use formal Croatian legal language throughout. Be precise, professional, and persuasive.
Ensure all citations are accurate.
PROMPT;

        try {
            $response = $this->openAI->chat([
                ['role' => 'system', 'content' => 'You are a Croatian criminal defense attorney drafting a formal appeal. Use proper Croatian legal terminology and format.'],
                ['role' => 'user', 'content' => $prompt],
            ], 'gpt-4o', [
                'temperature' => 0.3, // Lower temperature for formal legal writing
                'max_tokens' => 3000,
            ]);

            return $response['choices'][0]['message']['content'];

        } catch (\Exception $e) {
            Log::error('AppealBuilder: OpenAI API error', [
                'case_id' => $case->id,
                'appeal_type' => $appealType,
                'error' => $e->getMessage(),
            ]);

            // Return template if AI fails
            return $this->getTemplateAppeal($case, $grounds, $appealType);
        }
    }

    /**
     * Identify appeal grounds from misconduct instances
     *
     * @param  array  $instances  Misconduct instances
     * @param  int  $minSeverity  Minimum severity threshold
     * @return array Appeal grounds
     */
    protected function identifyAppealGrounds(array $instances, int $minSeverity): array
    {
        $grounds = array_filter(
            $instances,
            fn ($v) => ($v['severity'] ?? 0) >= $minSeverity
        );

        // Sort by severity descending
        usort($grounds, fn ($a, $b) => ($b['severity'] ?? 0) <=> ($a['severity'] ?? 0));

        return array_values($grounds);
    }

    /**
     * Format grounds for prompt
     *
     * @param  array  $grounds  Appeal grounds
     * @return string Formatted grounds
     */
    protected function formatGrounds(array $grounds): string
    {
        $formatted = [];

        foreach ($grounds as $i => $ground) {
            $num = $i + 1;
            $type = $ground['type'] ?? 'unknown';
            $description = $ground['description'] ?? 'No description';
            $severity = $ground['severity'] ?? 0;
            $legalBasis = $ground['legal_basis'] ?? 'Unknown legal basis';

            $formatted[] = <<<GROUND
Ground #{$num}:
Type: {$type}
Severity: {$severity}/100
Description: {$description}
Legal Basis: {$legalBasis}
GROUND;
        }

        return implode("\n\n", $formatted);
    }

    /**
     * Gather legal authorities for appeal
     *
     * @param  array  $grounds  Appeal grounds
     * @param  string  $appealType  Type of appeal
     * @return array Legal authorities grouped by source
     */
    protected function gatherAppealAuthorities(array $grounds, string $appealType): array
    {
        $zkpArticles = [];
        $ustavArticles = [];
        $otherAuthorities = [];

        foreach ($grounds as $ground) {
            $legalBasis = $ground['legal_basis'] ?? '';
            $citation = $ground['croatian_citation'] ?? '';

            // Extract ZKP articles
            if (preg_match_all('/ZKP\s+Članak\s+(\d+)/i', $legalBasis.' '.$citation, $matches)) {
                foreach ($matches[1] as $article) {
                    $zkpArticles[$article] = "ZKP Članak {$article}";
                }
            }

            // Extract Ustav RH articles
            if (preg_match_all('/Ustav\s+RH\s+Članak\s+(\d+)/i', $legalBasis.' '.$citation, $matches)) {
                foreach ($matches[1] as $article) {
                    $ustavArticles[$article] = "Ustav RH Članak {$article}";
                }
            }
        }

        // Add appeal-type specific authorities
        switch ($appealType) {
            case 'zalba':
                $zkpArticles['378'] = 'ZKP Članak 378 - Žalbeni razlozi';
                $zkpArticles['379'] = 'ZKP Članak 379 - Rokovi za žalbu';
                break;
            case 'zastita_zakonitosti':
                $zkpArticles['469'] = 'ZKP Članak 469 - Zahtjev za zaštitu zakonitosti';
                $zkpArticles['470'] = 'ZKP Članak 470 - Razlozi za zaštitu zakonitosti';
                break;
            case 'ustavna_tuzba':
                $otherAuthorities[] = 'Zakon o Ustavnom sudu RH';
                $ustavArticles['29'] = 'Ustav RH Članak 29 - Pravo na pravično suđenje';
                break;
        }

        return [
            'zkp' => array_values($zkpArticles),
            'ustav_rh' => array_values($ustavArticles),
            'other' => $otherAuthorities,
            'all_citations' => array_merge(
                array_values($zkpArticles),
                array_values($ustavArticles),
                $otherAuthorities
            ),
        ];
    }

    /**
     * Calculate filing deadline
     *
     * @param  LegalCase  $case  The case
     * @param  int  $days  Number of days from judgment
     * @return string Deadline date
     */
    protected function calculateDeadline(LegalCase $case, int $days): string
    {
        // If case has judgment_date, calculate from that
        if (! empty($case->judgment_date)) {
            try {
                $judgmentDate = Carbon::parse($case->judgment_date);
                $deadline = $judgmentDate->copy()->addDays($days);

                return $deadline->format('Y-m-d')." ({$days} days from judgment)";
            } catch (\Exception $e) {
                Log::warning('AppealBuilder: Invalid judgment_date', [
                    'case_id' => $case->id,
                    'judgment_date' => $case->judgment_date,
                ]);
            }
        }

        // Otherwise return relative deadline
        return "{$days} days from judgment date";
    }

    /**
     * Get appeal court based on original court
     *
     * @param  LegalCase  $case  The case
     * @param  string  $appealType  Type of appeal
     * @return string Appeal court name
     */
    protected function getAppealCourt(LegalCase $case, string $appealType): string
    {
        if ($appealType === 'zastita_zakonitosti') {
            return 'Vrhovni sud Republike Hrvatske';
        }

        if ($appealType === 'ustavna_tuzba') {
            return 'Ustavni sud Republike Hrvatske';
        }

        // For žalba, determine higher court based on original court
        $court = $case->court ?? '';

        // Municipal Court -> County Court
        if (stripos($court, 'općinski') !== false || stripos($court, 'municipal') !== false) {
            return 'Županijski sud';
        }

        // County Court -> Supreme Court
        if (stripos($court, 'županijski') !== false || stripos($court, 'county') !== false) {
            return 'Vrhovni sud Republike Hrvatske';
        }

        // Default to higher instance
        return 'Viša instanca suda';
    }

    /**
     * Summarize appeal grounds
     *
     * @param  array  $grounds  Appeal grounds
     * @return array Summary
     */
    protected function summarizeGrounds(array $grounds): array
    {
        $types = array_count_values(array_column($grounds, 'type'));
        $avgSeverity = ! empty($grounds)
            ? array_sum(array_column($grounds, 'severity')) / count($grounds)
            : 0;

        return [
            'total_grounds' => count($grounds),
            'violation_types' => $types,
            'average_severity' => round($avgSeverity, 1),
            'strongest_ground' => $this->getStrongestGround($grounds),
        ];
    }

    /**
     * Get strongest appeal ground
     *
     * @param  array  $grounds  Appeal grounds
     * @return array Strongest ground
     */
    protected function getStrongestGround(array $grounds): array
    {
        if (empty($grounds)) {
            return [];
        }

        // Already sorted by severity in identifyAppealGrounds
        $strongest = $grounds[0];

        return [
            'type' => $strongest['type'] ?? 'unknown',
            'severity' => $strongest['severity'] ?? 0,
            'description' => $strongest['description'] ?? '',
            'legal_basis' => $strongest['legal_basis'] ?? '',
        ];
    }

    /**
     * Determine requested relief based on grounds
     *
     * @param  array  $grounds  Appeal grounds
     * @return string Requested relief
     */
    protected function determineRequestedRelief(array $grounds): string
    {
        $maxSeverity = ! empty($grounds) ? max(array_column($grounds, 'severity')) : 0;

        if ($maxSeverity >= 90) {
            return 'Complete case dismissal and reversal of conviction';
        } elseif ($maxSeverity >= 80) {
            return 'Reversal of conviction and remand for new trial';
        } elseif ($maxSeverity >= 70) {
            return 'Reduction of sentence or remand for resentencing';
        } else {
            return 'Correction of procedural errors';
        }
    }

    /**
     * Identify constitutional rights violations
     *
     * @param  array  $grounds  Appeal grounds
     * @return array Constitutional rights violated
     */
    protected function identifyConstitutionalViolations(array $grounds): array
    {
        $violations = [];

        foreach ($grounds as $ground) {
            $type = $ground['type'] ?? '';
            $citation = $ground['croatian_citation'] ?? '';

            // Map misconduct types to constitutional rights
            if ($type === 'hidden_evidence') {
                $violations['Ustav RH Članak 29'] = 'Pravo na pravično suđenje (fair trial)';
            }

            if ($type === 'rights_violation' || $type === 'no_lawyer_access') {
                $violations['Ustav RH Članak 27'] = 'Pravo na branitelja (right to counsel)';
            }

            if ($type === 'fabricated_probable_cause' || $type === 'backdated_documents') {
                $violations['Ustav RH Članak 29'] = 'Pravo na zakonitost dokaznog postupka (lawful evidence)';
            }

            if ($type === 'prosecutor_threats_lying' || $type === 'coerced_statement') {
                $violations['Ustav RH Članak 23'] = 'Zabrana torture i ponižavajućeg postupanja (prohibition of torture)';
            }

            // Extract from citations if present
            if (preg_match_all('/Ustav\s+RH\s+Članak\s+(\d+)/i', $citation, $matches)) {
                foreach ($matches[1] as $article) {
                    $violations["Ustav RH Članak {$article}"] = 'Ustavno pravo';
                }
            }
        }

        return array_unique($violations);
    }

    /**
     * Get template appeal text (fallback if AI fails)
     *
     * @param  LegalCase  $case  The case
     * @param  array  $grounds  Appeal grounds
     * @param  string  $appealType  Type of appeal
     * @return string Template appeal
     */
    protected function getTemplateAppeal(LegalCase $case, array $grounds, string $appealType): string
    {
        $appealTitle = match ($appealType) {
            'zalba' => 'ŽALBA',
            'zastita_zakonitosti' => 'ZAHTJEV ZA ZAŠTITU ZAKONITOSTI',
            'ustavna_tuzba' => 'USTAVNA TUŽBA',
        };

        $courtName = match ($appealType) {
            'zalba' => $this->getAppealCourt($case, 'zalba'),
            'zastita_zakonitosti' => 'Vrhovni sud Republike Hrvatske',
            'ustavna_tuzba' => 'Ustavni sud Republike Hrvatske',
        };

        $court = $case->court ?? 'Nadležni sud';
        $caseNumber = $case->case_number ?? '[broj predmeta]';
        $today = now()->format('d.m.Y');

        $groundsList = '';
        foreach ($grounds as $i => $ground) {
            $num = $i + 1;
            $type = $ground['type'] ?? 'unknown';
            $description = $ground['description'] ?? '';
            $groundsList .= "{$num}. {$type}: {$description}\n";
        }

        return <<<TEMPLATE
{$appealTitle}

{$courtName}
Broj predmeta: {$caseNumber}

ŽALITELJ:
Okrivljenik: [ime okrivljenika]
Branitelj: [ime branitelja]

POBIJANA ODLUKA:
Presuda {$court}, broj {$caseNumber}

ŽALBENI RAZLOZI:

U predmetnom kaznenom postupku došlo je do ozbiljnih povreda procesnih propisa i ustavnih prava okrivljenika od strane državnog odvjetništva.

Utvrdene su slijedeće povrede koje predstavljaju osnovu za žalbu:

{$groundsList}

Navedene povrede predstavljaju kršenje temeljnih načela kaznenog postupka propisanih ZKP-om i Ustavom RH. Ustav RH Članak 29 jamči pravo na pravično suđenje, koje u ovom slučaju nije poštovano.

ZAHTJEV:

Molim poštovani sud da prihvati ovu žalbu kao osnovanu i:
1. Preinači pobijanu presudu ili poništi presudu i vrati predmet na ponovno suđenje
2. Odbaci sve dokaze prikupljene protivno zakonu
3. Odbaci optužnicu zbog nedopuštenih radnji državnog odvjetništva

{$today}
Branitelj okrivljenika
TEMPLATE;
    }
}
