<?php

namespace App\Services\Analysis\Analyzers;

use App\Models\CaseDocument;
use App\Models\DocumentAnalysis;
use App\Services\Analysis\Contracts\DocumentAnalyzerInterface;

class KeywordAnalyzer implements DocumentAnalyzerInterface
{
    /**
     * Croatian legal stop words to exclude from keyword extraction.
     */
    private const STOP_WORDS_HR = [
        'i', 'u', 'je', 'da', 'na', 'se', 'za', 'su', 'od', 'te', 'bi',
        'sa', 'po', 'ali', 'ili', 'kao', 'koje', 'koji', 'koja', 'nije',
        'bio', 'bila', 'bilo', 'biti', 'ne', 'do', 'iz', 'tog', 'toga',
        'tom', 'taj', 'ta', 'to', 'što', 'šta', 'sve', 'već', 'još',
        'samo', 'tek', 'čl', 'st', 'toč', 'stavak', 'stavka', 'članak',
        'članka', 'zakona', 'prema', 'nakon', 'prije', 'tijekom',
        'između', 'protiv', 'zbog', 'radi', 'ovaj', 'ova', 'ovo',
        'ovog', 'ovom', 'ovim', 'može', 'mogu', 'treba', 'ima', 'imati',
        'kada', 'kako', 'gdje', 'dok', 'jer', 'ako', 'tako', 'više',
        'manje', 'broj', 'dana', 'dan', 'mjesec', 'godina', 'str',
    ];

    /**
     * Croatian legal domain keywords get a boost.
     */
    private const LEGAL_DOMAIN_BOOST = [
        'optužen' => 2.0, 'okrivljen' => 2.0, 'oštećen' => 2.0,
        'presud' => 2.0, 'rješenj' => 2.0, 'žalb' => 2.0,
        'dokaz' => 2.5, 'svjedok' => 2.0, 'vještak' => 2.0,
        'pretres' => 2.0, 'pretraga' => 2.5, 'uhićenj' => 2.0,
        'pritvor' => 2.0, 'kazneno' => 1.5, 'prekršaj' => 1.5,
        'tužitelj' => 2.0, 'branitelj' => 2.0, 'sud' => 1.5,
        'nezakonit' => 3.0, 'izdvajan' => 3.0, 'ništav' => 2.5,
        'nalog' => 2.0, 'zapljen' => 2.0,
    ];

    public function type(): string
    {
        return DocumentAnalysis::TYPE_KEYWORDS;
    }

    public function layer(): string
    {
        return DocumentAnalysis::LAYER_EXTRACTION;
    }

    public function analyze(CaseDocument $document, string $text): array
    {
        $startTime = microtime(true);

        // Tokenize: lowercase, split on non-word chars, filter
        $words = $this->tokenize($text);
        $totalWords = count($words);

        // Calculate TF (term frequency)
        $tf = array_count_values($words);

        // Remove stop words
        foreach (self::STOP_WORDS_HR as $stop) {
            unset($tf[$stop]);
        }

        // Remove short words (< 3 chars) and pure numbers
        $tf = array_filter($tf, function ($count, $word) {
            return mb_strlen($word) >= 3 && !is_numeric($word);
        }, ARRAY_FILTER_USE_BOTH);

        // Score: TF normalized + domain boost
        $scored = [];
        foreach ($tf as $word => $count) {
            $tfScore = $count / max($totalWords, 1);
            $boost = $this->getDomainBoost($word);
            $scored[$word] = [
                'term' => $word,
                'count' => $count,
                'tf_score' => round($tfScore, 6),
                'boost' => $boost,
                'final_score' => round($tfScore * $boost, 6),
            ];
        }

        // Sort by final_score descending
        uasort($scored, fn($a, $b) => $b['final_score'] <=> $a['final_score']);

        // Top 50 keywords
        $topKeywords = array_slice(array_values($scored), 0, 50);

        // Extract bigrams (two-word phrases)
        $bigrams = $this->extractBigrams($words);

        $processingTime = round(microtime(true) - $startTime, 4);

        return [
            'results' => [
                'keywords' => $topKeywords,
                'bigrams' => array_slice($bigrams, 0, 30),
                'total_unique_terms' => count($tf),
                'total_words' => $totalWords,
            ],
            'metadata' => [
                'processing_time_seconds' => $processingTime,
                'analyzer' => 'KeywordAnalyzer',
                'api_calls' => 0,
                'cost' => 0,
            ],
        ];
    }

    private function tokenize(string $text): array
    {
        $text = mb_strtolower($text);
        // Split on non-letter/non-Croatian-diacritic chars
        $words = preg_split('/[^a-zA-ZčćžšđČĆŽŠĐ]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        return $words ?: [];
    }

    private function getDomainBoost(string $word): float
    {
        foreach (self::LEGAL_DOMAIN_BOOST as $stem => $boost) {
            if (str_starts_with($word, $stem)) {
                return $boost;
            }
        }
        return 1.0;
    }

    private function extractBigrams(array $words): array
    {
        $stopSet = array_flip(self::STOP_WORDS_HR);
        $bigrams = [];

        for ($i = 0; $i < count($words) - 1; $i++) {
            $w1 = $words[$i];
            $w2 = $words[$i + 1];

            // Skip if either word is a stop word or too short
            if (isset($stopSet[$w1]) || isset($stopSet[$w2])) continue;
            if (mb_strlen($w1) < 3 || mb_strlen($w2) < 3) continue;

            $bigram = "$w1 $w2";
            $bigrams[$bigram] = ($bigrams[$bigram] ?? 0) + 1;
        }

        arsort($bigrams);

        return array_map(fn($phrase, $count) => [
            'phrase' => $phrase,
            'count' => $count,
        ], array_keys($bigrams), array_values($bigrams));
    }
}
