<?php

namespace App\Http\Livewire;

use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Livewire\Component;

/**
 * TranscriptPreviewer Livewire Component
 *
 * A comprehensive transcript document viewer with support for:
 * - Multi-speaker transcripts with time codes (HH:MM:SS:FF format)
 * - Real-time search across speaker names and dialogue text
 * - Speaker filtering and visibility toggling
 * - Forensic linguistic analysis integration (lingua.txt)
 * - Automatic timestamp to absolute datetime conversion
 * - Timeline visualization of forensic events
 * - Auto-refresh capability for live transcripts
 *
 * ## Features
 *
 * ### Transcript Parsing
 * Reads transcript files with format: `HH:MM:SS:FF Speaker Name` followed by dialogue lines.
 * Segments are indexed, sorted chronologically, and assigned stable IDs for anchor links.
 *
 * ### Speaker Management
 * - Automatic detection of all speakers from transcript
 * - Per-speaker visibility toggles
 * - Bulk show/hide all speakers
 * - Segment filtering by active speaker selection
 *
 * ### Search & Filtering
 * - Case-insensitive full-text search across speaker names and text
 * - Debounced search input (400ms)
 * - HTML highlighting of matching terms in results
 * - Filtered segment list updates dynamically
 *
 * ### Forensic Linguistics
 * - Optional lingua.txt file with forensic analysis notes
 * - Extraction of timeline events from markdown bullets: `- **[HH:MM:SS] Title:** Excerpt`
 * - Summary extraction from "# Opća analiza" section
 * - Event timeline visualization with scroll bar positioning
 * - Event cards linked to transcript segments
 * - Temporal proximity detection (±15 second window)
 * - Expandable details for nearby events
 *
 * ### Timestamps & Timing
 * - Segment time codes in HH:MM:SS:FF format (frames ignored)
 * - Calculated transcript duration from max time code
 * - Absolute datetime calculation: base start time + segment offset
 * - Configurable base start time and timezone (default: Europe/Zagreb)
 * - Proper handling of daylight saving time via Carbon
 *
 * ### UI Controls
 * - Timestamps visibility toggle
 * - Auto-refresh every 5 seconds (optional)
 * - Clear search button
 * - Manual refresh trigger
 * - File path configuration (with fallback resolution)
 * - Lingua path configuration
 * - Visual duration display in HH:MM:SS format
 *
 * ## Properties
 */
class TranscriptPreviewer extends Component
{
    /**
     * Transcript file path (absolute or relative to storage_path).
     * Falls back to storage/iznedjenaIzjava.txt if not found.
     */
    public string $filePath;

    /**
     * Real-time search query. Case-insensitive, searches speaker names and text.
     * Debounced at 400ms intervals.
     */
    public string $search = '';

    /**
     * Speaker visibility flags. Format: ['S1' => true, 'S2' => false, ...]
     * Automatically populated from transcript parsing.
     */
    public array $speakers = [];

    /**
     * Whether to display segment timecodes in the UI.
     */
    public bool $showTimestamps = true;

    /**
     * Whether to enable 5-second auto-refresh polling.
     */
    public bool $autoRefresh = false;

    /**
     * Lingua (forensic linguistics) analysis file path.
     * Falls back to storage/lingua.txt if not found.
     */
    public string $linguaPath;

    /**
     * Whether to display the forensic analysis panel.
     */
    public bool $showLingua = true;

    /**
     * Base start datetime for absolute timestamps (Europe/Zagreb timezone).
     * Format: 'YYYY-MM-DD HH:MM:SS'
     */
    public string $baseStart = '2025-06-09 14:45:00';

    /**
     * Timezone identifier for absolute datetime calculations.
     * IMPORTANT: Must be a valid PHP timezone (e.g., 'Europe/Zagreb', 'UTC')
     */
    public string $timezone = 'Europe/Zagreb';

    /**
     * Parsed transcript segments.
     * Each segment contains:
     * - time: HH:MM:SS:FF timecode
     * - seconds: numeric offset in seconds
     * - speaker: speaker identifier
     * - text: dialogue text (may contain newlines)
     * - has_time: boolean indicating if timecode was present in source
     * - abs?: absolute datetime string (if has_time)
     * - id?: stable anchor link ID (seg-XXXXXX)
     *
     * @var array<int,array{time:string, seconds:int, speaker:string, text:string, has_time:bool, abs?:string, id?:string}>
     */
    public array $segments = [];

    /**
     * Extracted forensic linguistics timeline events.
     * Parsed from lingua.txt markdown bullets matching pattern: `- **[HH:MM:SS] Title:** Excerpt`
     * Each event contains:
     * - time: HH:MM:SS timecode
     * - seconds: numeric offset in seconds
     * - title: event title
     * - excerpt: text snippet (truncated to 1240 chars)
     *
     * @var array<int,array{time:string, seconds:int, title:string, excerpt:string}>
     */
    public array $linguaEvents = [];

    /**
     * Raw contents of lingua.txt file (optional display).
     * Useful for debugging or viewing raw analysis.
     */
    public string $linguaRaw = '';

    /**
     * Extracted summary from "# Opća analiza" section of lingua.txt.
     * First major section up to next header.
     * Collapsed to remove excessive newlines and truncated to 2500 chars if needed.
     */
    public string $linguaSummary = '';

    /**
     * Calculated transcript duration in seconds.
     * Derived from the maximum timecode in segments.
     */
    public int $durationSec = 0;

    /**
     * Mount component and load initial transcript and lingua files.
     *
     * @param  string|null  $path  Optional custom transcript file path
     */
    public function mount(?string $path = null): void
    {
        // Default to provided sample transcript under storage/iznedjenaIzjava.txt
        $default = storage_path('iznedjenaIzjava.txt');
        $this->filePath = $path ? $this->resolvePath($path) : $default;

        // Default lingua path
        $this->linguaPath = storage_path('lingua.txt');

        $this->loadTranscript();
        $this->loadLingua();
    }

    /**
     * Livewire property update handler.
     * Reloads transcript or lingua data when their file paths change.
     *
     * @param  string  $property  Property name that was updated
     */
    public function updated($property): void
    {
        if ($property === 'filePath') {
            $this->filePath = $this->resolvePath($this->filePath);
            $this->loadTranscript();
        }
        if ($property === 'linguaPath') {
            $this->linguaPath = $this->resolvePathLingua($this->linguaPath);
            $this->loadLingua();
        }
    }

    /**
     * Manually refresh transcript and lingua data from disk.
     * Useful when file contents change (auto-refresh mode).
     */
    public function refreshNow(): void
    {
        $this->loadTranscript();
        if ($this->showLingua) {
            $this->loadLingua();
        }
    }

    /**
     * Toggle visibility of a specific speaker's segments.
     *
     * @param  string  $speaker  Speaker identifier to toggle
     */
    public function toggleSpeaker(string $speaker): void
    {
        $cur = $this->speakers[$speaker] ?? true;
        $this->speakers[$speaker] = ! $cur;
    }

    /**
     * Set visibility for all speakers at once.
     *
     * @param  bool  $on  True to show all speakers, false to hide all
     */
    public function allSpeakers(bool $on = true): void
    {
        foreach ($this->speakers as $k => $v) {
            $this->speakers[$k] = $on;
        }
    }

    /**
     * Export the current filtered transcript segments to a downloadable file.
     * Generates a formatted text file with visible segments based on current filters.
     *
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function exportTranscript()
    {
        $items = $this->getFilteredProperty();
        $filename = 'transcript_export_'.date('Y-m-d_His').'.txt';

        $content = "TRANSCRIPT EXPORT\n";
        $content .= 'Generated: '.date('Y-m-d H:i:s')."\n";
        $content .= "Source: {$this->filePath}\n";
        $content .= "Base Start: {$this->baseStart} ({$this->timezone})\n";
        $content .= 'Total Segments: '.count($items)."\n";
        $content .= str_repeat('=', 80)."\n\n";

        foreach ($items as $seg) {
            if ($this->showTimestamps) {
                $content .= "[{$seg['time']}] ";
            }
            $content .= "Speaker {$seg['speaker']}";
            if (($seg['has_time'] ?? false) && ! empty($seg['abs'])) {
                $content .= " ({$seg['abs']})";
            }
            $content .= "\n";
            $content .= $seg['text']."\n";
            $content .= str_repeat('-', 80)."\n\n";
        }

        return response()->streamDownload(function () use ($content) {
            echo $content;
        }, $filename, [
            'Content-Type' => 'text/plain',
        ]);
    }

    protected function resolvePath(string $path): string
    {
        // Allow relative paths under storage/ or absolute paths
        $trim = trim($path);
        if ($trim === '') {
            return storage_path('iznedjenaIzjava.txt');
        }
        if (str_starts_with($trim, '/')) {
            return $trim;
        }
        // If user provided something like storage/xyz.txt, normalize to absolute
        if (str_starts_with($trim, 'storage/')) {
            return base_path($trim);
        }

        // treat as relative to storage_path
        return storage_path($trim);
    }

    protected function resolvePathLingua(string $path): string
    {
        $trim = trim($path);
        if ($trim === '') {
            return storage_path('lingua.txt');
        }
        if (str_starts_with($trim, '/')) {
            return $trim;
        }
        if (str_starts_with($trim, 'storage/')) {
            return base_path($trim);
        }

        return storage_path($trim);
    }

    protected function loadTranscript(): void
    {
        $this->segments = [];
        $this->speakers = [];
        $this->durationSec = 0;

        if (! File::exists($this->filePath)) {
            // Try fallback: base_path('storage/iznedjenaIzjava.txt') in case file is at project root storage dir
            $fallback = base_path('storage/iznedjenaIzjava.txt');
            if ($this->filePath !== $fallback && File::exists($fallback)) {
                $this->filePath = $fallback;
            } else {
                return;
            }
        }

        $lines = @file($this->filePath, FILE_IGNORE_NEW_LINES) ?: [];
        $current = [
            'time' => '00:00:00:00',
            'seconds' => 0,
            'speaker' => 'Unknown',
            'text' => '',
            'has_time' => false,
        ];
        $hasCurrent = false;

        foreach ($lines as $raw) {
            $line = trim($raw);
            if ($line === '' || str_starts_with($line, '// filepath:')) {
                continue;
            }

            // Match timestamp + speaker header
            if (preg_match('/^(?<t>\d{2}:\d{2}:\d{2}:\d{2})\s+Speaker\s+(?<sp>[\w\-]+)\s*$/u', $line, $m)) {
                if ($hasCurrent) {
                    $this->segments[] = $current;
                }
                $timeStr = $m['t'];
                $speaker = $m['sp'];
                $seconds = $this->timeToSeconds($timeStr);
                $current = [
                    'time' => $timeStr,
                    'seconds' => $seconds,
                    'speaker' => $speaker,
                    'text' => '',
                    'has_time' => true,
                ];
                $hasCurrent = true;
                $this->speakers[$speaker] = $this->speakers[$speaker] ?? true;
                $this->durationSec = max($this->durationSec, $seconds);

                continue;
            }

            // Fallback match
            if (preg_match('/^(?<t>\d{2}:\d{2}:\d{2}:\d{2})\s+(?<label>Speaker\s+)?(?<sp>[\w\-]+)?$/u', $line, $m2) && ($m2['label'] ?? '') !== '') {
                if ($hasCurrent) {
                    $this->segments[] = $current;
                }
                $timeStr = $m2['t'];
                $speaker = $m2['sp'] ?: 'Unknown';
                $seconds = $this->timeToSeconds($timeStr);
                $current = [
                    'time' => $timeStr,
                    'seconds' => $seconds,
                    'speaker' => $speaker,
                    'text' => '',
                    'has_time' => true,
                ];
                $hasCurrent = true;
                $this->speakers[$speaker] = $this->speakers[$speaker] ?? true;
                $this->durationSec = max($this->durationSec, $seconds);

                continue;
            }

            // If we have a current block, append text
            if ($hasCurrent) {
                if ($current['text'] !== '') {
                    $current['text'] .= "\n";
                }
                $current['text'] .= $raw; // keep original spacing/punctuation
            } else {
                // If transcript starts with text before first timestamp, attach to Unknown at 0
                $current['text'] .= ($current['text'] ? "\n" : '').$raw;
                $hasCurrent = true;
            }
        }
        if ($hasCurrent) {
            $this->segments[] = $current;
            $this->durationSec = max($this->durationSec, (int) ($current['seconds'] ?? 0));
        }

        // Normalize speakers: ensure boolean flags
        foreach ($this->speakers as $k => $v) {
            $this->speakers[$k] = (bool) $v;
        }

        // Sort segments by seconds in case input is unordered
        usort($this->segments, fn ($a, $b) => $a['seconds'] <=> $b['seconds']);

        // Attach stable ids to segments for anchor links
        foreach ($this->segments as $i => &$seg) {
            $seg['id'] = 'seg-'.str_pad((string) ($seg['seconds'] ?? 0), 6, '0', STR_PAD_LEFT);
        }
        unset($seg);

        // Compute absolute datetimes for segments with known time
        try {
            $base = Carbon::parse($this->baseStart, $this->timezone);
        } catch (\Throwable $e) {
            $base = Carbon::parse('2025-06-09 14:45:00', $this->timezone);
        }
        foreach ($this->segments as &$seg) {
            if (! empty($seg['has_time'])) {
                $dt = (clone $base)->addSeconds((int) ($seg['seconds'] ?? 0));
                $seg['abs'] = $dt->format('Y-m-d H:i:s');
            }
        }
        unset($seg);
    }

    protected function loadLingua(): void
    {
        $this->linguaEvents = [];
        $this->linguaRaw = '';
        $this->linguaSummary = '';

        $path = $this->resolvePathLingua($this->linguaPath);
        if (! File::exists($path)) {
            // Try fallback at project root
            $fallback = base_path('storage/lingua.txt');
            if (File::exists($fallback)) {
                $path = $fallback;
            } else {
                return;
            }
        }

        $raw = @file_get_contents($path) ?: '';
        $this->linguaRaw = $raw;

        // Extract first section after '# Opća analiza' as summary
        if ($raw !== '') {
            if (preg_match('/^#\s*Op\s*ća\s+analiza\s*\n+(.+?)(?=\n#|\z)/imsu', $raw, $m)) {
                $summary = trim($m[1]);
                // Collapse whitespace and limit length
                $summary = preg_replace('/\n{2,}/', "\n\n", $summary);
                $summary = trim($summary ?? '');
                if (mb_strlen($summary) > 2500) {
                    $summary = mb_substr($summary, 0, 2500).'…';
                }
                $this->linguaSummary = $summary;
            }

            // Extract timeline bullets: - **[HH:MM:SS] Title:** Excerpt… up to next bullet or header
            if (preg_match_all('/^\-\s*\*\*\[(\d{2}:\d{2}:\d{2})]\s*(.*?):\*\*\s*(.*?)(?=\n\-\s*\*\*\[|\n#|\z)/imsu', $raw, $mm, PREG_SET_ORDER)) {
                foreach ($mm as $hit) {
                    $t = $hit[1];
                    $title = trim($hit[2]);
                    $excerpt = trim($hit[3]);
                    // Keep only the first 240 chars of excerpt without newlines
                    $excerpt = preg_replace('/\s+/', ' ', $excerpt);
                    if (mb_strlen($excerpt) > 1240) {
                        $excerpt = mb_substr($excerpt, 0, 1240).'…';
                    }
                    $seconds = $this->timeToSeconds($t.':00'); // normalize to HH:MM:SS:FF parser
                    $this->linguaEvents[] = [
                        'time' => $t,
                        'seconds' => $seconds,
                        'title' => $title,
                        'excerpt' => $excerpt,
                    ];
                }
            } else {
                // Fallback: any [HH:MM:SS] in text with a nearby sentence
                if (preg_match_all('/\[(\d{2}:\d{2}:\d{2})]/imu', $raw, $mt)) {
                    foreach ($mt[1] as $t) {
                        $seconds = $this->timeToSeconds($t.':00');
                        // Create a lightweight title
                        $this->linguaEvents[] = [
                            'time' => $t,
                            'seconds' => $seconds,
                            'title' => 'Bilješka za '.$t,
                            'excerpt' => 'Dogadjaj označen u forenzičkoj analizi.',
                        ];
                    }
                }
            }
        }

        // Sort and unique by seconds/title
        if (! empty($this->linguaEvents)) {
            usort($this->linguaEvents, fn ($a, $b) => $a['seconds'] <=> $b['seconds']);
            $dedup = [];
            $seen = [];
            foreach ($this->linguaEvents as $ev) {
                $key = $ev['seconds'].'|'.mb_strtolower($ev['title']);
                if (! isset($seen[$key])) {
                    $seen[$key] = true;
                    $dedup[] = $ev;
                }
            }
            $this->linguaEvents = $dedup;
        }
    }

    protected function timeToSeconds(string $time): int
    {
        // Accept HH:MM:SS:FF, ignoring frames (approximate)
        if (preg_match('/^(\d{2}):(\d{2}):(\d{2})(?::(\d{2}))?$/', $time, $m)) {
            $h = (int) $m[1];
            $mi = (int) $m[2];
            $s = (int) $m[3];

            return $h * 3600 + $mi * 60 + $s; // ignoring frames
        }

        return 0;
    }

    /**
     * @return array<int,array{time:string, seconds:int, speaker:string, text:string}>
     */
    public function getFilteredProperty(): array
    {
        $needle = trim($this->search);
        $hasSearch = $needle !== '';
        $speakersOn = array_keys(array_filter($this->speakers, fn ($v) => $v));

        return array_values(array_filter($this->segments, function ($seg) use ($hasSearch, $needle, $speakersOn) {
            if (! in_array($seg['speaker'], $speakersOn, true)) {
                return false;
            }
            if ($hasSearch) {
                $hay = mb_strtolower($seg['speaker'].' '.($seg['text'] ?? ''));
                if (! str_contains($hay, mb_strtolower($needle))) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * Provide lingua events near a given segment time (±15s window)
     *
     * @return array<int,array{time:string, seconds:int, title:string, excerpt:string}>
     */
    public function eventsNear(int $sec): array
    {
        if (empty($this->linguaEvents)) {
            return [];
        }
        $win = 15;
        $out = [];
        foreach ($this->linguaEvents as $ev) {
            $d = abs(($ev['seconds'] ?? 0) - $sec);
            if ($d <= $win) {
                $out[] = $ev;
            }
        }

        return $out;
    }

    public function render()
    {
        return view('livewire.transcript-previewer');
    }

    /**
     * Find the nearest segment id to a given seconds timestamp (prefers <= sec)
     */
    public function segmentIdForSeconds(int $sec): string
    {
        if (empty($this->segments)) {
            return 'seg-000000';
        }
        // Exact or floor match
        $best = null;
        $bestDelta = PHP_INT_MAX;
        $floor = null;
        $floorTime = -1;
        foreach ($this->segments as $seg) {
            $s = (int) ($seg['seconds'] ?? 0);
            if ($s <= $sec && $s > $floorTime) {
                $floor = $seg;
                $floorTime = $s;
            }
            $d = abs($s - $sec);
            if ($d < $bestDelta) {
                $bestDelta = $d;
                $best = $seg;
            }
        }
        $target = $floor ?: ($best ?: $this->segments[0]);

        return (string) ($target['id'] ?? ('seg-'.str_pad((string) ($target['seconds'] ?? 0), 6, '0', STR_PAD_LEFT)));
    }
}
