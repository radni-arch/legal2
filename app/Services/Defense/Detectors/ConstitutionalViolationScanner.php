<?php

namespace App\Services\Defense\Detectors;

use App\DTOs\Defense\DefenseFlag;
use App\Services\Analysis\AI\ClaudeAnalysisService;
use App\Services\Defense\Contracts\DefenseTacticDetectorInterface;

/**
 * ConstitutionalViolationScanner - Tier C AI-Assisted Detector
 *
 * AI-powered scan for constitutional and ECHR violations.
 * Checks for known patterns:
 * - Dvorski pattern: lawyer blocked at police station
 * - Matanovic pattern: surveillance not disclosed to defense
 * - Kirincic pattern: excessive proceeding duration
 *
 * Uses Claude to analyze case timeline and key facts for violation patterns.
 */
class ConstitutionalViolationScanner implements DefenseTacticDetectorInterface
{
    public function __construct(
        private ClaudeAnalysisService $claude,
    ) {}

    public function tactic(): string
    {
        return 'constitutional_violation';
    }

    public function label(): string
    {
        return 'Ustavne povrede i ECHR (cl. 29. Ustav RH)';
    }

    public function requires(): array
    {
        return ['ai_key_facts', 'dates_with_context', 'ai_summary'];
    }

    /**
     * @return DefenseFlag[]
     */
    public function detect(string $caseId, array $analysisData): array
    {
        // Prepare context for AI analysis
        $context = $this->prepareContext($analysisData);

        if (empty($context)) {
            return [];
        }

        try {
            $violations = $this->analyzeWithAI($context);
            return $this->convertToFlags($violations);
        } catch (\Throwable $e) {
            // AI analysis failed - return empty (don't block detection)
            return [];
        }
    }

    /**
     * Prepare context from analysis data for AI analysis.
     */
    private function prepareContext(array $analysisData): string
    {
        $parts = [];

        // Add key facts
        $keyFacts = [];
        foreach ($analysisData['ai_key_facts'] ?? [] as $docId => $facts) {
            foreach ($facts['key_facts'] ?? [] as $fact) {
                $keyFacts[] = $fact['claim'] ?? '';
            }
        }
        if (!empty($keyFacts)) {
            $parts[] = "KLJUCNE CINJENICE:\n" . implode("\n", $keyFacts);
        }

        // Add timeline
        $timeline = [];
        foreach ($analysisData['dates_with_context'] ?? [] as $docId => $dateData) {
            foreach ($dateData['dates'] ?? [] as $d) {
                $date = $d['date'] ?? '';
                $type = $d['event_type'] ?? '';
                $context = $d['context'] ?? '';
                if ($date) {
                    $timeline[] = "{$date}: {$type}" . ($context ? " - {$context}" : '');
                }
            }
        }
        if (!empty($timeline)) {
            sort($timeline);
            $parts[] = "KRONOLOGIJA:\n" . implode("\n", $timeline);
        }

        return implode("\n\n", $parts);
    }

    /**
     * Use Claude to analyze for constitutional/ECHR violations.
     */
    private function analyzeWithAI(string $context): array
    {
        $systemPrompt = <<<PROMPT
Ti si strucnjak za ustavno pravo i Europski sud za ljudska prava (ECHR).
Analiziraj sljedece cinjenice slucaja i identificiraj moguce povrede Ustava RH i ECHR.

POZNATI OBRASCI POVREDA:
1. Dvorski obrazac (Dvorski v. Croatia [GC] 2015): Okrivljeniku nije omogucen branitelj tijekom ispitivanja na policiji
2. Matanovic obrazac (Matanovic v. Croatia 2017): Posebne dokazne radnje (prisluskivanje, tajno pracenje) nisu otkrivene obrani
3. Kirincic obrazac (Kirincic i dr. v. Hrvatske 2020): Prekomjerno trajanje postupka (>10 godina)
4. Zadnja rijec: Okrivljeniku nije dana mogucnost zadnje rijeci (cl. 448. ZKP)
5. Suocenje: Uskraceno suocenje sa svjedocima optuzbe (cl. 6. st. 3. d. ECHR)
6. Jednakost oruzja: Neravnopravnost stranaka u postupku

Za svaku identificiranu povredu navedi:
- pattern: Ime obrasca
- description: Opis povrede
- constitutional_basis: Clanak Ustava RH
- echr_basis: ECHR presuda ako postoji
- severity: critical/high/medium/low
- confidence: 0.0-1.0

Odgovori u JSON formatu:
{"violations": [...]}

Ako nema povreda, vrati: {"violations": []}
PROMPT;

        $result = $this->claude->analyzeJson($systemPrompt, $context);

        return $result['parsed']['violations'] ?? [];
    }

    /**
     * Convert AI-detected violations to DefenseFlags.
     *
     * @return DefenseFlag[]
     */
    private function convertToFlags(array $violations): array
    {
        $flags = [];

        foreach ($violations as $violation) {
            $severity = $this->mapSeverity($violation['severity'] ?? 'medium');

            $flags[] = new DefenseFlag(
                tactic: 'constitutional_violation',
                severity: $severity,
                title: $violation['pattern'] ?? 'Ustavna povreda',
                description: $violation['description'] ?? 'AI je identificirao mogucu povredu.',
                legalBasis: $violation['constitutional_basis'] ?? 'Ustav RH',
                echrBasis: $violation['echr_basis'] ?? null,
                evidence: [
                    'pattern' => $violation['pattern'] ?? null,
                    'ai_detected' => true,
                ],
                recommendedAction: $this->generateRecommendation($violation),
                confidence: (float) ($violation['confidence'] ?? 0.5),
            );
        }

        return $flags;
    }

    /**
     * Map AI severity string to DefenseFlag severity constant.
     */
    private function mapSeverity(string $aiSeverity): string
    {
        return match (mb_strtolower($aiSeverity)) {
            'critical' => DefenseFlag::SEVERITY_CRITICAL,
            'high' => DefenseFlag::SEVERITY_HIGH,
            'medium' => DefenseFlag::SEVERITY_MEDIUM,
            'low' => DefenseFlag::SEVERITY_LOW,
            default => DefenseFlag::SEVERITY_INFO,
        };
    }

    /**
     * Generate recommended action based on violation pattern.
     */
    private function generateRecommendation(array $violation): string
    {
        $pattern = $violation['pattern'] ?? '';

        return match (true) {
            str_contains($pattern, 'Dvorski') => "Istaknuti povredu prava na branitelja (cl. 29. st. 2. t. 3. Ustav RH). "
                . "Zahtijevati izdvajanje iskaza danih bez branitelja. "
                . "Pozvati se na Dvorski v. Croatia [GC] (2015).",

            str_contains($pattern, 'Matanovic') => "Zahtijevati uvid u sve posebne dokazne radnje. "
                . "Istaknuti povredu prava na obranu ako mjere nisu bile otkrivene. "
                . "Pozvati se na Matanovic v. Croatia (2017).",

            str_contains($pattern, 'Kirincic') => "Podnijeti zahtjev za zastitu prava na sudenje u razumnom roku "
                . "(cl. 63.-70. Zakona o sudovima). Razmotriti ustavnu tuzbu i prijavu ECHR. "
                . "Pozvati se na Kirincic i dr. v. Hrvatske (2020).",

            default => "Razmotriti ustavnu tuzbu i/ili prijavu Europskom sudu za ljudska prava. "
                . "Dokumentirati povredu i prikupiti dokaze za zalbeni postupak.",
        };
    }
}
