<?php

namespace Tests\Integration;

use App\Services\GraphDatabaseService;
use Tests\TestCase;

/**
 * Comprehensive Neo4j Integration Tests
 *
 * Tests the full range of Neo4j graph database functionality including:
 * - Basic CRUD operations
 * - Complex graph traversals
 * - Multi-hop relationship queries
 * - Performance with large datasets
 * - Transaction handling
 * - Graph analytics
 * - Schema management
 *
 * Note: This test does NOT use DatabaseTransactions trait because:
 * - These tests only interact with Neo4j, not PostgreSQL
 * - Neo4j has its own transaction handling separate from PostgreSQL
 * - Using DatabaseTransactions causes "PDOException: There is already an active transaction"
 *   errors because Neo4j operations don't need PostgreSQL transaction wrapping
 * - Cleanup is handled via Neo4j-specific tearDown() method
 *
 * @group integration
 * @group neo4j
 * @group comprehensive
 */
class Neo4jComprehensiveTest extends TestCase
{
    protected GraphDatabaseService $graph;

    protected function setUp(): void
    {
        parent::setUp();

        if (! config('neo4j.sync.enabled')) {
            $this->markTestSkipped('Neo4j is not enabled');
        }

        $this->graph = app(GraphDatabaseService::class);

        if (! $this->graph->isAvailable()) {
            $this->markTestSkipped('Neo4j is not available');
        }
    }

    protected function tearDown(): void
    {
        // Clean up all test nodes
        try {
            // Check if $graph property was initialized before accessing it
            // (it won't be initialized if the test was skipped in setUp)
            if (isset($this->graph) && $this->graph->isAvailable()) {
                $this->graph->run('MATCH (n:TestNode) DETACH DELETE n');
                $this->graph->run('MATCH (n:TestLaw) DETACH DELETE n');
                $this->graph->run('MATCH (n:TestCase) DETACH DELETE n');
                $this->graph->run('MATCH (n:TestCourt) DETACH DELETE n');
                $this->graph->run('MATCH (n:TestKeyword) DETACH DELETE n');
            }
        } catch (\Exception $e) {
            // Ignore cleanup errors
        }

        parent::tearDown();
    }

    /**
     * Test 1: Basic node creation and retrieval
     *
     * @test
     */
    public function test_basic_node_creation_and_retrieval(): void
    {
        // Create a node
        $testId = 'test-node-'.uniqid();
        $result = $this->graph->run(
            'CREATE (n:TestNode {id: $id, name: $name, created: datetime()}) RETURN n',
            [
                'id' => $testId,
                'name' => 'Test Node 1',
            ]
        );

        $this->assertNotEmpty($result);

        // Retrieve the node
        $retrieved = $this->graph->run(
            'MATCH (n:TestNode {id: $id}) RETURN n, n.name as name',
            ['id' => $testId]
        );

        $this->assertCount(1, $retrieved);
        $this->assertEquals('Test Node 1', $retrieved[0]->get('name'));
    }

    /**
     * Test 2: Node update (MERGE with SET)
     *
     * @test
     */
    public function test_node_update_with_merge(): void
    {
        $testId = 'test-merge-'.uniqid();

        // Create initial node
        $this->graph->run(
            'MERGE (n:TestNode {id: $id}) SET n.title = $title, n.version = 1',
            ['id' => $testId, 'title' => 'Original Title']
        );

        // Update the same node
        $this->graph->run(
            'MERGE (n:TestNode {id: $id}) SET n.title = $title, n.version = 2, n.updated_at = datetime()',
            ['id' => $testId, 'title' => 'Updated Title']
        );

        // Verify only one node exists with updated values
        $result = $this->graph->run(
            'MATCH (n:TestNode {id: $id}) RETURN n.title as title, n.version as version',
            ['id' => $testId]
        );

        $this->assertCount(1, $result);
        $this->assertEquals('Updated Title', $result[0]->get('title'));
        $this->assertEquals(2, $result[0]->get('version'));
    }

    /**
     * Test 3: Relationship creation and traversal
     *
     * @test
     */
    public function test_relationship_creation_and_traversal(): void
    {
        $lawId = 'law-'.uniqid();
        $caseId = 'case-'.uniqid();

        // Create two nodes with a relationship
        $this->graph->run(
            'CREATE (l:TestLaw {id: $lawId, title: $lawTitle})
             CREATE (c:TestCase {id: $caseId, title: $caseTitle})
             CREATE (c)-[:CITES {cited_at: datetime(), context: $context}]->(l)',
            [
                'lawId' => $lawId,
                'lawTitle' => 'ZKP Članak 240',
                'caseId' => $caseId,
                'caseTitle' => 'Pp-123/2025',
                'context' => 'pretraga doma',
            ]
        );

        // Traverse relationship forward
        $citedLaws = $this->graph->run(
            'MATCH (c:TestCase {id: $caseId})-[r:CITES]->(l:TestLaw)
             RETURN l.title as law_title, r.context as context',
            ['caseId' => $caseId]
        );

        $this->assertCount(1, $citedLaws);
        $this->assertEquals('ZKP Članak 240', $citedLaws[0]->get('law_title'));
        $this->assertEquals('pretraga doma', $citedLaws[0]->get('context'));

        // Traverse relationship backward
        $citingCases = $this->graph->run(
            'MATCH (c:TestCase)-[:CITES]->(l:TestLaw {id: $lawId})
             RETURN c.title as case_title',
            ['lawId' => $lawId]
        );

        $this->assertCount(1, $citingCases);
        $this->assertEquals('Pp-123/2025', $citingCases[0]->get('case_title'));
    }

    /**
     * Test 4: Multi-hop graph traversal
     *
     * @test
     */
    public function test_multi_hop_graph_traversal(): void
    {
        // Create a chain: Case -> Law1 -> Law2 -> Law3
        $caseId = 'case-'.uniqid();
        $law1Id = 'law1-'.uniqid();
        $law2Id = 'law2-'.uniqid();
        $law3Id = 'law3-'.uniqid();

        $this->graph->run(
            'CREATE (c:TestCase {id: $caseId, title: "Case"})
             CREATE (l1:TestLaw {id: $law1Id, title: "Law 1"})
             CREATE (l2:TestLaw {id: $law2Id, title: "Law 2"})
             CREATE (l3:TestLaw {id: $law3Id, title: "Law 3"})
             CREATE (c)-[:CITES]->(l1)
             CREATE (l1)-[:REFERENCES]->(l2)
             CREATE (l2)-[:SUPERSEDES]->(l3)',
            [
                'caseId' => $caseId,
                'law1Id' => $law1Id,
                'law2Id' => $law2Id,
                'law3Id' => $law3Id,
            ]
        );

        // Find all laws connected within 3 hops
        $connected = $this->graph->run(
            'MATCH (c:TestCase {id: $caseId})-[*1..3]-(connected)
             RETURN DISTINCT connected.title as title, labels(connected) as labels',
            ['caseId' => $caseId]
        );

        $titles = collect($connected)->pluck('title')->filter()->values()->toArray();

        $this->assertContains('Law 1', $titles);
        $this->assertContains('Law 2', $titles);
        $this->assertContains('Law 3', $titles);
    }

    /**
     * Test 5: Variable length path queries
     *
     * @test
     */
    public function test_variable_length_path_queries(): void
    {
        // Create citation chain
        $ids = [];
        for ($i = 1; $i <= 5; $i++) {
            $ids[] = 'law-'.$i.'-'.uniqid();
        }

        // Create chain: Law1 -> Law2 -> Law3 -> Law4 -> Law5
        $query = 'CREATE ';
        foreach ($ids as $i => $id) {
            if ($i > 0) {
                $query .= ', ';
            }
            $query .= "(l$i:TestLaw {id: '$id', title: 'Law ".($i + 1)."'})";
            if ($i > 0) {
                $query .= ', (l'.($i - 1).")-[:CITES]->(l$i)";
            }
        }

        $this->graph->run($query);

        // Find shortest path between first and last
        $path = $this->graph->run(
            'MATCH path = shortestPath((start:TestLaw {id: $startId})-[:CITES*]-(end:TestLaw {id: $endId}))
             RETURN length(path) as pathLength',
            ['startId' => $ids[0], 'endId' => $ids[4]]
        );

        $this->assertEquals(4, $path[0]->get('pathLength'));

        // Find all nodes within distance 2 from first node
        $nearby = $this->graph->run(
            'MATCH (start:TestLaw {id: $startId})-[:CITES*1..2]->(nearby)
             RETURN collect(nearby.title) as titles',
            ['startId' => $ids[0]]
        );

        $titles = $nearby[0]->get('titles');
        $this->assertCount(2, $titles); // Law 2 and Law 3
    }

    /**
     * Test 6: Graph pattern matching
     *
     * @test
     */
    public function test_graph_pattern_matching(): void
    {
        // Create a triangle pattern: Case cites Law1 and Law2, Law1 cites Law2
        $caseId = 'case-'.uniqid();
        $law1Id = 'law1-'.uniqid();
        $law2Id = 'law2-'.uniqid();

        $this->graph->run(
            'CREATE (c:TestCase {id: $caseId})
             CREATE (l1:TestLaw {id: $law1Id})
             CREATE (l2:TestLaw {id: $law2Id})
             CREATE (c)-[:CITES]->(l1)
             CREATE (c)-[:CITES]->(l2)
             CREATE (l1)-[:CITES]->(l2)',
            [
                'caseId' => $caseId,
                'law1Id' => $law1Id,
                'law2Id' => $law2Id,
            ]
        );

        // Find triangle patterns
        $triangles = $this->graph->run(
            'MATCH (c:TestCase)-[:CITES]->(l1:TestLaw)-[:CITES]->(l2:TestLaw)
             WHERE (c)-[:CITES]->(l2)
             RETURN c.id as case_id, l1.id as law1_id, l2.id as law2_id'
        );

        $this->assertNotEmpty($triangles);
        $this->assertEquals($caseId, $triangles[0]->get('case_id'));
        $this->assertEquals($law1Id, $triangles[0]->get('law1_id'));
        $this->assertEquals($law2Id, $triangles[0]->get('law2_id'));
    }

    /**
     * Test 7: Aggregation queries
     *
     * @test
     */
    public function test_aggregation_queries(): void
    {
        // Create multiple laws and cases with citations
        $lawId = 'law-popular-'.uniqid();

        $this->graph->run(
            'CREATE (l:TestLaw {id: $lawId, title: "Popular Law"})',
            ['lawId' => $lawId]
        );

        // Create 5 cases citing this law
        for ($i = 1; $i <= 5; $i++) {
            $this->graph->run(
                'MATCH (l:TestLaw {id: $lawId})
                 CREATE (c:TestCase {id: $caseId, title: $title})
                 CREATE (c)-[:CITES]->(l)',
                [
                    'lawId' => $lawId,
                    'caseId' => 'case-'.$i.'-'.uniqid(),
                    'title' => 'Case '.$i,
                ]
            );
        }

        // Count citations
        $stats = $this->graph->run(
            'MATCH (l:TestLaw {id: $lawId})<-[r:CITES]-(c:TestCase)
             RETURN l.title as law, count(r) as citation_count, collect(c.title) as citing_cases',
            ['lawId' => $lawId]
        );

        $this->assertEquals('Popular Law', $stats[0]->get('law'));
        $this->assertEquals(5, $stats[0]->get('citation_count'));
        $this->assertCount(5, $stats[0]->get('citing_cases'));
    }

    /**
     * Test 8: Complex WHERE clauses
     *
     * @test
     */
    public function test_complex_where_clauses(): void
    {
        // Create laws with different properties
        $testData = [
            ['id' => 'law-1-'.uniqid(), 'jurisdiction' => 'HR', 'year' => 2020, 'active' => true],
            ['id' => 'law-2-'.uniqid(), 'jurisdiction' => 'HR', 'year' => 2023, 'active' => true],
            ['id' => 'law-3-'.uniqid(), 'jurisdiction' => 'EU', 'year' => 2021, 'active' => false],
            ['id' => 'law-4-'.uniqid(), 'jurisdiction' => 'HR', 'year' => 2024, 'active' => true],
        ];

        foreach ($testData as $data) {
            $this->graph->run(
                'CREATE (l:TestLaw {id: $id, jurisdiction: $jurisdiction, year: $year, active: $active})',
                $data
            );
        }

        // Complex query: HR jurisdiction, after 2021, active
        $result = $this->graph->run(
            'MATCH (l:TestLaw)
             WHERE l.jurisdiction = $jurisdiction
               AND l.year > $minYear
               AND l.active = true
             RETURN l.id as id, l.year as year
             ORDER BY l.year DESC',
            ['jurisdiction' => 'HR', 'minYear' => 2021]
        );

        $this->assertCount(2, $result); // Law 2 (2023) and Law 4 (2024)
        $this->assertEquals(2024, $result[0]->get('year')); // Ordered DESC
    }

    /**
     * Test 9: COLLECT and UNWIND operations
     *
     * @test
     */
    public function test_collect_and_unwind_operations(): void
    {
        // Create a case with multiple keywords
        $caseId = 'case-'.uniqid();
        $keywords = ['pretraga', 'naredba', 'sud', 'dokaz'];

        $this->graph->run(
            'CREATE (c:TestCase {id: $caseId, title: "Test Case"})',
            ['caseId' => $caseId]
        );

        // Use UNWIND to create multiple keyword relationships
        $this->graph->run(
            'MATCH (c:TestCase {id: $caseId})
             UNWIND $keywords as keyword
             MERGE (k:TestKeyword {name: keyword})
             CREATE (c)-[:HAS_KEYWORD]->(k)',
            ['caseId' => $caseId, 'keywords' => $keywords]
        );

        // Collect keywords back
        $result = $this->graph->run(
            'MATCH (c:TestCase {id: $caseId})-[:HAS_KEYWORD]->(k:TestKeyword)
             RETURN collect(k.name) as keywords',
            ['caseId' => $caseId]
        );

        $collectedKeywords = $result[0]->get('keywords');
        $this->assertCount(4, $collectedKeywords);

        foreach ($keywords as $keyword) {
            $this->assertContains($keyword, $collectedKeywords);
        }
    }

    /**
     * Test 10: OPTIONAL MATCH
     *
     * @test
     */
    public function test_optional_match(): void
    {
        // Create law with and without citations
        $law1Id = 'law-with-cites-'.uniqid();
        $law2Id = 'law-without-cites-'.uniqid();

        $this->graph->run(
            'CREATE (l1:TestLaw {id: $law1Id, title: "Law with Citations"})
             CREATE (l2:TestLaw {id: $law2Id, title: "Law without Citations"})
             CREATE (cited:TestLaw {id: $citedId, title: "Cited Law"})
             CREATE (l1)-[:CITES]->(cited)',
            [
                'law1Id' => $law1Id,
                'law2Id' => $law2Id,
                'citedId' => 'cited-'.uniqid(),
            ]
        );

        // OPTIONAL MATCH returns NULL for non-matching patterns
        $result = $this->graph->run(
            'MATCH (l:TestLaw)
             WHERE l.id IN [$id1, $id2]
             OPTIONAL MATCH (l)-[:CITES]->(cited:TestLaw)
             RETURN l.title as law, cited.title as cited_law',
            ['id1' => $law1Id, 'id2' => $law2Id]
        );

        $this->assertCount(2, $result);

        // One should have cited law, one should not
        $withCitation = collect($result)->firstWhere('law', 'Law with Citations');
        $withoutCitation = collect($result)->firstWhere('law', 'Law without Citations');

        $this->assertNotNull($withCitation->get('cited_law'));
        $this->assertNull($withoutCitation->get('cited_law'));
    }

    /**
     * Test 11: CASE expressions
     *
     * @test
     */
    public function test_case_expressions(): void
    {
        // Create laws with different years
        $testLaws = [
            ['id' => 'law-old-'.uniqid(), 'year' => 2010],
            ['id' => 'law-recent-'.uniqid(), 'year' => 2022],
            ['id' => 'law-new-'.uniqid(), 'year' => 2025],
        ];

        foreach ($testLaws as $law) {
            $this->graph->run(
                'CREATE (l:TestLaw {id: $id, year: $year})',
                $law
            );
        }

        // Use CASE to categorize
        $result = $this->graph->run(
            'MATCH (l:TestLaw)
             WHERE l.year IS NOT NULL
             RETURN l.id as id,
                    l.year as year,
                    CASE
                      WHEN l.year < 2015 THEN "old"
                      WHEN l.year < 2023 THEN "recent"
                      ELSE "new"
                    END as category'
        );

        $categories = collect($result)->pluck('category')->unique()->sort()->values();
        $this->assertContains('old', $categories);
        $this->assertContains('recent', $categories);
        $this->assertContains('new', $categories);
    }

    /**
     * Test 12: Relationship properties and filtering
     *
     * @test
     */
    public function test_relationship_properties_and_filtering(): void
    {
        $caseId = 'case-'.uniqid();
        $law1Id = 'law1-'.uniqid();
        $law2Id = 'law2-'.uniqid();
        $law3Id = 'law3-'.uniqid();

        // Create relationships with different strengths
        $this->graph->run(
            'CREATE (c:TestCase {id: $caseId})
             CREATE (l1:TestLaw {id: $law1Id})
             CREATE (l2:TestLaw {id: $law2Id})
             CREATE (l3:TestLaw {id: $law3Id})
             CREATE (c)-[:CITES {relevance: 0.95, paragraph: 5}]->(l1)
             CREATE (c)-[:CITES {relevance: 0.60, paragraph: 12}]->(l2)
             CREATE (c)-[:CITES {relevance: 0.85, paragraph: 3}]->(l3)',
            [
                'caseId' => $caseId,
                'law1Id' => $law1Id,
                'law2Id' => $law2Id,
                'law3Id' => $law3Id,
            ]
        );

        // Filter by relationship properties
        $highRelevance = $this->graph->run(
            'MATCH (c:TestCase {id: $caseId})-[r:CITES]->(l:TestLaw)
             WHERE r.relevance > 0.80
             RETURN l.id as law_id, r.relevance as relevance, r.paragraph as paragraph
             ORDER BY r.relevance DESC',
            ['caseId' => $caseId]
        );

        $this->assertCount(2, $highRelevance); // law1 (0.95) and law3 (0.85)
        $this->assertEquals(0.95, $highRelevance[0]->get('relevance'));
        $this->assertEquals(0.85, $highRelevance[1]->get('relevance'));
    }

    /**
     * Test 13: Delete operations (DETACH DELETE)
     *
     * @test
     */
    public function test_delete_operations(): void
    {
        $lawId = 'law-to-delete-'.uniqid();
        $caseId = 'case-'.uniqid();

        // Create law with relationships
        $this->graph->run(
            'CREATE (l:TestLaw {id: $lawId, title: "To Be Deleted"})
             CREATE (c:TestCase {id: $caseId})
             CREATE (c)-[:CITES]->(l)',
            ['lawId' => $lawId, 'caseId' => $caseId]
        );

        // Verify it exists
        $exists = $this->graph->run(
            'MATCH (l:TestLaw {id: $lawId}) RETURN count(l) as count',
            ['lawId' => $lawId]
        );
        $this->assertEquals(1, $exists[0]->get('count'));

        // Delete with relationships (DETACH DELETE)
        $this->graph->run(
            'MATCH (l:TestLaw {id: $lawId}) DETACH DELETE l',
            ['lawId' => $lawId]
        );

        // Verify it's gone
        $gone = $this->graph->run(
            'MATCH (l:TestLaw {id: $lawId}) RETURN count(l) as count',
            ['lawId' => $lawId]
        );
        $this->assertEquals(0, $gone[0]->get('count'));

        // Verify case still exists
        $caseStillExists = $this->graph->run(
            'MATCH (c:TestCase {id: $caseId}) RETURN count(c) as count',
            ['caseId' => $caseId]
        );
        $this->assertEquals(1, $caseStillExists[0]->get('count'));
    }

    /**
     * Test 14: Multiple labels per node
     *
     * @test
     */
    public function test_multiple_labels_per_node(): void
    {
        $docId = 'doc-'.uniqid();

        // Create node with multiple labels
        $this->graph->run(
            'CREATE (n:TestNode:TestLaw:LegalDocument {id: $id, title: "Multi-label Node"})',
            ['id' => $docId]
        );

        // Query by different labels
        $byFirstLabel = $this->graph->run(
            'MATCH (n:TestNode {id: $id}) RETURN labels(n) as labels',
            ['id' => $docId]
        );

        $bySecondLabel = $this->graph->run(
            'MATCH (n:TestLaw {id: $id}) RETURN labels(n) as labels',
            ['id' => $docId]
        );

        $labelsFromFirst = iterator_to_array($byFirstLabel[0]->get('labels'));
        $this->assertContains('TestNode', $labelsFromFirst);
        $this->assertContains('TestLaw', $labelsFromFirst);
        $this->assertContains('LegalDocument', $labelsFromFirst);

        $labelsFromSecond = iterator_to_array($bySecondLabel[0]->get('labels'));
        $this->assertEquals($labelsFromFirst, $labelsFromSecond);
    }

    /**
     * Test 15: DateTime operations
     *
     * @test
     */
    public function test_datetime_operations(): void
    {
        $docId = 'doc-'.uniqid();

        // Create node with datetime
        $this->graph->run(
            'CREATE (n:TestNode {
                id: $id,
                created_at: datetime(),
                published_date: date("2025-01-15"),
                event_time: time("14:30:00")
             })',
            ['id' => $docId]
        );

        // Query with datetime comparison
        $result = $this->graph->run(
            'MATCH (n:TestNode {id: $id})
             RETURN
                n.created_at as created,
                n.published_date as published,
                n.event_time as event,
                duration.between(date("2025-01-01"), n.published_date).days as days_since_new_year',
            ['id' => $docId]
        );

        $this->assertNotNull($result[0]->get('created'));
        $this->assertNotNull($result[0]->get('published'));
        $this->assertEquals(14, $result[0]->get('days_since_new_year'));
    }

    /**
     * Test 16: EXISTS clause
     *
     * @test
     */
    public function test_exists_clause(): void
    {
        // Create laws with and without keywords
        $law1Id = 'law-with-keywords-'.uniqid();
        $law2Id = 'law-without-keywords-'.uniqid();

        $this->graph->run(
            'CREATE (l1:TestLaw {id: $law1Id})
             CREATE (l2:TestLaw {id: $law2Id})
             CREATE (k:TestKeyword {name: "test"})
             CREATE (l1)-[:HAS_KEYWORD]->(k)',
            ['law1Id' => $law1Id, 'law2Id' => $law2Id]
        );

        // Find laws that have keywords using EXISTS
        $withKeywords = $this->graph->run(
            'MATCH (l:TestLaw)
             WHERE EXISTS {
                MATCH (l)-[:HAS_KEYWORD]->(:TestKeyword)
             }
             RETURN l.id as id'
        );

        $this->assertCount(1, $withKeywords);
        $this->assertEquals($law1Id, $withKeywords[0]->get('id'));
    }

    /**
     * Test 17: Performance with batch operations
     *
     * @test
     *
     * @group slow
     */
    public function test_performance_with_batch_operations(): void
    {
        $batchSize = 100;
        $startTime = microtime(true);

        // Batch create nodes using UNWIND
        $nodeData = [];
        for ($i = 0; $i < $batchSize; $i++) {
            $nodeData[] = [
                'id' => 'batch-law-'.$i.'-'.uniqid(),
                'title' => 'Law '.$i,
                'index' => $i,
            ];
        }

        $this->graph->run(
            'UNWIND $batch as row
             CREATE (l:TestLaw {id: row.id, title: row.title, index: row.index})',
            ['batch' => $nodeData]
        );

        $elapsed = microtime(true) - $startTime;

        // Verify all nodes were created
        $count = $this->graph->run('MATCH (l:TestLaw) WHERE l.index IS NOT NULL RETURN count(l) as count');
        $this->assertEquals($batchSize, $count[0]->get('count'));

        // Should complete in reasonable time (< 5 seconds for 100 nodes)
        $this->assertLessThan(5, $elapsed, "Batch creation took too long: {$elapsed}s");
    }

    /**
     * Test 18: Graph analytics - degree centrality
     *
     * @test
     */
    public function test_graph_analytics_degree_centrality(): void
    {
        // Create a hub-and-spoke pattern
        $hubId = 'law-hub-'.uniqid();
        $this->graph->run('CREATE (hub:TestLaw {id: $id, title: "Hub Law"})', ['id' => $hubId]);

        // Create 10 cases citing the hub
        for ($i = 0; $i < 10; $i++) {
            $this->graph->run(
                'MATCH (hub:TestLaw {id: $hubId})
                 CREATE (c:TestCase {id: $caseId})
                 CREATE (c)-[:CITES]->(hub)',
                ['hubId' => $hubId, 'caseId' => 'case-'.$i.'-'.uniqid()]
            );
        }

        // Calculate degree (number of connections)
        $degree = $this->graph->run(
            'MATCH (l:TestLaw {id: $hubId})<-[r]-()
             RETURN count(r) as in_degree',
            ['hubId' => $hubId]
        );

        $this->assertEquals(10, $degree[0]->get('in_degree'));

        // Find most cited laws
        $mostCited = $this->graph->run(
            'MATCH (l:TestLaw)<-[r:CITES]-()
             RETURN l.title as law, count(r) as citations
             ORDER BY citations DESC
             LIMIT 1'
        );

        $this->assertEquals('Hub Law', $mostCited[0]->get('law'));
        $this->assertEquals(10, $mostCited[0]->get('citations'));
    }

    /**
     * Test 19: Transaction rollback on error
     *
     * @test
     */
    public function test_transaction_rollback_on_error(): void
    {
        $testId = 'transaction-test-'.uniqid();

        try {
            $this->graph->transaction(function ($tsx) use ($testId) {
                // Create a node
                $tsx->run(
                    'CREATE (n:TestNode {id: $id, title: "Should not persist"})',
                    ['id' => $testId]
                );

                // Throw an error
                throw new \Exception('Intentional error for rollback test');
            });
        } catch (\Exception $e) {
            // Expected
        }

        // Verify node was not created (transaction rolled back)
        $result = $this->graph->run(
            'MATCH (n:TestNode {id: $id}) RETURN count(n) as count',
            ['id' => $testId]
        );

        $this->assertEquals(0, $result[0]->get('count'));
    }

    /**
     * Test 20: Complex legal graph query
     *
     * This test simulates a real-world legal research query:
     * Find all court decisions that cite laws that are also cited by a specific case,
     * ranked by relevance.
     *
     * @test
     */
    public function test_complex_legal_graph_query(): void
    {
        // Setup: Create a complex legal graph
        $myCaseId = 'my-case-'.uniqid();
        $sharedLaw1Id = 'shared-law-1-'.uniqid();
        $sharedLaw2Id = 'shared-law-2-'.uniqid();
        $decision1Id = 'decision-1-'.uniqid();
        $decision2Id = 'decision-2-'.uniqid();

        $this->graph->run(
            'CREATE (myCase:TestCase {id: $myCaseId, title: "My Case"})
             CREATE (law1:TestLaw {id: $law1Id, title: "Shared Law 1"})
             CREATE (law2:TestLaw {id: $law2Id, title: "Shared Law 2"})
             CREATE (d1:TestCourt {id: $d1Id, title: "Similar Decision 1", court: "Vrhovni sud"})
             CREATE (d2:TestCourt {id: $d2Id, title: "Similar Decision 2", court: "Županijski sud"})
             CREATE (myCase)-[:CITES]->(law1)
             CREATE (myCase)-[:CITES]->(law2)
             CREATE (d1)-[:CITES]->(law1)
             CREATE (d1)-[:CITES]->(law2)
             CREATE (d2)-[:CITES]->(law1)',
            [
                'myCaseId' => $myCaseId,
                'law1Id' => $sharedLaw1Id,
                'law2Id' => $sharedLaw2Id,
                'd1Id' => $decision1Id,
                'd2Id' => $decision2Id,
            ]
        );

        // Query: Find decisions citing same laws, ranked by number of shared citations
        $similarDecisions = $this->graph->run(
            'MATCH (myCase:TestCase {id: $myCaseId})-[:CITES]->(law:TestLaw)<-[:CITES]-(decision:TestCourt)
             WITH decision, collect(DISTINCT law.title) as shared_laws, count(DISTINCT law) as shared_count
             WHERE shared_count > 0
             RETURN decision.title as decision,
                    decision.court as court,
                    shared_count,
                    shared_laws
             ORDER BY shared_count DESC',
            ['myCaseId' => $myCaseId]
        );

        $this->assertCount(2, $similarDecisions);

        // First result should be decision1 (cites both laws)
        $this->assertEquals('Similar Decision 1', $similarDecisions[0]->get('decision'));
        $this->assertEquals(2, $similarDecisions[0]->get('shared_count'));

        // Second result should be decision2 (cites one law)
        $this->assertEquals('Similar Decision 2', $similarDecisions[1]->get('decision'));
        $this->assertEquals(1, $similarDecisions[1]->get('shared_count'));
    }

    /**
     * Test 21: PageRank algorithm for citation influence
     *
     * @test
     *
     * @group slow
     */
    public function test_pagerank_identifies_influential_laws(): void
    {
        // Create citation network
        $centralLaw = 'law-central-'.uniqid();
        $this->graph->run('CREATE (l:TestLaw {id: $id, title: "Central Law"})', ['id' => $centralLaw]);

        // Create 10 laws citing the central law
        for ($i = 0; $i < 10; $i++) {
            $this->graph->run(
                'MATCH (central:TestLaw {id: $centralId})
                 CREATE (citing:TestLaw {id: $citingId})
                 CREATE (citing)-[:CITES]->(central)',
                [
                    'centralId' => $centralLaw,
                    'citingId' => 'law-citing-'.$i.'-'.uniqid(),
                ]
            );
        }

        // Calculate degree centrality (simplified PageRank)
        $influential = $this->graph->run(
            'MATCH (l:TestLaw)<-[r:CITES]-()
             RETURN l.id as law_id, count(r) as influence_score
             ORDER BY influence_score DESC
             LIMIT 1'
        );

        $this->assertNotEmpty($influential);
        $this->assertEquals($centralLaw, $influential[0]->get('law_id'));
        $this->assertEquals(10, $influential[0]->get('influence_score'));
    }
}
