<?php

namespace App\Services\Analysis\Analyzers;

use App\Models\CaseDocument;
use App\Models\DocumentAnalysis;
use App\Services\Analysis\Contracts\DocumentAnalyzerInterface;

class DocumentStatisticsAnalyzer implements DocumentAnalyzerInterface
{
    public function type(): string
    {
        return DocumentAnalysis::TYPE_STATISTICS;
    }

    public function layer(): string
    {
        return DocumentAnalysis::LAYER_EXTRACTION;
    }

    public function analyze(CaseDocument $document, string $text): array
    {
        $startTime = microtime(true);

        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $sentences = preg_split('/[.!?]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $paragraphs = preg_split('/\n\s*\n/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $lines = explode("\n", $text);

        // Language detection heuristic (Croatian vs other)
        $croatianIndicators = preg_match_all('/[čćžšđČĆŽŠĐ]/u', $text);
        $totalChars = mb_strlen($text);
        $croatianDensity = $totalChars > 0 ? round($croatianIndicators / $totalChars * 100, 3) : 0;

        // Reading time estimate (avg 200 words/min for legal text)
        $readingTimeMinutes = round(count($words) / 200, 1);

        return [
            'results' => [
                'word_count' => count($words),
                'sentence_count' => count($sentences),
                'paragraph_count' => count($paragraphs),
                'line_count' => count($lines),
                'character_count' => $totalChars,
                'avg_sentence_length' => count($sentences) > 0
                    ? round(count($words) / count($sentences), 1)
                    : 0,
                'avg_paragraph_length' => count($paragraphs) > 0
                    ? round(count($words) / count($paragraphs), 1)
                    : 0,
                'croatian_diacritic_density' => $croatianDensity,
                'estimated_reading_time_minutes' => $readingTimeMinutes,
                'estimated_page_count' => max(1, round(count($words) / 300)), // ~300 words/page for legal docs
            ],
            'metadata' => [
                'processing_time_seconds' => round(microtime(true) - $startTime, 4),
                'analyzer' => 'DocumentStatisticsAnalyzer',
                'api_calls' => 0,
                'cost' => 0,
            ],
        ];
    }
}
