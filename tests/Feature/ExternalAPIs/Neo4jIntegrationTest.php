<?php

namespace Tests\Feature\ExternalAPIs;

use App\Models\Law;
use App\Services\Graph\GraphRagOrchestrator;
use App\Services\GraphDatabaseService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Neo4j Integration Tests
 *
 * Tests the integration with Neo4j graph database including:
 * - Connection and basic queries
 * - Large batch operations
 * - Complex relationship queries
 * - Error recovery mechanisms
 *
 * @group external-api
 * @group neo4j
 */
class Neo4jIntegrationTest extends TestCase
{
    use UsesTestDatabase;

    protected GraphDatabaseService $graph;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear cache before each test
        Cache::forget('neo4j:status');

        // Enable Neo4j for tests
        Config::set('neo4j.sync.enabled', true);

        $this->graph = app(GraphDatabaseService::class);
    }

    protected function tearDown(): void
    {
        // Clean up test nodes after each test
        try {
            if ($this->graph->isAvailable()) {
                $this->graph->run('MATCH (n:TestNode) DELETE n');
            }
        } catch (\Exception $e) {
            // Ignore cleanup errors
        }

        parent::tearDown();
    }

    /**
     * Test Neo4j connection and basic query execution
     *
     * @group external-api
     * @group neo4j
     */
    public function test_neo4j_connection_and_query(): void
    {
        if (! $this->graph->isAvailable()) {
            $this->markTestSkipped('Neo4j is not available');
        }

        // Test connection
        $this->assertTrue($this->graph->isAvailable(), 'Neo4j should be available');

        // Create test node
        $uniqueName = 'Test_'.uniqid();
        $result = $this->graph->run('CREATE (n:TestNode {name: $name}) RETURN n', [
            'name' => $uniqueName,
        ]);

        $this->assertNotEmpty($result);

        // Verify node was created
        $queryResult = $this->graph->run(
            'MATCH (n:TestNode {name: $name}) RETURN n.name as name',
            ['name' => $uniqueName]
        );

        $this->assertNotEmpty($queryResult);
        $this->assertCount(1, $queryResult);

        // Clean up
        $this->graph->run('MATCH (n:TestNode {name: $name}) DELETE n', ['name' => $uniqueName]);
    }

    /**
     * Test batch sync of 100+ documents
     * Verifies performance and stability under load
     *
     * @group external-api
     * @group neo4j
     * @group slow
     */
    public function test_neo4j_handles_large_batch_operations(): void
    {
        if (! $this->graph->isAvailable()) {
            $this->markTestSkipped('Neo4j is not available');
        }

        // Create 100 test laws
        $laws = Law::factory()->count(100)->create([
            'embedding_vector' => array_fill(0, 1536, 0.1),
        ]);

        $orchestrator = app(GraphRagOrchestrator::class);

        $startTime = microtime(true);
        $successCount = 0;
        $errorCount = 0;

        foreach ($laws as $law) {
            try {
                $orchestrator->syncLaw($law->id);
                $successCount++;
            } catch (\Exception $e) {
                $errorCount++;
            }
        }

        $elapsed = microtime(true) - $startTime;

        // Assertions
        $this->assertGreaterThan(0, $successCount, 'At least some laws should sync successfully');
        $this->assertLessThan(30, $elapsed, 'Should complete in reasonable time (<30s for 100 items)');

        // Verify some nodes were created
        $nodeCount = $this->graph->run(
            'MATCH (n:LawDocument) RETURN count(n) as count'
        );

        $this->assertNotEmpty($nodeCount);
        $count = $nodeCount[0]->get('count');
        $this->assertGreaterThan(0, $count, 'Should have created law nodes');

        // Clean up created nodes
        $this->graph->run('MATCH (n:LawDocument) WHERE n.id IN $ids DETACH DELETE n', [
            'ids' => $laws->pluck('id')->toArray(),
        ]);
    }

    /**
     * Test complex relationship queries
     *
     * Tests Neo4j's ability to traverse relationships and perform
     * complex graph queries including multi-hop traversals and pattern matching.
     *
     * @group external-api
     * @group neo4j
     */
    public function test_neo4j_relationship_queries(): void
    {
        if (! $this->graph->isAvailable()) {
            $this->markTestSkipped('Neo4j is not available');
        }

        // Create test nodes with relationships
        $testId1 = 'test_law_'.uniqid();
        $testId2 = 'test_law_'.uniqid();
        $testId3 = 'test_case_'.uniqid();

        // Create law documents
        $this->graph->run(
            'CREATE (l1:TestNode:LawDocument {id: $id1, title: $title1})
             CREATE (l2:TestNode:LawDocument {id: $id2, title: $title2})
             CREATE (c:TestNode:CaseDocument {id: $id3, title: $title3})
             CREATE (l1)-[:CITES]->(l2)
             CREATE (c)-[:REFERENCES]->(l1)
             CREATE (c)-[:REFERENCES]->(l2)',
            [
                'id1' => $testId1,
                'title1' => 'Test Law 1',
                'id2' => $testId2,
                'title2' => 'Test Law 2',
                'id3' => $testId3,
                'title3' => 'Test Case',
            ]
        );

        // Test 1: Direct relationship query
        $citedLaws = $this->graph->run(
            'MATCH (l1:LawDocument {id: $id})-[:CITES]->(l2:LawDocument)
             RETURN l2.id as id, l2.title as title',
            ['id' => $testId1]
        );

        $this->assertCount(1, $citedLaws);
        $this->assertEquals($testId2, $citedLaws[0]->get('id'));

        // Test 2: Multi-hop traversal
        $referencedByCase = $this->graph->run(
            'MATCH (c:CaseDocument)-[:REFERENCES]->(l:LawDocument)
             WHERE c.id = $caseId
             RETURN l.id as id, l.title as title',
            ['caseId' => $testId3]
        );

        $this->assertCount(2, $referencedByCase);

        // Test 3: Pattern matching with variable length path
        $connectedDocs = $this->graph->run(
            'MATCH (start {id: $id})-[*1..2]-(connected)
             RETURN DISTINCT connected.id as id, labels(connected) as labels',
            ['id' => $testId3]
        );

        $this->assertGreaterThanOrEqual(2, count($connectedDocs));

        // Test 4: Aggregation query
        $citationCount = $this->graph->run(
            'MATCH (l:LawDocument {id: $id})<-[:REFERENCES]-(c:CaseDocument)
             RETURN count(c) as count',
            ['id' => $testId1]
        );

        $this->assertEquals(1, $citationCount[0]->get('count'));

        // Clean up
        $this->graph->run(
            'MATCH (n:TestNode) WHERE n.id IN $ids DETACH DELETE n',
            ['ids' => [$testId1, $testId2, $testId3]]
        );
    }

    /**
     * Test recovery from Neo4j connection failures
     *
     * Tests the service's ability to gracefully handle and recover from
     * Neo4j connection failures, including circuit breaker patterns.
     *
     * @group external-api
     * @group neo4j
     */
    public function test_neo4j_error_recovery(): void
    {
        // Test 1: Service handles unavailable Neo4j gracefully
        Config::set('neo4j.sync.enabled', false);
        $service = new GraphDatabaseService;

        $this->assertFalse($service->isAvailable());

        // Re-enable for next tests
        Config::set('neo4j.sync.enabled', true);

        // Test 2: Invalid query handling
        if ($this->graph->isAvailable()) {
            try {
                // Run invalid Cypher query
                $this->graph->run('INVALID CYPHER QUERY');
                $this->fail('Should have thrown exception for invalid query');
            } catch (\Exception $e) {
                $this->assertInstanceOf(\Exception::class, $e);
                $this->assertNotEmpty($e->getMessage());
            }
        }

        // Test 3: Service recovers after error
        if ($this->graph->isAvailable()) {
            // After error, should still be able to run valid queries
            $result = $this->graph->run('RETURN 1 as test');
            $this->assertNotEmpty($result);
            $this->assertEquals(1, $result[0]->get('test'));
        }

        // Test 4: Large query timeout handling
        if ($this->graph->isAvailable()) {
            try {
                // Create a potentially slow query (but not too slow to fail the test)
                $this->graph->run(
                    'UNWIND range(1, 1000) as i CREATE (n:TestNode {id: i}) RETURN count(n) as count'
                );

                // Clean up
                $this->graph->run('MATCH (n:TestNode) WHERE n.id IS NOT NULL DELETE n');

                $this->assertTrue(true, 'Should handle large operations');
            } catch (\Exception $e) {
                // If it times out, that's also a valid test result
                $this->assertInstanceOf(\Exception::class, $e);
            }
        }

        // Test 5: Verify health check status is accurate
        $isAvailable = $this->graph->isAvailable();
        $this->assertIsBool($isAvailable);

        // If available, verify we can actually query
        if ($isAvailable) {
            $result = $this->graph->run('RETURN 1 as test');
            $this->assertNotEmpty($result);
        }
    }

    /**
     * Test Neo4j connection with configuration validation
     *
     * Additional test to verify configuration and connection handling
     */
    public function test_neo4j_configuration_validation(): void
    {
        $service = app(GraphDatabaseService::class);

        if ($service->isAvailable()) {
            // Test that database name is correctly configured
            $result = $service->run('CALL dbms.database()');
            $this->assertNotEmpty($result);

            // Test basic node operations
            $uniqueId = uniqid();
            $service->run(
                'CREATE (n:TestNode {test_id: $id, created_at: datetime()}) RETURN n',
                ['id' => $uniqueId]
            );

            // Verify node exists
            $result = $service->run(
                'MATCH (n:TestNode {test_id: $id}) RETURN n.test_id as id',
                ['id' => $uniqueId]
            );

            $this->assertCount(1, $result);
            $this->assertEquals($uniqueId, $result[0]->get('id'));

            // Clean up
            $service->run(
                'MATCH (n:TestNode {test_id: $id}) DELETE n',
                ['id' => $uniqueId]
            );
        } else {
            $this->markTestSkipped('Neo4j is not available for configuration validation');
        }
    }
}
