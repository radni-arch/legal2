<?php

namespace App\Services\Analysis\Analyzers;

use App\Models\CaseDocument;
use App\Models\DocumentAnalysis;
use App\Services\Analysis\Contracts\DocumentAnalyzerInterface;
use Carbon\Carbon;

class DateExtractor implements DocumentAnalyzerInterface
{
    /**
     * Croatian month names for parsing.
     */
    private const MONTHS_HR = [
        'siječnja' => 1, 'siječanj' => 1, 'januar' => 1,
        'veljače' => 2, 'veljača' => 2, 'februar' => 2,
        'ožujka' => 3, 'ožujak' => 3, 'mart' => 3,
        'travnja' => 4, 'travanj' => 4, 'april' => 4,
        'svibnja' => 5, 'svibanj' => 5, 'maj' => 5,
        'lipnja' => 6, 'lipanj' => 6, 'juni' => 6,
        'srpnja' => 7, 'srpanj' => 7, 'juli' => 7,
        'kolovoza' => 8, 'kolovoz' => 8, 'august' => 8,
        'rujna' => 9, 'rujan' => 9, 'septembar' => 9,
        'listopada' => 10, 'listopad' => 10, 'oktobar' => 10,
        'studenoga' => 11, 'studeni' => 11, 'novembar' => 11,
        'prosinca' => 12, 'prosinac' => 12, 'decembar' => 12,
    ];

    public function type(): string
    {
        return DocumentAnalysis::TYPE_DATES;
    }

    public function layer(): string
    {
        return DocumentAnalysis::LAYER_EXTRACTION;
    }

    public function analyze(CaseDocument $document, string $text): array
    {
        $startTime = microtime(true);
        $dates = [];

        // Pattern 1: "15. siječnja 2024." or "15. siječnja 2024. godine"
        preg_match_all(
            '/(\d{1,2})\.\s*(' . implode('|', array_keys(self::MONTHS_HR)) . ')\s*(\d{4})\.?\s*(?:godine)?/ui',
            $text,
            $matches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE
        );

        foreach ($matches as $m) {
            $day = (int)$m[1][0];
            $monthName = mb_strtolower($m[2][0]);
            $month = self::MONTHS_HR[$monthName] ?? null;
            $year = (int)$m[3][0];
            $offset = $m[0][1];

            if ($month && checkdate($month, $day, $year)) {
                $dates[] = $this->buildDateEntry(
                    Carbon::create($year, $month, $day),
                    $m[0][0],
                    $offset,
                    $text,
                    'croatian_long'
                );
            }
        }

        // Pattern 2: "15.01.2024." or "15.01.2024" (DD.MM.YYYY)
        preg_match_all(
            '/(\d{1,2})\.(\d{1,2})\.(\d{4})\.?/u',
            $text,
            $matches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE
        );

        foreach ($matches as $m) {
            $day = (int)$m[1][0];
            $month = (int)$m[2][0];
            $year = (int)$m[3][0];
            $offset = $m[0][1];

            if ($month >= 1 && $month <= 12 && checkdate($month, $day, $year) && $year >= config('analysis.date_extractor.min_year', 1945) && $year <= config('analysis.date_extractor.max_year', 2050)) {
                $dateStr = Carbon::create($year, $month, $day)->toDateString();
                // Avoid duplicates from pattern 1
                if (!$this->dateAlreadyFound($dates, $dateStr)) {
                    $dates[] = $this->buildDateEntry(
                        Carbon::create($year, $month, $day),
                        $m[0][0],
                        $offset,
                        $text,
                        'dd_mm_yyyy'
                    );
                }
            }
        }

        // Pattern 3: ISO format "2024-01-15"
        preg_match_all(
            '/(\d{4})-(\d{2})-(\d{2})/u',
            $text,
            $matches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE
        );

        foreach ($matches as $m) {
            $year = (int)$m[1][0];
            $month = (int)$m[2][0];
            $day = (int)$m[3][0];
            $offset = $m[0][1];

            if (checkdate($month, $day, $year) && $year >= config('analysis.date_extractor.min_year', 1945) && $year <= config('analysis.date_extractor.max_year', 2050)) {
                $dateStr = Carbon::create($year, $month, $day)->toDateString();
                if (!$this->dateAlreadyFound($dates, $dateStr)) {
                    $dates[] = $this->buildDateEntry(
                        Carbon::create($year, $month, $day),
                        $m[0][0],
                        $offset,
                        $text,
                        'iso'
                    );
                }
            }
        }

        // Sort by date ascending
        usort($dates, fn($a, $b) => $a['date'] <=> $b['date']);

        // Determine date range
        $dateRange = null;
        if (count($dates) >= 2) {
            $dateRange = [
                'earliest' => $dates[0]['date'],
                'latest' => end($dates)['date'],
                'span_days' => Carbon::parse($dates[0]['date'])->diffInDays(Carbon::parse(end($dates)['date'])),
            ];
        }

        return [
            'results' => [
                'dates' => $dates,
                'date_count' => count($dates),
                'unique_dates' => count(array_unique(array_column($dates, 'date'))),
                'date_range' => $dateRange,
            ],
            'metadata' => [
                'processing_time_seconds' => round(microtime(true) - $startTime, 4),
                'analyzer' => 'DateExtractor',
                'api_calls' => 0,
                'cost' => 0,
            ],
        ];
    }

    private function buildDateEntry(Carbon $date, string $raw, int $offset, string $text, string $format): array
    {
        // Extract surrounding context (±100 chars)
        $contextStart = max(0, $offset - 100);
        $contextLen = min(mb_strlen($text) - $contextStart, 200 + mb_strlen($raw));
        $context = mb_substr($text, $contextStart, $contextLen);

        return [
            'date' => $date->toDateString(),
            'raw_match' => trim($raw),
            'format_detected' => $format,
            'context' => trim(preg_replace('/\s+/', ' ', $context)),
            'position' => $offset,
        ];
    }

    private function dateAlreadyFound(array $dates, string $dateStr): bool
    {
        foreach ($dates as $d) {
            if ($d['date'] === $dateStr) return true;
        }
        return false;
    }
}
