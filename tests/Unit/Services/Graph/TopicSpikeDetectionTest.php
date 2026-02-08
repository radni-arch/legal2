<?php

namespace Tests\Unit\Services\Graph;

use App\Services\Graph\TopicAnalyticsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * TDD Test for Topic Spike Analytics (Sprint 8.2)
 *
 * Testing approach:
 * 1. RED - Write failing test for spike detection
 * 2. GREEN - Implement minimal TopicAnalyticsService
 * 3. REFACTOR - Clean up while staying green
 *
 * Acceptance Criteria (from Sprint 8.2):
 * - Spikes detected when topic frequency increases >50%
 * - False positives filtered (absolute count <5)
 * - Weekly report shows top 10 growing topics
 * - Severity classification (minor/moderate/major) accurate
 */
class TopicSpikeDetectionTest extends TestCase
{
    use DatabaseTransactions;

    protected TopicAnalyticsService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Service doesn't exist yet - this will fail (RED phase)
        $this->service = app(TopicAnalyticsService::class);
    }

    /**
     * RED TEST 1: Detect topic spike when frequency increases >50%
     *
     * Given: A topic with baseline of 10 decisions/week
     * When: Current week has 16 decisions (60% increase)
     * Then: Spike should be detected
     */
    public function test_detects_spike_when_topic_frequency_increases_above_threshold()
    {
        // Arrange: Create baseline data (4 weeks ago, 3 weeks ago, 2 weeks ago, 1 week ago)
        $this->createTopicDecisions('pretres doma', [10, 10, 10, 10]); // Baseline: avg 10/week

        // Current week: 16 decisions (60% increase)
        $this->createTopicDecisions('pretres doma', [16], now());

        // Act: Detect spikes with 50% threshold
        $spikes = $this->service->detectTopicSpikes(threshold: 0.5);

        // Assert: Should detect the spike
        $this->assertCount(1, $spikes);
        $this->assertEquals('pretres doma', $spikes[0]['topic_name']);
        $this->assertEquals(10, $spikes[0]['baseline_count']);
        $this->assertEquals(16, $spikes[0]['current_count']);
        $this->assertEquals(60.0, $spikes[0]['percent_increase']);
    }

    /**
     * RED TEST 2: Filter false positives with absolute count <5
     *
     * Given: A topic with baseline of 2 decisions/week
     * When: Current week has 4 decisions (100% increase)
     * Then: Should NOT be flagged (absolute count too low)
     */
    public function test_filters_false_positives_with_low_absolute_count()
    {
        // Arrange: Low baseline
        $this->createTopicDecisions('minor topic', [2, 2, 2, 2]); // Baseline: avg 2/week
        $this->createTopicDecisions('minor topic', [4], now()); // 100% increase, but only 4 decisions

        // Act
        $spikes = $this->service->detectTopicSpikes(threshold: 0.5);

        // Assert: Should be filtered out
        $this->assertCount(0, $spikes);
    }

    /**
     * RED TEST 3: No spike when below threshold
     *
     * Given: A topic with baseline of 10 decisions/week
     * When: Current week has 14 decisions (40% increase)
     * Then: Should NOT be flagged (below 50% threshold)
     */
    public function test_does_not_detect_spike_when_below_threshold()
    {
        // Arrange
        $this->createTopicDecisions('stable topic', [10, 10, 10, 10]);
        $this->createTopicDecisions('stable topic', [14], now()); // 40% increase

        // Act
        $spikes = $this->service->detectTopicSpikes(threshold: 0.5);

        // Assert
        $this->assertCount(0, $spikes);
    }

    /**
     * RED TEST 4: Severity classification
     *
     * Given: Topics with different increase percentages
     * Then: Severity should be classified correctly
     * - minor: 50-100% increase
     * - moderate: 100-200% increase
     * - major: >200% increase
     */
    public function test_classifies_severity_correctly()
    {
        // Arrange: Different severity levels
        $this->createTopicDecisions('minor spike', [10, 10, 10, 10]);
        $this->createTopicDecisions('minor spike', [17], now()); // 70% increase

        $this->createTopicDecisions('moderate spike', [10, 10, 10, 10]);
        $this->createTopicDecisions('moderate spike', [25], now()); // 150% increase

        $this->createTopicDecisions('major spike', [10, 10, 10, 10]);
        $this->createTopicDecisions('major spike', [35], now()); // 250% increase

        // Act
        $spikes = $this->service->detectTopicSpikes(threshold: 0.5);

        // Assert: Check severity classification
        $this->assertCount(3, $spikes);

        $minorSpike = collect($spikes)->firstWhere('topic_name', 'minor spike');
        $this->assertEquals('minor', $minorSpike['severity']);

        $moderateSpike = collect($spikes)->firstWhere('topic_name', 'moderate spike');
        $this->assertEquals('moderate', $moderateSpike['severity']);

        $majorSpike = collect($spikes)->firstWhere('topic_name', 'major spike');
        $this->assertEquals('major', $majorSpike['severity']);
    }

    /**
     * RED TEST 5: Get topic trends over time
     *
     * Given: 12 weeks of topic data
     * Then: Should return weekly counts for trend analysis
     */
    public function test_gets_topic_trends_for_specified_period()
    {
        // Arrange: 12 weeks of data with increasing trend
        for ($week = 12; $week >= 1; $week--) {
            $date = now()->subWeeks($week);
            $count = 5 + (13 - $week); // Increasing trend: older weeks have lower counts
            $this->createTopicDecisions('trending topic', [$count], $date);
        }

        // Act: Get 12-week trends
        $trends = $this->service->getTopicTrends('trending topic', weeks: 12);

        // Assert
        $this->assertCount(12, $trends);
        $this->assertEquals('trending topic', $trends[0]['topic_name']);
        // Trends are ordered by week_start ASC, so last week should have higher count than first
        $this->assertLessThan($trends[11]['decision_count'], $trends[0]['decision_count']);
    }

    /**
     * RED TEST 6: Weekly growth report with top 10 topics
     *
     * Given: 15 topics with various growth rates
     * Then: Report should show top 10 by growth percentage
     */
    public function test_generates_weekly_growth_report_with_top_topics()
    {
        // Arrange: Create 15 topics with different growth rates
        for ($i = 1; $i <= 15; $i++) {
            $baseline = 10;
            $current = 10 + ($i * 2); // Varying growth

            $this->createTopicDecisions("topic $i", [$baseline, $baseline, $baseline, $baseline]);
            $this->createTopicDecisions("topic $i", [$current], now());
        }

        // Act
        $report = $this->service->getTopicGrowthReport(limit: 10);

        // Assert
        $this->assertCount(10, $report['top_growing_topics']);
        $this->assertEquals(15, $report['total_topics_analyzed']);
        $this->assertArrayHasKey('generated_at', $report);

        // Verify sorting (highest growth first)
        $growthRates = collect($report['top_growing_topics'])->pluck('percent_increase');
        $this->assertEquals($growthRates->sort()->reverse()->values()->toArray(), $growthRates->toArray());
    }

    // ===========================================
    // Helper Methods
    // ===========================================

    /**
     * Create mock topic decisions for testing
     *
     * @param  string  $topicName  Topic name
     * @param  array  $counts  Array of decision counts per week
     * @param  \Carbon\Carbon|null  $startDate  Starting date (default: 4 weeks ago)
     */
    protected function createTopicDecisions(string $topicName, array $counts, $startDate = null)
    {
        $startDate = $startDate ?? now()->subWeeks(count($counts));

        foreach ($counts as $weekOffset => $count) {
            $weekStart = $startDate->copy()->addWeeks($weekOffset)->startOfWeek();

            // Create ONE aggregated row per week with the total count
            DB::table('topic_decision_counts')->insert([
                'topic_name' => $topicName,
                'decision_count' => $count, // Total count for the week
                'week_start' => $weekStart,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
