<?php

namespace App\Services\Ocr;

use Illuminate\Support\Facades\Log;

/**
 * DocumentOcrRouter
 *
 * Analyzes document characteristics and recommends the optimal OCR engine
 * using a weighted scoring system.
 *
 * ## Routing Decision Tree
 *
 * The router uses a weighted scoring model:
 * - Positive scores favor Tesseract (better for Croatian diacritics)
 * - Negative scores favor Textract (better for structured content, tables)
 * - Score = 0 defaults to Textract
 *
 * ## Signal Weights (configurable via ocr.routing config)
 *
 * 1. **Textract Confidence** (strongest signal):
 *    - Very low (< 70%): +3.0 toward Tesseract
 *    - Moderate (70-85%): +1.0 toward Tesseract
 *    - High (>= 85%): -2.0 toward Textract
 *
 * 2. **Croatian Content** (medium signal):
 *    - Above threshold (>= 60% score): +1.5 toward Tesseract
 *
 * 3. **Structured Content** (tables/forms):
 *    - Present: -1.0 toward Textract
 *
 * ## Conflict Resolution
 *
 * When signals conflict, explicit precedence rules resolve them:
 *
 * **Priority 1 - Very low confidence always wins**: Regardless of other signals,
 * very low Textract confidence (+3.0) overrides tables (-1.0) and routes to Tesseract.
 *
 * **Priority 2 - Croatian content attenuates high confidence**: Textract's confidence
 * metric measures structural recognition, NOT diacritic accuracy. When Croatian content
 * is detected (score >= threshold), the high-confidence signal is attenuated by +1.0
 * because Textract lacks a Croatian language model. This means:
 * - Croatian text + High confidence (no tables) = Tesseract wins (diacritics matter)
 * - Croatian text + High confidence + Tables = Textract wins (tables tip balance)
 * - Croatian text + Moderate confidence = Tesseract wins (both signals align)
 *
 * **Priority 3 - Default tie-breaking**: Score = 0 defaults to Textract.
 *
 * **Unchanged for non-Croatian**: English text routing is unaffected by the
 * Croatian adjustment. High confidence + tables = Textract, low confidence = Tesseract.
 *
 * @see config/ocr.php for configurable thresholds
 */
class DocumentOcrRouter
{
    /** Croatian-specific words and patterns for detection */
    private const CROATIAN_INDICATORS = [
        // Legal terms
        'presuda', 'rješenje', 'zahtjev', 'žalba', 'optužnica', 'ugovor',
        'punomoć', 'izjava', 'potvrda', 'zakon', 'pravilnik', 'tužitelj',
        'tuženik', 'odvjetnik', 'sud', 'sudac', 'rasprava', 'spis',
        'predmet', 'članak', 'stavak', 'točka', 'alineja', 'odluka',
        // Common Croatian words
        'republika', 'hrvatska', 'općinski', 'županijski', 'vrhovni',
        'trgovački', 'upravni', 'ustavni', 'kazneni', 'građanski',
        'narodne', 'novine', 'objavljen', 'stupiti', 'snagu',
    ];

    /** Croatian diacritic characters */
    private const CROATIAN_DIACRITICS = ['č', 'ć', 'ž', 'š', 'đ', 'Č', 'Ć', 'Ž', 'Š', 'Đ'];

    /**
     * Recommend which OCR engine to use.
     *
     * Uses weighted scoring to resolve conflicts between signals:
     * - Positive scores favor Tesseract (better Croatian diacritics)
     * - Negative scores favor Textract (better structured content)
     * - Score = 0 defaults to Textract
     *
     * @param string $text Extracted text (from initial Textract pass or pdftotext)
     * @param array $blocks Textract response blocks (if available)
     * @return array{engine: string, reasons: array, confidence: float, analysis: array, routing_score: float, signal_breakdown: array, decision_trace: array}
     */
    public function recommend(string $text, array $blocks = []): array
    {
        $decisionTrace = [];
        $decisionTrace[] = sprintf('[%s] Starting OCR routing decision', date('H:i:s.u'));

        $forcedEngine = config('ocr.default_engine', 'auto');
        if ($forcedEngine !== 'auto') {
            $decisionTrace[] = sprintf('Config forces engine: %s', $forcedEngine);
            return [
                'engine' => $forcedEngine,
                'reasons' => ['forced_by_config'],
                'confidence' => 1.0,
                'analysis' => [],
                'routing_score' => 0.0,
                'signal_breakdown' => [
                    'confidence_signal' => 0.0,
                    'croatian_signal' => 0.0,
                    'structured_signal' => 0.0,
                ],
                'decision_trace' => $decisionTrace,
            ];
        }

        $textAnalysis = $this->analyzeDocument($text);
        $blockAnalysis = ! empty($blocks) ? $this->analyzeTextractBlocks($blocks) : [
            'has_structured_content' => false,
            'table_count' => 0,
            'form_count' => 0,
            'avg_confidence' => 0.0,
        ];

        $decisionTrace[] = sprintf(
            'Text analysis: croatian_score=%.4f, word_count=%d',
            $textAnalysis['croatian_score'],
            $textAnalysis['word_count']
        );
        $decisionTrace[] = sprintf(
            'Block analysis: avg_confidence=%.4f, tables=%d, forms=%d',
            $blockAnalysis['avg_confidence'],
            $blockAnalysis['table_count'],
            $blockAnalysis['form_count']
        );

        // Weighted scoring: positive = favors Tesseract, negative = favors Textract
        $score = 0.0;
        $reasons = [];

        // Signal breakdown for debugging
        $signalBreakdown = [
            'confidence_signal' => 0.0,
            'croatian_signal' => 0.0,
            'structured_signal' => 0.0,
        ];

        $routingConfig = config('ocr.routing', []);
        $fallbackThreshold = (float) ($routingConfig['fallback_confidence_threshold'] ?? 0.70);
        $textractMinConf = (float) ($routingConfig['textract_confidence_threshold'] ?? 0.85);
        $croatianThreshold = (float) ($routingConfig['croatian_text_threshold'] ?? 0.6);

        // Signal 1: Textract confidence (strongest signal)
        // Weight rationale: Confidence is the most reliable indicator of OCR quality
        $avgConf = $blockAnalysis['avg_confidence'];
        if ($avgConf > 0 && $avgConf < $fallbackThreshold) {
            $signalBreakdown['confidence_signal'] = 3.0;
            $score += 3.0;  // Strong push toward tesseract
            $reasons[] = sprintf('very_low_textract_confidence:%.2f', $avgConf);
            $decisionTrace[] = sprintf(
                'Signal 1: Very low confidence (%.2f < %.2f) -> +3.0 toward Tesseract',
                $avgConf, $fallbackThreshold
            );
        } elseif ($avgConf > 0 && $avgConf < $textractMinConf) {
            $signalBreakdown['confidence_signal'] = 1.0;
            $score += 1.0;  // Moderate push toward tesseract
            $reasons[] = sprintf('moderate_textract_confidence:%.2f', $avgConf);
            $decisionTrace[] = sprintf(
                'Signal 1: Moderate confidence (%.2f < %.2f) -> +1.0 toward Tesseract',
                $avgConf, $textractMinConf
            );
        } elseif ($avgConf >= $textractMinConf) {
            $signalBreakdown['confidence_signal'] = -2.0;
            $score -= 2.0;  // Push toward textract
            $reasons[] = sprintf('high_textract_confidence:%.2f', $avgConf);
            $decisionTrace[] = sprintf(
                'Signal 1: High confidence (%.2f >= %.2f) -> -2.0 toward Textract',
                $avgConf, $textractMinConf
            );
        } else {
            $decisionTrace[] = 'Signal 1: No confidence data available -> 0.0';
        }

        // Signal 2: Croatian content
        // Weight rationale: Croatian diacritics benefit from Tesseract's hrv model
        if ($textAnalysis['has_croatian_content'] && $textAnalysis['croatian_score'] >= $croatianThreshold) {
            $signalBreakdown['croatian_signal'] = 1.5;
            $score += 1.5;
            $reasons[] = sprintf('croatian_content:%.2f', $textAnalysis['croatian_score']);
            $decisionTrace[] = sprintf(
                'Signal 2: Croatian content detected (%.2f >= %.2f) -> +1.5 toward Tesseract',
                $textAnalysis['croatian_score'], $croatianThreshold
            );
        } else {
            $decisionTrace[] = sprintf(
                'Signal 2: No significant Croatian content (%.2f < %.2f) -> 0.0',
                $textAnalysis['croatian_score'], $croatianThreshold
            );
        }

        // Signal 3: Structured content (tables/forms favor Textract)
        // Weight rationale: Textract excels at table/form extraction, but confidence is more important
        if ($blockAnalysis['has_structured_content']) {
            $signalBreakdown['structured_signal'] = -1.0;
            $score -= 1.0;  // Moderate push toward textract
            $reasons[] = sprintf('structured_content:tables=%d,forms=%d',
                $blockAnalysis['table_count'], $blockAnalysis['form_count']);
            $decisionTrace[] = sprintf(
                'Signal 3: Structured content (tables=%d, forms=%d) -> -1.0 toward Textract',
                $blockAnalysis['table_count'], $blockAnalysis['form_count']
            );
        } else {
            $decisionTrace[] = 'Signal 3: No structured content detected -> 0.0';
        }

        // ============================================================
        // Conflict Resolution: Croatian content attenuates high-confidence signal
        // ============================================================
        // Textract's confidence metric measures structural recognition (layout,
        // bounding boxes, character shapes) but NOT diacritic accuracy. Croatian
        // documents may score high structural confidence while systematically
        // mishandling diacritics (e.g., dropping accents from č, ć, ž, š, đ).
        //
        // When Croatian content is above threshold AND Textract reports high
        // confidence, we attenuate the confidence signal by +1.0 to account
        // for the fact that confidence does not reflect diacritic quality.
        //
        // This ensures:
        // - Croatian + high conf (no tables): -2.0 +1.0 +1.5 = +0.5 -> Tesseract
        // - Croatian + high conf + tables:    -2.0 +1.0 +1.5 -1.0 = -0.5 -> Textract
        // - English + high conf:              -2.0 = -2.0 -> Textract (no adjustment)
        $croatianSignalFired = $signalBreakdown['croatian_signal'] > 0;
        $highConfidenceSignalFired = $signalBreakdown['confidence_signal'] === -2.0;

        if ($croatianSignalFired && $highConfidenceSignalFired) {
            $adjustment = 1.0;
            $signalBreakdown['confidence_signal'] += $adjustment;
            $signalBreakdown['croatian_confidence_adjustment'] = $adjustment;
            $score += $adjustment;
            $reasons[] = 'croatian_confidence_adjusted';
            $decisionTrace[] = sprintf(
                'Conflict resolution: Croatian content (score=%.2f) attenuates high-confidence '
                . 'signal by +%.1f (Textract confidence does not measure diacritic accuracy)',
                $textAnalysis['croatian_score'], $adjustment
            );
        }

        // Decision
        $engine = $score > 0 ? 'tesseract' : 'textract';

        // Determine the reason when no signals contributed
        if (empty($reasons)) {
            $reasons[] = 'no_signals';
            $decisionTrace[] = 'No signals contributed -> defaulting to Textract';
        }

        // Add explanation when score is exactly zero
        if ($score === 0.0 && ! in_array('no_signals', $reasons)) {
            $reasons[] = 'neutral_score';
            $decisionTrace[] = 'Score equals zero (signals balanced) -> defaulting to Textract';
        }

        $decisionTrace[] = sprintf(
            'Final decision: engine=%s, score=%.2f, reasons=[%s]',
            $engine, $score, implode(', ', $reasons)
        );

        Log::debug('DocumentOcrRouter: routing decision trace', [
            'engine' => $engine,
            'score' => $score,
            'signal_breakdown' => $signalBreakdown,
            'trace' => $decisionTrace,
        ]);

        Log::info('DocumentOcrRouter: recommendation', [
            'engine' => $engine,
            'score' => $score,
            'reasons' => $reasons,
            'croatian_score' => $textAnalysis['croatian_score'],
            'avg_confidence' => $blockAnalysis['avg_confidence'],
        ]);

        return [
            'engine' => $engine,
            'reasons' => $reasons,
            'confidence' => $this->calculateDecisionConfidence($textAnalysis, $blockAnalysis),
            'analysis' => array_merge($textAnalysis, $blockAnalysis),
            'routing_score' => round($score, 2),
            'signal_breakdown' => $signalBreakdown,
            'decision_trace' => $decisionTrace,
        ];
    }

    /**
     * Analyze document text for language and content characteristics.
     */
    public function analyzeDocument(string $text): array
    {
        $normalizedText = mb_strtolower(trim($text), 'UTF-8');
        $wordCount = count(preg_split('/\s+/u', $normalizedText, -1, PREG_SPLIT_NO_EMPTY));

        if ($wordCount === 0) {
            return [
                'croatian_score' => 0.0,
                'has_croatian_content' => false,
                'diacritic_density' => 0.0,
                'word_count' => 0,
                'indicator_matches' => 0,
            ];
        }

        // Count Croatian indicator word matches
        $indicatorMatches = 0;
        foreach (self::CROATIAN_INDICATORS as $indicator) {
            $indicatorMatches += mb_substr_count($normalizedText, $indicator);
        }

        // Calculate diacritic density
        $diacriticDensity = $this->calculateDiacriticDensity($text);

        // Croatian score: combination of indicator density and diacritic presence
        $indicatorDensity = min(1.0, $indicatorMatches / max(1, $wordCount) * 10);
        $croatianScore = ($indicatorDensity * 0.6) + ($diacriticDensity * 0.4);
        $croatianScore = min(1.0, $croatianScore);

        $threshold = (float) config('ocr.routing.croatian_text_threshold', 0.6);

        return [
            'croatian_score' => round($croatianScore, 4),
            'has_croatian_content' => $croatianScore >= $threshold,
            'diacritic_density' => round($diacriticDensity, 4),
            'word_count' => $wordCount,
            'indicator_matches' => $indicatorMatches,
        ];
    }

    /**
     * Analyze Textract blocks for structured content and confidence.
     */
    public function analyzeTextractBlocks(array $blocks): array
    {
        $tableCount = 0;
        $formCount = 0;
        $totalConfidence = 0.0;
        $wordCount = 0;

        foreach ($blocks as $block) {
            $type = $block['BlockType'] ?? '';
            $confidence = (float) ($block['Confidence'] ?? 0);

            match ($type) {
                'TABLE' => $tableCount++,
                'KEY_VALUE_SET' => $formCount++,
                'WORD' => (function () use (&$wordCount, &$totalConfidence, $confidence) {
                    $wordCount++;
                    $totalConfidence += $confidence / 100.0;
                })(),
                default => null,
            };
        }

        $avgConfidence = $wordCount > 0 ? $totalConfidence / $wordCount : 0.0;

        return [
            'has_structured_content' => ($tableCount + $formCount) > 0,
            'table_count' => $tableCount,
            'form_count' => $formCount,
            'avg_confidence' => round($avgConfidence, 4),
            'word_count' => $wordCount,
        ];
    }

    /**
     * Calculate density of Croatian diacritical characters in text.
     */
    public function calculateDiacriticDensity(string $text): float
    {
        $len = mb_strlen($text, 'UTF-8');
        if ($len === 0) {
            return 0.0;
        }

        $diacriticCount = 0;
        foreach (self::CROATIAN_DIACRITICS as $char) {
            $diacriticCount += mb_substr_count($text, $char);
        }

        // Normalize: expect roughly 5-10% diacritics in Croatian text
        return min(1.0, ($diacriticCount / $len) * 15);
    }

    /**
     * Calculate overall decision confidence.
     */
    private function calculateDecisionConfidence(array $textAnalysis, array $blockAnalysis): float
    {
        $signals = 0;
        $totalWeight = 0;

        if ($textAnalysis['croatian_score'] > 0) {
            $signals += $textAnalysis['croatian_score'] * 0.4;
            $totalWeight += 0.4;
        }

        if ($blockAnalysis['avg_confidence'] > 0) {
            $signals += $blockAnalysis['avg_confidence'] * 0.4;
            $totalWeight += 0.4;
        }

        if ($blockAnalysis['has_structured_content']) {
            $signals += 0.2;
            $totalWeight += 0.2;
        }

        return $totalWeight > 0 ? round($signals / $totalWeight, 4) : 0.5;
    }
}
