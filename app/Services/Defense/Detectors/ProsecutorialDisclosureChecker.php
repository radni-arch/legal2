<?php

namespace App\Services\Defense\Detectors;

use App\DTOs\Defense\DefenseFlag;
use App\Services\Defense\Contracts\DefenseTacticDetectorInterface;

/**
 * Prosecutorial Disclosure Checker - Task 34
 *
 * Cross-references CaseFileRegistry missing references:
 * - Flags undisclosed documents (cl. 9./184. ZKP)
 * - Counts witness references
 *
 * Under Croatian law:
 * - cl. 9. ZKP: Prosecution must gather evidence of guilt AND innocence with equal care
 * - cl. 184. ZKP: Defense right to inspect case file
 */
class ProsecutorialDisclosureChecker implements DefenseTacticDetectorInterface
{
    /**
     * Threshold for elevating severity to HIGH.
     */
    private const HIGH_SEVERITY_THRESHOLD = 3;

    public function tactic(): string
    {
        return 'prosecutorial_disclosure';
    }

    public function label(): string
    {
        return 'Obveza razotkrivanja dokaza (cl. 9./184. ZKP)';
    }

    public function requires(): array
    {
        return ['case_reference_registry', 'entities'];
    }

    public function detect(string $caseId, array $analysisData): array
    {
        $flags = [];

        // Check for missing references in the CaseFileRegistry
        $missingFlags = $this->checkMissingReferences($analysisData);
        $flags = array_merge($flags, $missingFlags);

        // Count and report witnesses
        $witnessFlags = $this->countWitnesses($analysisData);
        $flags = array_merge($flags, $witnessFlags);

        return $flags;
    }

    /**
     * Check CaseFileRegistry for missing references.
     */
    private function checkMissingReferences(array $analysisData): array
    {
        $flags = [];
        $registry = $analysisData['case_reference_registry'] ?? [];

        // Filter for missing references
        $missingRefs = array_filter($registry, function ($r) {
            $status = is_object($r) ? ($r->status ?? '') : ($r['status'] ?? '');
            return $status === 'missing';
        });

        if (count($missingRefs) > 0) {
            $missingList = array_map(function ($r) {
                $type = is_object($r) ? ($r->reference_type ?? '') : ($r['reference_type'] ?? '');
                $value = is_object($r) ? ($r->reference_value ?? '') : ($r['reference_value'] ?? '');
                return "{$type}: {$value}";
            }, $missingRefs);

            $severity = count($missingRefs) > self::HIGH_SEVERITY_THRESHOLD
                ? DefenseFlag::SEVERITY_HIGH
                : DefenseFlag::SEVERITY_MEDIUM;

            $flags[] = new DefenseFlag(
                tactic: 'prosecutorial_disclosure',
                severity: $severity,
                title: count($missingRefs) . ' referenci bez izvornog dokumenta u spisu',
                description: "Sljedece reference se spominju u dokumentima ali izvorni dokumenti "
                    . "nisu pronadeni u spisu:\n" . implode("\n", array_slice($missingList, 0, 15)),
                legalBasis: 'cl. 9. ZKP, cl. 184. ZKP',
                echrBasis: 'Matanovic v. Hrvatske (2017)',
                evidence: [
                    'missing_count' => count($missingRefs),
                    'missing_references' => array_slice($missingList, 0, 30),
                ],
                recommendedAction: "Podnijeti zahtjev za uvid u spis (cl. 184. ZKP) i zatraziti "
                    . "dostavu svih nedostajucih dokumenata. Ako tuzilastvo ne dostavi, "
                    . "istaknuti povredu cl. 9. ZKP (obveza prikupljanja dokaza o krivnji "
                    . "i neduznosti s jednakom paznjom).",
                confidence: 0.8,
            );
        }

        return $flags;
    }

    /**
     * Count witnesses referenced in entities.
     */
    private function countWitnesses(array $analysisData): array
    {
        $flags = [];
        $allWitnesses = [];

        foreach ($analysisData['entities'] ?? [] as $docId => $entities) {
            foreach ($entities['persons'] ?? [] as $person) {
                if (preg_match('/svjedok/ui', $person)) {
                    $allWitnesses[] = ['person' => $person, 'doc_id' => $docId];
                }
            }
        }

        if (count($allWitnesses) > 0) {
            // Count unique witnesses (simplified - in production use NLP for name matching)
            $uniqueWitnesses = count(array_unique(array_column($allWitnesses, 'person')));

            $flags[] = new DefenseFlag(
                tactic: 'prosecutorial_disclosure',
                severity: DefenseFlag::SEVERITY_INFO,
                title: "{$uniqueWitnesses} svjedoka identificirano u spisu",
                description: "Sustav je identificirao {$uniqueWitnesses} jedinstvenih referenci "
                    . "na svjedoke. Potrebno rucno provjeriti jesu li svi predlozeni kao svjedoci obrane/optuzbe.",
                legalBasis: 'cl. 184. ZKP, cl. 9. ZKP',
                echrBasis: null,
                evidence: ['witness_count' => $uniqueWitnesses],
                recommendedAction: "Usporediti sa svjedockom listom optuznice.",
                confidence: 0.5,
            );
        }

        return $flags;
    }
}
