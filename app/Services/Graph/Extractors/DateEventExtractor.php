<?php

namespace App\Services\Graph\Extractors;

use Carbon\Carbon;
use Illuminate\Support\Str;

class DateEventExtractor
{
    /**
     * Croatian date patterns
     */
    protected array $datePatterns = [
        // 15. siječnja 2024.
        '/(\d{1,2})\.\s*(sije[čc]nja|velja[čc]e|o[žz]ujka|travnja|svibnja|lipnja|srpnja|kolovoza|rujna|listopada|studenoga?|prosinca)\s*(\d{4})\.?/ui',
        // 15.01.2024. or 15.1.2024
        '/(\d{1,2})\.(\d{1,2})\.(\d{4})\.?/u',
        // 2024-01-15 (ISO format)
        '/(\d{4})-(\d{2})-(\d{2})/u',
    ];

    /**
     * Event type indicators
     */
    protected array $eventIndicators = [
        'filing' => [
            'podnesena', 'zaprimljen', 'podnijet', 'podnesen', 'pristigla', 'primitak',
            'tu[žz]ba.*podnesena', 'prijava.*podnesena',
        ],
        'hearing' => [
            'ro[čc]i[šs]te', 'rasprava', 'saslu[šs]anje', 'javna\s+sjednica',
            'odr[žz]ano.*ro[čc]i[šs]te', 'zakazano',
        ],
        'judgment' => [
            'presuda.*donesena', 'rje[šs]enje.*doneseno', 'izre[čc]ena', 'donesena.*odluka',
            'pravomo[ćc]n', 'objavljeno',
        ],
        'effective' => [
            'stupa.*na\s+snagu', 'primjenjuje\s+se\s+od', 'va[žz]i\s+od',
            'po[čc]etak\s+primjene',
        ],
        'expiry' => [
            'prestaje\s+va[žz]iti', 'rok.*istje[čc]e', 'istekao', 'do\s+dana',
            'krajnji\s+rok',
        ],
    ];

    /**
     * Croatian month names to numbers
     */
    protected array $monthMap = [
        'siječnja' => 1, 'sijecnja' => 1,
        'veljače' => 2, 'veljace' => 2,
        'ožujka' => 3, 'ozujka' => 3,
        'travnja' => 4,
        'svibnja' => 5,
        'lipnja' => 6,
        'srpnja' => 7,
        'kolovoza' => 8,
        'rujna' => 9,
        'listopada' => 10,
        'studenog' => 11, 'studenoga' => 11,
        'prosinca' => 12,
    ];

    /**
     * Extract date events from text
     */
    public function extract(string $text, string $sourceId, string $sourceType = 'decision'): array
    {
        if (empty(trim($text))) {
            return [];
        }

        $events = [];
        $seen = [];

        foreach ($this->datePatterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                foreach ($matches as $match) {
                    $dateString = $match[0][0];
                    $position = $match[0][1];

                    $parsedDate = $this->parseDate($match);
                    if (!$parsedDate) {
                        continue;
                    }

                    $context = $this->extractContext($text, $position, 40);
                    $eventType = $this->determineEventType($context);

                    // Create unique key
                    $key = $parsedDate . '_' . $eventType;
                    if (isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;

                    $events[] = [
                        'id' => (string) Str::ulid(),
                        'date' => $parsedDate,
                        'event_type' => $eventType,
                        'description' => $this->generateDescription($context, $eventType),
                        'source_type' => $sourceType,
                        'source_id' => $sourceId,
                    ];
                }
            }
        }

        // Sort by date
        usort($events, fn($a, $b) => strcmp($a['date'], $b['date']));

        return $events;
    }

    /**
     * Parse date from regex match
     */
    protected function parseDate(array $match): ?string
    {
        try {
            // Check if it's a Croatian month name format
            if (isset($match[2]) && !is_numeric($match[2][0])) {
                $day = (int) $match[1][0];
                $monthName = mb_strtolower($match[2][0]);
                $year = (int) $match[3][0];

                $month = $this->monthMap[$monthName] ?? null;
                if (!$month) {
                    return null;
                }

                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            }

            // Numeric format (dd.mm.yyyy or yyyy-mm-dd)
            if (strlen($match[1][0]) === 4) {
                // ISO format: yyyy-mm-dd
                return sprintf('%04d-%02d-%02d', (int)$match[1][0], (int)$match[2][0], (int)$match[3][0]);
            } else {
                // Croatian format: dd.mm.yyyy
                return sprintf('%04d-%02d-%02d', (int)$match[3][0], (int)$match[2][0], (int)$match[1][0]);
            }
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Extract context around date mention
     */
    protected function extractContext(string $text, int $position, int $length): string
    {
        $start = max(0, $position - $length);
        $end = min(strlen($text), $position + $length);

        return trim(substr($text, $start, $end - $start));
    }

    /**
     * Determine event type from context
     * Prioritizes indicators before the date, then closest to center
     */
    protected function determineEventType(string $context): string
    {
        $normalizedContext = mb_strtolower($context);
        $centerPos = strlen($context) / 2; // Use strlen, not mb_strlen (preg_match returns byte offsets)

        $bestBeforeMatch = null;
        $bestAfterMatch = null;
        $bestBeforeDistance = PHP_INT_MAX;
        $bestAfterDistance = PHP_INT_MAX;
        $bestBeforeType = null;
        $bestAfterType = null;

        foreach ($this->eventIndicators as $type => $indicators) {
            foreach ($indicators as $indicator) {
                if (preg_match('/' . $indicator . '/ui', $normalizedContext, $matches, PREG_OFFSET_CAPTURE)) {
                    $matchPos = $matches[0][1];
                    $distance = abs($matchPos - $centerPos);

                    // Prioritize indicators that come before the date
                    if ($matchPos < $centerPos) {
                        if ($distance < $bestBeforeDistance) {
                            $bestBeforeDistance = $distance;
                            $bestBeforeType = $type;
                            $bestBeforeMatch = $matches[0][0];
                        }
                    } else {
                        if ($distance < $bestAfterDistance) {
                            $bestAfterDistance = $distance;
                            $bestAfterType = $type;
                            $bestAfterMatch = $matches[0][0];
                        }
                    }
                }
            }
        }

        // Prefer indicators before the date, fall back to after, then default
        return $bestBeforeType ?? $bestAfterType ?? 'judgment';
    }

    /**
     * Generate description for the event
     */
    protected function generateDescription(string $context, string $eventType): string
    {
        // Clean up and truncate
        $description = preg_replace('/\s+/', ' ', trim($context));
        return mb_substr($description, 0, 300);
    }

    /**
     * Get available event types
     */
    public function getEventTypes(): array
    {
        return array_keys($this->eventIndicators);
    }
}
