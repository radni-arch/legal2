<?php

namespace App\Services\Defense\Detectors;

use App\DTOs\Defense\DefenseFlag;
use App\Services\Defense\Contracts\DefenseTacticDetectorInterface;
use Carbon\Carbon;

class DefenseTimeAdequacyChecker implements DefenseTacticDetectorInterface
{
    /**
     * Time constraints between procedural events.
     * Based on ZKP provisions and ECHR case law.
     * Format: [event_a_type, event_b_type, days, severity, description_hr, constraint_type]
     * constraint_type: 'min' = gap must be >= days, 'max' = gap must be <= days, 'before' = a must be before b
     */
    private const TIME_THRESHOLDS = [
        ['dostava', 'rociste', 8, 'high', 'Dostava optuznice -> rociste (min. 8 dana, cl. 374. ZKP)', 'min'],
        ['uhicenje', 'ispitivanje', 2, 'critical', 'Uhicenje -> sudsko ispitivanje (max 48h, cl. 112. ZKP)', 'max'],
        ['nalog_izdavanje', 'pretraga', 0, 'critical', 'Nalog mora PRETHODITI pretrazi (cl. 246. ZKP)', 'before'],
        ['prijava', 'dostava', 15, 'medium', 'Kaznena prijava -> dostava obrani (cl. 341. ZKP)', 'min'],
    ];

    /**
     * ECHR reasonable time benchmarks (Kirincic v. Croatia).
     */
    private const ECHR_REASONABLE_TIME = [
        'warning_years' => 5,
        'violation_years' => 10,
        'clear_violation_years' => 15,
    ];

    public function tactic(): string
    {
        return 'defense_time_adequacy';
    }

    public function label(): string
    {
        return 'Pripremljenost obrane / razumni rok';
    }

    public function requires(): array
    {
        return ['dates_with_context'];
    }

    public function detect(string $caseId, array $analysisData): array
    {
        $flags = [];

        // Collect all dates with event types across all documents
        $events = [];
        foreach ($analysisData['dates_with_context'] ?? [] as $docId => $dateData) {
            foreach ($dateData['dates'] ?? [] as $d) {
                if (!empty($d['date']) && !empty($d['event_type'])) {
                    $events[] = [
                        'date' => $d['date'],
                        'time' => $d['time'] ?? null,
                        'type' => $d['event_type'],
                        'context' => $d['context'] ?? '',
                        'doc_id' => $docId,
                    ];
                }
            }
        }

        if (empty($events)) {
            return $flags;
        }

        // Sort chronologically
        usort($events, fn($a, $b) => $a['date'] <=> $b['date']);

        // Check sequential time gaps
        foreach (self::TIME_THRESHOLDS as [$typeA, $typeB, $days, $severity, $desc, $constraintType]) {
            $eventsA = array_filter($events, fn($e) => $e['type'] === $typeA);
            $eventsB = array_filter($events, fn($e) => $e['type'] === $typeB);

            foreach ($eventsA as $a) {
                foreach ($eventsB as $b) {
                    $dateA = Carbon::parse($a['date']);
                    $dateB = Carbon::parse($b['date']);
                    $gap = $dateA->diffInDays($dateB, false); // signed

                    // Handle 'before' constraint: event A must happen before event B
                    if ($constraintType === 'before') {
                        if ($gap < 0) {
                            // B happened BEFORE A - violation
                            $flags[] = new DefenseFlag(
                                tactic: 'defense_time_adequacy',
                                severity: DefenseFlag::SEVERITY_CRITICAL,
                                title: 'PRETRAGA PRIJE NALOGA',
                                description: "Pretraga provedena {$b['date']} ali nalog izdan {$a['date']} "
                                    . '- pretraga prethodi nalogu za ' . abs($gap) . ' dana!',
                                legalBasis: 'cl. 246. ZKP, cl. 10. st. 2. ZKP',
                                echrBasis: null,
                                evidence: [
                                    'warrant_date' => $a['date'],
                                    'search_date' => $b['date'],
                                    'gap_days' => $gap,
                                    'warrant_doc' => $a['doc_id'],
                                    'search_doc' => $b['doc_id'],
                                ],
                                recommendedAction: 'Zahtijevati izdvajanje svih dokaza proizaslih iz pretrage '
                                    . 'provedene bez valjanog naloga (cl. 10. st. 2. t. 1. ZKP).',
                                confidence: 0.95,
                            );
                        }
                        continue;
                    }

                    // Handle 'max' constraint: gap must not exceed days
                    if ($constraintType === 'max') {
                        if ($gap >= 0 && $gap > $days) {
                            $flags[] = new DefenseFlag(
                                tactic: 'defense_time_adequacy',
                                severity: $severity,
                                title: "Nedovoljan rok: {$desc}",
                                description: "{$typeA} ({$a['date']}) -> {$typeB} ({$b['date']}) = {$gap} dana. "
                                    . "Maximum: {$days} dana.",
                                legalBasis: $desc,
                                echrBasis: 'Dvorski v. Croatia [GC] (2015)',
                                evidence: [
                                    'event_a' => $a,
                                    'event_b' => $b,
                                    'gap_days' => $gap,
                                    'maximum_days' => $days,
                                ],
                                recommendedAction: 'Istaknuti povredu prava na pripremu obrane. '
                                    . 'Zahtijevati odgodu i/ili ponistenje radnji provedenih bez '
                                    . 'dovoljnog vremena za pripremu.',
                                confidence: 0.85,
                            );
                        }
                        continue;
                    }

                    // Handle 'min' constraint: gap must be at least days
                    if ($constraintType === 'min') {
                        if ($gap >= 0 && $gap < $days) {
                            $flags[] = new DefenseFlag(
                                tactic: 'defense_time_adequacy',
                                severity: $severity,
                                title: "Nedovoljan rok: {$desc}",
                                description: "{$typeA} ({$a['date']}) -> {$typeB} ({$b['date']}) = {$gap} dana. "
                                    . "Minimum: {$days} dana.",
                                legalBasis: $desc,
                                echrBasis: 'Dvorski v. Croatia [GC] (2015)',
                                evidence: [
                                    'event_a' => $a,
                                    'event_b' => $b,
                                    'gap_days' => $gap,
                                    'minimum_days' => $days,
                                ],
                                recommendedAction: 'Istaknuti povredu prava na pripremu obrane. '
                                    . 'Zahtijevati odgodu i/ili ponistenje radnji provedenih bez '
                                    . 'dovoljnog vremena za pripremu.',
                                confidence: 0.85,
                            );
                        }
                    }
                }
            }
        }

        // Check total proceeding duration (ECHR reasonable time)
        if (!empty($events)) {
            $earliest = Carbon::parse($events[0]['date']);
            $latest = Carbon::parse(end($events)['date']);
            $totalYears = $earliest->diffInYears($latest);

            if ($totalYears >= self::ECHR_REASONABLE_TIME['clear_violation_years']) {
                $flags[] = new DefenseFlag(
                    tactic: 'defense_time_adequacy',
                    severity: DefenseFlag::SEVERITY_HIGH,
                    title: "Postupak traje {$totalYears} godina - ocita povreda razumnog roka",
                    description: "Trajanje postupka ({$totalYears} god.) prelazi ECHR prag od "
                        . self::ECHR_REASONABLE_TIME['clear_violation_years'] . ' godina '
                        . '(Kirincic i dr. v. Hrvatske, 2020).',
                    legalBasis: 'cl. 29. Ustav RH',
                    echrBasis: 'Kirincic i dr. v. Hrvatske (2020), cl. 6. ECHR',
                    evidence: ['earliest' => $earliest->toDateString(), 'latest' => $latest->toDateString()],
                    recommendedAction: 'Podnijeti zahtjev za zastitu prava na sudjenje u razumnom roku '
                        . '(cl. 63.-70. Zakona o sudovima). Razmotriti ustavnu tuzbu.',
                    confidence: 0.9,
                );
            } elseif ($totalYears >= self::ECHR_REASONABLE_TIME['warning_years']) {
                $flags[] = new DefenseFlag(
                    tactic: 'defense_time_adequacy',
                    severity: DefenseFlag::SEVERITY_MEDIUM,
                    title: "Postupak traje {$totalYears} godina - priblizava se povredi razumnog roka",
                    description: 'ECHR prag upozorenja dosegnut.',
                    legalBasis: 'cl. 29. Ustav RH',
                    echrBasis: 'cl. 6. ECHR',
                    evidence: ['total_years' => $totalYears],
                    recommendedAction: 'Dokumentirati sve periode neaktivnosti suda za eventualnu prituzbu.',
                    confidence: 0.8,
                );
            }
        }

        return $flags;
    }
}
