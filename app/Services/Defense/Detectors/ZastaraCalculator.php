<?php

namespace App\Services\Defense\Detectors;

use App\DTOs\Defense\DefenseFlag;
use App\Services\Defense\Contracts\DefenseTacticDetectorInterface;
use Carbon\Carbon;

class ZastaraCalculator implements DefenseTacticDetectorInterface
{
    /**
     * Penalty ranges for common offenses under KZ/11.
     * Maps article number => paragraph number => [min_years, max_years, description_hr]
     *
     * In production: load from database or config. This covers the most common.
     */
    private const OFFENSE_PENALTIES = [
        // Droge - cl. 190. KZ
        190 => [
            1 => ['min' => 1, 'max' => 3, 'desc' => 'Neovlastena proizvodnja i promet drogama (st. 1)'],
            2 => ['min' => 3, 'max' => 15, 'desc' => 'Neovlastena proizvodnja i promet drogama (st. 2 - veca kolicina)'],
            3 => ['min' => 1, 'max' => 12, 'desc' => 'Neovlastena proizvodnja i promet drogama (st. 3 - organizirano)'],
            4 => ['min' => 0, 'max' => 3, 'desc' => 'Neovlasteno posjedovanje droga (st. 4)'],
        ],
        // Teska tjelesna ozljeda - cl. 118. KZ
        118 => [
            1 => ['min' => 0.5, 'max' => 5, 'desc' => 'Teska tjelesna ozljeda (st. 1)'],
            2 => ['min' => 1, 'max' => 8, 'desc' => 'Teska tjelesna ozljeda (st. 2 - kvalificirani)'],
        ],
        // Oruzje - cl. 331. KZ
        331 => [
            1 => ['min' => 0, 'max' => 3, 'desc' => 'Nedozvoljeno posjedovanje oruzja (st. 1)'],
        ],
        // Kradja - cl. 228. KZ
        228 => [
            1 => ['min' => 0, 'max' => 3, 'desc' => 'Kradja (st. 1)'],
        ],
        // Prijevara - cl. 236. KZ
        236 => [
            1 => ['min' => 0, 'max' => 3, 'desc' => 'Prijevara (st. 1)'],
            2 => ['min' => 1, 'max' => 8, 'desc' => 'Prijevara (st. 2 - veca vrijednost)'],
        ],
    ];

    /**
     * KZ/11 cl. 81. - Limitation periods based on max penalty.
     */
    private const ZASTARA_TABLE = [
        ['max_penalty_years' => 999, 'min_penalty_years' => 15, 'relative' => 40, 'label' => 'dugotrajni zatvor'],
        ['max_penalty_years' => 15,  'min_penalty_years' => 10, 'relative' => 25, 'label' => '10-15 godina'],
        ['max_penalty_years' => 10,  'min_penalty_years' => 5,  'relative' => 20, 'label' => '5-10 godina'],
        ['max_penalty_years' => 5,   'min_penalty_years' => 3,  'relative' => 15, 'label' => '3-5 godina'],
        ['max_penalty_years' => 3,   'min_penalty_years' => 1,  'relative' => 10, 'label' => '1-3 godine'],
        ['max_penalty_years' => 1,   'min_penalty_years' => 0,  'relative' => 6,  'label' => 'do 1 godine / novcana'],
    ];

    /**
     * Offenses with NO limitation - cl. 81. st. 2. KZ
     */
    private const NO_LIMITATION_ARTICLES = [88, 89, 90, 91, 92, 93, 97, 99, 352, 353];

    public function tactic(): string
    {
        return 'zastara';
    }

    public function label(): string
    {
        return 'Zastara kaznenog progona';
    }

    public function requires(): array
    {
        return ['dates_with_context', 'case_references', 'entities'];
    }

    public function detect(string $caseId, array $analysisData): array
    {
        $flags = [];

        // Step 1: Find the charged offense article
        $chargedOffense = $this->findChargedOffense($analysisData);
        if (!$chargedOffense) {
            return [new DefenseFlag(
                tactic: 'zastara',
                severity: DefenseFlag::SEVERITY_INFO,
                title: 'Kazneno djelo nije identificirano',
                description: 'Sustav nije mogao automatski odrediti koje kazneno djelo se stavlja na teret. Potrebna rucna provjera zastare.',
                legalBasis: 'cl. 81. KZ',
                echrBasis: null,
                evidence: [],
                recommendedAction: 'Rucno unijeti clanak KZ i datum pocinjenja za izracun zastare.',
                confidence: 0,
            )];
        }

        // Step 2: Find offense date(s)
        $offenseDate = $this->findOffenseDate($analysisData);
        if (!$offenseDate) {
            return [new DefenseFlag(
                tactic: 'zastara',
                severity: DefenseFlag::SEVERITY_INFO,
                title: 'Datum pocinjenja nije utvrdjen',
                description: "Kazneno djelo: cl. {$chargedOffense['article']}. st. {$chargedOffense['paragraph']}. KZ - ali datum pocinjenja nije pronadjen u dokumentima.",
                legalBasis: 'cl. 81. KZ',
                echrBasis: null,
                evidence: [],
                recommendedAction: 'Rucno unijeti datum pocinjenja.',
                confidence: 0,
            )];
        }

        // Step 3: Check no-limitation offenses
        if (in_array($chargedOffense['article'], self::NO_LIMITATION_ARTICLES)) {
            return [new DefenseFlag(
                tactic: 'zastara',
                severity: DefenseFlag::SEVERITY_INFO,
                title: 'Kazneno djelo bez zastare',
                description: "Cl. {$chargedOffense['article']}. KZ ne zastarijeva (cl. 81. st. 2. KZ).",
                legalBasis: 'cl. 81. st. 2. KZ',
                echrBasis: null,
                evidence: [],
                recommendedAction: 'Zastara nije primjenjiva na ovo kazneno djelo.',
                confidence: 1.0,
            )];
        }

        // Step 4: Compute zastara periods
        $penalty = self::OFFENSE_PENALTIES[$chargedOffense['article']][$chargedOffense['paragraph']] ?? null;
        if (!$penalty) {
            return [new DefenseFlag(
                tactic: 'zastara',
                severity: DefenseFlag::SEVERITY_INFO,
                title: 'Nepoznata kazna za clanak',
                description: "Cl. {$chargedOffense['article']}. st. {$chargedOffense['paragraph']}. KZ - raspon kazne nije u bazi. Potrebna rucna provjera.",
                legalBasis: 'cl. 81. KZ',
                echrBasis: null,
                evidence: [],
                recommendedAction: 'Dodati raspon kazne za ovaj clanak u konfiguraciju.',
                confidence: 0,
            )];
        }

        $zastaraInfo = $this->computeZastara($penalty['max'], $offenseDate);
        $now = Carbon::now();

        // Step 5: Generate flags based on zastara status

        // CRITICAL: Absolute zastara exceeded
        if ($now->greaterThan($zastaraInfo['absolute_deadline'])) {
            $flags[] = new DefenseFlag(
                tactic: 'zastara',
                severity: DefenseFlag::SEVERITY_CRITICAL,
                title: 'APSOLUTNA ZASTARA ISTEKLA',
                description: "Kazneno djelo cl. {$chargedOffense['article']}. st. {$chargedOffense['paragraph']}. KZ "
                    . "({$penalty['desc']}) pocinjeno {$offenseDate->format('d.m.Y.')} - "
                    . "apsolutna zastara istekla {$zastaraInfo['absolute_deadline']->format('d.m.Y.')} "
                    . "(rok: {$zastaraInfo['absolute_years']} godina). "
                    . "Kazneni progon je nedopusten.",
                legalBasis: 'cl. 81. st. 4. KZ, cl. 82. KZ',
                echrBasis: null,
                evidence: [
                    'offense_date' => $offenseDate->toDateString(),
                    'charged_article' => "cl. {$chargedOffense['article']}. st. {$chargedOffense['paragraph']}.",
                    'max_penalty_years' => $penalty['max'],
                    'relative_years' => $zastaraInfo['relative_years'],
                    'absolute_years' => $zastaraInfo['absolute_years'],
                    'absolute_deadline' => $zastaraInfo['absolute_deadline']->toDateString(),
                ],
                recommendedAction: 'Podnijeti prigovor o zastari kaznenog progona. '
                    . 'Apsolutna zastara ne moze biti produzena nikakvim prekidima (cl. 82. st. 3. KZ). '
                    . 'Postupak se mora obustaviti.',
                confidence: 0.95,
            );
        }
        // CRITICAL: Relative zastara exceeded (if no interruptions found)
        elseif ($now->greaterThan($zastaraInfo['relative_deadline'])) {
            $flags[] = new DefenseFlag(
                tactic: 'zastara',
                severity: DefenseFlag::SEVERITY_HIGH,
                title: 'Relativna zastara mozda istekla',
                description: "Relativni rok zastare ({$zastaraInfo['relative_years']} god.) istekao "
                    . "{$zastaraInfo['relative_deadline']->format('d.m.Y.')} - ali svaka postupovna radnja prekida zastaru "
                    . 'i rok pocinje teci iznova. Potrebno provjeriti prekide.',
                legalBasis: 'cl. 81. KZ, cl. 82. st. 1-2. KZ',
                echrBasis: null,
                evidence: [
                    'offense_date' => $offenseDate->toDateString(),
                    'relative_deadline' => $zastaraInfo['relative_deadline']->toDateString(),
                    'absolute_deadline' => $zastaraInfo['absolute_deadline']->toDateString(),
                ],
                recommendedAction: 'Utvrditi sve radnje koje prekidaju zastaru (kaznena prijava, nalog za istragu, '
                    . 'optuznica, pozivi). Izracunati teku li novi rokovi prema posljednjem prekidu.',
                confidence: 0.7,
            );

            // Also check if absolute zastara is approaching (even if relative has passed)
            $monthsToAbsolute = $now->diffInMonths($zastaraInfo['absolute_deadline']);
            if ($monthsToAbsolute <= 12) {
                $flags[] = new DefenseFlag(
                    tactic: 'zastara',
                    severity: DefenseFlag::SEVERITY_MEDIUM,
                    title: "Apsolutna zastara za {$monthsToAbsolute} mjeseci",
                    description: "Apsolutna zastara istjece {$zastaraInfo['absolute_deadline']->format('d.m.Y.')} "
                        . "- preostalo {$monthsToAbsolute} mjeseci. Razmotriti taktiku odugovlacenja.",
                    legalBasis: 'cl. 81. st. 4. KZ',
                    echrBasis: null,
                    evidence: [
                        'absolute_deadline' => $zastaraInfo['absolute_deadline']->toDateString(),
                        'months_remaining' => $monthsToAbsolute,
                    ],
                    recommendedAction: 'Zastara se priblizava. Razmotriti legitimne procesne mogucnosti '
                        . 'za produzenje trajanja postupka (zahtjevi za dopunu dokaznog postupka, '
                        . 'zalbe na procesna rjesenja, izuzece suca).',
                    confidence: 0.9,
                );
            }
        }
        // WARNING: Approaching zastara
        else {
            $monthsToAbsolute = $now->diffInMonths($zastaraInfo['absolute_deadline']);
            $monthsToRelative = $now->diffInMonths($zastaraInfo['relative_deadline']);

            if ($monthsToAbsolute <= 12) {
                $flags[] = new DefenseFlag(
                    tactic: 'zastara',
                    severity: DefenseFlag::SEVERITY_MEDIUM,
                    title: "Apsolutna zastara za {$monthsToAbsolute} mjeseci",
                    description: "Apsolutna zastara istjece {$zastaraInfo['absolute_deadline']->format('d.m.Y.')} "
                        . "- preostalo {$monthsToAbsolute} mjeseci. Razmotriti taktiku odugovlacenja.",
                    legalBasis: 'cl. 81. st. 4. KZ',
                    echrBasis: null,
                    evidence: [
                        'absolute_deadline' => $zastaraInfo['absolute_deadline']->toDateString(),
                        'months_remaining' => $monthsToAbsolute,
                    ],
                    recommendedAction: 'Zastara se priblizava. Razmotriti legitimne procesne mogucnosti '
                        . 'za produzenje trajanja postupka (zahtjevi za dopunu dokaznog postupka, '
                        . 'zalbe na procesna rjesenja, izuzece suca).',
                    confidence: 0.9,
                );
            }

            // Also report: INFO with full calculation
            $flags[] = new DefenseFlag(
                tactic: 'zastara',
                severity: DefenseFlag::SEVERITY_INFO,
                title: "Zastara: izracun za cl. {$chargedOffense['article']}. KZ",
                description: "{$penalty['desc']} - kazna do {$penalty['max']} god. zatvora. "
                    . "Relativna zastara: {$zastaraInfo['relative_years']} god. (do {$zastaraInfo['relative_deadline']->format('d.m.Y.')}). "
                    . "Apsolutna zastara: {$zastaraInfo['absolute_years']} god. (do {$zastaraInfo['absolute_deadline']->format('d.m.Y.')}).",
                legalBasis: 'cl. 81. KZ',
                echrBasis: null,
                evidence: $zastaraInfo,
                recommendedAction: 'Pratiti rokove zastare.',
                confidence: 0.9,
            );
        }

        return $flags;
    }

    private function computeZastara(float $maxPenalty, Carbon $offenseDate): array
    {
        $relativeYears = 6; // Default for fine-only

        foreach (self::ZASTARA_TABLE as $row) {
            if ($maxPenalty > $row['min_penalty_years'] && $maxPenalty <= $row['max_penalty_years']) {
                $relativeYears = $row['relative'];
                break;
            }
        }

        // Special: if maxPenalty > 15, it falls in the highest bracket = 40 years
        // Note: maxPenalty == 15 stays in the 25-year bracket (10-15 range)
        if ($maxPenalty > 15) {
            $relativeYears = 40; // dugotrajni zatvor
        }

        $absoluteYears = $relativeYears * 2; // cl. 82. st. 3. KZ

        return [
            'offense_date' => $offenseDate->toDateString(),
            'max_penalty_years' => $maxPenalty,
            'relative_years' => $relativeYears,
            'absolute_years' => $absoluteYears,
            'relative_deadline' => $offenseDate->copy()->addYears($relativeYears),
            'absolute_deadline' => $offenseDate->copy()->addYears($absoluteYears),
        ];
    }

    /**
     * Find charged offense from entity extraction results.
     * Looks for patterns like "cl. 190. st. 2. KZ" in indictment/criminal complaint.
     */
    private function findChargedOffense(array $analysisData): ?array
    {
        // Prioritize documents that are indictments or criminal complaints
        foreach ($analysisData['entities'] ?? [] as $docId => $entities) {
            $lawRefs = $entities['law_references'] ?? [];
            foreach ($lawRefs as $ref) {
                // Match "cl. NNN. st. N. KZ" pattern (various spellings)
                // Handles: cl., clanak, clanku, clanke, clanka, clanu
                if (preg_match('/(?:cl|čl)(?:\.?|an[aeu]?k[aue]?)\s*(\d+)\.\s*(?:st(?:av[ackeu]*|\.)?\s*(\d+)\.)?/ui', $ref, $m)) {
                    $article = (int) $m[1];
                    $paragraph = (int) ($m[2] ?? 1);

                    // Check if article is known (either in penalties or no-limitation list)
                    if (isset(self::OFFENSE_PENALTIES[$article]) || in_array($article, self::NO_LIMITATION_ARTICLES)) {
                        return ['article' => $article, 'paragraph' => $paragraph];
                    }
                }
            }
        }

        return null;
    }

    /**
     * Find offense date - look for dates classified as 'zapljena', 'uhicenje',
     * or earliest event in the timeline.
     */
    private function findOffenseDate(array $analysisData): ?Carbon
    {
        $candidates = [];

        foreach ($analysisData['dates_with_context'] ?? [] as $docId => $dateData) {
            foreach ($dateData['dates'] ?? [] as $d) {
                $eventType = $d['event_type'] ?? null;

                // Offense-indicating event types get priority
                if (in_array($eventType, ['zapljena', 'uhicenje', 'pretraga'])) {
                    $candidates[] = ['date' => $d['date'], 'priority' => 1];
                } elseif ($eventType === 'prijava') {
                    $candidates[] = ['date' => $d['date'], 'priority' => 2];
                } elseif (!empty($d['date'])) {
                    $candidates[] = ['date' => $d['date'], 'priority' => 3];
                }
            }
        }

        if (empty($candidates)) {
            return null;
        }

        // Sort: priority first, then earliest date
        usort($candidates, function ($a, $b) {
            $pCmp = $a['priority'] <=> $b['priority'];

            return $pCmp !== 0 ? $pCmp : $a['date'] <=> $b['date'];
        });

        return Carbon::parse($candidates[0]['date']);
    }
}
