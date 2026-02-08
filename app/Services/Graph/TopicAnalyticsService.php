<?php

namespace App\Services\Graph;

use App\Services\GraphDatabaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Topic Analytics Service (Sprint 8.2)
 *
 * Detects topic spikes and trends in court decision topics.
 *
 * Algorithm:
 * - Baseline: 4-week rolling average of decisions per topic
 * - Spike threshold: Configurable % increase from baseline (default 50%)
 * - Significance filter: Absolute count must be >5 decisions
 * - Severity classification:
 *   - minor: 50-100% increase
 *   - moderate: 100-200% increase
 *   - major: >200% increase
 */
class TopicAnalyticsService
{
    public function __construct(
        protected GraphDatabaseService $graph
    ) {}

    /**
     * Detect topic spikes based on threshold
     *
     * @param  float  $threshold  Minimum percent increase to flag (default: 0.5 = 50%)
     * @param  int  $baselineWeeks  Number of weeks for baseline calculation (default: 4)
     * @param  int  $minAbsoluteCount  Minimum absolute count to avoid false positives (default: 5)
     * @return array Array of spike events
     */
    public function detectTopicSpikes(
        float $threshold = 0.5,
        int $baselineWeeks = 4,
        int $minAbsoluteCount = 5
    ): array {
        Log::info('TopicAnalyticsService - Detecting topic spikes', [
            'threshold' => $threshold,
            'baseline_weeks' => $baselineWeeks,
            'min_absolute_count' => $minAbsoluteCount,
        ]);

        // Get current week start
        $currentWeekStart = now()->startOfWeek();

        // Get baseline period (4 weeks before current week)
        $baselineStart = $currentWeekStart->copy()->subWeeks($baselineWeeks);
        $baselineEnd = $currentWeekStart->copy()->subWeek();

        // Calculate baseline averages for each topic
        $baselines = DB::table('topic_decision_counts')
            ->whereBetween('week_start', [$baselineStart, $baselineEnd])
            ->select('topic_name')
            ->selectRaw('AVG(decision_count) as avg_count')
            ->groupBy('topic_name')
            ->get()
            ->keyBy('topic_name');

        // Get current week counts
        $currentCounts = DB::table('topic_decision_counts')
            ->where('week_start', $currentWeekStart)
            ->select('topic_name')
            ->selectRaw('SUM(decision_count) as total_count')
            ->groupBy('topic_name')
            ->get();

        $spikes = [];

        foreach ($currentCounts as $current) {
            $topicName = $current->topic_name;
            $currentCount = $current->total_count;

            // Skip if no baseline data
            if (! isset($baselines[$topicName])) {
                continue;
            }

            $baselineCount = (float) $baselines[$topicName]->avg_count;

            // Skip if baseline is zero
            if ($baselineCount == 0) {
                continue;
            }

            // Calculate percent increase
            $percentIncrease = (($currentCount - $baselineCount) / $baselineCount) * 100;

            // Check if meets spike criteria
            if ($percentIncrease >= ($threshold * 100) && $currentCount >= $minAbsoluteCount) {
                $spikes[] = [
                    'topic_name' => $topicName,
                    'baseline_count' => (int) round($baselineCount),
                    'current_count' => $currentCount,
                    'percent_increase' => round($percentIncrease, 2),
                    'severity' => $this->classifySeverity($percentIncrease),
                ];
            }
        }

        // Sort by percent increase descending
        usort($spikes, fn ($a, $b) => $b['percent_increase'] <=> $a['percent_increase']);

        Log::info('TopicAnalyticsService - Spikes detected', ['count' => count($spikes)]);

        return $spikes;
    }

    /**
     * Get topic trends over specified number of weeks
     *
     * @param  string  $topicName  Topic to analyze
     * @param  int  $weeks  Number of weeks to analyze
     * @return array Weekly trend data
     */
    public function getTopicTrends(string $topicName, int $weeks = 12): array
    {
        Log::info('TopicAnalyticsService - Getting topic trends', [
            'topic' => $topicName,
            'weeks' => $weeks,
        ]);

        $endDate = now()->startOfWeek();
        $startDate = $endDate->copy()->subWeeks($weeks);

        $trends = DB::table('topic_decision_counts')
            ->where('topic_name', $topicName)
            ->whereBetween('week_start', [$startDate, $endDate])
            ->select('topic_name', 'week_start')
            ->selectRaw('SUM(decision_count) as decision_count')
            ->groupBy('topic_name', 'week_start')
            ->orderBy('week_start', 'asc')
            ->get()
            ->map(function ($row) {
                return [
                    'topic_name' => $row->topic_name,
                    'week_start' => $row->week_start,
                    'decision_count' => $row->decision_count,
                ];
            })
            ->toArray();

        return $trends;
    }

    /**
     * Generate weekly growth report
     *
     * @param  int  $limit  Number of top topics to include
     * @return array Growth report with top topics
     */
    public function getTopicGrowthReport(int $limit = 10): array
    {
        Log::info('TopicAnalyticsService - Generating growth report', ['limit' => $limit]);

        // Detect all spikes (no minimum count filter for report)
        $allSpikes = $this->detectTopicSpikes(threshold: 0.0, minAbsoluteCount: 0);

        // Filter to only positive growth
        $growingTopics = array_filter($allSpikes, fn ($spike) => $spike['percent_increase'] > 0);

        // Sort by growth percentage descending
        usort($growingTopics, fn ($a, $b) => $b['percent_increase'] <=> $a['percent_increase']);

        // Take top N
        $topGrowing = array_slice($growingTopics, 0, $limit);

        return [
            'top_growing_topics' => $topGrowing,
            'total_topics_analyzed' => count($allSpikes),
            'generated_at' => now(),
        ];
    }

    /**
     * Get courts affected by a topic within a time period
     *
     * @param  string  $topicName  Name of the topic/keyword
     * @param  \Carbon\Carbon  $startDate  Start date of the period
     * @param  \Carbon\Carbon  $endDate  End date of the period
     * @return array List of unique court names
     */
    public function getAffectedCourts(string $topicName, $startDate, $endDate): array
    {
        Log::info('TopicAnalyticsService - Getting affected courts', [
            'topic' => $topicName,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
        ]);

        try {
            // Query Neo4j to find courts with decisions containing this topic
            $query = '
                MATCH (k:Keyword {term: $topic})<-[:HAS_KEYWORD]-(doc:CourtDecisionDocument)
                WHERE doc.decision_date >= date($startDate)
                  AND doc.decision_date <= date($endDate)
                  AND doc.court IS NOT NULL
                RETURN DISTINCT doc.court as court
                ORDER BY court
            ';

            $result = $this->graph->run($query, [
                'topic' => $topicName,
                'startDate' => $startDate->toDateString(),
                'endDate' => $endDate->toDateString(),
            ]);

            $courts = [];
            foreach ($result as $record) {
                if (isset($record['court'])) {
                    $courts[] = $record['court'];
                }
            }

            Log::info('TopicAnalyticsService - Found affected courts', [
                'topic' => $topicName,
                'court_count' => count($courts),
            ]);

            return $courts;
        } catch (\Exception $e) {
            Log::error('TopicAnalyticsService - Failed to get affected courts', [
                'topic' => $topicName,
                'error' => $e->getMessage(),
            ]);

            // Return empty array on error to avoid breaking the spike detection
            return [];
        }
    }

    /**
     * Classify spike severity based on percent increase
     *
     * @param  float  $percentIncrease  Percent increase from baseline
     * @return string Severity level (minor/moderate/major)
     */
    protected function classifySeverity(float $percentIncrease): string
    {
        if ($percentIncrease > 200) {
            return 'major';
        } elseif ($percentIncrease > 100) {
            return 'moderate';
        } else {
            return 'minor';
        }
    }
}
