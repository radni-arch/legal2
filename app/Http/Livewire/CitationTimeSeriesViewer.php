<?php

namespace App\Http\Livewire;

use App\Models\CitationTimeSeries;
use App\Models\CourtDecision;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

/**
 * Citation Time Series Viewer
 *
 * Interactive visualization of citation trends over time for court decisions.
 *
 * Features:
 * - Multiple period types (daily, weekly, monthly, yearly)
 * - Line and bar chart visualizations
 * - Date range filtering
 * - CSV and PDF export
 * - Statistics panel
 * - Interactive tooltips
 * - Responsive design
 * - Croatian language support
 */
class CitationTimeSeriesViewer extends Component
{
    // Note: WithFileDownloads trait removed (not available in Livewire 3)

    // Data
    public ?CourtDecision $decision = null;

    public array $timeSeriesData = [];

    // Period selection
    public string $selectedPeriod = 'monthly';

    public array $availablePeriods = [
        'daily' => 'Dnevno',
        'weekly' => 'Tjedno',
        'monthly' => 'Mjesečno',
        'yearly' => 'Godišnje',
    ];

    // Chart options
    public string $chartType = 'line';

    public array $chartTypes = [
        'line' => 'Linijski',
        'bar' => 'Stupčasti',
    ];

    // Filtering
    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public array $filteredData = [];

    // Statistics
    public array $statistics = [];

    // UI state
    public bool $loading = false;

    public ?string $error = null;

    public ?string $successMessage = null;

    /**
     * Mount the component
     */
    public function mount(?string $decision_id = null): void
    {
        if ($decision_id) {
            $this->loadDecision($decision_id);
        }

        // Set default date range to last 6 months
        $now = now();
        $this->dateTo = $now->toDateString();
        $this->dateFrom = $now->subMonths(6)->toDateString();
    }

    /**
     * Load decision and its time series data
     */
    protected function loadDecision(string $decisionId): void
    {
        try {
            $this->loading = true;
            $this->error = null;

            $this->decision = CourtDecision::findOrFail($decisionId);

            // Load time series data for the selected period
            $this->loadTimeSeriesData();

            // Calculate statistics
            $this->calculateStatistics();

            $this->loading = false;

        } catch (\Exception $e) {
            Log::error('CitationTimeSeriesViewer: Failed to load decision', [
                'decision_id' => $decisionId,
                'error' => $e->getMessage(),
            ]);

            $this->error = 'Decision not found. No time series data available.';
            $this->loading = false;
        }
    }

    /**
     * Load time series data for selected period
     */
    protected function loadTimeSeriesData(): void
    {
        if (! $this->decision) {
            return;
        }

        try {
            $query = CitationTimeSeries::query()
                ->where('decision_id', $this->decision->id)
                ->where('period_type', $this->selectedPeriod)
                ->orderBy('period_start', 'desc');

            $this->timeSeriesData = $query->get()
                ->map(function ($record) {
                    return [
                        'id' => $record->id,
                        'period_type' => $record->period_type,
                        'period_start' => $record->period_start->toDateString(),
                        'period_end' => $record->period_end->toDateString(),
                        'period_label' => $this->formatPeriodLabel($record->period_start, $this->selectedPeriod),
                        'citation_count' => $record->citation_count,
                        'incoming_citations' => $record->incoming_citations,
                        'outgoing_citations' => $record->outgoing_citations,
                        'avg_citation_importance' => (float) $record->avg_citation_importance,
                        'citing_courts' => $record->citing_courts ?? [],
                        'top_citing_decisions' => $record->top_citing_decisions ?? [],
                        'trend' => $record->trend,
                    ];
                })
                ->toArray();

            // Apply date filter
            $this->applyDateFilter();

        } catch (\Exception $e) {
            Log::error('CitationTimeSeriesViewer: Failed to load time series data', [
                'decision_id' => $this->decision?->id,
                'period' => $this->selectedPeriod,
                'error' => $e->getMessage(),
            ]);

            $this->error = 'Failed to load time series data.';
        }
    }

    /**
     * Apply date range filter
     */
    public function applyDateFilter(): void
    {
        if (! $this->dateFrom || ! $this->dateTo) {
            $this->filteredData = $this->timeSeriesData;

            return;
        }

        $dateFrom = \Carbon\Carbon::parse($this->dateFrom);
        $dateTo = \Carbon\Carbon::parse($this->dateTo);

        $this->filteredData = array_filter(
            $this->timeSeriesData,
            function ($record) use ($dateFrom, $dateTo) {
                $recordDate = \Carbon\Carbon::parse($record['period_start']);

                return $recordDate->between($dateFrom, $dateTo);
            }
        );

        $this->filteredData = array_values($this->filteredData);
        $this->calculateStatistics();
    }

    /**
     * Reset filters
     */
    public function resetFilters(): void
    {
        $now = now();
        $this->dateFrom = $now->subMonths(6)->toDateString();
        $this->dateTo = $now->toDateString();
        $this->selectedPeriod = 'monthly';
        $this->chartType = 'line';

        if ($this->decision) {
            $this->loadTimeSeriesData();
        }

        $this->successMessage = 'Filtri su resetirani.';
    }

    /**
     * Change selected period
     */
    public function selectPeriod(string $period): void
    {
        if (! in_array($period, array_keys($this->availablePeriods))) {
            return;
        }

        $this->selectedPeriod = $period;

        if ($this->decision) {
            $this->loadTimeSeriesData();
        }
    }

    /**
     * Change chart type
     */
    public function selectChartType(string $type): void
    {
        if (! in_array($type, array_keys($this->chartTypes))) {
            return;
        }

        $this->chartType = $type;
    }

    /**
     * Calculate statistics from filtered data
     */
    protected function calculateStatistics(): void
    {
        $data = ! empty($this->filteredData) ? $this->filteredData : $this->timeSeriesData;

        if (empty($data)) {
            $this->statistics = [
                'total_citations' => 0,
                'avg_importance' => 0,
                'total_periods' => 0,
                'max_citations' => 0,
                'min_citations' => 0,
                'latest_period' => null,
                'growth_trend' => 'stable',
            ];

            return;
        }

        $citationCounts = array_column($data, 'citation_count');
        $importances = array_column($data, 'avg_citation_importance');

        $totalCitations = array_sum($citationCounts);
        $avgImportance = ! empty($importances)
            ? array_sum($importances) / count($importances)
            : 0;

        // Calculate growth trend
        $growthTrend = 'stable';
        if (count($data) >= 2) {
            $first = $data[count($data) - 1]['citation_count'];
            $last = $data[0]['citation_count'];
            if ($last > $first) {
                $growthTrend = 'up';
            } elseif ($last < $first) {
                $growthTrend = 'down';
            }
        }

        $this->statistics = [
            'total_citations' => $totalCitations,
            'avg_importance' => round($avgImportance, 2),
            'total_periods' => count($data),
            'max_citations' => max($citationCounts),
            'min_citations' => min($citationCounts),
            'latest_period' => $data[0]['period_label'] ?? null,
            'growth_trend' => $growthTrend,
        ];
    }

    /**
     * Format period label based on period type
     */
    protected function formatPeriodLabel($date, string $periodType): string
    {
        $date = \Carbon\Carbon::parse($date);

        return match ($periodType) {
            'daily' => $date->format('d. m. Y.'),
            'weekly' => sprintf('Tjedan %d (%s)', $date->weekOfYear, $date->format('Y')),
            'monthly' => $date->format('F Y.'),
            'yearly' => $date->format('Y.'),
            default => $date->toDateString(),
        };
    }

    /**
     * Export data to CSV
     */
    public function exportCsv(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $data = ! empty($this->filteredData) ? $this->filteredData : $this->timeSeriesData;

        if (empty($data)) {
            $this->error = 'Nema podataka za izvoz.';

            return response()->stream(function () {}, 200);
        }

        $filename = sprintf(
            'citations_%s_%s.csv',
            $this->decision?->case_number ?? 'unknown',
            now()->format('Y-m-d-H-i-s')
        );

        return response()->stream(
            function () use ($data) {
                $handle = fopen('php://output', 'w');

                // Header
                fputcsv($handle, [
                    'Razdoblje',
                    'Broj citiranja',
                    'Dolazna citiranja',
                    'Odlazna citiranja',
                    'Prosječna važnost',
                    'Trend',
                ]);

                // Data rows
                foreach ($data as $record) {
                    fputcsv($handle, [
                        $record['period_label'],
                        $record['citation_count'],
                        $record['incoming_citations'],
                        $record['outgoing_citations'],
                        $record['avg_citation_importance'],
                        $record['trend'],
                    ]);
                }

                fclose($handle);
            },
            200,
            [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]
        );
    }

    /**
     * Export data to PDF
     */
    public function exportPdf()
    {
        $data = ! empty($this->filteredData) ? $this->filteredData : $this->timeSeriesData;

        if (empty($data)) {
            $this->error = 'Nema podataka za izvoz.';

            return;
        }

        $filename = sprintf(
            'citations_%s_%s.pdf',
            $this->decision?->case_number ?? 'unknown',
            now()->format('Y-m-d-H-i-s')
        );

        // Generate HTML report
        $html = $this->generatePdfHtml($data);

        // TODO: Implement PDF download using Laravel's response()->download()
        // For now, just flash a message
        session()->flash('message', 'PDF export not yet implemented');

        return null;
    }

    /**
     * Generate HTML for PDF report
     */
    protected function generatePdfHtml(array $data): string
    {
        $courtName = $this->decision?->court ?? 'Unknown';
        $caseNumber = $this->decision?->case_number ?? 'Unknown';
        $title = $this->decision?->title ?? 'N/A';
        $generatedAt = now()->format('d. m. Y. H:i');

        $tableRows = '';
        foreach ($data as $record) {
            $tableRows .= sprintf(
                '<tr><td>%s</td><td>%d</td><td>%d</td><td>%d</td><td>%.2f</td></tr>',
                $record['period_label'],
                $record['citation_count'],
                $record['incoming_citations'],
                $record['outgoing_citations'],
                $record['avg_citation_importance']
            );
        }

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Izvještaj o vremenskom nizu citiranja</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1 { color: #333; }
        .metadata { background: #f5f5f5; padding: 15px; border-radius: 5px; margin: 15px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f0f0f0; font-weight: bold; }
        .generated { font-size: 12px; color: #999; margin-top: 20px; }
    </style>
</head>
<body>
    <h1>Izvještaj o vremenskom nizu citiranja</h1>

    <div class="metadata">
        <p><strong>Broj slučaja:</strong> {$caseNumber}</p>
        <p><strong>Naslov:</strong> {$title}</p>
        <p><strong>Sud:</strong> {$courtName}</p>
        <p><strong>Tip razdoblja:</strong> {$this->availablePeriods[$this->selectedPeriod]}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Razdoblje</th>
                <th>Broj citiranja</th>
                <th>Dolazna citiranja</th>
                <th>Odlazna citiranja</th>
                <th>Prosječna važnost</th>
            </tr>
        </thead>
        <tbody>
            {$tableRows}
        </tbody>
    </table>

    <div class="generated">
        <p>Generirano: {$generatedAt}</p>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Get chart data formatted for Chart.js
     */
    public function getChartData(): array
    {
        $data = ! empty($this->filteredData) ? $this->filteredData : $this->timeSeriesData;

        if (empty($data)) {
            return [
                'labels' => [],
                'datasets' => [],
            ];
        }

        // Sort by date ascending for chart
        usort($data, function ($a, $b) {
            return strcmp($a['period_start'], $b['period_start']);
        });

        $labels = array_column($data, 'period_label');
        $citationCounts = array_column($data, 'citation_count');
        $importances = array_map(fn ($val) => round($val * 100), array_column($data, 'avg_citation_importance'));

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Broj citiranja',
                    'data' => $citationCounts,
                    'borderColor' => 'rgb(34, 211, 238)',
                    'backgroundColor' => 'rgba(34, 211, 238, 0.1)',
                    'fill' => true,
                    'tension' => 0.4,
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'Prosječna važnost (%)',
                    'data' => $importances,
                    'borderColor' => 'rgb(239, 68, 68)',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                    'fill' => true,
                    'tension' => 0.4,
                    'yAxisID' => 'y1',
                ],
            ],
        ];
    }

    /**
     * Get court hierarchy level for styling
     */
    public function getCourtHierarchyLevel(): int
    {
        if (! $this->decision) {
            return 1;
        }

        $court = mb_strtolower($this->decision->court ?? '', 'UTF-8');

        if (str_contains($court, 'vrhovni') || str_contains($court, 'ustavni')) {
            return 5;
        } elseif (str_contains($court, 'visoki') || str_contains($court, 'žalbeni')) {
            return 4;
        } elseif (str_contains($court, 'županijski')) {
            return 3;
        } elseif (str_contains($court, 'općinski')) {
            return 2;
        }

        return 1;
    }

    /**
     * Get top citing courts
     */
    public function getTopCitingCourts(): array
    {
        $data = ! empty($this->filteredData) ? $this->filteredData : $this->timeSeriesData;

        $courtCounts = [];
        foreach ($data as $record) {
            foreach ($record['citing_courts'] as $court) {
                $courtCounts[$court] = ($courtCounts[$court] ?? 0) + 1;
            }
        }

        arsort($courtCounts);

        return array_slice($courtCounts, 0, 5);
    }

    public function render()
    {
        return view('livewire.citation-time-series-viewer', [
            'chartData' => $this->getChartData(),
            'topCitingCourts' => $this->getTopCitingCourts(),
            'courtHierarchyLevel' => $this->getCourtHierarchyLevel(),
        ]);
    }
}
