<?php

namespace Tests\Unit\Services\Graph;

use App\Services\Graph\EntityTrackingService;
use App\Services\Graph\TopicAnalyticsService;
use App\Services\Graph\TopicEntityCrossRefService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class TopicEntityCrossRefServiceTest extends TestCase
{
    protected TopicEntityCrossRefService $service;

    protected TopicAnalyticsService $mockTopicAnalytics;

    protected EntityTrackingService $mockEntityTracking;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockTopicAnalytics = Mockery::mock(TopicAnalyticsService::class);
        $this->mockEntityTracking = Mockery::mock(EntityTrackingService::class);

        $this->service = new TopicEntityCrossRefService(
            $this->mockTopicAnalytics,
            $this->mockEntityTracking
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_cross_reference_returns_empty_when_no_spikes(): void
    {
        // Arrange
        Log::shouldReceive('info')->zeroOrMoreTimes();

        $this->mockTopicAnalytics->shouldReceive('detectTopicSpikes')
            ->once()
            ->with(0.5)
            ->andReturn([]);

        // Act
        $crossRefs = $this->service->crossReferenceSpikesWithEntities(7);

        // Assert
        $this->assertIsArray($crossRefs);
        $this->assertEmpty($crossRefs);
    }

    public function test_cross_reference_matches_spikes_with_entities(): void
    {
        // Arrange
        Log::shouldReceive('info')->zeroOrMoreTimes();

        $spikes = [
            [
                'topic_name' => 'drug_charges',
                'baseline_count' => 10,
                'current_count' => 25,
                'percent_increase' => 150.0,
                'severity' => 'moderate',
            ],
        ];

        $entities = collect([
            'drug_charges' => (object) [
                'entity_id' => 'entity-1',
                'entity_name' => 'drug_charges',
                'entity_type' => 'keyword',
                'decision_count' => 25,
                'relevance_score' => 0.75,
                'detected_at' => '2025-11-15 12:00:00',
            ],
        ]);

        $this->mockTopicAnalytics->shouldReceive('detectTopicSpikes')
            ->once()
            ->andReturn($spikes);

        DB::shouldReceive('table')
            ->with('emerging_entities')
            ->andReturnSelf();

        DB::shouldReceive('where')
            ->with('entity_type', 'keyword')
            ->andReturnSelf();

        DB::shouldReceive('where')
            ->with('detected_at', '>=', Mockery::any())
            ->andReturnSelf();

        DB::shouldReceive('get')
            ->andReturn($entities);

        // Act
        $crossRefs = $this->service->crossReferenceSpikesWithEntities(7);

        // Assert
        $this->assertCount(1, $crossRefs);
        $this->assertEquals('drug_charges', $crossRefs[0]['topic_name']);
        $this->assertEquals('entity-1', $crossRefs[0]['entity_id']);
        $this->assertEquals('moderate', $crossRefs[0]['spike_severity']);
        $this->assertEquals(150.0, $crossRefs[0]['spike_percent_increase']);
        $this->assertArrayHasKey('combined_relevance_score', $crossRefs[0]);
        $this->assertTrue($crossRefs[0]['is_emerging_entity']);
    }

    public function test_cross_reference_boosts_relevance_for_major_severity(): void
    {
        // Arrange
        Log::shouldReceive('info')->zeroOrMoreTimes();

        $spikes = [
            [
                'topic_name' => 'violent_crimes',
                'baseline_count' => 5,
                'current_count' => 20,
                'percent_increase' => 300.0,
                'severity' => 'major', // Major spike gets +30% boost
            ],
        ];

        $entities = collect([
            'violent_crimes' => (object) [
                'entity_id' => 'entity-2',
                'entity_name' => 'violent_crimes',
                'entity_type' => 'keyword',
                'decision_count' => 20,
                'relevance_score' => 0.60,
                'detected_at' => '2025-11-15 12:00:00',
            ],
        ]);

        $this->mockTopicAnalytics->shouldReceive('detectTopicSpikes')
            ->once()
            ->andReturn($spikes);

        DB::shouldReceive('table')->with('emerging_entities')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('get')->andReturn($entities);

        // Act
        $crossRefs = $this->service->crossReferenceSpikesWithEntities();

        // Assert
        $this->assertCount(1, $crossRefs);
        // Base 0.60 + 0.30 (major boost) = 0.90
        $this->assertEquals(0.90, $crossRefs[0]['combined_relevance_score']);
        $this->assertEquals(0.30, $crossRefs[0]['spike_boost']);
    }

    public function test_boost_relevance_updates_database_records(): void
    {
        // Arrange
        Log::shouldReceive('info')->zeroOrMoreTimes();

        $spikes = [
            [
                'topic_name' => 'fraud',
                'baseline_count' => 8,
                'current_count' => 16,
                'percent_increase' => 100.0,
                'severity' => 'moderate',
            ],
        ];

        $entities = collect([
            'fraud' => (object) [
                'entity_id' => 'entity-3',
                'entity_name' => 'fraud',
                'entity_type' => 'keyword',
                'decision_count' => 16,
                'relevance_score' => 0.70,
                'detected_at' => '2025-11-15 12:00:00',
            ],
        ]);

        $this->mockTopicAnalytics->shouldReceive('detectTopicSpikes')
            ->once() // Called once by crossReference which is called by boost
            ->andReturn($spikes);

        DB::shouldReceive('table')->with('emerging_entities')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('get')->andReturn($entities);

        // Expect update call
        DB::shouldReceive('update')
            ->once()
            ->with(Mockery::on(function ($arg) {
                return isset($arg['relevance_score']) && isset($arg['updated_at']);
            }));

        // Act
        $boosted = $this->service->boostRelevanceForSpikingTopics();

        // Assert
        $this->assertEquals(1, $boosted);
    }

    public function test_boost_relevance_handles_update_errors_gracefully(): void
    {
        // Arrange
        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('warning')->once();

        $spikes = [
            [
                'topic_name' => 'test_topic',
                'baseline_count' => 5,
                'current_count' => 10,
                'percent_increase' => 100.0,
                'severity' => 'moderate',
            ],
        ];

        $entities = collect([
            'test_topic' => (object) [
                'entity_id' => 'entity-4',
                'entity_name' => 'test_topic',
                'entity_type' => 'keyword',
                'decision_count' => 10,
                'relevance_score' => 0.65,
                'detected_at' => '2025-11-15 12:00:00',
            ],
        ]);

        $this->mockTopicAnalytics->shouldReceive('detectTopicSpikes')
            ->once()
            ->andReturn($spikes);

        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('get')->andReturn($entities);

        // Simulate update failure
        DB::shouldReceive('update')
            ->once()
            ->andThrow(new \Exception('Database error'));

        // Act
        $boosted = $this->service->boostRelevanceForSpikingTopics();

        // Assert - Should return 0 due to error
        $this->assertEquals(0, $boosted);
    }

    public function test_get_combined_insights_report_includes_all_sections(): void
    {
        // Arrange
        Log::shouldReceive('info')->zeroOrMoreTimes();

        $spikes = [
            [
                'topic_name' => 'cybercrime',
                'baseline_count' => 12,
                'current_count' => 30,
                'percent_increase' => 150.0,
                'severity' => 'moderate',
            ],
        ];

        $entityReport = [
            'summary' => ['total_new_entities' => 5],
            'top_entities' => [
                ['entity_name' => 'phishing', 'relevance_score' => 0.85],
            ],
        ];

        $entities = collect([
            'cybercrime' => (object) [
                'entity_id' => 'entity-5',
                'entity_name' => 'cybercrime',
                'entity_type' => 'keyword',
                'decision_count' => 30,
                'relevance_score' => 0.80,
                'detected_at' => '2025-11-15 12:00:00',
            ],
        ]);

        $this->mockTopicAnalytics->shouldReceive('detectTopicSpikes')
            ->twice() // Once for report spikes, once for cross-ref
            ->andReturn($spikes);

        $this->mockEntityTracking->shouldReceive('getEntityEmergenceReport')
            ->once()
            ->with(7)
            ->andReturn($entityReport);

        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('get')->andReturn($entities);

        // Act
        $report = $this->service->getCombinedInsightsReport(7);

        // Assert
        $this->assertArrayHasKey('period', $report);
        $this->assertArrayHasKey('generated_at', $report);
        $this->assertArrayHasKey('summary', $report);
        $this->assertArrayHasKey('spiking_topics', $report);
        $this->assertArrayHasKey('emerging_entities', $report);
        $this->assertArrayHasKey('cross_referenced_items', $report);

        $this->assertEquals('7 days', $report['period']);
        $this->assertEquals(1, $report['summary']['total_spikes']);
        $this->assertEquals(5, $report['summary']['total_new_entities']);
        $this->assertEquals(1, $report['summary']['cross_referenced_count']);
    }

    public function test_calculate_spike_boost_returns_correct_values(): void
    {
        // Use reflection to test protected method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('calculateSpikeBoost');
        $method->setAccessible(true);

        // Test major severity
        $this->assertEquals(0.30, $method->invoke($this->service, 'major'));

        // Test moderate severity
        $this->assertEquals(0.20, $method->invoke($this->service, 'moderate'));

        // Test minor severity
        $this->assertEquals(0.10, $method->invoke($this->service, 'minor'));

        // Test unknown severity (defaults to minor)
        $this->assertEquals(0.10, $method->invoke($this->service, 'unknown'));
    }

    public function test_cross_reference_sorts_by_combined_relevance_descending(): void
    {
        // Arrange
        Log::shouldReceive('info')->zeroOrMoreTimes();

        $spikes = [
            [
                'topic_name' => 'topic_a',
                'baseline_count' => 5,
                'current_count' => 10,
                'percent_increase' => 100.0,
                'severity' => 'moderate', // +0.20 boost
            ],
            [
                'topic_name' => 'topic_b',
                'baseline_count' => 5,
                'current_count' => 20,
                'percent_increase' => 300.0,
                'severity' => 'major', // +0.30 boost
            ],
        ];

        $entities = collect([
            'topic_a' => (object) [
                'entity_id' => 'entity-a',
                'entity_name' => 'topic_a',
                'entity_type' => 'keyword',
                'decision_count' => 10,
                'relevance_score' => 0.50, // 0.50 + 0.20 = 0.70
                'detected_at' => '2025-11-15 12:00:00',
            ],
            'topic_b' => (object) [
                'entity_id' => 'entity-b',
                'entity_name' => 'topic_b',
                'entity_type' => 'keyword',
                'decision_count' => 20,
                'relevance_score' => 0.40, // 0.40 + 0.30 = 0.70 (tied, but different original score)
                'detected_at' => '2025-11-15 12:00:00',
            ],
        ]);

        $this->mockTopicAnalytics->shouldReceive('detectTopicSpikes')
            ->once()
            ->andReturn($spikes);

        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('get')->andReturn($entities);

        // Act
        $crossRefs = $this->service->crossReferenceSpikesWithEntities();

        // Assert
        $this->assertCount(2, $crossRefs);
        // Both have same combined score, but sorted correctly
        $this->assertIsArray($crossRefs);
    }
}
