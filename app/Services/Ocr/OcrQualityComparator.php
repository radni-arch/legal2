<?php

namespace App\Services\Ocr;

/**
 * OcrQualityComparator
 *
 * Compares OCR output from Textract and Tesseract to determine which
 * produced higher quality text, especially for Croatian legal documents.
 * Evaluates diacritic preservation, text completeness, and readability.
 */
class OcrQualityComparator
{
    private const CROATIAN_DIACRITICS = ['č', 'ć', 'ž', 'š', 'đ', 'Č', 'Ć', 'Ž', 'Š', 'Đ'];

    /**
     * Map of Croatian diacritics to their ASCII equivalents.
     * Used for fair content comparison between texts with and without diacritics.
     */
    private const DIACRITIC_NORMALIZATION_MAP = [
        'č' => 'c', 'ć' => 'c',
        'ž' => 'z', 'š' => 's', 'đ' => 'dz',
        'Č' => 'C', 'Ć' => 'C',
        'Ž' => 'Z', 'Š' => 'S', 'Đ' => 'DZ',
    ];

    /**
     * Compare Textract output vs Tesseract output and pick the winner.
     *
     * @return array{winner: string, improvement_percent: float, textract_score: float, tesseract_score: float, reasons: array}
     */
    public function compare(string $textractText, string $tesseractText): array
    {
        $textractScore = $this->scoreText($textractText);
        $tesseractScore = $this->scoreText($tesseractText);

        $reasons = [];
        $minImprovement = (float) config('ocr.quality.min_improvement_percent', 5.0);

        // Diacritic comparison
        $diacTextract = $this->scoreDiacritics($textractText);
        $diacTesseract = $this->scoreDiacritics($tesseractText);
        if ($diacTesseract > $diacTextract) {
            $reasons[] = sprintf('better_diacritics:+%.1f%%', ($diacTesseract - $diacTextract) * 100);
        }

        // Content length (more complete extraction)
        $lenTextract = mb_strlen(trim($textractText), 'UTF-8');
        $lenTesseract = mb_strlen(trim($tesseractText), 'UTF-8');
        if ($lenTesseract > $lenTextract * 1.1) {
            $reasons[] = 'more_content';
        }

        $improvementPercent = $textractScore > 0
            ? (($tesseractScore - $textractScore) / $textractScore) * 100
            : ($tesseractScore > 0 ? 100.0 : 0.0);

        $winner = $improvementPercent >= $minImprovement ? 'tesseract' : 'textract';

        return [
            'winner' => $winner,
            'improvement_percent' => round($improvementPercent, 2),
            'textract_score' => round($textractScore, 4),
            'tesseract_score' => round($tesseractScore, 4),
            'reasons' => $reasons,
        ];
    }

    /**
     * Score text quality on a 0-1 scale.
     *
     * Weights: valid word ratio (0.4), diacritic score (0.3),
     * completeness (0.15), line quality (0.15).
     */
    private function scoreText(string $text): float
    {
        $text = trim($text);
        $len = mb_strlen($text, 'UTF-8');

        if ($len === 0) {
            return 0.0;
        }

        $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $wordCount = count($words);

        // Factor 1: Valid word ratio
        $validWords = 0;
        foreach ($words as $word) {
            $wLen = mb_strlen($word, 'UTF-8');
            if ($wLen >= 2 && $wLen <= 45) {
                $alphaCount = mb_strlen(preg_replace('/[^a-zA-ZčćžšđČĆŽŠĐ]/u', '', $word), 'UTF-8');
                if ($wLen > 0 && ($alphaCount / $wLen) > 0.6) {
                    $validWords++;
                }
            }
        }
        $validWordRatio = $wordCount > 0 ? $validWords / $wordCount : 0.0;

        // Factor 2: Diacritic presence (important for Croatian)
        $diacriticScore = $this->scoreDiacritics($text);

        // Factor 3: Content completeness (normalized by expected document length)
        $completeness = min(1.0, $wordCount / 100);

        // Factor 4: Readability (line structure quality)
        $lineBreaks = substr_count($text, "\n");
        $avgCharsPerLine = $lineBreaks > 0 ? $len / $lineBreaks : $len;
        $lineQuality = min(1.0, $avgCharsPerLine / 40);

        return ($validWordRatio * 0.4) + ($diacriticScore * 0.3) + ($completeness * 0.15) + ($lineQuality * 0.15);
    }

    /**
     * Score diacritic preservation quality.
     *
     * Returns a 0-1 score based on the density of Croatian diacritic
     * characters in the text. Croatian text typically has 3-8% diacritics.
     *
     * The scoring uses an exponential approach that:
     * - Starts at 0.0 for 0% diacritic density (no artificial base score)
     * - Rises smoothly through the typical 3-8% Croatian range
     * - Caps at 0.95 to prevent saturation at extreme densities
     *
     * Approximate scores:
     *   0% -> 0.00, 3% -> 0.45, 5% -> 0.63, 8% -> 0.80, 15%+ -> 0.95
     */
    public function scoreDiacritics(string $text): float
    {
        $len = mb_strlen($text, 'UTF-8');
        if ($len === 0) {
            return 0.0;
        }

        $diacriticCount = 0;
        foreach (self::CROATIAN_DIACRITICS as $char) {
            $diacriticCount += mb_substr_count($text, $char);
        }

        $density = $diacriticCount / $len;

        // Exponential formula that starts at 0 for 0% density.
        // The constant 20 is tuned so that 5% density yields ~0.63 score.
        // Cap at 0.95 to prevent saturation for extreme densities.
        $k = 20.0;

        return min(0.95, 1.0 - exp(-$k * $density));
    }

    /**
     * Normalize Croatian diacritics to ASCII equivalents.
     *
     * This method is useful for fair content comparison between texts
     * where one has properly preserved diacritics and another has them
     * stripped or mangled. The normalization allows comparing the
     * underlying content without penalizing or rewarding diacritic presence.
     *
     * Mapping:
     *   č,ć -> c, ž -> z, š -> s, đ -> dz
     *   Č,Ć -> C, Ž -> Z, Š -> S, Đ -> DZ
     *
     * @param string $text Text with Croatian diacritics
     * @return string Text with diacritics replaced by ASCII equivalents
     */
    public function normalizeDiacritics(string $text): string
    {
        return strtr($text, self::DIACRITIC_NORMALIZATION_MAP);
    }
}
