<?php

namespace App\Services\Analysis\Analyzers;

use App\Models\CaseDocument;
use App\Models\DocumentAnalysis;
use App\Services\Analysis\Contracts\DocumentAnalyzerInterface;
use Carbon\Carbon;

class DateContextExtractor implements DocumentAnalyzerInterface
{
    /**
     * Croatian month names (genitive forms as they appear in legal text).
     */
    private const MONTHS_HR = [
        'siječnja' => 1, 'siječanj' => 1,
        'veljače' => 2, 'veljača' => 2,
        'ožujka' => 3, 'ožujak' => 3,
        'travnja' => 4, 'travanj' => 4,
        'svibnja' => 5, 'svibanj' => 5,
        'lipnja' => 6, 'lipanj' => 6,
        'srpnja' => 7, 'srpanj' => 7,
        'kolovoza' => 8, 'kolovoz' => 8,
        'rujna' => 9, 'rujan' => 9,
        'listopada' => 10, 'listopad' => 10,
        'studenog' => 11, 'studenoga' => 11, 'studeni' => 11,
        'prosinca' => 12, 'prosinac' => 12,
    ];

    /**
     * Legal event type classification patterns.
     * Applied against the +-200 char context around each date.
     * Order matters - more specific patterns should come before general ones.
     */
    private const EVENT_CLASSIFIERS = [
        'pretraga' => '/pretrag[aieu]|pretraživanj/ui',
        'uhicenje' => '/uhić|uhit|lišen|lisšen|privođenj/ui',
        'ispitivanje' => '/ispitivan|saslušan|iskazao|izjavi/ui',
        'nalog_izdavanje' => '/naredbu?|naloga?|naložio|odobri/ui',
        'prijava' => '/prijav[aieu]|kaznena.*prijava|podn[ie][jo]/ui',
        'zalba' => '/žalb[aieu]|prigovor|ulog|pobija/ui', // Must be before presuda
        'presuda' => '/presud[aieu]|osuđ|oslobođ|pravomoćn/ui',
        'rjesenje' => '/rješenj[aieu]|odluk[aieu]|zaključ/ui',
        'zapljena' => '/zapljen|oduzim|oduzet|pronađ|pronašao/ui',
        'vjestacenje' => '/vješta[čck]|analiz|laboratorij|nalaz/ui',
        'rociste' => '/ročišt[aieu]|rasprav[aieu]|sjednic/ui',
        'podnesak' => '/podnes[akieu]|zahtjev|prijedlog/ui',
        'dostava' => '/dostav[aieu]|uručen|primljen|zaprim/ui',
    ];

    /**
     * Time extraction patterns - many Croatian legal docs include time alongside date.
     */
    private const TIME_PATTERNS = [
        // "u 14:30 sati" or "u 14,30 sati"
        '/u\s+(\d{1,2})[:\.,](\d{2})\s*sati/ui',
        // "u 14 sati"
        '/u\s+(\d{1,2})\s+sati/ui',
        // "14:30" within 20 chars of a date
        '/(\d{1,2}):(\d{2})(?:\s*(?:h|sati?))?/u',
    ];

    public function type(): string
    {
        return 'dates_with_context';
    }

    public function layer(): string
    {
        return DocumentAnalysis::LAYER_EXTRACTION;
    }

    public function analyze(CaseDocument $document, string $text): array
    {
        $startTime = microtime(true);
        $dates = [];

        // Pattern 1: "15. sijecnja 2024." / "15. sijecnja 2024. godine"
        $monthNames = implode('|', array_keys(self::MONTHS_HR));
        preg_match_all(
            '/(\d{1,2})\.?\s*(' . $monthNames . ')\s*(\d{4})\.?\s*(?:god(?:ine)?\.?)?/ui',
            $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE
        );
        foreach ($matches as $m) {
            $entry = $this->buildDateEntry($m, $text, 'croatian_long');
            if ($entry) {
                $dates[] = $entry;
            }
        }

        // Pattern 2: DD.MM.YYYY. (with optional spaces, g., godine)
        // From extract_references.sh: handles "01. 02. 2025. g."
        preg_match_all(
            '/(\d{1,2})\.\s*(\d{1,2})\.\s*(\d{4})\.?\s*(?:g\.|god\.|godine?)?/ui',
            $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE
        );
        foreach ($matches as $m) {
            $entry = $this->buildDateEntryFromDMY($m, $text, 'dd_mm_yyyy');
            if ($entry) {
                $dates[] = $entry;
            }
        }

        // Pattern 3: "dana DD.MM.YYYY." (contextual prefix)
        preg_match_all(
            '/dana\s+(\d{1,2})\.\s*(\d{1,2})\.\s*(\d{4})\.?\s*(?:g\.|god\.|godine?)?/ui',
            $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE
        );
        foreach ($matches as $m) {
            $entry = $this->buildDateEntryFromDMY($m, $text, 'dana_prefix');
            if ($entry) {
                $dates[] = $entry;
            }
        }

        // Pattern 4: ISO YYYY-MM-DD
        preg_match_all(
            '/(\d{4})-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])/u',
            $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE
        );
        foreach ($matches as $m) {
            $entry = $this->buildDateEntryFromISO($m, $text);
            if ($entry) {
                $dates[] = $entry;
            }
        }

        // Pattern 5: "od DD.MM.YYYY." / "do DD.MM.YYYY." (range endpoints)
        preg_match_all(
            '/(od|do)\s+(\d{1,2})\.\s*(\d{1,2})\.\s*(\d{4})\.?/ui',
            $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE
        );
        foreach ($matches as $m) {
            $entry = $this->buildDateEntryFromRange($m, $text);
            if ($entry) {
                $dates[] = $entry;
            }
        }

        // Deduplicate by date + position proximity (same date within 10 chars = same mention)
        $dates = $this->dedupDates($dates);

        // Sort chronologically
        usort($dates, fn($a, $b) => ($a['date'] ?? '') <=> ($b['date'] ?? ''));

        // Compute timeline span
        $validDates = array_filter($dates, fn($d) => !empty($d['date']));
        $dateRange = null;
        if (count($validDates) >= 2) {
            $allDates = array_column($validDates, 'date');
            $dateRange = [
                'earliest' => min($allDates),
                'latest' => max($allDates),
                'span_days' => Carbon::parse(min($allDates))->diffInDays(Carbon::parse(max($allDates))),
            ];
        }

        return [
            'results' => [
                'dates' => $dates,
                'date_count' => count($dates),
                'unique_dates' => count(array_unique(array_column($dates, 'date'))),
                'date_range' => $dateRange,
                'events_by_type' => $this->groupByEventType($dates),
            ],
            'metadata' => [
                'processing_time_seconds' => round(microtime(true) - $startTime, 4),
                'analyzer' => 'DateContextExtractor',
                'api_calls' => 0,
                'cost' => 0,
            ],
        ];
    }

    private function buildDateEntry(array $match, string $text, string $format): ?array
    {
        $day = (int)$match[1][0];
        $monthName = mb_strtolower($match[2][0]);
        $month = self::MONTHS_HR[$monthName] ?? null;
        $year = (int)$match[3][0];
        $offset = $match[0][1];

        if (!$month || !checkdate($month, $day, $year)) {
            return null;
        }
        if ($year < 1990 || $year > 2030) {
            return null;
        }

        $context = $this->extractContext($text, $offset, 200);
        $time = $this->extractNearbyTime($text, $offset);

        return [
            'date' => Carbon::create($year, $month, $day)->toDateString(),
            'time' => $time,
            'raw_match' => trim($match[0][0]),
            'format_detected' => $format,
            'context' => $context,
            'event_type' => $this->classifyEvent($context),
            'position' => $offset,
        ];
    }

    private function buildDateEntryFromDMY(array $match, string $text, string $format): ?array
    {
        // Index offsets differ because of leading group (e.g., "dana")
        $dayIdx = ($format === 'dana_prefix') ? 1 : 1;
        $monthIdx = ($format === 'dana_prefix') ? 2 : 2;
        $yearIdx = ($format === 'dana_prefix') ? 3 : 3;

        $day = (int)$match[$dayIdx][0];
        $month = (int)$match[$monthIdx][0];
        $year = (int)$match[$yearIdx][0];
        $offset = $match[0][1];

        if ($month < 1 || $month > 12 || !checkdate($month, $day, $year)) {
            return null;
        }
        if ($year < 1990 || $year > 2030) {
            return null;
        }

        $context = $this->extractContext($text, $offset, 200);
        $time = $this->extractNearbyTime($text, $offset);

        return [
            'date' => Carbon::create($year, $month, $day)->toDateString(),
            'time' => $time,
            'raw_match' => trim($match[0][0]),
            'format_detected' => $format,
            'context' => $context,
            'event_type' => $this->classifyEvent($context),
            'position' => $offset,
        ];
    }

    private function buildDateEntryFromISO(array $match, string $text): ?array
    {
        $year = (int)$match[1][0];
        $month = (int)$match[2][0];
        $day = (int)$match[3][0];
        $offset = $match[0][1];

        if (!checkdate($month, $day, $year)) {
            return null;
        }

        $context = $this->extractContext($text, $offset, 200);

        return [
            'date' => Carbon::create($year, $month, $day)->toDateString(),
            'time' => $this->extractNearbyTime($text, $offset),
            'raw_match' => trim($match[0][0]),
            'format_detected' => 'iso',
            'context' => $context,
            'event_type' => $this->classifyEvent($context),
            'position' => $offset,
        ];
    }

    private function buildDateEntryFromRange(array $match, string $text): ?array
    {
        $rangeType = mb_strtolower($match[1][0]); // "od" or "do"
        $day = (int)$match[2][0];
        $month = (int)$match[3][0];
        $year = (int)$match[4][0];
        $offset = $match[0][1];

        if ($month < 1 || $month > 12 || !checkdate($month, $day, $year)) {
            return null;
        }
        if ($year < 1990 || $year > 2030) {
            return null;
        }

        $context = $this->extractContext($text, $offset, 200);

        return [
            'date' => Carbon::create($year, $month, $day)->toDateString(),
            'time' => $this->extractNearbyTime($text, $offset),
            'raw_match' => trim($match[0][0]),
            'format_detected' => "range_{$rangeType}",
            'context' => $context,
            'event_type' => $this->classifyEvent($context),
            'range_position' => $rangeType, // 'od' (start) or 'do' (end)
            'position' => $offset,
        ];
    }

    /**
     * Classify the event type from surrounding context using keyword patterns.
     */
    private function classifyEvent(string $context): ?string
    {
        foreach (self::EVENT_CLASSIFIERS as $type => $pattern) {
            if (preg_match($pattern, $context)) {
                return $type;
            }
        }
        return null;
    }

    /**
     * Look for time mentions within +-50 chars of a date position.
     */
    private function extractNearbyTime(string $text, int $dateOffset): ?string
    {
        $searchStart = max(0, $dateOffset - 50);
        $searchEnd = min(mb_strlen($text), $dateOffset + 100);
        $nearby = mb_substr($text, $searchStart, $searchEnd - $searchStart);

        // "u 14:30 sati" or "u 14,30 sati"
        if (preg_match('/u\s+(\d{1,2})[:\.,](\d{2})\s*sati/ui', $nearby, $tm)) {
            return sprintf('%02d:%02d', (int)$tm[1], (int)$tm[2]);
        }

        // "u 14 sati"
        if (preg_match('/u\s+(\d{1,2})\s+sati/ui', $nearby, $tm)) {
            return sprintf('%02d:00', (int)$tm[1]);
        }

        // Bare HH:MM near a date
        if (preg_match('/(\d{1,2}):(\d{2})/', $nearby, $tm)) {
            $h = (int)$tm[1];
            $m = (int)$tm[2];
            if ($h >= 0 && $h <= 23 && $m >= 0 && $m <= 59) {
                return sprintf('%02d:%02d', $h, $m);
            }
        }

        return null;
    }

    private function extractContext(string $text, int $offset, int $radius = 200): string
    {
        $start = max(0, $offset - $radius);
        $length = min(mb_strlen($text) - $start, $radius * 2 + 100);
        $context = mb_substr($text, $start, $length);
        return trim(preg_replace('/\s+/', ' ', $context));
    }

    private function dedupDates(array $dates): array
    {
        $dates = array_filter($dates); // Remove nulls
        $deduped = [];

        foreach ($dates as $entry) {
            $dominated = false;
            $replaceIdx = null;

            foreach ($deduped as $idx => $existing) {
                // Same date within 20 chars = same mention
                if ($existing['date'] === $entry['date']
                    && abs($existing['position'] - $entry['position']) < 20) {

                    // Prefer entry with range_position (more specific info)
                    if (isset($entry['range_position']) && !isset($existing['range_position'])) {
                        $replaceIdx = $idx;
                        break;
                    }

                    $dominated = true;
                    break;
                }
            }

            if ($replaceIdx !== null) {
                $deduped[$replaceIdx] = $entry;
            } elseif (!$dominated) {
                $deduped[] = $entry;
            }
        }

        return array_values($deduped);
    }

    private function groupByEventType(array $dates): array
    {
        $grouped = [];
        foreach ($dates as $d) {
            $type = $d['event_type'] ?? 'unclassified';
            $grouped[$type][] = $d['date'] ?? 'unknown';
        }
        return $grouped;
    }
}
