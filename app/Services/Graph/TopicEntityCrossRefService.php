<?php

namespace App\Services\Graph;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Topic-Entity Cross-Reference Service (Sprint 8.2 Integration)
 *
 * Connects topic spike analytics with entity tracking to provide
 * comprehensive insights about emerging legal trends and entities.
 *
 * Features:
 * - Cross-reference spiking topics with keyword entities
 * - Boost relevance scores for entities with spiking topics
 * - Generate combined reports showing both insights
 * - Filter and prioritize actionable intelligence
 *
 * Integration Points:
 * - TopicAnalyticsService: Provides topic spike data
 * - EntityTrackingService: Provides emerging entity data
 * - Neo4j: Tags entities with :Trending label
 * - PostgreSQL: Stores cross-reference data
 */
class TopicEntityCrossRefService
{
    /**
     * Spike severity weights for relevance boosting
     */
    protected const SPIKE_BOOST_WEIGHTS = [
        'major' => 0.30,      // +30% boost for major spikes (>200% increase)
        'moderate' => 0.20,   // +20% boost for moderate spikes (100-200%)
        'minor' => 0.10,      // +10% boost for minor spikes (50-100%)
    ];

    public function __construct(
        protected TopicAnalyticsService $topicAnalytics,
        protected EntityTrackingService $entityTracking
    ) {}

    /**
     * Cross-reference topic spikes with keyword entities
     *
     * Finds keyword entities that have corresponding topic spikes,
     * indicating both emergence and trending behavior.
     *
     * @param  int  $days  Number of days to look back (default: 7)
     * @return array Cross-referenced items with combined data
     */
    public function crossReferenceSpikesWithEntities(int $days = 7): array
    {
        Log::info('TopicEntityCrossRefService - Cross-referencing spikes with entities', [
            'days' => $days,
        ]);

        // Get current topic spikes
        $spikes = $this->topicAnalytics->detectTopicSpikes(threshold: 0.5);

        if (empty($spikes)) {
            Log::info('No topic spikes detected for cross-referencing');

            return [];
        }

        // Get emerging keyword entities from the period
        $cutoffDate = now()->subDays($days);
        $keywordEntities = DB::table('emerging_entities')
            ->where('entity_type', 'keyword')
            ->where('detected_at', '>=', $cutoffDate)
            ->get()
            ->keyBy('entity_name');

        // Cross-reference by matching topic names with entity names
        $crossRefs = [];

        foreach ($spikes as $spike) {
            $topicName = $spike['topic_name'];

            // Check if this topic has a corresponding keyword entity
            if (isset($keywordEntities[$topicName])) {
                $entity = $keywordEntities[$topicName];

                // Calculate combined relevance score
                $baseScore = (float) $entity->relevance_score;
                $spikeBoost = $this->calculateSpikeBoost($spike['severity']);
                $combinedScore = min(1.0, $baseScore + $spikeBoost);

                $crossRefs[] = [
                    'topic_name' => $topicName,
                    'entity_id' => $entity->entity_id,
                    'entity_name' => $entity->entity_name,
                    'entity_type' => $entity->entity_type,
                    'spike_baseline_count' => $spike['baseline_count'],
                    'spike_current_count' => $spike['current_count'],
                    'spike_percent_increase' => $spike['percent_increase'],
                    'spike_severity' => $spike['severity'],
                    'entity_decision_count' => $entity->decision_count,
                    'base_relevance_score' => $baseScore,
                    'spike_boost' => $spikeBoost,
                    'combined_relevance_score' => round($combinedScore, 2),
                    'is_emerging_entity' => true,
                    'detected_at' => $entity->detected_at,
                ];
            }
        }

        // Sort by combined relevance score descending
        usort($crossRefs, fn ($a, $b) => $b['combined_relevance_score'] <=> $a['combined_relevance_score']);

        Log::info('TopicEntityCrossRefService - Cross-referencing complete', [
            'total_spikes' => count($spikes),
            'total_entities' => $keywordEntities->count(),
            'cross_referenced' => count($crossRefs),
        ]);

        return $crossRefs;
    }

    /**
     * Boost relevance scores for entities with spiking topics
     *
     * Updates the relevance_score in emerging_entities table for
     * keyword entities that have corresponding topic spikes.
     *
     * @return int Number of entities boosted
     */
    public function boostRelevanceForSpikingTopics(): int
    {
        Log::info('TopicEntityCrossRefService - Boosting relevance scores');

        $crossRefs = $this->crossReferenceSpikesWithEntities();
        $boosted = 0;

        foreach ($crossRefs as $crossRef) {
            try {
                DB::table('emerging_entities')
                    ->where('entity_id', $crossRef['entity_id'])
                    ->update([
                        'relevance_score' => $crossRef['combined_relevance_score'],
                        'updated_at' => now(),
                    ]);

                $boosted++;
            } catch (\Exception $e) {
                Log::warning('Failed to boost entity relevance', [
                    'entity_id' => $crossRef['entity_id'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('TopicEntityCrossRefService - Relevance boosting complete', [
            'boosted' => $boosted,
        ]);

        return $boosted;
    }

    /**
     * Generate combined insights report
     *
     * Produces a comprehensive report showing both topic spikes
     * and emerging entities, with cross-referenced items highlighted.
     *
     * @param  int  $days  Number of days to include in report
     * @return array Combined report with summary and insights
     */
    public function getCombinedInsightsReport(int $days = 7): array
    {
        Log::info('TopicEntityCrossRefService - Generating combined insights report', [
            'days' => $days,
        ]);

        // Get topic spikes
        $spikes = $this->topicAnalytics->detectTopicSpikes(threshold: 0.5);

        // Get emerging entities
        $entityReport = $this->entityTracking->getEntityEmergenceReport($days);

        // Get cross-references
        $crossRefs = $this->crossReferenceSpikesWithEntities($days);

        $report = [
            'period' => "{$days} days",
            'generated_at' => now()->toIso8601String(),
            'summary' => [
                'total_spikes' => count($spikes),
                'total_new_entities' => $entityReport['summary']['total_new_entities'],
                'cross_referenced_count' => count($crossRefs),
                'period_start' => now()->subDays($days)->toDateString(),
                'period_end' => now()->toDateString(),
            ],
            'spiking_topics' => array_map(function ($spike) {
                return [
                    'topic_name' => $spike['topic_name'],
                    'percent_increase' => $spike['percent_increase'],
                    'severity' => $spike['severity'],
                    'current_count' => $spike['current_count'],
                    'baseline_count' => $spike['baseline_count'],
                ];
            }, $spikes),
            'emerging_entities' => $entityReport['top_entities'] ?? [],
            'cross_referenced_items' => $crossRefs,
        ];

        Log::info('TopicEntityCrossRefService - Report generated', [
            'spikes' => count($spikes),
            'entities' => count($report['emerging_entities']),
            'cross_refs' => count($crossRefs),
        ]);

        return $report;
    }

    /**
     * Calculate spike boost based on severity
     *
     * @param  string  $severity  Spike severity (minor/moderate/major)
     * @return float Boost amount (0.00-0.30)
     */
    protected function calculateSpikeBoost(string $severity): float
    {
        return self::SPIKE_BOOST_WEIGHTS[$severity] ?? 0.10;
    }
}
