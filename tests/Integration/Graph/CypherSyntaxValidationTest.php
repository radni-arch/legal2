<?php

namespace Tests\Integration\Graph;

use App\Services\GraphDatabaseService;
use App\Services\Graph\DecisionGraphSyncService;
use App\Services\Graph\JudgeGraphSyncService;
use App\Services\Graph\PartyGraphSyncService;
use App\Services\Graph\LegalPrincipleGraphSyncService;
use App\Services\Graph\PrecedentDetector;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionMethod;

class CypherSyntaxValidationTest extends GraphIntegrationTestCase
{
    /**
     * Extract Cypher queries from a service class by analyzing its methods
     */
    protected function extractCypherQueries(string $serviceClass): array
    {
        $queries = [];
        $reflection = new ReflectionClass($serviceClass);
        $source = file_get_contents($reflection->getFileName());

        // Match common Cypher patterns
        preg_match_all(
            '/(?:run|execute)\s*\(\s*[\'"]([^"\']+(?:MATCH|CREATE|MERGE|DELETE|RETURN)[^"\']+)[\'"]/is',
            $source,
            $matches
        );

        foreach ($matches[1] as $query) {
            // Clean up escaped quotes and whitespace
            $query = str_replace(['\\"', "\\'", '\\n'], ['"', "'", ' '], $query);
            $query = preg_replace('/\s+/', ' ', trim($query));
            if (!empty($query)) {
                $queries[] = $query;
            }
        }

        return array_unique($queries);
    }

    #[Test]
    public function it_validates_graph_database_service_queries(): void
    {
        // Test basic operations that GraphDatabaseService supports
        $queries = [
            'RETURN 1 as test',
            'CREATE (n:TestNode {id: $id}) RETURN n',
            'MATCH (n:TestNode {id: $id}) RETURN n',
            'MATCH (n:TestNode {id: $id}) DETACH DELETE n',
            'MERGE (n:TestNode {id: $id}) SET n.updated = true RETURN n',
        ];

        foreach ($queries as $query) {
            try {
                // Use safe parameter values
                $result = $this->graph->run($query, ['id' => 'test_syntax_' . md5($query)]);
                $this->assertNotNull($result, "Query should execute: {$query}");
            } catch (\Throwable $e) {
                if (str_contains($e->getMessage(), 'not available')) {
                    $this->markTestSkipped('Neo4j not available');
                }
                $this->fail("Cypher syntax error in query: {$query}\nError: {$e->getMessage()}");
            }
        }

        // Cleanup
        $this->graph->run('MATCH (n:TestNode) WHERE n.id STARTS WITH $prefix DETACH DELETE n',
            ['prefix' => 'test_syntax_']);
    }

    #[Test]
    public function it_validates_upsert_node_query_syntax(): void
    {
        // Test the MERGE pattern used by upsertNode
        $query = 'MERGE (n:TestLabel {id: $id})
                  SET n.name = $name, n.updated_at = $updated_at
                  RETURN n';

        try {
            $result = $this->graph->run($query, [
                'id' => 'syntax_test_1',
                'name' => 'Test',
                'updated_at' => date('c'),
            ]);
            $this->assertNotNull($result);

            // Cleanup
            $this->graph->run('MATCH (n:TestLabel {id: $id}) DELETE n', ['id' => 'syntax_test_1']);
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'not available')) {
                $this->markTestSkipped('Neo4j not available');
            }
            $this->fail("Query syntax error: {$e->getMessage()}");
        }
    }

    #[Test]
    public function it_validates_create_relationship_query_syntax(): void
    {
        // Test the relationship creation pattern
        try {
            // Create two nodes first
            $this->graph->run('CREATE (a:TestNode {id: $id})', ['id' => 'rel_test_a']);
            $this->graph->run('CREATE (b:TestNode {id: $id})', ['id' => 'rel_test_b']);

            // Test relationship creation
            $query = 'MATCH (a:TestNode {id: $fromId}), (b:TestNode {id: $toId})
                      MERGE (a)-[r:TEST_REL]->(b)
                      SET r.created_at = $created_at
                      RETURN r';

            $result = $this->graph->run($query, [
                'fromId' => 'rel_test_a',
                'toId' => 'rel_test_b',
                'created_at' => date('c'),
            ]);

            $this->assertNotNull($result);

            // Cleanup
            $this->graph->run('MATCH (n:TestNode) WHERE n.id STARTS WITH $prefix DETACH DELETE n',
                ['prefix' => 'rel_test_']);
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'not available')) {
                $this->markTestSkipped('Neo4j not available');
            }
            $this->fail("Relationship query syntax error: {$e->getMessage()}");
        }
    }

    #[Test]
    public function it_validates_complex_match_patterns(): void
    {
        // Test complex patterns used in graph traversal
        $queries = [
            // Path matching
            'MATCH (a:TestNode)-[:RELATES_TO*1..3]->(b:TestNode) RETURN a, b LIMIT 1',
            // Optional match
            'MATCH (a:TestNode) OPTIONAL MATCH (a)-[r]->(b) RETURN a, r, b LIMIT 1',
            // Aggregation
            'MATCH (n:TestNode) RETURN labels(n) as labels, count(n) as count',
            // WHERE with multiple conditions
            'MATCH (n:TestNode) WHERE n.id IS NOT NULL AND n.name CONTAINS $search RETURN n LIMIT 1',
        ];

        foreach ($queries as $query) {
            try {
                $result = $this->graph->run($query, ['search' => 'test']);
                $this->assertNotNull($result, "Query should execute: {$query}");
            } catch (\Throwable $e) {
                if (str_contains($e->getMessage(), 'not available')) {
                    $this->markTestSkipped('Neo4j not available');
                }
                $this->fail("Complex query syntax error: {$query}\nError: {$e->getMessage()}");
            }
        }
    }

    #[Test]
    public function it_validates_index_and_constraint_syntax(): void
    {
        // Test constraint and index creation syntax (IF NOT EXISTS should be safe)
        $statements = [
            'CREATE CONSTRAINT test_constraint IF NOT EXISTS FOR (n:TestSyntax) REQUIRE n.id IS UNIQUE',
            'CREATE INDEX test_index IF NOT EXISTS FOR (n:TestSyntax) ON (n.name)',
        ];

        foreach ($statements as $statement) {
            try {
                $this->graph->run($statement);
                $this->assertTrue(true, "Statement should execute: {$statement}");
            } catch (\Throwable $e) {
                if (str_contains($e->getMessage(), 'not available')) {
                    $this->markTestSkipped('Neo4j not available');
                }
                // Some constraint errors are okay (already exists, etc.)
                if (!str_contains($e->getMessage(), 'already exists') &&
                    !str_contains($e->getMessage(), 'equivalent')) {
                    $this->fail("Schema statement syntax error: {$statement}\nError: {$e->getMessage()}");
                }
            }
        }

        // Cleanup - drop test constraint and index
        try {
            $this->graph->run('DROP CONSTRAINT test_constraint IF EXISTS');
            $this->graph->run('DROP INDEX test_index IF EXISTS');
        } catch (\Throwable $e) {
            // Ignore cleanup errors
        }
    }
}
