<?php

namespace App\Services\Case;

use App\Models\CaseChronology;
use App\Services\LegalReasoning\FactPatternExtractor;
use Illuminate\Support\Facades\Log;

/**
 * Chronology Builder
 *
 * Builds detailed, visual chronologies from fact patterns. Essential
 * for case presentation, trial preparation, and understanding complex
 * timelines. Automatically organizes events, identifies gaps, and
 * suggests additional investigation.
 *
 * Use Case: After extracting facts, automatically generate a detailed
 * timeline that can be used in court presentations, client meetings,
 * and case analysis.
 */
class ChronologyBuilder
{
    public function __construct(
        protected FactPatternExtractor $factExtractor
    ) {}

    /**
     * Build comprehensive chronology
     *
     * @param  string  $factPatternId  UUID of fact pattern
     * @return CaseChronology Complete chronology with analysis
     */
    public function buildChronology(string $factPatternId): CaseChronology
    {
        $factPattern = $this->factExtractor->getFactPattern($factPatternId);

        if (! $factPattern) {
            throw new \Exception('Fact pattern not found');
        }

        Log::info('ChronologyBuilder - Starting', [
            'fact_pattern_id' => $factPatternId,
        ]);

        $facts = $factPattern->structured_facts;

        // Extract and sort all events
        $events = $this->extractAllEvents($facts);
        $sortedEvents = $this->sortEvents($events);

        // Convert events to proper format for storage (dates as strings)
        $eventsForStorage = $this->convertEventsForStorage($sortedEvents);

        // Identify time gaps
        $gaps = $this->identifyTimeGaps($sortedEvents);

        // Identify critical dates
        $criticalDates = $this->identifyCriticalDates($sortedEvents, $facts);

        // Generate timeline summary
        $timelineSummary = $this->generateTimelineSummary($sortedEvents, $facts);

        // Generate visualization data
        $visualizationData = $this->generateVisualizationDataForStorage($sortedEvents, $gaps);

        // Create or update chronology
        $chronology = CaseChronology::updateOrCreate(
            [
                'fact_pattern_id' => $factPatternId,
            ],
            [
                'user_id' => $factPattern->user_id,
                'events' => $eventsForStorage,
                'analysis' => [
                    'time_gaps' => $gaps,
                    'critical_dates' => $criticalDates,
                    'timeline_summary' => $timelineSummary,
                ],
                'visualization_data' => $visualizationData,
            ]
        );

        return $chronology;
    }

    /**
     * Extract all events from facts
     */
    protected function extractAllEvents(array $facts): array
    {
        $allEvents = [];

        // Main events
        $events = $facts['events'] ?? [];
        foreach ($events as $event) {
            $allEvents[] = [
                'date' => $this->parseDate($event['date'] ?? null),
                'description' => $event['description'] ?? 'Event',
                'significance' => $event['significance'] ?? null,
                'location' => $event['location'] ?? null,
                'category' => 'event',
                'source' => 'main_events',
            ];
        }

        // Timeline items
        $timeline = $facts['timeline'] ?? [];
        foreach ($timeline as $item) {
            $allEvents[] = [
                'date' => $this->parseDate($item['date'] ?? null),
                'description' => $item['event'] ?? 'Timeline event',
                'category' => 'timeline',
                'source' => 'timeline',
            ];
        }

        // Procedural events
        $posture = $facts['procedural_posture'] ?? [];
        if (! empty($posture['deadlines'])) {
            foreach ($posture['deadlines'] as $deadline) {
                $allEvents[] = [
                    'date' => $this->parseDate($deadline['date'] ?? null),
                    'description' => $deadline['description'] ?? 'Deadline',
                    'category' => 'procedural',
                    'source' => 'deadlines',
                ];
            }
        }

        // Evidence-related dates
        $evidence = $facts['evidence'] ?? [];
        foreach ($evidence as $item) {
            if (isset($item['date'])) {
                $allEvents[] = [
                    'date' => $this->parseDate($item['date']),
                    'description' => 'Evidence: '.($item['description'] ?? 'Unknown'),
                    'category' => 'evidence',
                    'source' => 'evidence',
                ];
            }
        }

        return $allEvents;
    }

    /**
     * Sort events chronologically
     */
    protected function sortEvents(array $events): array
    {
        usort($events, function ($a, $b) {
            $dateA = $a['date'] ?? null;
            $dateB = $b['date'] ?? null;

            if ($dateA === null && $dateB === null) {
                return 0;
            }
            if ($dateA === null) {
                return 1;
            }
            if ($dateB === null) {
                return -1;
            }

            return $dateA <=> $dateB;
        });

        // Add sequence numbers
        $sequenced = [];
        $counter = 1;
        foreach ($events as $event) {
            $event['sequence'] = $counter++;
            $sequenced[] = $event;
        }

        return $sequenced;
    }

    /**
     * Parse date string
     */
    protected function parseDate(?string $dateString): ?\DateTime
    {
        if (empty($dateString)) {
            return null;
        }

        try {
            return new \DateTime($dateString);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Identify time gaps
     */
    protected function identifyTimeGaps(array $sortedEvents): array
    {
        $gaps = [];

        for ($i = 0; $i < count($sortedEvents) - 1; $i++) {
            $current = $sortedEvents[$i];
            $next = $sortedEvents[$i + 1];

            $currentDate = $current['date'];
            $nextDate = $next['date'];

            if ($currentDate && $nextDate) {
                $diff = $currentDate->diff($nextDate);
                $daysDiff = $diff->days;

                // Flag gaps over 30 days
                if ($daysDiff > 30) {
                    $gaps[] = [
                        'after_event' => $current['description'],
                        'before_event' => $next['description'],
                        'days' => $daysDiff,
                        'date_range' => [
                            'from' => $currentDate->format('Y-m-d'),
                            'to' => $nextDate->format('Y-m-d'),
                        ],
                        'investigation_needed' => true,
                        'questions' => [
                            "What happened between {$currentDate->format('M d, Y')} and {$nextDate->format('M d, Y')}?",
                            'Are there any relevant events during this period?',
                        ],
                    ];
                }
            }
        }

        return $gaps;
    }

    /**
     * Categorize events
     */
    protected function categorizeEvents(array $events): array
    {
        $categorized = [
            'event' => [],
            'procedural' => [],
            'evidence' => [],
            'timeline' => [],
            'other' => [],
        ];

        foreach ($events as $event) {
            $category = $event['category'] ?? 'other';
            if (! isset($categorized[$category])) {
                $categorized[$category] = [];
            }
            $categorized[$category][] = $event;
        }

        return $categorized;
    }

    /**
     * Calculate date range
     */
    protected function calculateDateRange(array $events): array
    {
        $dates = array_filter(array_map(fn ($e) => $e['date'], $events));

        if (empty($dates)) {
            return [
                'start' => null,
                'end' => null,
                'duration_days' => 0,
            ];
        }

        $start = min($dates);
        $end = max($dates);

        return [
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
            'duration_days' => $start->diff($end)->days,
        ];
    }

    /**
     * Identify critical dates
     */
    protected function identifyCriticalDates(array $events, array $facts): array
    {
        $critical = [];

        // First event
        if (! empty($events)) {
            $critical[] = [
                'date' => $events[0]['date']?->format('Y-m-d'),
                'description' => $events[0]['description'],
                'reason' => 'First event in chronology - case inception',
            ];
        }

        // Events with significance
        foreach ($events as $event) {
            if (! empty($event['significance'])) {
                $critical[] = [
                    'date' => $event['date']?->format('Y-m-d'),
                    'description' => $event['description'],
                    'reason' => $event['significance'],
                ];
            }
        }

        // Procedural deadlines
        $posture = $facts['procedural_posture'] ?? [];
        foreach ($posture['deadlines'] ?? [] as $deadline) {
            $critical[] = [
                'date' => $deadline['date'] ?? null,
                'description' => $deadline['description'] ?? 'Deadline',
                'reason' => 'Court-imposed deadline',
            ];
        }

        return $critical;
    }

    /**
     * Convert events to storage format (dates as strings)
     */
    protected function convertEventsForStorage(array $events): array
    {
        $converted = [];

        foreach ($events as $event) {
            $converted[] = [
                'date' => $event['date'] ? $event['date']->format('Y-m-d') : null,
                'description' => $event['description'],
                'sequence' => $event['sequence'],
                'category' => $event['category'],
                'significance' => $event['significance'] ?? null,
                'location' => $event['location'] ?? null,
                'source' => $event['source'] ?? null,
            ];
        }

        return $converted;
    }

    /**
     * Generate timeline summary
     */
    protected function generateTimelineSummary(array $events, array $facts): string
    {
        if (empty($events)) {
            return 'No events in chronology.';
        }

        $dateRange = $this->calculateDateRange($events);
        $summary = "This chronology covers the period from {$dateRange['start']} to {$dateRange['end']}, ";
        $summary .= "spanning approximately {$dateRange['duration_days']} days. ";

        $summary .= 'There are '.count($events).' total events in the timeline. ';

        // Highlight key events
        $significantEvents = array_filter($events, fn ($e) => ! empty($e['significance']));
        if (! empty($significantEvents)) {
            $summary .= 'Key events include: ';
            $keyDescriptions = [];
            foreach (array_slice($significantEvents, 0, 3) as $event) {
                $keyDescriptions[] = $event['description'].' ('.$event['date']->format('M d, Y').')';
            }
            $summary .= implode(', ', $keyDescriptions).'. ';
        }

        return $summary;
    }

    /**
     * Generate visualization data for storage
     */
    protected function generateVisualizationDataForStorage(array $events, array $gaps): array
    {
        $timelineData = [];

        foreach ($events as $event) {
            $timelineData[] = [
                'x' => $event['date'] ? $event['date']->format('Y-m-d') : null,
                'y' => $event['sequence'],
                'label' => $event['description'],
                'category' => $event['category'],
            ];
        }

        $gapVisualization = [];
        foreach ($gaps as $gap) {
            $gapVisualization[] = [
                'start' => $gap['date_range']['from'],
                'end' => $gap['date_range']['to'],
                'duration' => $gap['days'],
            ];
        }

        return [
            'timeline_data' => $timelineData,
            'gap_visualization' => $gapVisualization,
        ];
    }

    /**
     * Identify missing information
     */
    protected function identifyMissingInformation(array $events): array
    {
        $missing = [];

        // Events without dates
        $undatedCount = count(array_filter($events, fn ($e) => $e['date'] === null));
        if ($undatedCount > 0) {
            $missing[] = [
                'issue' => "Missing dates for {$undatedCount} events",
                'priority' => 'high',
                'action' => 'Interview client to establish dates',
            ];
        }

        // Events without locations
        $withoutLocation = count(array_filter($events, fn ($e) => empty($e['location'])));
        if ($withoutLocation > count($events) / 2) {
            $missing[] = [
                'issue' => 'Many events lack location information',
                'priority' => 'medium',
                'action' => 'Document event locations',
            ];
        }

        return $missing;
    }

    /**
     * Identify investigation priorities
     */
    protected function identifyInvestigationPriorities(array $gaps, array $events): array
    {
        $priorities = [];

        // Large time gaps need investigation
        foreach ($gaps as $gap) {
            if ($gap['days'] > 90) {
                $priorities[] = [
                    'priority' => 'high',
                    'investigation' => "Investigate {$gap['days']}-day gap between events",
                    'date_range' => $gap['date_range'],
                    'questions' => $gap['questions'],
                ];
            }
        }

        // Events without sufficient detail
        foreach ($events as $event) {
            $desc = $event['description'] ?? '';
            if (strlen($desc) < 20) {
                $priorities[] = [
                    'priority' => 'medium',
                    'investigation' => 'Obtain more detail about: '.$desc,
                    'date' => $event['date']?->format('Y-m-d'),
                ];
            }
        }

        return $priorities;
    }

    /**
     * Generate visualization data
     */
    protected function generateVisualizationData(array $events): array
    {
        $timeline = [];

        foreach ($events as $event) {
            $timeline[] = [
                'date' => $event['date']?->format('Y-m-d'),
                'label' => $event['description'],
                'category' => $event['category'],
                'sequence' => $event['sequence'],
            ];
        }

        return [
            'type' => 'timeline',
            'data' => $timeline,
            'render_suggestions' => [
                'Use horizontal timeline for court presentation',
                'Color-code by category',
                'Highlight critical dates in red',
                'Show time gaps with dotted lines',
            ],
        ];
    }

    /**
     * Generate narrative timeline
     */
    protected function generateNarrativeTimeline(array $events): string
    {
        $narrative = "CASE CHRONOLOGY\n\n";

        foreach ($events as $event) {
            $date = $event['date'] ? $event['date']->format('F j, Y') : 'Unknown date';
            $description = $event['description'];

            $narrative .= "{$event['sequence']}. {$date}: {$description}\n";

            if (! empty($event['significance'])) {
                $narrative .= "   Significance: {$event['significance']}\n";
            }

            if (! empty($event['location'])) {
                $narrative .= "   Location: {$event['location']}\n";
            }

            $narrative .= "\n";
        }

        return $narrative;
    }

    /**
     * Generate visual chart (returns SVG or data for chart library)
     */
    public function generateVisualChart(string $factPatternId, string $format = 'data'): array
    {
        $chronology = $this->buildChronology($factPatternId);
        $events = $chronology['chronology']['events'];

        // Prepare data for chart libraries (e.g., Chart.js, D3.js)
        $chartData = [
            'labels' => [],
            'datasets' => [
                [
                    'label' => 'Timeline Events',
                    'data' => [],
                    'backgroundColor' => [],
                ],
            ],
        ];

        $colors = [
            'event' => '#3B82F6',
            'procedural' => '#EF4444',
            'evidence' => '#10B981',
            'timeline' => '#F59E0B',
        ];

        foreach ($events as $event) {
            $chartData['labels'][] = $event['date']?->format('M d, Y') ?? 'Unknown';
            $chartData['datasets'][0]['data'][] = $event['sequence'];
            $chartData['datasets'][0]['backgroundColor'][] = $colors[$event['category']] ?? '#6B7280';
        }

        return $chartData;
    }

    /**
     * Export chronology in various formats
     */
    public function exportChronology(string $chronologyId, string $format = 'text'): string
    {
        $chronology = CaseChronology::findOrFail($chronologyId);

        return match ($format) {
            'text' => $this->convertToText($chronology),
            'json' => $this->convertToJSON($chronology),
            'csv' => $this->convertToCSV($chronology->events),
            'markdown' => $this->convertToMarkdown($chronology),
            default => $this->convertToText($chronology),
        };
    }

    /**
     * Convert to text format
     */
    protected function convertToText(CaseChronology $chronology): string
    {
        $text = "CASE CHRONOLOGY\n\n";

        foreach ($chronology->events as $event) {
            $date = $event['date'] ?? 'Unknown date';
            $description = $event['description'];

            $text .= "{$event['sequence']}. {$date}: {$description}\n";

            if (! empty($event['significance'])) {
                $text .= "   Significance: {$event['significance']}\n";
            }

            if (! empty($event['location'])) {
                $text .= "   Location: {$event['location']}\n";
            }

            $text .= "\n";
        }

        return $text;
    }

    /**
     * Convert to JSON format
     */
    protected function convertToJSON(CaseChronology $chronology): string
    {
        return json_encode([
            'events' => $chronology->events,
            'analysis' => $chronology->analysis,
            'visualization_data' => $chronology->visualization_data,
        ], JSON_PRETTY_PRINT);
    }

    /**
     * Convert to CSV
     */
    protected function convertToCSV(array $events): string
    {
        $csv = "Date,Sequence,Description,Category\n";

        foreach ($events as $event) {
            $csv .= sprintf(
                "%s,%d,%s,%s\n",
                $event['date'] ?? '',
                $event['sequence'],
                '"'.str_replace('"', '""', $event['description']).'"',
                $event['category']
            );
        }

        return $csv;
    }

    /**
     * Convert to Markdown
     */
    protected function convertToMarkdown(CaseChronology $chronology): string
    {
        $md = "# Case Chronology\n\n";

        $md .= '**Total Events:** '.count($chronology->events)."\n\n";

        $md .= "## Timeline\n\n";

        foreach ($chronology->events as $event) {
            $date = $event['date'] ?? 'Unknown date';
            $md .= "### {$event['sequence']}. {$date}\n\n";
            $md .= "{$event['description']}\n\n";

            if (! empty($event['significance'])) {
                $md .= "**Significance:** {$event['significance']}\n\n";
            }
        }

        // Add gaps section
        if (! empty($chronology->analysis['time_gaps'])) {
            $md .= "## Time Gaps Requiring Investigation\n\n";
            foreach ($chronology->analysis['time_gaps'] as $gap) {
                $md .= "- **{$gap['days']} days** between \"{$gap['after_event']}\" and \"{$gap['before_event']}\"\n";
            }
            $md .= "\n";
        }

        return $md;
    }
}
