<?php

namespace App\Services\Defense\Detectors;

use App\DTOs\Defense\DefenseFlag;
use App\Services\Defense\Contracts\DefenseTacticDetectorInterface;

class NeBisInIdemDetector implements DefenseTacticDetectorInterface
{
    /**
     * Prekrsajni case type prefixes that indicate minor offense proceedings.
     */
    private const PREKRSAJNI_TYPES = ['Pp Prz', 'Pp J', 'Pn'];

    public function tactic(): string
    {
        return 'ne_bis_in_idem';
    }

    public function label(): string
    {
        return 'Ne bis in idem';
    }

    public function requires(): array
    {
        return ['case_hierarchy', 'case_references', 'dates_with_context', 'entities'];
    }

    public function detect(string $caseId, array $analysisData): array
    {
        $flags = [];
        $hierarchy = $analysisData['case_hierarchy'] ?? [];

        if (empty($hierarchy)) {
            return $flags;
        }

        // Find all prekrsajni satellite cases
        $prekrsajniSatellites = array_filter(
            $hierarchy,
            function ($h) {
                $satelliteType = is_object($h)
                    ? ($h->satellite_case_type ?? '')
                    : ($h['satellite_case_type'] ?? '');

                return in_array($satelliteType, self::PREKRSAJNI_TYPES);
            }
        );

        if (empty($prekrsajniSatellites)) {
            return $flags;
        }

        foreach ($prekrsajniSatellites as $satellite) {
            $satCase = is_object($satellite)
                ? ($satellite->satellite_case_number ?? '')
                : ($satellite['satellite_case_number'] ?? '');
            $mainCase = is_object($satellite)
                ? ($satellite->main_case_number ?? '')
                : ($satellite['main_case_number'] ?? '');
            $relationship = is_object($satellite)
                ? ($satellite->relationship ?? '')
                : ($satellite['relationship'] ?? '');
            $evidence = is_object($satellite)
                ? ($satellite->evidence ?? [])
                : ($satellite['evidence'] ?? []);

            // If the prekrsajni case exists alongside a kazneni case
            // for the same defendant (same OIB / name), flag ne bis in idem
            $flags[] = new DefenseFlag(
                tactic: 'ne_bis_in_idem',
                severity: DefenseFlag::SEVERITY_HIGH,
                title: "Ne bis in idem: {$satCase} <-> {$mainCase}",
                description: "Prekrsajni predmet {$satCase} ({$relationship}) vodi se "
                    . "paralelno s kaznenim predmetom {$mainCase}. "
                    . 'Ako se cinjenicni opisi podudaraju, moguca je povreda nacela ne bis in idem '
                    . '(Maresti v. Hrvatske, 2009). '
                    . 'Prema cl. 10. Prekrsajnog zakona, pokretanje kaznenog postupka '
                    . 'za djelo koje obuhvaca prekrsaj iskljucuje prekrsajni progon.',
                legalBasis: 'cl. 31. Ustav RH, cl. 12. ZKP, cl. 10. Prekrsajni zakon',
                echrBasis: 'Maresti v. Hrvatske (2009), Zolotukhin v. Russia [GC] (2009)',
                evidence: [
                    'prekrsajni_case' => $satCase,
                    'kazneni_case' => $mainCase,
                    'relationship' => $relationship,
                    'co_occurring_documents' => $evidence,
                ],
                recommendedAction: "1. Pribaviti pravomoćnu odluku prekrsajnog suda u predmetu {$satCase}.\n"
                    . "2. Usporediti cinjenicni opis prekrsaja s cinjenicnim opisom optuznice.\n"
                    . "3. Ako se radi o istim cinjenicama (Zolotukhin test: 'identical facts or facts "
                    . "which are substantially the same'), istaknuti prigovor ne bis in idem.\n"
                    . '4. Pozvati se na VSRH Kzz 7/11-3, VSRH III Kr 214/09-9, VSRH I Kz 403/10-5.',
                confidence: 0.75, // Needs manual verification of cinjenicni opis
            );
        }

        return $flags;
    }
}
