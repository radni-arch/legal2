<?php

namespace App\Modules\Misconduct\Services;

use App\Models\LegalCase;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Log;

/**
 * DismissalMotionGenerator
 *
 * Generates motions to dismiss criminal cases based on prosecutorial misconduct.
 *
 * Croatian Motion Type: "Prijedlog za obustavu postupka"
 *
 * Dismissal warranted when:
 * - Violation severity >= 85
 * - Violation explicitly mandates dismissal
 * - Examples: Brady violations, fabricated probable cause, prosecutor threats
 *
 * Legal Basis:
 * - ZKP Članak 175 - Obustava kaznenog postupka (dismissal)
 * - ZKP Članak 177 - Razlozi za obustavu (grounds for dismissal)
 * - Ustav RH Članak 29 - Pravo na pravično suđenje (fair trial)
 *
 * Croatian Court Format:
 * 1. Naslov (Title)
 * 2. Sud i broj predmeta (Court and case number)
 * 3. Stranke (Parties)
 * 4. Pravna osnova (Legal basis)
 * 5. Činjenično stanje (Facts)
 * 6. Pravna argumentacija (Legal argument)
 * 7. Zahtjev (Request)
 * 8. Potpis i datum (Signature and date)
 */
class DismissalMotionGenerator
{
    public function __construct(
        protected OpenAIService $openAI
    ) {}

    /**
     * Generate dismissal motion based on misconduct instances
     *
     * @param  LegalCase  $case  The case for which to generate motion
     * @param  array  $misconductInstances  Detected misconduct violations
     * @return array Motion details or dismissal_warranted = false
     */
    public function generate(LegalCase $case, array $misconductInstances): array
    {
        Log::info('DismissalMotionGenerator: Starting motion generation', [
            'case_id' => $case->id,
            'total_instances' => count($misconductInstances),
        ]);

        // Filter for dismissal-worthy violations (severity >= 85 or mandates_dismissal)
        $dismissalGrounds = array_filter(
            $misconductInstances,
            fn ($v) => ($v['severity'] ?? 0) >= 85 || ($v['mandates_dismissal'] ?? false)
        );

        if (empty($dismissalGrounds)) {
            Log::info('DismissalMotionGenerator: No dismissal-worthy violations found', [
                'case_id' => $case->id,
            ]);

            return [
                'dismissal_warranted' => false,
                'reason' => 'No violations with severity >= 85 or mandates_dismissal flag',
                'highest_severity' => ! empty($misconductInstances)
                    ? max(array_column($misconductInstances, 'severity'))
                    : 0,
            ];
        }

        Log::info('DismissalMotionGenerator: Dismissal grounds identified', [
            'case_id' => $case->id,
            'grounds_count' => count($dismissalGrounds),
            'types' => array_column($dismissalGrounds, 'type'),
        ]);

        // Generate motion text in Croatian
        $motionText = $this->generateMotionText($case, $dismissalGrounds);

        // Gather legal authorities
        $legalAuthorities = $this->gatherAuthorities($dismissalGrounds);

        // Get filing instructions
        $filingInstructions = $this->getFilingInstructions($case);

        $result = [
            'dismissal_warranted' => true,
            'motion_type' => 'Prijedlog za obustavu postupka',
            'grounds' => $dismissalGrounds,
            'grounds_summary' => $this->summarizeGrounds($dismissalGrounds),
            'motion_text' => $motionText,
            'legal_authorities' => $legalAuthorities,
            'filing_instructions' => $filingInstructions,
            'urgency' => $this->assessUrgency($dismissalGrounds),
            'generated_at' => now()->toIso8601String(),
        ];

        Log::info('DismissalMotionGenerator: Motion generated successfully', [
            'case_id' => $case->id,
            'urgency' => $result['urgency'],
        ]);

        return $result;
    }

    /**
     * Generate formal Croatian dismissal motion text
     *
     * @param  LegalCase  $case  The case
     * @param  array  $grounds  Dismissal grounds
     * @return string Croatian motion text
     */
    protected function generateMotionText(LegalCase $case, array $grounds): string
    {
        $groundsFormatted = $this->formatGrounds($grounds);

        $prompt = <<<PROMPT
Generate a formal Croatian court motion to dismiss criminal case due to prosecutorial misconduct.

Case Information:
Title: {$case->title}
Description: {$case->description}
Court: {$case->court}
Case Number: {$case->case_number}
Prosecutor: {$case->prosecutor}

Grounds for Dismissal:
{$groundsFormatted}

Generate "PRIJEDLOG ZA OBUSTAVU POSTUPKA" with the following structure:

1. NASLOV (Title):
   PRIJEDLOG ZA OBUSTAVU POSTUPKA
   (MOTION TO DISMISS CRIMINAL PROCEEDINGS)

2. SUD I BROJ PREDMETA (Court and Case Number):
   [Court name]
   Broj predmeta: [case number]

3. STRANKE (Parties):
   Predlagatelj: [Defendant/Defense attorney]
   Protivna stranka: Državno odvjetništvo Republike Hrvatske

4. PRAVNA OSNOVA (Legal Basis):
   - ZKP Članak 175 - Obustava kaznenog postupka
   - ZKP Članak 177 - Razlozi za obustavu
   - Ustav RH Članak 29 - Pravo na pravično suđenje
   [Include specific articles based on grounds]

5. ČINJENIČNO STANJE (Factual Background):
   Describe the misconduct that occurred. Be specific and factual.
   Include dates, evidence, and specific violations.

6. PRAVNA ARGUMENTACIJA (Legal Argument):
   Explain why the misconduct mandates case dismissal under Croatian law.
   - Reference ZKP provisions on evidence exclusion
   - Reference Ustav RH provisions on fair trial rights
   - Explain how misconduct violated defendant's constitutional rights
   - Cite Croatian case law if applicable

7. ZAHTJEV (Request):
   "Zbog navedenih razloga, predlažem sudu da:
   1. Obustavi kazneni postupak protiv okrivljenika [name]
   2. Odbaci sve dokaze prikupljene protivno zakonu
   3. Snosi troškove postupka na teret državnog proračuna"

8. POTPIS I DATUM (Signature and Date):
   [Location], [date]
   Branitelj okrivljenika
   [Defense attorney name]

Use formal Croatian legal language throughout. Be precise, professional, and persuasive.
Ensure all ZKP and Ustav RH citations are accurate.
PROMPT;

        try {
            $response = $this->openAI->chat([
                ['role' => 'system', 'content' => 'You are a Croatian criminal defense attorney drafting a formal motion to dismiss. Use proper Croatian legal terminology and format.'],
                ['role' => 'user', 'content' => $prompt],
            ], 'gpt-4o', [
                'temperature' => 0.3, // Lower temperature for formal legal writing
                'max_tokens' => 3000,
            ]);

            return $response['choices'][0]['message']['content'];

        } catch (\Exception $e) {
            Log::error('DismissalMotionGenerator: OpenAI API error', [
                'case_id' => $case->id,
                'error' => $e->getMessage(),
            ]);

            // Return template if AI fails
            return $this->getTemplateMotion($case, $grounds);
        }
    }

    /**
     * Format grounds for prompt
     *
     * @param  array  $grounds  Dismissal grounds
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
     * Gather legal authorities from grounds
     *
     * @param  array  $grounds  Dismissal grounds
     * @return array Legal authorities grouped by source
     */
    protected function gatherAuthorities(array $grounds): array
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

        // Always include fundamental dismissal authorities
        $zkpArticles['175'] = 'ZKP Članak 175 - Obustava kaznenog postupka';
        $zkpArticles['177'] = 'ZKP Članak 177 - Razlozi za obustavu';
        $ustavArticles['29'] = 'Ustav RH Članak 29 - Pravo na pravično suđenje';

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
     * Get filing instructions for the motion
     *
     * @param  LegalCase  $case  The case
     * @return array Filing instructions
     */
    protected function getFilingInstructions(LegalCase $case): array
    {
        return [
            'court' => $case->court ?? 'Nadležni sud',
            'filing_method' => 'Submit motion to the court handling the criminal case',
            'deadline' => 'File as soon as misconduct is discovered. No statutory deadline, but timely filing recommended.',
            'copies_required' => 3,
            'copies_distribution' => [
                'Original to court',
                'Copy to State Attorney (Državno odvjetništvo)',
                'Copy for defense file',
            ],
            'filing_fee' => 'No filing fee for criminal defense motions',
            'procedural_notes' => [
                'Include all supporting evidence as exhibits',
                'Reference specific misconduct instances',
                'Request immediate hearing if urgent',
                'May request oral arguments',
            ],
            'next_steps' => [
                'Court will schedule hearing within 8-15 days',
                'State Attorney will file response',
                'Court will issue ruling',
                'If denied, grounds preserved for appeal',
            ],
        ];
    }

    /**
     * Summarize dismissal grounds
     *
     * @param  array  $grounds  Dismissal grounds
     * @return array Summary
     */
    protected function summarizeGrounds(array $grounds): array
    {
        if (empty($grounds)) {
            return [
                'total_grounds' => 0,
                'violation_types' => [],
                'average_severity' => 0,
                'critical_violations' => 0,
                'mandates_dismissal' => 0,
                'strongest_ground' => [],
            ];
        }

        $types = array_count_values(array_column($grounds, 'type'));
        $avgSeverity = array_sum(array_column($grounds, 'severity')) / count($grounds);

        $criticalViolations = array_filter($grounds, fn ($g) => ($g['severity'] ?? 0) >= 90);

        return [
            'total_grounds' => count($grounds),
            'violation_types' => $types,
            'average_severity' => round($avgSeverity, 1),
            'critical_violations' => count($criticalViolations),
            'mandates_dismissal' => count(array_filter($grounds, fn ($g) => $g['mandates_dismissal'] ?? false)),
            'strongest_ground' => $this->getStrongestGround($grounds),
        ];
    }

    /**
     * Get strongest dismissal ground
     *
     * @param  array  $grounds  Dismissal grounds
     * @return array Strongest ground
     */
    protected function getStrongestGround(array $grounds): array
    {
        if (empty($grounds)) {
            return [];
        }

        // Sort by severity descending
        usort($grounds, fn ($a, $b) => ($b['severity'] ?? 0) <=> ($a['severity'] ?? 0));

        $strongest = $grounds[0];

        return [
            'type' => $strongest['type'] ?? 'unknown',
            'severity' => $strongest['severity'] ?? 0,
            'description' => $strongest['description'] ?? '',
            'legal_basis' => $strongest['legal_basis'] ?? '',
        ];
    }

    /**
     * Assess urgency of dismissal motion
     *
     * @param  array  $grounds  Dismissal grounds
     * @return string Urgency level
     */
    protected function assessUrgency(array $grounds): string
    {
        if (empty($grounds)) {
            return 'low';
        }

        $severities = array_column($grounds, 'severity');
        $maxSeverity = ! empty($severities) ? max($severities) : 0;
        $criticalCount = count(array_filter($grounds, fn ($g) => ($g['severity'] ?? 0) >= 90));

        if ($maxSeverity >= 95 || $criticalCount >= 3) {
            return 'critical'; // File immediately
        } elseif ($maxSeverity >= 90 || $criticalCount >= 2) {
            return 'high'; // File within 1-2 days
        } elseif ($maxSeverity >= 85) {
            return 'medium'; // File within 1 week
        } else {
            return 'low'; // File when convenient
        }
    }

    /**
     * Get template motion text (fallback if AI fails)
     *
     * @param  LegalCase  $case  The case
     * @param  array  $grounds  Dismissal grounds
     * @return string Template motion
     */
    protected function getTemplateMotion(LegalCase $case, array $grounds): string
    {
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
PRIJEDLOG ZA OBUSTAVU POSTUPKA

{$court}
Broj predmeta: {$caseNumber}

STRANKE:
Predlagatelj: Branitelj okrivljenika
Protivna stranka: Državno odvjetništvo Republike Hrvatske

PRAVNA OSNOVA:
- ZKP Članak 175 - Obustava kaznenog postupka
- ZKP Članak 177 - Razlozi za obustavu
- Ustav RH Članak 29 - Pravo na pravično suđenje

ČINJENIČNO STANJE:

U predmetnom kaznenom postupku došlo je do ozbiljnih povreda procesnih propisa od strane državnog odvjetništva koje onemogućavaju pravično suđenje okrivljeniku.

Utvrdene su slijedeće povrede:

{$groundsList}

PRAVNA ARGUMENTACIJA:

Navedene povrede predstavljaju kršenje temeljnih načela kaznenog postupka propisanih ZKP-om i Ustavom RH. Ustav RH Članak 29 jamči pravo na pravično suđenje, koje u ovom slučaju nije moguće ostvariti zbog ozbiljnih procesnih povreda.

Sukladno ZKP Članku 175 i 177, kazneni postupak se mora obustaviti kada su povrede takve prirode da onemogućavaju pravično suđenje.

ZAHTJEV:

Zbog navedenih razloga, predlažem sudu da:
1. Obustavi kazneni postupak protiv okrivljenika
2. Odbaci sve dokaze prikupljene protivno zakonu
3. Snosi troškove postupka na teret državnog proračuna

{$today}
Branitelj okrivljenika
TEMPLATE;
    }
}
