<?php

namespace Tests\Unit\Services\Graph;

use App\Services\Graph\LegalPrincipleExtractor;
use App\Services\Graph\LegalPrincipleGraphSyncService;
use App\Services\GraphDatabaseService;
use Mockery;
use Tests\TestCase;

/**
 * TDD Tests for LegalPrincipleGraphSyncService
 *
 * Task A.3 - Service for syncing extracted legal principles to Neo4j graph
 */
class LegalPrincipleGraphSyncServiceTest extends TestCase
{
    private $mockGraph;

    private $mockExtractor;

    private LegalPrincipleGraphSyncService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockGraph = Mockery::mock(GraphDatabaseService::class);
        $this->mockExtractor = Mockery::mock(LegalPrincipleExtractor::class);
        $this->service = new LegalPrincipleGraphSyncService(
            $this->mockGraph,
            $this->mockExtractor
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test that service extracts principles and creates nodes
     *
     * @test
     */
    public function it_syncs_principles_from_decision_text(): void
    {
        $decisionId = 'decision-1';
        $text = 'Sud utvrđuje pravno načelo da...';

        // Mock extractor to return principles
        $this->mockExtractor->shouldReceive('extract')
            ->once()
            ->with($text)
            ->andReturn([
                'count' => 1,
                'principles' => [
                    [
                        'pattern_matched' => 'pravno načelo',
                        'context' => 'Sud utvrđuje pravno načelo da stranka koja...',
                        'position' => 100,
                    ],
                ],
            ]);

        // Expect upsertNode to be called with LegalPrinciple label
        $this->mockGraph->shouldReceive('upsertNode')
            ->once()
            ->with('LegalPrinciple', Mockery::type('string'), Mockery::on(function ($props) {
                return isset($props['name'])
                    && isset($props['description'])
                    && isset($props['category'])
                    && isset($props['status']);
            }));

        // Expect createRelationship to be called (APPLIES for non-landmark case)
        $this->mockGraph->shouldReceive('createRelationship')
            ->once()
            ->with(
                'CourtDecisionDocument',
                $decisionId,
                'APPLIES',
                'LegalPrinciple',
                Mockery::type('string'),
                Mockery::type('array')
            );

        // Act
        $result = $this->service->syncPrinciplesFromDecision($decisionId, $text);

        // Assert
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    /**
     * Test that landmark cases create ESTABLISHES relationships
     *
     * @test
     */
    public function it_creates_establishes_relationship_for_landmark_cases(): void
    {
        $decisionId = 'landmark-decision-1';
        $text = 'Sud zauzima stajalište da...';

        // Mock extractor
        $this->mockExtractor->shouldReceive('extract')
            ->once()
            ->andReturn([
                'count' => 1,
                'principles' => [
                    [
                        'pattern_matched' => 'Sud zauzima stajalište',
                        'context' => 'Sud zauzima stajalište da ugovorna strana...',
                        'position' => 50,
                    ],
                ],
            ]);

        $this->mockGraph->shouldReceive('upsertNode')
            ->once();

        // Expect ESTABLISHES relationship for landmark case
        $this->mockGraph->shouldReceive('createRelationship')
            ->once()
            ->with(
                'CourtDecisionDocument',
                $decisionId,
                'ESTABLISHES',
                'LegalPrinciple',
                Mockery::type('string'),
                Mockery::type('array')
            );

        // Act - with isLandmarkCase = true
        $result = $this->service->syncPrinciplesFromDecision($decisionId, $text, true);

        // Assert
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    /**
     * Test that empty extraction returns empty array
     *
     * @test
     */
    public function it_handles_empty_extraction_gracefully(): void
    {
        $decisionId = 'decision-2';
        $text = 'Some decision text without principles';

        // Mock extractor to return no principles
        $this->mockExtractor->shouldReceive('extract')
            ->once()
            ->with($text)
            ->andReturn([
                'count' => 0,
                'principles' => [],
            ]);

        // Should not call any graph methods
        $this->mockGraph->shouldNotReceive('upsertNode');
        $this->mockGraph->shouldNotReceive('createRelationship');

        // Act
        $result = $this->service->syncPrinciplesFromDecision($decisionId, $text);

        // Assert
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test that multiple principles are synced correctly
     *
     * @test
     */
    public function it_syncs_multiple_principles(): void
    {
        $decisionId = 'decision-3';
        $text = 'Text with multiple principles';

        // Mock extractor to return multiple principles
        $this->mockExtractor->shouldReceive('extract')
            ->once()
            ->andReturn([
                'count' => 3,
                'principles' => [
                    ['pattern_matched' => 'pravno načelo', 'context' => 'Context 1', 'position' => 100],
                    ['pattern_matched' => 'načelo savjesnosti', 'context' => 'Context 2', 'position' => 300],
                    ['pattern_matched' => 'ustaljena sudska praksa', 'context' => 'Context 3', 'position' => 500],
                ],
            ]);

        // Expect 3 nodes to be created
        $this->mockGraph->shouldReceive('upsertNode')
            ->times(3)
            ->with('LegalPrinciple', Mockery::type('string'), Mockery::type('array'));

        // Expect 3 relationships to be created
        $this->mockGraph->shouldReceive('createRelationship')
            ->times(3)
            ->with(
                'CourtDecisionDocument',
                $decisionId,
                'APPLIES',
                'LegalPrinciple',
                Mockery::type('string'),
                Mockery::type('array')
            );

        // Act
        $result = $this->service->syncPrinciplesFromDecision($decisionId, $text);

        // Assert
        $this->assertCount(3, $result);
    }

    /**
     * Test that principle ID is deterministic and follows naming convention
     *
     * @test
     */
    public function it_generates_deterministic_principle_id(): void
    {
        $decisionId = 'decision-4';
        $text = 'Sud utvrđuje pravno načelo da...';
        $capturedId = null;

        $this->mockExtractor->shouldReceive('extract')
            ->once()
            ->andReturn([
                'count' => 1,
                'principles' => [
                    ['pattern_matched' => 'pravno načelo', 'context' => 'Context', 'position' => 100],
                ],
            ]);

        // Capture the ID
        $this->mockGraph->shouldReceive('upsertNode')
            ->once()
            ->with('LegalPrinciple', Mockery::capture($capturedId), Mockery::any());

        $this->mockGraph->shouldReceive('createRelationship')
            ->once();

        // Act
        $this->service->syncPrinciplesFromDecision($decisionId, $text);

        // Assert - ID should follow principle_<md5> format
        $this->assertStringStartsWith('principle_', $capturedId);
        $expectedId = 'principle_'.md5('pravno načelo');
        $this->assertEquals($expectedId, $capturedId);
    }

    /**
     * Test that principle node has correct properties
     *
     * @test
     */
    public function it_creates_principle_node_with_correct_properties(): void
    {
        $decisionId = 'decision-5';
        $text = 'Sud utvrđuje pravno načelo da...';
        $capturedProps = null;

        $this->mockExtractor->shouldReceive('extract')
            ->once()
            ->andReturn([
                'count' => 1,
                'principles' => [
                    [
                        'pattern_matched' => 'pravno načelo',
                        'context' => 'Sud utvrđuje pravno načelo da ugovorna strana...',
                        'position' => 100,
                    ],
                ],
            ]);

        // Capture the properties
        $this->mockGraph->shouldReceive('upsertNode')
            ->once()
            ->with('LegalPrinciple', Mockery::any(), Mockery::capture($capturedProps));

        $this->mockGraph->shouldReceive('createRelationship')
            ->once();

        // Act
        $this->service->syncPrinciplesFromDecision($decisionId, $text);

        // Assert - Check all required properties exist
        $this->assertArrayHasKey('name', $capturedProps);
        $this->assertArrayHasKey('description', $capturedProps);
        $this->assertArrayHasKey('category', $capturedProps);
        $this->assertArrayHasKey('status', $capturedProps);
        $this->assertEquals('active', $capturedProps['status']);
    }

    /**
     * Test that relationship properties include created_at timestamp
     *
     * @test
     */
    public function it_includes_timestamp_in_relationship(): void
    {
        $decisionId = 'decision-6';
        $text = 'Sud utvrđuje pravno načelo da...';
        $capturedRelProps = null;

        $this->mockExtractor->shouldReceive('extract')
            ->once()
            ->andReturn([
                'count' => 1,
                'principles' => [
                    ['pattern_matched' => 'pravno načelo', 'context' => 'Context', 'position' => 100],
                ],
            ]);

        $this->mockGraph->shouldReceive('upsertNode')
            ->once();

        // Capture relationship properties
        $this->mockGraph->shouldReceive('createRelationship')
            ->once()
            ->with(
                Mockery::any(),
                Mockery::any(),
                Mockery::any(),
                Mockery::any(),
                Mockery::any(),
                Mockery::capture($capturedRelProps)
            );

        // Act
        $this->service->syncPrinciplesFromDecision($decisionId, $text);

        // Assert
        $this->assertArrayHasKey('created_at', $capturedRelProps);
    }

    /**
     * Test that syncing the same principle from different decisions reuses the same node ID
     *
     * CRITICAL: This verifies deduplication works correctly
     *
     * @test
     */
    public function it_reuses_existing_principle_node_for_same_pattern(): void
    {
        // Arrange: Same principle extracted from two different decisions
        $decision1Id = 'decision-A';
        $decision2Id = 'decision-B';
        $samePrincipleName = 'načelo savjesnosti i poštenja';

        $capturedId1 = null;
        $capturedId2 = null;

        // Mock extractor for first decision
        $this->mockExtractor->shouldReceive('extract')
            ->once()
            ->andReturn([
                'count' => 1,
                'principles' => [
                    [
                        'pattern_matched' => $samePrincipleName,
                        'context' => 'Context from decision A',
                        'position' => 100,
                    ],
                ],
            ]);

        // Capture ID from first sync
        $this->mockGraph->shouldReceive('upsertNode')
            ->once()
            ->with('LegalPrinciple', Mockery::capture($capturedId1), Mockery::any());

        $this->mockGraph->shouldReceive('createRelationship')
            ->once();

        // Act: Sync first decision
        $this->service->syncPrinciplesFromDecision($decision1Id, 'text1');

        // Mock extractor for second decision (same principle)
        $this->mockExtractor->shouldReceive('extract')
            ->once()
            ->andReturn([
                'count' => 1,
                'principles' => [
                    [
                        'pattern_matched' => $samePrincipleName,
                        'context' => 'Context from decision B',
                        'position' => 200,
                    ],
                ],
            ]);

        // Capture ID from second sync
        $this->mockGraph->shouldReceive('upsertNode')
            ->once()
            ->with('LegalPrinciple', Mockery::capture($capturedId2), Mockery::any());

        $this->mockGraph->shouldReceive('createRelationship')
            ->once();

        // Act: Sync second decision
        $this->service->syncPrinciplesFromDecision($decision2Id, 'text2');

        // Assert: Both calls should use the SAME principle ID (deterministic)
        $this->assertEquals($capturedId1, $capturedId2, 'Same principle should generate same ID for deduplication');
        $this->assertStringStartsWith('principle_', $capturedId1, 'ID should follow naming convention');
    }
}
