<?php

namespace Tests\Unit\Services\Graph;

use App\Services\Graph\EntityTrackingService;
use App\Services\GraphDatabaseService;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Tests for EntityTrackingService (Sprint 8.1)
 *
 * Tests verify emerging entity detection and tracking:
 * - Detecting new prosecutors, judges, keywords, courts
 * - Tagging entities with :Emerging label
 * - Generating emergence reports
 * - Calculating relevance scores
 *
 * Acceptance Criteria:
 * ✅ New prosecutors detected within 24 hours
 * ✅ Weekly report shows entities from last 7 days
 * ✅ Relevance scoring prioritizes high-impact entities
 */
class EntityTrackingServiceTest extends TestCase
{
    use UsesTestDatabase;

    protected EntityTrackingService $service;

    protected GraphDatabaseService $graphDb;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock GraphDatabaseService for testing without Neo4j
        $this->graphDb = Mockery::mock(GraphDatabaseService::class);
        $this->service = new EntityTrackingService($this->graphDb);
    }

    // ========================================
    // Test 1: Detect New Prosecutors
    // ========================================

    /** @test */
    public function it_detects_new_prosecutors_in_last_7_days()
    {
        // Arrange: Mock Neo4j response with new prosecutors
        $this->graphDb->shouldReceive('run')
            ->once()
            ->with(
                Mockery::on(fn ($query) => str_contains($query, 'Prosecutor')),
                Mockery::on(fn ($params) => isset($params['cutoffDate']))
            )
            ->andReturn([
                (object) [
                    'entity_id' => 'pros-001',
                    'entity_name' => 'Ivan Horvat',
                    'first_seen_at' => now()->subDays(3)->toDateTimeString(),
                    'decision_count' => 5,
                ],
                (object) [
                    'entity_id' => 'pros-002',
                    'entity_name' => 'Ana Kovač',
                    'first_seen_at' => now()->subDays(1)->toDateTimeString(),
                    'decision_count' => 2,
                ],
            ]);

        // Act
        $newEntities = $this->service->detectNewEntities('prosecutor', 7);

        // Assert
        $this->assertCount(2, $newEntities);
        $this->assertEquals('pros-001', $newEntities[0]['entity_id']);
        $this->assertEquals('Ivan Horvat', $newEntities[0]['entity_name']);
        $this->assertEquals(5, $newEntities[0]['decision_count']);
    }

    /** @test */
    public function it_detects_new_judges_in_last_7_days()
    {
        // Arrange: Mock Neo4j response with new judges
        $this->graphDb->shouldReceive('run')
            ->once()
            ->with(
                Mockery::on(fn ($query) => str_contains($query, 'Judge')),
                Mockery::on(fn ($params) => isset($params['cutoffDate']))
            )
            ->andReturn([
                (object) [
                    'entity_id' => 'judge-001',
                    'entity_name' => 'Marko Marić',
                    'first_seen_at' => now()->subDays(2)->toDateTimeString(),
                    'decision_count' => 3,
                ],
            ]);

        // Act
        $newEntities = $this->service->detectNewEntities('judge', 7);

        // Assert
        $this->assertCount(1, $newEntities);
        $this->assertEquals('judge-001', $newEntities[0]['entity_id']);
        $this->assertEquals('Marko Marić', $newEntities[0]['entity_name']);
    }

    // ========================================
    // Test 2: Calculate Relevance Scores
    // ========================================

    /** @test */
    public function it_calculates_relevance_score_based_on_decision_count()
    {
        // Arrange
        $entity = [
            'entity_type' => 'prosecutor',
            'entity_id' => 'pros-001',
            'entity_name' => 'Ivan Horvat',
            'decision_count' => 10,
            'first_seen_at' => now()->subDays(5)->toDateTimeString(),
        ];

        // Act
        $score = $this->service->calculateRelevanceScore($entity);

        // Assert
        $this->assertGreaterThan(0.5, $score); // High decision count = high relevance
        $this->assertLessThanOrEqual(1.0, $score);
    }

    /** @test */
    public function it_gives_higher_relevance_to_prosecutors_than_keywords()
    {
        // Arrange
        $prosecutor = [
            'entity_type' => 'prosecutor',
            'decision_count' => 5,
        ];

        $keyword = [
            'entity_type' => 'keyword',
            'decision_count' => 5,
        ];

        // Act
        $prosecutorScore = $this->service->calculateRelevanceScore($prosecutor);
        $keywordScore = $this->service->calculateRelevanceScore($keyword);

        // Assert: Prosecutors more relevant than keywords for same decision count
        $this->assertGreaterThan($keywordScore, $prosecutorScore);
    }

    // ========================================
    // Test 3: Tag Emerging Entities
    // ========================================

    /** @test */
    public function it_tags_emerging_entities_in_neo4j()
    {
        // Arrange
        $entities = [
            [
                'entity_type' => 'prosecutor',
                'entity_id' => 'pros-001',
                'entity_name' => 'Ivan Horvat',
            ],
            [
                'entity_type' => 'judge',
                'entity_id' => 'judge-001',
                'entity_name' => 'Ana Jurić',
            ],
        ];

        // Expect Neo4j MATCH + SET queries for each entity
        $this->graphDb->shouldReceive('run')
            ->times(2)
            ->with(
                Mockery::on(fn ($query) => str_contains($query, 'SET') && str_contains($query, ':Emerging')),
                Mockery::on(fn ($params) => isset($params['entityId']))
            )
            ->andReturn([]);

        // Act
        $tagged = $this->service->tagEmergingEntities($entities);

        // Assert
        $this->assertEquals(2, $tagged);
    }

    // ========================================
    // Test 4: Store Emerging Entities in Database
    // ========================================

    /** @test */
    public function it_stores_emerging_entities_in_database()
    {
        // Arrange
        $entities = [
            [
                'entity_type' => 'prosecutor',
                'entity_id' => 'pros-001',
                'entity_name' => 'Ivan Horvat',
                'first_seen_at' => now()->subDays(3)->toDateTimeString(),
                'decision_count' => 5,
            ],
        ];

        // Act
        $stored = $this->service->storeEmergingEntities($entities);

        // Assert
        $this->assertEquals(1, $stored);

        $record = DB::table('emerging_entities')
            ->where('entity_id', 'pros-001')
            ->first();

        $this->assertNotNull($record);
        $this->assertEquals('prosecutor', $record->entity_type);
        $this->assertEquals('Ivan Horvat', $record->entity_name);
        $this->assertEquals(5, $record->decision_count);
        $this->assertNotNull($record->relevance_score);
    }

    /** @test */
    public function it_does_not_duplicate_existing_entities()
    {
        // Arrange: Insert entity first time
        $entity = [
            'entity_type' => 'prosecutor',
            'entity_id' => 'pros-001',
            'entity_name' => 'Ivan Horvat',
            'first_seen_at' => now()->subDays(3)->toDateTimeString(),
            'decision_count' => 5,
        ];

        $this->service->storeEmergingEntities([$entity]);

        // Act: Try to insert same entity again
        $stored = $this->service->storeEmergingEntities([$entity]);

        // Assert: Should skip duplicate
        $this->assertEquals(0, $stored);

        $count = DB::table('emerging_entities')
            ->where('entity_id', 'pros-001')
            ->count();

        $this->assertEquals(1, $count);
    }

    // ========================================
    // Test 5: Generate Weekly Report
    // ========================================

    /** @test */
    public function it_generates_weekly_emergence_report()
    {
        // Arrange: Insert test entities
        DB::table('emerging_entities')->insert([
            [
                'entity_type' => 'prosecutor',
                'entity_id' => 'pros-001',
                'entity_name' => 'Ivan Horvat',
                'first_seen_at' => now()->subDays(3),
                'decision_count' => 5,
                'detected_at' => now()->subDays(3),
                'relevance_score' => 0.85,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'entity_type' => 'judge',
                'entity_id' => 'judge-001',
                'entity_name' => 'Ana Jurić',
                'first_seen_at' => now()->subDays(1),
                'decision_count' => 2,
                'detected_at' => now()->subDays(1),
                'relevance_score' => 0.65,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'entity_type' => 'prosecutor',
                'entity_id' => 'pros-old',
                'entity_name' => 'Old Prosecutor',
                'first_seen_at' => now()->subDays(30), // Outside 7-day window
                'decision_count' => 10,
                'detected_at' => now()->subDays(30),
                'relevance_score' => 0.90,
                'created_at' => now()->subDays(30),
                'updated_at' => now()->subDays(30),
            ],
        ]);

        // Act
        $report = $this->service->getEntityEmergenceReport(7);

        // Assert
        $this->assertArrayHasKey('summary', $report);
        $this->assertArrayHasKey('entities_by_type', $report);
        $this->assertArrayHasKey('top_entities', $report);

        // Should only include entities from last 7 days
        $this->assertEquals(2, $report['summary']['total_new_entities']);

        // Should group by entity type
        $this->assertEquals(1, $report['entities_by_type']['prosecutor']);
        $this->assertEquals(1, $report['entities_by_type']['judge']);

        // Top entities ordered by relevance score
        $this->assertEquals('pros-001', $report['top_entities'][0]['entity_id']);
        $this->assertEquals(0.85, $report['top_entities'][0]['relevance_score']);
    }

    // ========================================
    // Test 6: Edge Cases
    // ========================================

    /** @test */
    public function it_returns_empty_array_when_no_new_entities()
    {
        // Arrange: Mock empty Neo4j response
        $this->graphDb->shouldReceive('run')
            ->once()
            ->andReturn([]);

        // Act
        $newEntities = $this->service->detectNewEntities('prosecutor', 7);

        // Assert
        $this->assertEmpty($newEntities);
    }

    /** @test */
    public function it_handles_invalid_entity_type_gracefully()
    {
        // Arrange: Mock Neo4j to throw exception
        $this->graphDb->shouldReceive('run')
            ->once()
            ->andThrow(new \Exception('Invalid entity type'));

        // Act & Assert: Should not crash
        $result = $this->service->detectNewEntities('invalid_type', 7);

        $this->assertEmpty($result);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
