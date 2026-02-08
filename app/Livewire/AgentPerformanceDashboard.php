<?php

namespace App\Livewire;

use App\Models\LearningOpportunity;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Agent Performance Dashboard Component
 *
 * Sprint 5.5: Agent Performance Dashboards
 *
 * Displays comprehensive agent performance metrics including:
 * - Total runs, success rate, average confidence
 * - Learning opportunities, feedback incorporation rate
 * - Time-series charts showing performance trends
 * - Before/after active learning comparison charts
 * - Filtering by agent type and date range
 */
class AgentPerformanceDashboard extends Component
{
    // Filter properties
    public $filterAgentType = '';

    public $filterDateFrom = '';

    public $filterDateTo = '';

    /**
     * Get base query with filters applied
     */
    protected function getFilteredQuery()
    {
        $query = LearningOpportunity::query();

        // Filter by agent type if specified
        if (! empty($this->filterAgentType)) {
            $query->where('opportunity_type', $this->filterAgentType);
        }

        // Filter by date range if specified
        if (! empty($this->filterDateFrom)) {
            $query->where('created_at', '>=', $this->filterDateFrom);
        }

        if (! empty($this->filterDateTo)) {
            $query->where('created_at', '<=', $this->filterDateTo.' 23:59:59');
        }

        return $query;
    }

    /**
     * Calculate total runs metric
     */
    public function getTotalRunsProperty()
    {
        return $this->getFilteredQuery()->count();
    }

    /**
     * Calculate success rate (reviewed opportunities with high confidence)
     */
    public function getSuccessRateProperty()
    {
        $total = $this->getFilteredQuery()->count();

        if ($total === 0) {
            return 0;
        }

        // Success = reviewed status with confidence >= 0.80
        $successful = $this->getFilteredQuery()
            ->where('status', 'reviewed')
            ->where('confidence_score', '>=', 0.80)
            ->count();

        return round(($successful / $total) * 100);
    }

    /**
     * Calculate average confidence score
     */
    public function getAverageConfidenceProperty()
    {
        $avg = $this->getFilteredQuery()->avg('confidence_score');

        return $avg ? number_format(round($avg, 2), 2) : '0.00';
    }

    /**
     * Count learning opportunities generated
     */
    public function getLearningOpportunitiesProperty()
    {
        return $this->getFilteredQuery()->count();
    }

    /**
     * Calculate feedback incorporation rate
     */
    public function getFeedbackIncorporationRateProperty()
    {
        $reviewed = $this->getFilteredQuery()
            ->where('status', 'reviewed')
            ->count();

        if ($reviewed === 0) {
            return 0;
        }

        $incorporated = $this->getFilteredQuery()
            ->where('status', 'reviewed')
            ->whereNotNull('incorporated_at')
            ->count();

        return round(($incorporated / $reviewed) * 100);
    }

    /**
     * Get time series chart data
     */
    public function getTimeSeriesDataProperty()
    {
        $opportunities = $this->getFilteredQuery()
            ->orderBy('created_at')
            ->get()
            ->groupBy(fn ($item) => $item->created_at->format('Y-m-d'));

        $labels = [];
        $confidenceData = [];

        foreach ($opportunities as $date => $items) {
            $labels[] = $date;
            $confidenceData[] = round($items->avg('confidence_score'), 2);
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Average Confidence',
                    'data' => $confidenceData,
                ],
            ],
        ];
    }

    /**
     * Get comparison data (before vs after active learning)
     */
    public function getComparisonDataProperty()
    {
        // Check if we have any incorporated data (active learning applied)
        $incorporatedCount = $this->getFilteredQuery()
            ->whereNotNull('incorporated_at')
            ->count();

        if ($incorporatedCount > 0) {
            // Use incorporated_at as split point (active learning comparison)
            $beforeOpportunities = $this->getFilteredQuery()
                ->whereNull('incorporated_at')
                ->get();

            $afterOpportunities = $this->getFilteredQuery()
                ->whereNotNull('incorporated_at')
                ->get();
        } else {
            // Fall back to date-based comparison (older vs newer data)
            $allOpportunities = $this->getFilteredQuery()
                ->orderBy('created_at')
                ->get();

            $count = $allOpportunities->count();

            if ($count > 0) {
                // Split at midpoint by count
                $midpointIndex = (int) floor($count / 2);
                $beforeOpportunities = $allOpportunities->take($midpointIndex);
                $afterOpportunities = $allOpportunities->slice($midpointIndex);
            } else {
                $beforeOpportunities = collect();
                $afterOpportunities = collect();
            }
        }

        return [
            'before' => [
                'avg_confidence' => round($beforeOpportunities->avg('confidence_score') ?? 0, 2),
                'count' => $beforeOpportunities->count(),
            ],
            'after' => [
                'avg_confidence' => round($afterOpportunities->avg('confidence_score') ?? 0, 2),
                'count' => $afterOpportunities->count(),
            ],
        ];
    }

    /**
     * Get agent type breakdown
     */
    public function getAgentTypeBreakdownProperty()
    {
        return $this->getFilteredQuery()
            ->select('opportunity_type', DB::raw('count(*) as count'))
            ->groupBy('opportunity_type')
            ->get()
            ->mapWithKeys(fn ($item) => [$item->opportunity_type => $item->count])
            ->toArray();
    }

    /**
     * Calculate improvement metrics
     */
    public function getImprovementMetricsProperty()
    {
        $comparisonData = $this->comparisonData;

        $beforeAvg = $comparisonData['before']['avg_confidence'];
        $afterAvg = $comparisonData['after']['avg_confidence'];

        if ($beforeAvg == 0) {
            return ['confidence_improvement' => 0];
        }

        $improvement = (($afterAvg - $beforeAvg) / $beforeAvg) * 100;

        return [
            'confidence_improvement' => round($improvement, 2),
        ];
    }

    /**
     * Refresh dashboard data
     */
    public function refreshDashboard()
    {
        // Livewire will automatically re-render with fresh data
        $this->dispatch('dashboard-refreshed');
    }

    /**
     * Get export data
     */
    public function getExportData()
    {
        return [
            'metrics' => [
                'total_runs' => $this->totalRuns,
                'success_rate' => $this->successRate,
                'average_confidence' => $this->averageConfidence,
                'learning_opportunities' => $this->learningOpportunities,
                'feedback_incorporation_rate' => $this->feedbackIncorporationRate,
            ],
            'time_series' => $this->timeSeriesData,
            'comparison' => $this->comparisonData,
        ];
    }

    /**
     * Render the component
     */
    public function render()
    {
        $hasData = $this->totalRuns > 0;

        return view('livewire.agent-performance-dashboard', [
            'totalRuns' => $this->totalRuns,
            'successRate' => $this->successRate,
            'averageConfidence' => $this->averageConfidence,
            'learningOpportunities' => $this->learningOpportunities,
            'feedbackIncorporationRate' => $this->feedbackIncorporationRate,
            'timeSeriesData' => $this->timeSeriesData,
            'comparisonData' => $this->comparisonData,
            'agentTypeBreakdown' => $this->agentTypeBreakdown,
            'improvementMetrics' => $this->improvementMetrics,
            'exportData' => $this->getExportData(),
            'hasData' => $hasData,
        ]);
    }
}
