<?php

namespace Tests\Unit\Services\Graph;

use App\Services\Graph\EntityTrackingService;
use App\Services\Graph\TopicAnalyticsService;
use App\Services\Graph\TopicEntityCrossRefService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * TDD Test for Topic-Entity Integration (Sprint 8.2 Integration)
 *
 * Testing approach:
 * 1. RED - Write failing test for topic-entity cross-referencing
 * 2. GREEN - Implement minimal TopicEntityCrossRefService
 * 3. REFACTOR - Clean up while staying green
 *
 * Integration Requirements:
 * - Detect when a topic spike corresponds to a keyword entity
 * - Boost relevance scores for entities with spiking topics
 * - Tag entities with :Trending label in Neo4j
 * - Generate combined reports showing both spikes and entities
 */
class TopicEntityIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    protected TopicEntityCrossRefService $crossRefService;

    protected TopicAnalyticsService $topicService;

    protected EntityTrackingService $entityService;

    protected function setUp(): void
    {
        parent::setUp();

        // Service doesn't exist yet - this will fail (RED phase)
        $this->crossRefService = app(TopicEntityCrossRefService::class);
        $this->topicService = app(TopicAnalyticsService::class);
        $this->entityService = app(EntityTrackingService::class);
    }

    /**
     * RED TEST 1: Cross-reference spiking topics with keyword entities
     *
     * Given: A topic spike exists AND a keyword entity with same name exists
     * When: Cross-referencing is performed
     * Then: Should detect the match and return cross-reference data
     */
    public function test_cross_references_spiking_topics_with_keyword_entities()
    {
        // Arrange: Create a spiking topic
        $this->createTopicSpike('pretres doma', baselineCount: 10, currentCount: 20);

        // Create a matching keyword entity
        $this->createKeywordEntity('pretres doma', decisionCount: 15);

        // Act: Cross-reference
        $crossRefs = $this->crossRefService->crossReferenceSpikesWithEntities();

        // Assert: Should find the match
        $this->assertCount(1, $crossRefs);
        $this->assertEquals('pretres doma', $crossRefs[0]['topic_name']);
        $this->assertEquals('pretres doma', $crossRefs[0]['entity_name']);
        $this->assertEquals(100.0, $crossRefs[0]['spike_percent_increase']);
        $this->assertTrue($crossRefs[0]['is_emerging_entity']);
    }

    /**
     * RED TEST 2: Boost relevance score for entities with spiking topics
     *
     * Given: A keyword entity exists
     * When: Its corresponding topic spikes
     * Then: Entity's relevance score should be boosted
     */
    public function test_boosts_relevance_score_for_entities_with_spiking_topics()
    {
        // Arrange: Create entity with initial score
        $entityId = $this->createKeywordEntity('ovrha', decisionCount: 5);
        $initialScore = DB::table('emerging_entities')
            ->where('entity_id', $entityId)
            ->value('relevance_score');

        // Create topic spike
        $this->createTopicSpike('ovrha', baselineCount: 10, currentCount: 25);

        // Act: Apply spike boost
        $boosted = $this->crossRefService->boostRelevanceForSpikingTopics();

        // Assert: Score should increase
        $newScore = DB::table('emerging_entities')
            ->where('entity_id', $entityId)
            ->value('relevance_score');

        $this->assertEquals(1, $boosted);
        $this->assertGreaterThan($initialScore, $newScore);
        $this->assertLessThanOrEqual(1.0, $newScore); // Max score is 1.0
    }

    /**
     * RED TEST 3: Generate combined entity and topic spike report
     *
     * Given: Both entity emergences and topic spikes exist
     * When: Combined report is generated
     * Then: Report should include both types of insights
     */
    public function test_generates_combined_entity_and_spike_report()
    {
        // Arrange: Create various entities and spikes
        $this->createKeywordEntity('pretres doma', decisionCount: 20);
        $this->createKeywordEntity('droga', decisionCount: 10);
        $this->createProsecutorEntity('Ivana Horvat', decisionCount: 15);

        $this->createTopicSpike('pretres doma', baselineCount: 10, currentCount: 25);
        $this->createTopicSpike('droga', baselineCount: 8, currentCount: 20);

        // Act: Generate combined report
        $report = $this->crossRefService->getCombinedInsightsReport(days: 7);

        // Assert: Report structure
        $this->assertArrayHasKey('summary', $report);
        $this->assertArrayHasKey('spiking_topics', $report);
        $this->assertArrayHasKey('emerging_entities', $report);
        $this->assertArrayHasKey('cross_referenced_items', $report);

        // Summary counts
        $this->assertEquals(2, $report['summary']['total_spikes']);
        $this->assertEquals(3, $report['summary']['total_new_entities']);
        $this->assertEquals(2, $report['summary']['cross_referenced_count']);

        // Cross-referenced items should show topics that are also entities
        $this->assertCount(2, $report['cross_referenced_items']);
    }

    /**
     * RED TEST 4: Filter out non-keyword entities from cross-referencing
     *
     * Given: Mix of entity types (prosecutors, judges, keywords)
     * When: Cross-referencing with topics
     * Then: Only keyword entities should be considered
     */
    public function test_only_cross_references_keyword_entities_with_topics()
    {
        // Arrange: Create various entity types
        $this->createKeywordEntity('pretres', decisionCount: 10);
        $this->createProsecutorEntity('Marko Perić', decisionCount: 20);
        $this->createJudgeEntity('Ana Novak', decisionCount: 15);

        // Create topic spikes with names matching ALL entity types
        $this->createTopicSpike('pretres', baselineCount: 5, currentCount: 15);
        $this->createTopicSpike('Marko Perić', baselineCount: 1, currentCount: 5); // Should NOT match
        $this->createTopicSpike('Ana Novak', baselineCount: 2, currentCount: 6); // Should NOT match

        // Act
        $crossRefs = $this->crossRefService->crossReferenceSpikesWithEntities();

        // Assert: Only keyword entity should be matched
        $this->assertCount(1, $crossRefs);
        $this->assertEquals('pretres', $crossRefs[0]['topic_name']);
        $this->assertEquals('keyword', $crossRefs[0]['entity_type']);
    }

    /**
     * RED TEST 5: Calculate combined relevance score
     *
     * Given: A keyword entity with a topic spike
     * When: Combined score is calculated
     * Then: Score should factor in both entity weight and spike severity
     */
    public function test_calculates_combined_relevance_score()
    {
        // Arrange: Create entity and spike
        $entityId = $this->createKeywordEntity('privremena mjera', decisionCount: 10);
        $this->createTopicSpike('privremena mjera', baselineCount: 10, currentCount: 35); // 250% increase = major

        // Act: Calculate combined score
        $crossRefs = $this->crossRefService->crossReferenceSpikesWithEntities();
        $combinedScore = $crossRefs[0]['combined_relevance_score'];

        // Assert: Combined score should be higher due to major spike
        $baseScore = $this->entityService->calculateRelevanceScore([
            'entity_type' => 'keyword',
            'decision_count' => 10,
        ]);

        $this->assertGreaterThan($baseScore, $combinedScore);
        $this->assertLessThanOrEqual(1.0, $combinedScore);
    }

    // ===========================================
    // Helper Methods
    // ===========================================

    /**
     * Create a topic spike for testing
     */
    protected function createTopicSpike(string $topicName, int $baselineCount, int $currentCount): void
    {
        // Create baseline (4 weeks of data)
        for ($week = 4; $week >= 1; $week--) {
            $weekStart = now()->subWeeks($week)->startOfWeek();
            DB::table('topic_decision_counts')->insert([
                'topic_name' => $topicName,
                'decision_count' => $baselineCount,
                'week_start' => $weekStart,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Create current week spike
        DB::table('topic_decision_counts')->insert([
            'topic_name' => $topicName,
            'decision_count' => $currentCount,
            'week_start' => now()->startOfWeek(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Create a keyword entity for testing
     */
    protected function createKeywordEntity(string $name, int $decisionCount): string
    {
        $entityId = 'keyword_'.md5($name);

        DB::table('emerging_entities')->insert([
            'entity_type' => 'keyword',
            'entity_id' => $entityId,
            'entity_name' => $name,
            'first_seen_at' => now()->subDays(3),
            'decision_count' => $decisionCount,
            'detected_at' => now(),
            'relevance_score' => 0.50,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $entityId;
    }

    /**
     * Create a prosecutor entity for testing
     */
    protected function createProsecutorEntity(string $name, int $decisionCount): string
    {
        $entityId = 'prosecutor_'.md5($name);

        DB::table('emerging_entities')->insert([
            'entity_type' => 'prosecutor',
            'entity_id' => $entityId,
            'entity_name' => $name,
            'first_seen_at' => now()->subDays(2),
            'decision_count' => $decisionCount,
            'detected_at' => now(),
            'relevance_score' => 0.70,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $entityId;
    }

    /**
     * Create a judge entity for testing
     */
    protected function createJudgeEntity(string $name, int $decisionCount): string
    {
        $entityId = 'judge_'.md5($name);

        DB::table('emerging_entities')->insert([
            'entity_type' => 'judge',
            'entity_id' => $entityId,
            'entity_name' => $name,
            'first_seen_at' => now()->subDays(5),
            'decision_count' => $decisionCount,
            'detected_at' => now(),
            'relevance_score' => 0.65,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $entityId;
    }
}
