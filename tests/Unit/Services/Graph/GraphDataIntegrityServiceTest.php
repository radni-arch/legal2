<?php

namespace Tests\Unit\Services\Graph;

use App\Services\Graph\GraphDataIntegrityService;
use App\Services\GraphDatabaseService;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

/**
 * TDD Tests for GraphDataIntegrityService
 *
 * Phase 1: Data Integrity - Task 1.1
 * Tests for orphan node detection in the Neo4j graph database.
 */
class GraphDataIntegrityServiceTest extends TestCase
{
    private GraphDataIntegrityService $service;

    private $mockGraph;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockGraph = Mockery::mock(GraphDatabaseService::class);
        $this->service = new GraphDataIntegrityService($this->mockGraph);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test that findOrphanNodes detects Neo4j nodes without PostgreSQL records
     *
     * @test
     */
    public function it_detects_orphan_court_decision_nodes(): void
    {
        // Arrange: Neo4j has nodes but PostgreSQL doesn't have matching records
        $this->mockGraph->shouldReceive('run')
            ->once()
            ->with(Mockery::on(fn ($q) => str_contains($q, 'CourtDecisionDocument')))
            ->andReturn([
                ['id' => 'orphan-1', 'case_number' => 'Rev 1/2020'],
                ['id' => 'orphan-2', 'case_number' => 'Rev 2/2020'],
                ['id' => 'existing-1', 'case_number' => 'Rev 3/2020'],
            ]);

        // Mock PostgreSQL - only one record exists
        DB::shouldReceive('table')
            ->with('court_decision_documents')
            ->andReturnSelf();
        DB::shouldReceive('whereIn')
            ->with('id', ['orphan-1', 'orphan-2', 'existing-1'])
            ->andReturnSelf();
        DB::shouldReceive('pluck')
            ->with('id')
            ->andReturnSelf();
        DB::shouldReceive('toArray')
            ->andReturn(['existing-1']);

        // Act: Find orphan nodes
        $orphans = $this->service->findOrphanNodes('CourtDecisionDocument');

        // Assert: Should find 2 orphans (orphan-1 and orphan-2)
        $this->assertCount(2, $orphans);
        $this->assertEquals('orphan-1', $orphans[0]['id']);
        $this->assertEquals('orphan-2', $orphans[1]['id']);
    }

    /**
     * Test that findOrphanNodes handles empty Neo4j results gracefully
     *
     * @test
     */
    public function it_returns_empty_array_when_no_neo4j_nodes_exist(): void
    {
        // Arrange: Neo4j returns no nodes
        $this->mockGraph->shouldReceive('run')
            ->once()
            ->andReturn([]);

        // Act
        $orphans = $this->service->findOrphanNodes('CourtDecisionDocument');

        // Assert
        $this->assertEmpty($orphans);
    }

    /**
     * Test that findOrphanNodes throws exception for unknown node types
     *
     * @test
     */
    public function it_throws_exception_for_unknown_node_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown node type: UnknownNodeType');

        // Note: With the refactored implementation, getTableForNodeType is called
        // BEFORE any graph queries, so no mock expectations are needed
        $this->service->findOrphanNodes('UnknownNodeType');
    }

    /**
     * Test that service handles different node types correctly
     *
     * @test
     */
    public function it_maps_node_types_to_correct_tables(): void
    {
        // Test LawDocument mapping
        $this->mockGraph->shouldReceive('run')
            ->once()
            ->with(Mockery::on(fn ($q) => str_contains($q, 'LawDocument')))
            ->andReturn([]);

        $orphans = $this->service->findOrphanNodes('LawDocument');
        $this->assertEmpty($orphans);
    }

    // ========================================
    // Task 1.2: Missing Node Detection Tests
    // ========================================

    /**
     * Test that findMissingNodes detects PostgreSQL records without Neo4j nodes
     *
     * @test
     */
    public function it_detects_missing_nodes_in_neo4j(): void
    {
        // Arrange: PostgreSQL has 3 records, Neo4j only has 1
        $idsToCheck = ['id-1', 'id-2', 'id-3'];

        // Mock Neo4j - only returns one ID as existing
        $this->mockGraph->shouldReceive('run')
            ->once()
            ->with(Mockery::on(fn ($q) => str_contains($q, 'CourtDecisionDocument') && str_contains($q, 'WHERE n.id IN')))
            ->andReturn([
                ['id' => 'id-1'],
            ]);

        // Act: Find missing nodes
        $missing = $this->service->findMissingNodes('CourtDecisionDocument', $idsToCheck);

        // Assert: Should find 2 missing (id-2 and id-3)
        $this->assertCount(2, $missing);
        $this->assertContains('id-2', $missing);
        $this->assertContains('id-3', $missing);
    }

    /**
     * Test that findMissingNodes returns empty when all nodes exist
     *
     * @test
     */
    public function it_returns_empty_when_all_nodes_exist_in_neo4j(): void
    {
        // Arrange
        $idsToCheck = ['id-1', 'id-2'];

        $this->mockGraph->shouldReceive('run')
            ->once()
            ->andReturn([
                ['id' => 'id-1'],
                ['id' => 'id-2'],
            ]);

        // Act
        $missing = $this->service->findMissingNodes('CourtDecisionDocument', $idsToCheck);

        // Assert
        $this->assertEmpty($missing);
    }

    /**
     * Test that findMissingNodes handles empty input gracefully
     *
     * @test
     */
    public function it_returns_empty_for_empty_input_ids(): void
    {
        // Act - no IDs to check
        $missing = $this->service->findMissingNodes('CourtDecisionDocument', []);

        // Assert
        $this->assertEmpty($missing);
    }

    /**
     * Test findMissingNodes fetches from database when no IDs provided
     *
     * @test
     */
    public function it_fetches_ids_from_database_when_none_provided(): void
    {
        // Mock PostgreSQL to return IDs
        DB::shouldReceive('table')
            ->with('court_decision_documents')
            ->andReturnSelf();
        DB::shouldReceive('limit')
            ->with(1000)
            ->andReturnSelf();
        DB::shouldReceive('pluck')
            ->with('id')
            ->andReturnSelf();
        DB::shouldReceive('toArray')
            ->andReturn(['db-id-1', 'db-id-2']);

        // Mock Neo4j - only one exists
        $this->mockGraph->shouldReceive('run')
            ->once()
            ->andReturn([['id' => 'db-id-1']]);

        // Act
        $missing = $this->service->findMissingNodes('CourtDecisionDocument');

        // Assert
        $this->assertCount(1, $missing);
        $this->assertContains('db-id-2', $missing);
    }

    // ========================================
    // Task 1.3: Relationship Consistency Tests
    // ========================================

    /**
     * Test that validateRelationshipConsistency finds dangling relationships
     *
     * @test
     */
    public function it_validates_relationship_consistency(): void
    {
        // Arrange: One relationship points to non-existent target
        $this->mockGraph->shouldReceive('run')
            ->once()
            ->with(Mockery::on(fn ($q) => str_contains($q, 'CITES')))
            ->andReturn([
                ['from_id' => 'valid-1', 'to_id' => 'orphan-target', 'rel_type' => 'CITES'],
            ]);

        // Act
        $issues = $this->service->validateRelationshipConsistency('CITES');

        // Assert
        $this->assertNotEmpty($issues);
        $this->assertEquals('orphan-target', $issues[0]['to_id']);
    }

    /**
     * Test validateRelationshipConsistency returns empty when all relationships valid
     *
     * @test
     */
    public function it_returns_empty_when_all_relationships_valid(): void
    {
        // Arrange: No dangling relationships
        $this->mockGraph->shouldReceive('run')
            ->once()
            ->andReturn([]);

        // Act
        $issues = $this->service->validateRelationshipConsistency('CITES');

        // Assert
        $this->assertEmpty($issues);
    }

    /**
     * Test validateRelationshipConsistency handles different relationship types
     *
     * @test
     */
    public function it_validates_different_relationship_types(): void
    {
        // Arrange
        $this->mockGraph->shouldReceive('run')
            ->once()
            ->with(Mockery::on(fn ($q) => str_contains($q, 'REFERENCES')))
            ->andReturn([
                ['from_id' => 'doc-1', 'to_id' => 'missing-case', 'rel_type' => 'REFERENCES'],
            ]);

        // Act
        $issues = $this->service->validateRelationshipConsistency('REFERENCES');

        // Assert
        $this->assertCount(1, $issues);
        $this->assertEquals('REFERENCES', $issues[0]['rel_type']);
    }

    // ========================================
    // Task 1.4: Integrity Report Generation Tests
    // ========================================

    /**
     * Test that generateIntegrityReport produces comprehensive report
     *
     * @test
     */
    public function it_generates_comprehensive_integrity_report(): void
    {
        // Arrange: Mock all graph queries to return empty (healthy state)
        $this->mockGraph->shouldReceive('run')
            ->andReturn([]);

        // Act
        $report = $this->service->generateIntegrityReport();

        // Assert: Report has all required keys
        $this->assertArrayHasKey('orphan_nodes', $report);
        $this->assertArrayHasKey('missing_nodes', $report);
        $this->assertArrayHasKey('dangling_relationships', $report);
        $this->assertArrayHasKey('generated_at', $report);
        $this->assertArrayHasKey('summary', $report);

        // Assert: Summary has expected metrics
        $this->assertArrayHasKey('total_orphan_nodes', $report['summary']);
        $this->assertArrayHasKey('total_missing_nodes', $report['summary']);
        $this->assertArrayHasKey('total_dangling_relationships', $report['summary']);
        $this->assertArrayHasKey('health_status', $report['summary']);
    }

    /**
     * Test that report correctly identifies healthy status
     *
     * @test
     */
    public function it_reports_healthy_status_when_no_issues(): void
    {
        // Arrange: No issues
        $this->mockGraph->shouldReceive('run')
            ->andReturn([]);

        // Act
        $report = $this->service->generateIntegrityReport();

        // Assert
        $this->assertEquals('healthy', $report['summary']['health_status']);
        $this->assertEquals(0, $report['summary']['total_orphan_nodes']);
    }

    /**
     * Test that report correctly identifies issues
     *
     * @test
     */
    public function it_reports_needs_attention_when_issues_found(): void
    {
        // Arrange: Some orphan nodes exist
        $this->mockGraph->shouldReceive('run')
            ->andReturnUsing(function ($query) {
                // Return orphans for CourtDecisionDocument query
                if (str_contains($query, 'CourtDecisionDocument') && ! str_contains($query, 'WHERE')) {
                    return [['id' => 'orphan-1', 'case_number' => 'test']];
                }

                return [];
            });

        // Mock DB to return no matching records (so the node is orphaned)
        DB::shouldReceive('table')
            ->andReturnSelf();
        DB::shouldReceive('whereIn')
            ->andReturnSelf();
        DB::shouldReceive('pluck')
            ->andReturnSelf();
        DB::shouldReceive('toArray')
            ->andReturn([]);
        DB::shouldReceive('limit')
            ->andReturnSelf();

        // Act
        $report = $this->service->generateIntegrityReport();

        // Assert
        $this->assertEquals('needs_attention', $report['summary']['health_status']);
    }

    /**
     * Test that report handles errors gracefully
     *
     * @test
     */
    public function it_handles_errors_gracefully_in_report(): void
    {
        // Arrange: Neo4j throws error for one query
        $queryCount = 0;
        $this->mockGraph->shouldReceive('run')
            ->andReturnUsing(function () use (&$queryCount) {
                $queryCount++;
                if ($queryCount === 1) {
                    throw new \Exception('Connection failed');
                }

                return [];
            });

        // Act
        $report = $this->service->generateIntegrityReport();

        // Assert: Report is still generated with error noted
        $this->assertArrayHasKey('orphan_nodes', $report);
        $this->assertArrayHasKey('error', $report['orphan_nodes']['CourtDecisionDocument']);
    }

    // ========================================
    // Task B.1: Pagination Tests
    // ========================================

    /**
     * Test that findOrphanNodes paginates through all nodes in batches
     *
     * @test
     */
    public function it_finds_orphans_across_multiple_batches(): void
    {
        // Arrange: Simulate 2500 nodes total, 1000 per batch
        // Batch 1: nodes 0-999 (SKIP 0 LIMIT 1000)
        // Batch 2: nodes 1000-1999 (SKIP 1000 LIMIT 1000)
        // Batch 3: nodes 2000-2499 (SKIP 2000 LIMIT 1000)
        // Batch 4: empty (SKIP 3000 LIMIT 1000) - should terminate

        $this->mockGraph->shouldReceive('run')
            ->times(3)
            ->andReturnUsing(function ($query) {
                // First batch: SKIP 0
                if (str_contains($query, 'SKIP 0')) {
                    return array_map(
                        fn($i) => ['id' => "node-$i", 'case_number' => "case-$i"],
                        range(0, 999)
                    );
                }
                // Second batch: SKIP 1000
                if (str_contains($query, 'SKIP 1000')) {
                    return array_map(
                        fn($i) => ['id' => "node-$i", 'case_number' => "case-$i"],
                        range(1000, 1999)
                    );
                }
                // Third batch: SKIP 2000 (partial batch)
                if (str_contains($query, 'SKIP 2000')) {
                    return array_map(
                        fn($i) => ['id' => "node-$i", 'case_number' => "case-$i"],
                        range(2000, 2499)
                    );
                }

                return [];
            });

        // Mock PostgreSQL - say none exist, so all are orphans
        DB::shouldReceive('table')
            ->with('court_decision_documents')
            ->andReturnSelf();
        DB::shouldReceive('whereIn')
            ->andReturnSelf();
        DB::shouldReceive('pluck')
            ->with('id')
            ->andReturnSelf();
        DB::shouldReceive('toArray')
            ->andReturn([]);

        // Act
        $orphans = $this->service->findOrphanNodes('CourtDecisionDocument');

        // Assert: Should find all 2500 orphans
        $this->assertCount(2500, $orphans);
        $this->assertEquals('node-0', $orphans[0]['id']);
        $this->assertEquals('node-2499', $orphans[2499]['id']);
    }

    /**
     * Test that findOrphanNodes respects custom batch size
     *
     * @test
     */
    public function it_respects_custom_batch_size(): void
    {
        // Arrange: Use batch size of 500 instead of default 1000
        // Will make 3 calls: SKIP 0, SKIP 500, SKIP 1000 (empty, terminates)
        $this->mockGraph->shouldReceive('run')
            ->times(3)
            ->andReturnUsing(function ($query) {
                // Verify LIMIT 500 is in query
                $this->assertStringContainsString('LIMIT 500', $query);

                // First batch
                if (str_contains($query, 'SKIP 0')) {
                    return array_map(
                        fn($i) => ['id' => "node-$i", 'case_number' => "case-$i"],
                        range(0, 499)
                    );
                }
                // Second batch (full batch, triggers another check)
                if (str_contains($query, 'SKIP 500')) {
                    return array_map(
                        fn($i) => ['id' => "node-$i", 'case_number' => "case-$i"],
                        range(500, 999)
                    );
                }
                // Third batch - empty, terminates loop
                if (str_contains($query, 'SKIP 1000')) {
                    return [];
                }

                return [];
            });

        // Mock PostgreSQL - none exist
        DB::shouldReceive('table')
            ->with('court_decision_documents')
            ->andReturnSelf();
        DB::shouldReceive('whereIn')
            ->andReturnSelf();
        DB::shouldReceive('pluck')
            ->with('id')
            ->andReturnSelf();
        DB::shouldReceive('toArray')
            ->andReturn([]);

        // Act: Call with custom batch size
        $orphans = $this->service->findOrphanNodes('CourtDecisionDocument', 500);

        // Assert: Should find all 1000 orphans
        $this->assertCount(1000, $orphans);
    }

    /**
     * Test that pagination terminates when batch is smaller than batch size
     *
     * @test
     */
    public function it_terminates_when_batch_is_incomplete(): void
    {
        // Arrange: Only 1500 nodes total (2 full batches + 1 partial)
        $this->mockGraph->shouldReceive('run')
            ->times(2)
            ->andReturnUsing(function ($query) {
                // First batch: 1000 nodes
                if (str_contains($query, 'SKIP 0')) {
                    return array_map(
                        fn($i) => ['id' => "node-$i", 'case_number' => "case-$i"],
                        range(0, 999)
                    );
                }
                // Second batch: 500 nodes (partial, should terminate after this)
                if (str_contains($query, 'SKIP 1000')) {
                    return array_map(
                        fn($i) => ['id' => "node-$i", 'case_number' => "case-$i"],
                        range(1000, 1499)
                    );
                }

                return [];
            });

        // Mock PostgreSQL
        DB::shouldReceive('table')
            ->with('court_decision_documents')
            ->andReturnSelf();
        DB::shouldReceive('whereIn')
            ->andReturnSelf();
        DB::shouldReceive('pluck')
            ->with('id')
            ->andReturnSelf();
        DB::shouldReceive('toArray')
            ->andReturn([]);

        // Act
        $orphans = $this->service->findOrphanNodes('CourtDecisionDocument');

        // Assert: Should find all 1500 and stop (not make a 3rd call)
        $this->assertCount(1500, $orphans);
    }

    /**
     * Test that findOrphanNodes maintains backward compatibility with default batch size
     *
     * @test
     */
    public function it_maintains_backward_compatibility(): void
    {
        // Arrange: Single batch of data (< 1000 nodes)
        $this->mockGraph->shouldReceive('run')
            ->once()
            ->with(Mockery::on(function ($query) {
                // Should use default LIMIT 1000
                return str_contains($query, 'LIMIT 1000') &&
                       str_contains($query, 'SKIP 0');
            }))
            ->andReturn([
                ['id' => 'orphan-1', 'case_number' => 'Rev 1/2020'],
                ['id' => 'orphan-2', 'case_number' => 'Rev 2/2020'],
            ]);

        DB::shouldReceive('table')
            ->with('court_decision_documents')
            ->andReturnSelf();
        DB::shouldReceive('whereIn')
            ->andReturnSelf();
        DB::shouldReceive('pluck')
            ->with('id')
            ->andReturnSelf();
        DB::shouldReceive('toArray')
            ->andReturn([]);

        // Act: Call without batch size parameter (should use default)
        $orphans = $this->service->findOrphanNodes('CourtDecisionDocument');

        // Assert
        $this->assertCount(2, $orphans);
    }

    /**
     * Test that orphans from different batches are correctly accumulated
     *
     * @test
     */
    public function it_accumulates_orphans_from_multiple_batches(): void
    {
        // Arrange: 2 batches of 2 nodes each, some orphans in each
        // Will make 3 calls: SKIP 0, SKIP 2, SKIP 4 (empty, terminates)
        $this->mockGraph->shouldReceive('run')
            ->times(3)
            ->andReturnUsing(function ($query) {
                if (str_contains($query, 'SKIP 0')) {
                    return [
                        ['id' => 'batch1-orphan1', 'case_number' => 'case-1'],
                        ['id' => 'batch1-valid1', 'case_number' => 'case-2'],
                    ];
                }
                if (str_contains($query, 'SKIP 2')) {
                    return [
                        ['id' => 'batch2-orphan1', 'case_number' => 'case-3'],
                        ['id' => 'batch2-valid1', 'case_number' => 'case-4'],
                    ];
                }
                // Third call - empty, terminates
                if (str_contains($query, 'SKIP 4')) {
                    return [];
                }

                return [];
            });

        // Mock PostgreSQL - only the "valid" ones exist
        DB::shouldReceive('table')
            ->with('court_decision_documents')
            ->andReturnSelf();
        DB::shouldReceive('whereIn')
            ->andReturnSelf();
        DB::shouldReceive('pluck')
            ->with('id')
            ->andReturnSelf();
        DB::shouldReceive('toArray')
            ->times(2)
            ->andReturn(['batch1-valid1'], ['batch2-valid1']);

        // Act: Use batch size of 2 for easier testing
        $orphans = $this->service->findOrphanNodes('CourtDecisionDocument', 2);

        // Assert: Should find orphans from both batches
        $this->assertCount(2, $orphans);

        $orphanIds = array_column($orphans, 'id');
        $this->assertContains('batch1-orphan1', $orphanIds);
        $this->assertContains('batch2-orphan1', $orphanIds);
    }

    // ========================================
    // Task B.2: Batching for findMissingNodes
    // ========================================

    /**
     * Test that findMissingNodes chunks large ID sets into batches
     *
     * @test
     */
    public function it_chunks_large_id_sets_into_batches(): void
    {
        // Arrange: 1500 IDs to check, should be split into batches of 500
        // Expect 3 Neo4j queries
        $idsToCheck = array_map(fn($i) => "id-$i", range(1, 1500));

        $queryCount = 0;
        $this->mockGraph->shouldReceive('run')
            ->times(3)
            ->andReturnUsing(function ($query) use (&$queryCount) {
                $queryCount++;
                // Verify query contains WHERE n.id IN clause
                $this->assertStringContainsString('WHERE n.id IN', $query);
                // Return empty (all missing)
                return [];
            });

        // Act: Find missing nodes with default batch size (500)
        $missing = $this->service->findMissingNodes('CourtDecisionDocument', $idsToCheck);

        // Assert: All 1500 IDs should be missing (3 batches × 500)
        $this->assertCount(1500, $missing);
        $this->assertEquals(3, $queryCount);
    }

    /**
     * Test that findMissingNodes finds missing nodes across multiple batches
     *
     * @test
     */
    public function it_finds_missing_nodes_across_multiple_batches(): void
    {
        // Arrange: 1000 IDs, batches of 500
        // Batch 1: ids 1-500, Neo4j has 1-250 (250 missing)
        // Batch 2: ids 501-1000, Neo4j has 501-750 (250 missing)
        // Total: 500 missing
        $idsToCheck = array_map(fn($i) => "id-$i", range(1, 1000));

        $this->mockGraph->shouldReceive('run')
            ->times(2)
            ->andReturnUsing(function ($query) {
                // First batch: return first 250
                if (str_contains($query, 'id-1') && str_contains($query, 'id-500')) {
                    return array_map(fn($i) => ['id' => "id-$i"], range(1, 250));
                }
                // Second batch: return first 250 of this batch
                if (str_contains($query, 'id-501') && str_contains($query, 'id-1000')) {
                    return array_map(fn($i) => ['id' => "id-$i"], range(501, 750));
                }
                return [];
            });

        // Act
        $missing = $this->service->findMissingNodes('CourtDecisionDocument', $idsToCheck);

        // Assert: Should find 500 missing (251-500 and 751-1000)
        $this->assertCount(500, $missing);
        $this->assertContains('id-251', $missing);
        $this->assertContains('id-500', $missing);
        $this->assertContains('id-751', $missing);
        $this->assertContains('id-1000', $missing);
    }

    /**
     * Test that findMissingNodes respects custom batch size
     *
     * @test
     */
    public function it_respects_custom_batch_size_for_missing_nodes(): void
    {
        // Arrange: 600 IDs with batch size 200 (should make 3 queries)
        $idsToCheck = array_map(fn($i) => "id-$i", range(1, 600));

        $queryCount = 0;
        $this->mockGraph->shouldReceive('run')
            ->times(3)
            ->andReturnUsing(function ($query) use (&$queryCount) {
                $queryCount++;
                return [];
            });

        // Act: Use custom batch size of 200
        $missing = $this->service->findMissingNodes('CourtDecisionDocument', $idsToCheck, 200);

        // Assert: Should make 3 queries (600 / 200)
        $this->assertEquals(3, $queryCount);
        $this->assertCount(600, $missing);
    }

    /**
     * Test that findMissingNodes accumulates missing IDs from all batches
     *
     * @test
     */
    public function it_accumulates_missing_ids_from_multiple_batches(): void
    {
        // Arrange: 10 IDs, batch size 5
        // Batch 1: id-1 to id-5, Neo4j has id-1, id-3 (missing: id-2, id-4, id-5)
        // Batch 2: id-6 to id-10, Neo4j has id-7, id-9 (missing: id-6, id-8, id-10)
        $idsToCheck = ['id-1', 'id-2', 'id-3', 'id-4', 'id-5', 'id-6', 'id-7', 'id-8', 'id-9', 'id-10'];

        $callCount = 0;
        $this->mockGraph->shouldReceive('run')
            ->times(2)
            ->andReturnUsing(function ($query) use (&$callCount) {
                $callCount++;
                // First batch call
                if ($callCount === 1) {
                    return [
                        ['id' => 'id-1'],
                        ['id' => 'id-3'],
                    ];
                }
                // Second batch call
                if ($callCount === 2) {
                    return [
                        ['id' => 'id-7'],
                        ['id' => 'id-9'],
                    ];
                }
                return [];
            });

        // Act
        $missing = $this->service->findMissingNodes('CourtDecisionDocument', $idsToCheck, 5);

        // Assert: Should find 6 missing IDs from both batches
        $this->assertCount(6, $missing);
        $this->assertContains('id-2', $missing);
        $this->assertContains('id-4', $missing);
        $this->assertContains('id-5', $missing);
        $this->assertContains('id-6', $missing);
        $this->assertContains('id-8', $missing);
        $this->assertContains('id-10', $missing);
    }

    /**
     * Test that batching maintains backward compatibility with empty input
     *
     * @test
     */
    public function it_handles_empty_input_with_batching(): void
    {
        // Act: Empty input should not make any queries
        $missing = $this->service->findMissingNodes('CourtDecisionDocument', [], 500);

        // Assert
        $this->assertEmpty($missing);
    }
}
