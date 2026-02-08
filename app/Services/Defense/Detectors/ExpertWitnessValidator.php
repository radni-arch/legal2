<?php

namespace App\Services\Defense\Detectors;

use App\DTOs\Defense\DefenseFlag;
use App\Services\Defense\Contracts\DefenseTacticDetectorInterface;

/**
 * ExpertWitnessValidator - Tier C Detector
 *
 * Validates expert witness credentials and methodology:
 * - Cross-references against known accredited labs
 * - Checks for ISO 17025 methodology mentions
 * - Flags missing: method description, measurement uncertainty, accreditation
 */
class ExpertWitnessValidator implements DefenseTacticDetectorInterface
{
    /**
     * Known accredited forensic labs in Croatia.
     */
    private const ACCREDITED_LABS = [
        'centar za forenzicna ispitivanja ivan vucetic',
        'cfi ivan vucetic',
        'ivan vucetic',
        'hrvatski zavod za javno zdravstvo',
        'hzjz',
        'institut ruder boskovic',
        'irb',
    ];

    /**
     * Quality indicators that should be present in expert reports.
     */
    private const QUALITY_INDICATORS = [
        'methodology' => [
            'metod',
            'gc-ms',
            'hplc',
            'spektrometrij',
            'kromatografij',
            'analiz',
            'postupak',
        ],
        'accreditation' => [
            'iso 17025',
            'iso17025',
            'akreditacij',
            'akreditiran',
            'certificiran',
        ],
        'uncertainty' => [
            '+/-',
            '+-',
            'nesigurnost',
            'uncertainty',
            'interval pouzdanosti',
            'mjerna nesigurnost',
        ],
    ];

    public function tactic(): string
    {
        return 'expert_witness';
    }

    public function label(): string
    {
        return 'Vjestak - valjanost nalaza (cl. 317. ZKP)';
    }

    public function requires(): array
    {
        return ['entities', 'ai_summary'];
    }

    /**
     * @return DefenseFlag[]
     */
    public function detect(string $caseId, array $analysisData): array
    {
        $flags = [];

        // Find expert reports in the analysis data
        $expertReports = $this->findExpertReports($analysisData);

        if (empty($expertReports)) {
            return [];
        }

        foreach ($expertReports as $docId => $report) {
            $reportFlags = $this->validateReport($docId, $report, $analysisData);
            $flags = array_merge($flags, $reportFlags);
        }

        return $flags;
    }

    /**
     * Find documents that are expert reports.
     */
    private function findExpertReports(array $analysisData): array
    {
        $reports = [];

        foreach ($analysisData['ai_summary'] ?? [] as $docId => $summary) {
            $docType = $summary['document_type'] ?? '';

            if (in_array($docType, ['nalaz_vjestaka', 'vjestacenje', 'strucni_nalaz'])) {
                $reports[$docId] = $summary;
            }
        }

        // Also check entities for expert mentions if no explicit expert reports found
        if (empty($reports)) {
            foreach ($analysisData['entities'] ?? [] as $docId => $entities) {
                foreach ($entities['persons'] ?? [] as $person) {
                    if (preg_match('/vjestak/ui', $person)) {
                        // Check if we have a summary for this doc
                        if (isset($analysisData['ai_summary'][$docId])) {
                            $reports[$docId] = $analysisData['ai_summary'][$docId];
                        }
                    }
                }
            }
        }

        return $reports;
    }

    /**
     * Validate a single expert report for quality indicators.
     *
     * @return DefenseFlag[]
     */
    private function validateReport(string $docId, array $summary, array $analysisData): array
    {
        $flags = [];
        $reportText = mb_strtolower($summary['summary'] ?? '');
        $entities = $analysisData['entities'][$docId] ?? [];

        // Check institution accreditation
        $institutions = $entities['institutions'] ?? [];
        $isAccreditedLab = $this->checkAccreditedLab($institutions);
        $hasAccreditationMention = $this->checkQualityIndicator($reportText, 'accreditation');

        if (!$isAccreditedLab && !$hasAccreditationMention) {
            $flags[] = new DefenseFlag(
                tactic: 'expert_witness',
                severity: DefenseFlag::SEVERITY_LOW,
                title: 'Akreditacija laboratorija nije potvrdena',
                description: "Nalaz vjestaka ne navodi akreditaciju laboratorija prema ISO 17025 "
                    . "ili drugom medunarodno priznatom standardu. Institucija nije prepoznata "
                    . "kao poznati akreditirani laboratorij. Potrebno provjeriti akreditacijski status.",
                legalBasis: 'cl. 317. ZKP, cl. 308. ZKP',
                echrBasis: null,
                evidence: [
                    'document_id' => $docId,
                    'institutions_found' => $institutions,
                    'known_accredited' => false,
                ],
                recommendedAction: "Zatraziti dokaz o akreditaciji laboratorija (ISO 17025 certifikat). "
                    . "Ako laboratorij nije akreditiran, osporiti pouzdanost rezultata vjestacenja.",
                confidence: 0.5,
            );
        }

        // Check methodology description
        $hasMethodology = $this->checkQualityIndicator($reportText, 'methodology');

        if (!$hasMethodology) {
            $flags[] = new DefenseFlag(
                tactic: 'expert_witness',
                severity: DefenseFlag::SEVERITY_MEDIUM,
                title: 'Metoda analize nije opisana',
                description: "Nalaz vjestaka ne sadrzi opis koristene analiticke metode. "
                    . "Bez opisa metode, nije moguce ocijeniti pouzdanost rezultata "
                    . "niti mogucnost pogreske.",
                legalBasis: 'cl. 317. st. 2. ZKP - nalaz mora sadrzavati opis postupka',
                echrBasis: null,
                evidence: [
                    'document_id' => $docId,
                    'methodology_found' => false,
                ],
                recommendedAction: "Zatraziti dopunu nalaza s opisom metode. "
                    . "Postaviti pitanja o validaciji metode, granicama detekcije "
                    . "i mogucim interferencijama.",
                confidence: 0.6,
            );
        }

        // Check measurement uncertainty
        $hasUncertainty = $this->checkQualityIndicator($reportText, 'uncertainty');

        if (!$hasUncertainty && $hasMethodology) {
            // Only flag if methodology exists but uncertainty doesn't
            $flags[] = new DefenseFlag(
                tactic: 'expert_witness',
                severity: DefenseFlag::SEVERITY_LOW,
                title: 'Mjerna nesigurnost nije navedena',
                description: "Kvantitativni rezultati u nalazu vjestaka ne navode mjernu nesigurnost. "
                    . "Prema ISO 17025 i dobroj laboratorijskoj praksi, svaki kvantitativni rezultat "
                    . "mora ukljucivati procjenu nesigurnosti mjerenja.",
                legalBasis: 'cl. 317. ZKP, ISO 17025',
                echrBasis: null,
                evidence: [
                    'document_id' => $docId,
                    'uncertainty_found' => false,
                ],
                recommendedAction: "Zatraziti informaciju o mjernoj nesigurnosti rezultata. "
                    . "Nesigurnost moze utjecati na tumacenje granicnih vrijednosti "
                    . "(npr. je li koncentracija iznad ili ispod praga).",
                confidence: 0.5,
            );
        }

        return $flags;
    }

    /**
     * Check if any institution is a known accredited lab.
     */
    private function checkAccreditedLab(array $institutions): bool
    {
        foreach ($institutions as $institution) {
            $normalized = mb_strtolower($institution);
            foreach (self::ACCREDITED_LABS as $accreditedLab) {
                if (str_contains($normalized, $accreditedLab)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Check if text contains quality indicators of a specific type.
     */
    private function checkQualityIndicator(string $text, string $indicatorType): bool
    {
        $indicators = self::QUALITY_INDICATORS[$indicatorType] ?? [];

        foreach ($indicators as $indicator) {
            if (str_contains($text, $indicator)) {
                return true;
            }
        }

        return false;
    }
}
