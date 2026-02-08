<?php

namespace App\Services\Defense\Detectors;

use App\DTOs\Defense\DefenseFlag;
use App\Services\Defense\Contracts\DefenseTacticDetectorInterface;

/**
 * ProportionalityChecker - Tier C Detector
 *
 * Compares offense severity vs investigative measures used.
 * Flags disproportionate use of invasive measures for minor offenses.
 *
 * - If charged offense < 10 years max penalty but surveillance was used -> flag
 * - If charged offense is possession-level but home search was conducted -> flag
 */
class ProportionalityChecker implements DefenseTacticDetectorInterface
{
    /**
     * Penalty ranges for common offenses under KZ/11.
     * Maps article.paragraph -> max_years
     */
    private const OFFENSE_PENALTIES = [
        // Droge - cl. 190. KZ
        '190.1' => 3,   // Production/trafficking basic
        '190.2' => 15,  // Large quantity
        '190.3' => 12,  // Organized
        '190.4' => 3,   // Possession
        // Teska tjelesna ozljeda - cl. 118. KZ
        '118.1' => 5,
        '118.2' => 8,
        // Oruzje - cl. 331. KZ
        '331.1' => 3,
        // Krada - cl. 228. KZ
        '228.1' => 3,
        // Prijevara - cl. 236. KZ
        '236.1' => 3,
        '236.2' => 8,
    ];

    /**
     * Minimum offense severity (max years) for invasive measures.
     */
    private const SURVEILLANCE_MIN_YEARS = 10; // cl. 332. ZKP - posebne dokazne radnje
    private const HOME_SEARCH_MIN_YEARS = 1;   // Basic threshold for home search proportionality

    /**
     * "Possession-level" offenses where home search may be disproportionate.
     */
    private const POSSESSION_OFFENSES = ['190.4', '331.1'];

    /**
     * Invasive measures that require proportionality check.
     */
    private const INVASIVE_MEASURES = [
        'prisluskivanje',
        'tajno pracenje',
        'posebne dokazne radnje',
        'prikriveni istrazitelj',
        'simulirana kupnja',
    ];

    public function tactic(): string
    {
        return 'proportionality';
    }

    public function label(): string
    {
        return 'Razmjernost istraznih mjera (cl. 332. ZKP)';
    }

    public function requires(): array
    {
        return ['entities', 'dates_with_context'];
    }

    /**
     * @return DefenseFlag[]
     */
    public function detect(string $caseId, array $analysisData): array
    {
        $flags = [];

        // Step 1: Identify charged offense and its severity
        $offenseInfo = $this->identifyChargedOffense($analysisData);

        if (!$offenseInfo) {
            return [];
        }

        $maxPenalty = $offenseInfo['max_years'];
        $offenseKey = $offenseInfo['offense_key'];

        // Step 2: Identify investigative measures used
        $measuresUsed = $this->identifyMeasuresUsed($analysisData);

        // Step 3: Check proportionality

        // 3a: Surveillance for minor offense (< 10 years)
        $surveillanceUsed = $this->checkSurveillanceUsed($measuresUsed);
        if ($surveillanceUsed && $maxPenalty < self::SURVEILLANCE_MIN_YEARS) {
            $flags[] = new DefenseFlag(
                tactic: 'proportionality',
                severity: DefenseFlag::SEVERITY_MEDIUM,
                title: "Prisluskivanje za kazneno djelo s kaznom do {$maxPenalty} godina",
                description: "Posebne dokazne radnje (prisluskivanje, tajno pracenje) korištene su "
                    . "za kazneno djelo s maksimalnom kaznom od {$maxPenalty} godina. "
                    . "Prema cl. 332. st. 1. ZKP, ove mjere dopustene su samo za najteža kaznena djela. "
                    . "Korištenje za blaža djela predstavlja povredu načela razmjernosti.",
                legalBasis: 'cl. 332. ZKP, cl. 16. Ustav RH, cl. 35. Ustav RH',
                echrBasis: 'Roman Zakharov v. Russia [GC] (2015), Szabo and Vissy v. Hungary (2016)',
                evidence: [
                    'offense_article' => $offenseInfo['article'],
                    'offense_paragraph' => $offenseInfo['paragraph'],
                    'max_penalty_years' => $maxPenalty,
                    'measures_used' => $surveillanceUsed,
                ],
                recommendedAction: "Zahtijevati izdvajanje dokaza pribavljenih posebnim dokaznim radnjama. "
                    . "Istaknuti povredu cl. 332. ZKP - mjere nisu bile razmjerne tezini kaznenog djela. "
                    . "Pozvati se na ECHR standard: 'strictly necessary in a democratic society'.",
                confidence: 0.7,
            );
        }

        // 3b: Home search for possession-level offense
        $homeSearchUsed = $this->checkHomeSearchUsed($measuresUsed);
        if ($homeSearchUsed && in_array($offenseKey, self::POSSESSION_OFFENSES)) {
            $flags[] = new DefenseFlag(
                tactic: 'proportionality',
                severity: DefenseFlag::SEVERITY_MEDIUM,
                title: "Pretraga stana za posjedovanje",
                description: "Pretraga stana provedena je za kazneno djelo posjedovanja. "
                    . "Zadiranje u privatnost doma mora biti razmjerno cilju. "
                    . "Za djela posjedovanja (bez daljnje distribucije), agresivne mjere "
                    . "mogu predstavljati povredu načela razmjernosti.",
                legalBasis: 'cl. 246. ZKP, cl. 34. Ustav RH',
                echrBasis: 'Camenzind v. Switzerland (1997), Smirnov v. Russia (2007)',
                evidence: [
                    'offense_article' => $offenseInfo['article'],
                    'offense_paragraph' => $offenseInfo['paragraph'],
                    'offense_type' => 'possession',
                    'measure' => 'home_search',
                ],
                recommendedAction: "Osporiti razmjernost pretrage. Analizirati je li postojala "
                    . "alternativna, manje invazivna mjera za postizanje istog cilja. "
                    . "Zahtijevati obrazlozenje nuznosti pretrage stana.",
                confidence: 0.6,
            );
        }

        return $flags;
    }

    /**
     * Extract charged offense from entity data.
     */
    private function identifyChargedOffense(array $analysisData): ?array
    {
        foreach ($analysisData['entities'] ?? [] as $docId => $entities) {
            foreach ($entities['law_references'] ?? [] as $ref) {
                // Match "cl. NNN. st. N. KZ" pattern
                if (preg_match('/cl\.\s*(\d+)\.\s*st\.\s*(\d+)\.\s*KZ/ui', $ref, $m)) {
                    $article = (int) $m[1];
                    $paragraph = (int) $m[2];
                    $offenseKey = "{$article}.{$paragraph}";

                    if (isset(self::OFFENSE_PENALTIES[$offenseKey])) {
                        return [
                            'article' => $article,
                            'paragraph' => $paragraph,
                            'offense_key' => $offenseKey,
                            'max_years' => self::OFFENSE_PENALTIES[$offenseKey],
                        ];
                    }
                }
            }
        }

        return null;
    }

    /**
     * Collect all investigative measures mentioned in analysis data.
     */
    private function identifyMeasuresUsed(array $analysisData): array
    {
        $measures = [];

        // From entities
        foreach ($analysisData['entities'] ?? [] as $docId => $entities) {
            foreach ($entities['measures'] ?? [] as $measure) {
                $measures[] = mb_strtolower($measure);
            }
        }

        // From dates_with_context
        foreach ($analysisData['dates_with_context'] ?? [] as $docId => $dateData) {
            foreach ($dateData['dates'] ?? [] as $d) {
                $eventType = $d['event_type'] ?? '';
                if ($eventType) {
                    $measures[] = mb_strtolower($eventType);
                }
                // Check context for additional measures
                $context = mb_strtolower($d['context'] ?? '');
                foreach (self::INVASIVE_MEASURES as $invasive) {
                    if (str_contains($context, $invasive)) {
                        $measures[] = $invasive;
                    }
                }
            }
        }

        return array_unique($measures);
    }

    /**
     * Check if surveillance measures were used.
     */
    private function checkSurveillanceUsed(array $measures): array
    {
        $found = [];
        foreach ($measures as $measure) {
            foreach (self::INVASIVE_MEASURES as $invasive) {
                if (str_contains($measure, $invasive)) {
                    $found[] = $invasive;
                }
            }
        }
        return array_unique($found);
    }

    /**
     * Check if home search was used.
     */
    private function checkHomeSearchUsed(array $measures): bool
    {
        foreach ($measures as $measure) {
            if (str_contains($measure, 'pretraga')) {
                return true;
            }
        }
        return false;
    }
}
