<?php

namespace App\Services\Defense\Detectors;

use App\DTOs\Defense\DefenseFlag;
use App\Services\Defense\Contracts\DefenseTacticDetectorInterface;
use Carbon\Carbon;

/**
 * Chain of Custody Analyzer - Task 32
 *
 * Tracks physical evidence across documents:
 * - Seizure -> Storage -> Lab -> Court
 * - Flags gaps > 90 days between seizure and analysis
 * - Checks for missing solenitetni svjedoci (witness count < 2 on search records)
 */
class ChainOfCustodyAnalyzer implements DefenseTacticDetectorInterface
{
    /**
     * Minimum gap (days) between seizure and analysis to flag.
     */
    private const GAP_THRESHOLD_DAYS = 90;

    /**
     * Minimum required witnesses on search records (solenitetni svjedoci).
     */
    private const MIN_WITNESSES_REQUIRED = 2;

    public function tactic(): string
    {
        return 'chain_of_custody';
    }

    public function label(): string
    {
        return 'Lanac cuvanja dokaza';
    }

    public function requires(): array
    {
        return ['dates_with_context', 'entities', 'case_reference_registry'];
    }

    public function detect(string $caseId, array $analysisData): array
    {
        $flags = [];

        // Check for gaps between seizure and analysis
        $gapFlags = $this->checkSeizureAnalysisGaps($analysisData);
        $flags = array_merge($flags, $gapFlags);

        // Check for missing solenitetni svjedoci on search records
        $witnessFlags = $this->checkSearchRecordWitnesses($analysisData);
        $flags = array_merge($flags, $witnessFlags);

        return $flags;
    }

    /**
     * Check for time gaps between seizure (zapljena) and analysis (vjestacenje).
     */
    private function checkSeizureAnalysisGaps(array $analysisData): array
    {
        $flags = [];
        $evidenceEvents = [];

        // Collect all seizure and analysis events from dates_with_context
        foreach ($analysisData['dates_with_context'] ?? [] as $docId => $dateData) {
            foreach ($dateData['dates'] ?? [] as $d) {
                $eventType = $d['event_type'] ?? null;
                if (in_array($eventType, ['zapljena', 'vjestacenje'])) {
                    $evidenceEvents[] = [
                        'date' => $d['date'],
                        'type' => $eventType,
                        'context' => $d['context'] ?? '',
                        'doc_id' => $docId,
                    ];
                }
            }
        }

        // Sort chronologically
        usort($evidenceEvents, fn($a, $b) => $a['date'] <=> $b['date']);

        // Find seizure and analysis events
        $seizures = array_filter($evidenceEvents, fn($e) => $e['type'] === 'zapljena');
        $analyses = array_filter($evidenceEvents, fn($e) => $e['type'] === 'vjestacenje');

        foreach ($seizures as $seizure) {
            $seizureDate = Carbon::parse($seizure['date']);
            $foundAnalysis = false;

            foreach ($analyses as $analysis) {
                $analysisDate = Carbon::parse($analysis['date']);
                $gap = $seizureDate->diffInDays($analysisDate);

                if ($analysisDate > $seizureDate) {
                    $foundAnalysis = true;

                    if ($gap > self::GAP_THRESHOLD_DAYS) {
                        $flags[] = new DefenseFlag(
                            tactic: 'chain_of_custody',
                            severity: DefenseFlag::SEVERITY_MEDIUM,
                            title: "Dugacak interval zapljena - vjestacenje ({$gap} dana)",
                            description: "Izmedju zapljene ({$seizure['date']}) i vjestacenja ({$analysis['date']}) "
                                . "proslo je {$gap} dana. Nedostaju dokumenti o cuvanju, "
                                . "prijenosu i integritetu dokaza u tom periodu.",
                            legalBasis: 'cl. 250. ZKP, cl. 261-262. ZKP',
                            echrBasis: null,
                            evidence: [
                                'seizure_date' => $seizure['date'],
                                'seizure_doc' => $seizure['doc_id'],
                                'analysis_date' => $analysis['date'],
                                'analysis_doc' => $analysis['doc_id'],
                                'gap_days' => $gap,
                            ],
                            recommendedAction: "Zatraziti potpunu dokumentaciju o lancu cuvanja: "
                                . "tko je preuzeo predmete, gdje su cuvani, tko im je pristupao. "
                                . "Osporiti autenticnost dokaza ako dokumentacija ne postoji.",
                            confidence: 0.7,
                        );
                    }
                    break; // Only check first analysis after seizure
                }
            }

            // Check for seizure without any subsequent analysis
            if (!$foundAnalysis && !empty($analyses)) {
                $flags[] = new DefenseFlag(
                    tactic: 'chain_of_custody',
                    severity: DefenseFlag::SEVERITY_MEDIUM,
                    title: "Zaplijena bez vidljivog vjestacenja",
                    description: "Zapljena dokumentirana ({$seizure['date']}) ali nema vidljivog "
                        . "vjestacenja u spisu - ili nedostaje nalaz vjestaka, ili zaplijenjeni "
                        . "predmeti nikada nisu analizirani.",
                    legalBasis: 'cl. 250. ZKP',
                    echrBasis: null,
                    evidence: ['seizure_date' => $seizure['date'], 'seizure_doc' => $seizure['doc_id']],
                    recommendedAction: "Provjeriti je li nalaz vjestaka dostavljen obrani. "
                        . "Ako nedostaje, zatraziti ga od tuzilastva (cl. 184. ZKP).",
                    confidence: 0.6,
                );
            }
        }

        return $flags;
    }

    /**
     * Check for missing solenitetni svjedoci (witnesses) on search records.
     * Per cl. 246. st. 7. ZKP, at least 2 adult witnesses are required.
     */
    private function checkSearchRecordWitnesses(array $analysisData): array
    {
        $flags = [];

        foreach ($analysisData['entities'] ?? [] as $docId => $entities) {
            $persons = $entities['persons'] ?? [];
            $evidence = $entities['evidence'] ?? [];

            // Check if this document is a search record (Zapisnik o pretrazi)
            $isSearchRecord = false;
            foreach ($evidence as $ev) {
                if (preg_match('/Zapisnik\s+o\s+pretraz/ui', $ev)) {
                    $isSearchRecord = true;
                    break;
                }
            }

            if ($isSearchRecord) {
                // Count witnesses - look for "svjedok" in person references
                $witnessCount = 0;
                foreach ($persons as $p) {
                    if (preg_match('/svjedok/ui', $p)) {
                        $witnessCount++;
                    }
                }

                if ($witnessCount < self::MIN_WITNESSES_REQUIRED) {
                    $flags[] = new DefenseFlag(
                        tactic: 'chain_of_custody',
                        severity: DefenseFlag::SEVERITY_HIGH,
                        title: "Manje od " . self::MIN_WITNESSES_REQUIRED . " svjedoka na zapisniku o pretrazi",
                        description: "Zapisnik o pretrazi (dokument {$docId}) navodi {$witnessCount} svjedoka. "
                            . "Cl. 246. st. 7. ZKP zahtijeva najmanje " . self::MIN_WITNESSES_REQUIRED 
                            . " punoljetna svjedoka (solenitetni svjedoci).",
                        legalBasis: 'cl. 246. st. 7. ZKP',
                        echrBasis: null,
                        evidence: ['doc_id' => $docId, 'witness_count' => $witnessCount],
                        recommendedAction: "Ako nisu bila prisutna najmanje " . self::MIN_WITNESSES_REQUIRED 
                            . " punoljetna svjedoka, zapisnik o pretrazi je nezakonit i svi pronadeni "
                            . "dokazi su nezakoniti (cl. 10. st. 2. t. 1. ZKP).",
                        confidence: 0.65,
                    );
                }
            }
        }

        return $flags;
    }
}
