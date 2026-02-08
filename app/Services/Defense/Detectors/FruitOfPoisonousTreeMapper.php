<?php

namespace App\Services\Defense\Detectors;

use App\DTOs\Defense\DefenseFlag;
use App\Services\Defense\Contracts\DefenseTacticDetectorInterface;
use Carbon\Carbon;

/**
 * Fruit of the Poisonous Tree Mapper - Task 33
 *
 * Builds dependency tree from tainted evidence:
 * - Uses existing defense_flags for primary exclusions
 * - Maps all derivative evidence discovered after tainted date
 * - References cl. 10. st. 2. t. 4. ZKP
 *
 * In Croatian law, there is NO good faith exception.
 * The cascade effect is mandatory, not discretionary.
 */
class FruitOfPoisonousTreeMapper implements DefenseTacticDetectorInterface
{
    /**
     * Event types that represent potentially tainted derivative evidence.
     */
    private const DERIVATIVE_EVENT_TYPES = [
        'zapljena',
        'ispitivanje',
        'vjestacenje',
        'nalog_izdavanje',
        'pretraga',
    ];

    /**
     * Tactics that indicate primary evidence exclusion.
     */
    private const TAINTED_TACTICS = [
        'chain_of_custody',
        'defense_time_adequacy',
    ];

    public function tactic(): string
    {
        return 'fruit_of_poisonous_tree';
    }

    public function label(): string
    {
        return 'Plodovi otrovnog drveta (cl. 10. st. 2. t. 4. ZKP)';
    }

    public function requires(): array
    {
        return ['dates_with_context', 'entities', 'case_references', 'defense_flags'];
    }

    public function detect(string $caseId, array $analysisData): array
    {
        $flags = [];

        // Step 1: Identify tainted evidence from existing defense flags
        $taintedDate = $this->findTaintedDate($analysisData);

        if (!$taintedDate) {
            // No tainted evidence found - nothing to cascade from
            return [];
        }

        // Step 2: Build temporal evidence chain - all events after tainted date
        $derivativeEvents = $this->findDerivativeEvents($analysisData, $taintedDate);

        if (empty($derivativeEvents)) {
            return [];
        }

        // Step 3: Create flag for derivative evidence
        $eventDescriptions = array_map(function ($e) {
            $type = $e['event_type'] ?? 'nepoznato';
            return "{$type} ({$e['date']}, dok. {$e['doc_id']})";
        }, $derivativeEvents);

        $flags[] = new DefenseFlag(
            tactic: 'fruit_of_poisonous_tree',
            severity: DefenseFlag::SEVERITY_HIGH,
            title: 'Moguci plodovi otrovnog drveta - ' . count($derivativeEvents) . ' radnji',
            description: "Nakon nezakonitog dokaza ({$taintedDate}) provedeno je "
                . count($derivativeEvents) . " radnji koje su potencijalno derivativni dokazi:\n"
                . implode("\n", array_slice($eventDescriptions, 0, 10)),
            legalBasis: 'cl. 10. st. 2. t. 4. ZKP',
            echrBasis: 'Gafgen v. Germany [GC] (2010)',
            evidence: [
                'tainted_date' => $taintedDate,
                'derivative_events' => array_slice($derivativeEvents, 0, 20),
                'total_derivative' => count($derivativeEvents),
            ],
            recommendedAction: "Zahtijevati izdvajanje SVIH dokaza za koje se saznalo iz nezakonitih "
                . "dokaza (cl. 10. st. 2. t. 4. ZKP). U hrvatskom pravu NE POSTOJI iznimka "
                . "dobre vjere (good faith exception). Kaskadni ucinak je obvezan, ne diskrecijski.\n\n"
                . "Jedina iznimka: cl. 10. st. 3. ZKP - za djela u nadleznosti zupanijskog suda "
                . "koja su 'osobito teska', interes progona moze prevagnuti. Ali teret dokaza "
                . "je na tuzilastvu.",
            confidence: 0.65, // Temporal proximity != causal connection - needs manual review
        );

        return $flags;
    }

    /**
     * Find the earliest tainted date from existing defense flags.
     */
    private function findTaintedDate(array $analysisData): ?string
    {
        $existingFlags = $analysisData['defense_flags'] ?? [];

        if (empty($existingFlags)) {
            return null;
        }

        $taintedDate = null;

        foreach ($existingFlags as $flag) {
            // Check if this is a critical flag from relevant tactics
            $tactic = is_array($flag) ? ($flag['tactic'] ?? '') : ($flag->tactic ?? '');
            $severity = is_array($flag) ? ($flag['severity'] ?? '') : ($flag->severity ?? '');

            if ($severity !== 'critical') {
                continue;
            }

            // Extract date from evidence
            $evidence = is_array($flag) ? ($flag['evidence'] ?? []) : ($flag->evidence ?? []);
            if (is_string($evidence)) {
                $evidence = json_decode($evidence, true) ?? [];
            }

            // Look for various date fields in evidence
            $dateFields = ['search_date', 'seizure_date', 'tainted_date', 'date'];
            foreach ($dateFields as $field) {
                if (isset($evidence[$field])) {
                    $date = $evidence[$field];
                    if (!$taintedDate || $date < $taintedDate) {
                        $taintedDate = $date;
                    }
                    break;
                }
            }
        }

        return $taintedDate;
    }

    /**
     * Find all events that occurred after the tainted date.
     */
    private function findDerivativeEvents(array $analysisData, string $taintedDate): array
    {
        $allEvents = [];

        foreach ($analysisData['dates_with_context'] ?? [] as $docId => $dateData) {
            foreach ($dateData['dates'] ?? [] as $d) {
                if (!empty($d['date'])) {
                    $allEvents[] = array_merge($d, ['doc_id' => $docId]);
                }
            }
        }

        // Sort chronologically
        usort($allEvents, fn($a, $b) => $a['date'] <=> $b['date']);

        // Filter: only events after tainted date AND of relevant types
        $derivativeEvents = array_filter($allEvents, function ($e) use ($taintedDate) {
            return $e['date'] > $taintedDate
                && in_array($e['event_type'] ?? '', self::DERIVATIVE_EVENT_TYPES);
        });

        return array_values($derivativeEvents);
    }
}
