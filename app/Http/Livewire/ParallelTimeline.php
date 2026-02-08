<?php

namespace App\Http\Livewire;

use Livewire\Component;

/**
 * ParallelTimeline Livewire Component
 *
 * Displays events in a parallel two-lane timeline visualization, allowing users
 * to compare and track concurrent proceedings or events. Useful for showing
 * two parallel legal proceedings, case timelines, or comparative legal analysis.
 *
 * Features:
 * - Dual-lane timeline display (top and bottom lanes)
 * - Event display with metadata (counts, dates, status)
 * - Filter capabilities by lane and event type
 * - Navigation between days and date ranges
 * - Responsive grid layout (1 column on mobile, 2 columns on desktop)
 * - Empty state handling
 * - Event grouping and organization
 *
 * @property array $dataTop Top lane events data
 * @property array $dataBottom Bottom lane events data
 * @property string $day Current day being displayed (format: YYYY-MM-DD or descriptive)
 * @property string $filterLane Currently filtered lane (all|top|bottom)
 * @property string $filterEventType Currently filtered event type
 * @property string $sortField Field to sort by (date|title)
 * @property string $sortDirection Sort direction (asc|desc)
 * @property bool $showEventDetails Show detailed view for selected event
 * @property array $selectedEvent Currently selected event for details view
 * @property string $viewMode Current view mode (timeline|list|comparison)
 * @property bool $isResponsiveLayout Is responsive mobile layout active
 * @property string $dateRange Selected date range for filtering (all|week|month|custom)
 * @property string $startDate Start date for custom range filtering
 * @property string $endDate End date for custom range filtering
 * @property bool $showFilterPanel Show filter configuration panel
 * @property bool $autoUpdate Enable automatic updates of timeline
 * @property int $updateInterval Interval in seconds for auto-update (default: 30)
 */
class ParallelTimeline extends Component
{
    /**
     * Top lane events data
     */
    public array $dataTop = [];

    /**
     * Bottom lane events data
     */
    public array $dataBottom = [];

    /**
     * Current day being displayed
     */
    public string $day = '2025-06-09';

    /**
     * Currently filtered lane: all|top|bottom
     */
    public string $filterLane = 'all';

    /**
     * Currently filtered event type (empty string for all types)
     */
    public string $filterEventType = '';

    /**
     * Sort field: date|title
     */
    public string $sortField = 'date';

    /**
     * Sort direction: asc|desc
     */
    public string $sortDirection = 'asc';

    /**
     * Show detailed event view
     */
    public bool $showEventDetails = false;

    /**
     * Currently selected event for details
     */
    public array $selectedEvent = [];

    /**
     * View mode: timeline|list|comparison
     */
    public string $viewMode = 'timeline';

    /**
     * Responsive mobile layout active
     */
    public bool $isResponsiveLayout = false;

    /**
     * Date range filter: all|week|month|custom
     */
    public string $dateRange = 'all';

    /**
     * Start date for custom range filtering
     */
    public string $startDate = '';

    /**
     * End date for custom range filtering
     */
    public string $endDate = '';

    /**
     * Show filter panel
     */
    public bool $showFilterPanel = false;

    /**
     * Enable automatic updates
     */
    public bool $autoUpdate = false;

    /**
     * Auto-update interval in seconds
     */
    public int $updateInterval = 30;

    /**
     * Initialize component with sample data
     */
    public function mount(): void
    {
        // Initialize with sample data for demonstration
        $this->dataTop = [
            [
                'id' => 'event-top-1',
                'title' => 'Optužnica podnesena',
                'description' => 'Državno odvjetništvo podnijelo optužnicu',
                'type' => 'filing',
                'date' => '2025-06-09',
                'time' => '09:00',
                'status' => 'completed',
                'priority' => 'high',
            ],
            [
                'id' => 'event-top-2',
                'title' => 'Pripremno ročište',
                'description' => 'Održano pripremno ročište pred sudom',
                'type' => 'hearing',
                'date' => '2025-06-09',
                'time' => '14:00',
                'status' => 'completed',
                'priority' => 'medium',
            ],
        ];

        $this->dataBottom = [
            [
                'id' => 'event-bottom-1',
                'title' => 'Pretraga doma',
                'description' => 'Obavljena pretraga doma prema naredbi suda',
                'type' => 'action',
                'date' => '2025-06-09',
                'time' => '11:00',
                'status' => 'completed',
                'priority' => 'high',
            ],
            [
                'id' => 'event-bottom-2',
                'title' => 'Dostava dokaza',
                'description' => 'Dostava dokaza od istrage',
                'type' => 'filing',
                'date' => '2025-06-09',
                'time' => '16:00',
                'status' => 'pending',
                'priority' => 'medium',
            ],
        ];
    }

    /**
     * Filter events by selected lane
     */
    public function filterByLane(string $lane): void
    {
        $this->filterLane = $lane;
        $this->dispatch('lane-filtered', lane: $lane);
    }

    /**
     * Filter events by type (filing, hearing, action, etc.)
     */
    public function filterByEventType(string $eventType): void
    {
        $this->filterEventType = $eventType;
        $this->dispatch('event-type-filtered', eventType: $eventType);
    }

    /**
     * Clear all active filters
     */
    public function clearFilters(): void
    {
        $this->filterLane = 'all';
        $this->filterEventType = '';
        $this->dateRange = 'all';
        $this->dispatch('filters-cleared');
    }

    /**
     * Change sort field and direction
     */
    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
        $this->dispatch('sorted', field: $field, direction: $this->sortDirection);
    }

    /**
     * Toggle detailed view for an event
     */
    public function toggleEventDetails(string $eventId): void
    {
        // Find event in either lane
        $topEvent = collect($this->dataTop)->firstWhere('id', $eventId);
        $bottomEvent = collect($this->dataBottom)->firstWhere('id', $eventId);

        $this->selectedEvent = $topEvent ?? $bottomEvent ?? [];
        $this->showEventDetails = ! $this->showEventDetails;

        if ($this->showEventDetails && ! empty($this->selectedEvent)) {
            $this->dispatch('event-selected', event: $this->selectedEvent);
        }
    }

    /**
     * Close event details panel
     */
    public function closeEventDetails(): void
    {
        $this->showEventDetails = false;
        $this->selectedEvent = [];
    }

    /**
     * Change view mode (timeline, list, or comparison)
     */
    public function changeViewMode(string $mode): void
    {
        if (in_array($mode, ['timeline', 'list', 'comparison'])) {
            $this->viewMode = $mode;
            $this->dispatch('view-mode-changed', mode: $mode);
        }
    }

    /**
     * Navigate to previous day
     */
    public function previousDay(): void
    {
        // Parse current day and subtract 1 day
        try {
            $date = \Carbon\Carbon::parse($this->day);
            $this->day = $date->subDay()->format('Y-m-d');
            $this->dispatch('day-changed', day: $this->day);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error navigating to previous day', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Navigate to next day
     */
    public function nextDay(): void
    {
        // Parse current day and add 1 day
        try {
            $date = \Carbon\Carbon::parse($this->day);
            $this->day = $date->addDay()->format('Y-m-d');
            $this->dispatch('day-changed', day: $this->day);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error navigating to next day', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Jump to specific date
     */
    public function jumpToDate(string $date): void
    {
        try {
            \Carbon\Carbon::parse($date);
            $this->day = $date;
            $this->dispatch('day-changed', day: $this->day);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Invalid date format', ['date' => $date]);
        }
    }

    /**
     * Toggle filter panel visibility
     */
    public function toggleFilterPanel(): void
    {
        $this->showFilterPanel = ! $this->showFilterPanel;
    }

    /**
     * Apply date range filter
     */
    public function applyDateRange(string $range): void
    {
        $this->dateRange = $range;
        $this->dispatch('date-range-changed', range: $range);
    }

    /**
     * Toggle automatic updates
     */
    public function toggleAutoUpdate(): void
    {
        $this->autoUpdate = ! $this->autoUpdate;
        $this->dispatch('auto-update-toggled', enabled: $this->autoUpdate);
    }

    /**
     * Refresh timeline data from backend
     */
    public function refreshTimeline(): void
    {
        $this->dispatch('timeline-refreshing');
        // Simulate refresh - in real implementation would fetch from database
        $this->mount();
        $this->dispatch('timeline-refreshed');
    }

    /**
     * Export timeline as PDF
     */
    public function exportPdf(): void
    {
        $this->dispatch('export-pdf-requested');
    }

    /**
     * Export timeline as CSV
     */
    public function exportCsv(): void
    {
        $this->dispatch('export-csv-requested');
    }

    /**
     * Get filtered top lane events
     */
    public function getFilteredTopLaneEvents(): array
    {
        return $this->getFilteredEvents($this->dataTop);
    }

    /**
     * Get filtered bottom lane events
     */
    public function getFilteredBottomLaneEvents(): array
    {
        return $this->getFilteredEvents($this->dataBottom);
    }

    /**
     * Apply all active filters to events
     *
     * @param  array  $events  Events to filter
     */
    private function getFilteredEvents(array $events): array
    {
        $filtered = collect($events);

        // Apply event type filter
        if (! empty($this->filterEventType)) {
            $filtered = $filtered->where('type', $this->filterEventType);
        }

        // Apply date range filter
        if ($this->dateRange !== 'all') {
            $filtered = $filtered->filter(function ($event) {
                try {
                    $eventDate = \Carbon\Carbon::parse($event['date']);
                    $now = \Carbon\Carbon::now();

                    return match ($this->dateRange) {
                        'week' => $eventDate->diffInDays($now) <= 7,
                        'month' => $eventDate->diffInDays($now) <= 30,
                        default => true,
                    };
                } catch (\Exception $e) {
                    return true;
                }
            });
        }

        // Apply sorting
        $sorted = $filtered->sortBy(function ($event) {
            return match ($this->sortField) {
                'title' => $event['title'],
                'date' => $event['date'].' '.($event['time'] ?? '00:00'),
                default => $event['date'],
            };
        });

        if ($this->sortDirection === 'desc') {
            $sorted = $sorted->reverse();
        }

        return $sorted->values()->toArray();
    }

    /**
     * Get event type color class
     *
     * @param  string  $type  Event type
     */
    public function getEventTypeColor(string $type): string
    {
        return match ($type) {
            'filing' => 'event-filing',
            'hearing' => 'event-hearing',
            'action' => 'event-action',
            'deadline' => 'event-deadline',
            'decision' => 'event-decision',
            default => 'event-default',
        };
    }

    /**
     * Get priority badge color
     *
     * @param  string  $priority  Priority level
     */
    public function getPriorityBadgeColor(string $priority): string
    {
        return match ($priority) {
            'high' => 'priority-high',
            'medium' => 'priority-medium',
            'low' => 'priority-low',
            default => 'priority-default',
        };
    }

    /**
     * Get event count for top lane
     */
    public function getTopLaneEventCount(): int
    {
        return count($this->getFilteredTopLaneEvents());
    }

    /**
     * Get event count for bottom lane
     */
    public function getBottomLaneEventCount(): int
    {
        return count($this->getFilteredBottomLaneEvents());
    }

    /**
     * Check if events exist in either lane
     */
    public function hasEvents(): bool
    {
        return ! empty($this->dataTop) || ! empty($this->dataBottom);
    }

    /**
     * Render the component view
     *
     * @return \Illuminate\View\View
     */
    public function render()
    {
        return view('livewire.parallel-timeline', [
            'filteredTopEvents' => $this->getFilteredTopLaneEvents(),
            'filteredBottomEvents' => $this->getFilteredBottomLaneEvents(),
            'topLaneCount' => $this->getTopLaneEventCount(),
            'bottomLaneCount' => $this->getBottomLaneEventCount(),
        ])->layout('layouts.app', ['title' => 'Parallel Timeline']);
    }
}
