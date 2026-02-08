<?php

namespace App\Services\Defense\Detectors;

use App\DTOs\Defense\DefenseFlag;
use App\Services\Analysis\AI\ClaudeAnalysisService;
use App\Services\Defense\Contracts\DefenseTacticDetectorInterface;

/**
 * JudicialBiasDetector - Tier C AI-Assisted Detector
 *
 * Two-phase detection:
 * 1. Statistical: Count defense motion denial rate from key_facts
 * 2. AI text similarity: Compare prosecution vs court ruling text
 *
 * Flags if denial rate >= 85% or text similarity >= 70%
 */
class JudicialBiasDetector implements DefenseTacticDetectorInterface
{
    private const DENIAL_RATE_THRESHOLD = 85;
    private const SIMILARITY_THRESHOLD_HIGH = 85;
    private const SIMILARITY_THRESHOLD_MEDIUM = 70;
    private const MINIMUM_MOTIONS_FOR_STATISTICS = 3;

    public function __construct(
        private ClaudeAnalysisService $claude,
    ) {}

    public function tactic(): string
    {
        return 'judicial_bias';
    }

    public function label(): string
    {
        return 'Nepristranost suca (cl. 32. ZKP)';
    }

    public function requires(): array
    {
        return ['entities', 'ai_summary', 'ai_key_facts'];
    }

    /**
     * @return DefenseFlag[]
     */
    public function detect(string $caseId, array $analysisData): array
    {
        $flags = [];

        // Phase 1: Statistical - defense motion denial rate
        $statisticalFlags = $this->detectStatisticalBias($analysisData);
        $flags = array_merge($flags, $statisticalFlags);

        // Phase 2: AI text similarity - prosecution vs court ruling
        $similarityFlags = $this->detectTextSimilarity($analysisData);
        $flags = array_merge($flags, $similarityFlags);

        return $flags;
    }

    /**
     * Phase 1: Statistical analysis of defense motion denial rate.
     * Counts "odbijen"/"usvojen" patterns in key_facts for defense proposals.
     *
     * @return DefenseFlag[]
     */
    private function detectStatisticalBias(array $analysisData): array
    {
        $denials = 0;
        $approvals = 0;

        foreach ($analysisData['ai_key_facts'] ?? [] as $docId => $facts) {
            foreach ($facts['key_facts'] ?? [] as $fact) {
                $text = mb_strtolower($fact['claim'] ?? '');

                // Check if this is a defense motion (prijedlog obrane/branitelja/okrivljenika)
                if (preg_match('/prijedlog\s+(obrane|branitelja|okrivljenika)/u', $text)) {
                    if (preg_match('/odbij[ae]/u', $text)) {
                        $denials++;
                    }
                    if (preg_match('/usvoj[ae]/u', $text)) {
                        $approvals++;
                    }
                }
            }
        }

        $total = $denials + $approvals;

        // Require minimum motions for statistical significance
        if ($total < self::MINIMUM_MOTIONS_FOR_STATISTICS) {
            return [];
        }

        $denialRate = round(($denials / $total) * 100);

        if ($denialRate >= self::DENIAL_RATE_THRESHOLD) {
            return [
                new DefenseFlag(
                    tactic: 'judicial_bias',
                    severity: DefenseFlag::SEVERITY_MEDIUM,
                    title: "Odbijeno {$denialRate}% prijedloga obrane ({$denials}/{$total})",
                    description: "Od {$total} identificiranih prijedloga obrane, sud je odbio {$denials} "
                        . "({$denialRate}%). Ovaj omjer moze ukazivati na pristranost.",
                    legalBasis: 'cl. 32. st. 2. ZKP - izuzece suca',
                    echrBasis: 'Piersack v. Belgium (1982), Meznaric v. Hrvatske (2005)',
                    evidence: [
                        'denials' => $denials,
                        'approvals' => $approvals,
                        'total' => $total,
                        'denial_rate' => $denialRate,
                    ],
                    recommendedAction: "Razmotriti zahtjev za izuzece suca (cl. 34. ZKP). "
                        . "Dokumentirati svaki odbijeni prijedlog s obrazlozenjem. "
                        . "ECHR test: 'objectively justified doubts about impartiality'.",
                    confidence: 0.6,
                ),
            ];
        }

        return [];
    }

    /**
     * Phase 2: AI-powered text similarity analysis.
     * Compares prosecution submission reasoning against court ruling reasoning.
     *
     * @return DefenseFlag[]
     */
    private function detectTextSimilarity(array $analysisData): array
    {
        $prosecutionTexts = [];
        $courtRulingTexts = [];

        // Collect texts by document type
        foreach ($analysisData['ai_summary'] ?? [] as $docId => $summary) {
            $docType = $summary['document_type'] ?? '';

            if (in_array($docType, ['optuznica', 'prijedlog_tuzilastva', 'kaznena_prijava'])) {
                $prosecutionTexts[$docId] = $summary['summary'] ?? '';
            }

            if (in_array($docType, ['presuda', 'rjesenje'])) {
                $courtRulingTexts[$docId] = $summary['summary'] ?? '';
            }
        }

        // Need both prosecution and court texts to compare
        if (empty($prosecutionTexts) || empty($courtRulingTexts)) {
            return [];
        }

        try {
            // Build prompt for Claude
            $systemPrompt = "Ti si pravni analiticar. Usporedi obrazlozenja tuzilastva i suda "
                . "i ocijeni koliko je sud kopirao argumentaciju tuzilastva vs. proveo vlastitu analizu.";

            $userContent = "TUZILASTVO:\n" . implode("\n---\n", $prosecutionTexts)
                . "\n\nSUD:\n" . implode("\n---\n", $courtRulingTexts)
                . "\n\nOdgovori u JSON formatu: "
                . '{"similarity_percent": N, "copied_sections": ["..."], "independent_reasoning": ["..."]}';

            $result = $this->claude->analyzeJson($systemPrompt, $userContent);
            $parsed = $result['parsed'] ?? [];

            $similarity = $parsed['similarity_percent'] ?? 0;

            if ($similarity >= self::SIMILARITY_THRESHOLD_MEDIUM) {
                $severity = $similarity >= self::SIMILARITY_THRESHOLD_HIGH
                    ? DefenseFlag::SEVERITY_HIGH
                    : DefenseFlag::SEVERITY_MEDIUM;

                return [
                    new DefenseFlag(
                        tactic: 'judicial_bias',
                        severity: $severity,
                        title: "Obrazlozenje suda {$similarity}% slicno tuzilastvu",
                        description: "AI analiza pokazuje {$similarity}% slicnost izmedu obrazlozenja "
                            . "tuzilastva i sudskog obrazlozenja. Cl. 468. ZKP zahtijeva da presuda "
                            . "ima odredeno (jasno) i potpuno (kompletno) obrazlozenje.",
                        legalBasis: 'cl. 468. ZKP, cl. 32. ZKP',
                        echrBasis: 'Garcia Ruiz v. Spain [GC] (1999)',
                        evidence: [
                            'similarity_percent' => $similarity,
                            'copied_sections' => $parsed['copied_sections'] ?? [],
                            'independent_reasoning' => $parsed['independent_reasoning'] ?? [],
                        ],
                        recommendedAction: "Istaknuti bitnu povredu odredaba kaznenog postupka u zalbi "
                            . "(cl. 468. st. 1. t. 11. ZKP - presuda nema razloga ili razlozi nisu jasni). "
                            . "Sud je duzan provesti vlastitu ocjenu dokaza, a ne preuzeti "
                            . "argumentaciju tuzilastva.",
                        confidence: 0.7,
                    ),
                ];
            }
        } catch (\Throwable $e) {
            // AI comparison failed - log but don't block
            // In production, this should be logged properly
        }

        return [];
    }
}
