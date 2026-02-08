<?php

namespace Tests\Performance\Graph;

use App\Models\CourtDecision;
use App\Models\CourtDecisionDocument;
use App\Services\GraphDatabaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Integration\Graph\GraphIntegrationTestCase;

#[Group('performance')]
class QueryPerformanceTest extends GraphIntegrationTestCase
{
    use RefreshDatabase;

    protected GraphDatabaseService $graphService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->graphService = app(GraphDatabaseService::class);

        // Create baseline dataset for query performance testing
        $this->seedTestData();
    }

    /**
     * Seed graph database with test data for query performance testing
     */
    protected function seedTestData(): void
    {
        try {
            // Create 50 test decisions with various relationships
            for ($i = 0; $i < 50; $i++) {
                $decision = CourtDecision::factory()->create([
                    'case_number' => "QUERY-PERF-{$i}/2026",
                    'court' => 'Query Test Court ' . ($i % 5),
                    'jurisdiction' => 'Croatia',
                    'judge' => 'Judge ' . ($i % 10),
                    'decision_date' => '2026-01-' . str_pad(($i % 28) + 1, 2, '0', STR_PAD_LEFT),
                ]);

                $doc = CourtDecisionDocument::factory()->create([
                    'decision_id' => $decision->id,
                    'title' => "Query Performance Test {$i}",
                    'content' => "Test content for query performance benchmark {$i}.",
                ]);

                // Sync to graph (minimal sync for query testing)
                $docId = $doc->id;

                $this->graph->upsertNode('CourtDecisionDocument', $docId, [
                    'decision_id' => $decision->id,
                    'case_number' => $decision->case_number,
                    'court' => $decision->court,
                    'jurisdiction' => $decision->jurisdiction,
                    'judge' => $decision->judge,
                    'decision_date' => $decision->decision_date,
                ]);

                // Create court node
                $courtId = 'court_' . md5($decision->court);
                $this->graph->upsertNode('Court', $courtId, [
                    'name' => $decision->court,
                ]);

                // Create relationship
                $this->graph->createRelationship(
                    'CourtDecisionDocument',
                    $docId,
                    'DECIDED_BY',
                    'Court',
                    $courtId
                );
            }
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'not available') ||
                str_contains($e->getMessage(), 'Connection refused')) {
                $this->markTestSkipped('Neo4j not available: ' . $e->getMessage());
            }
            throw $e;
        }
    }

    #[Test]
    public function simple_node_query_completes_under_50ms(): void
    {
        try {
            // Warm up query cache
            $this->graph->run(
                'MATCH (d:CourtDecisionDocument {case_number: $cn}) RETURN d',
                ['cn' => 'QUERY-PERF-0/2026']
            );

            // Measure actual query performance
            $times = [];
            for ($i = 0; $i < 10; $i++) {
                $start = microtime(true);

                $this->graph->run(
                    'MATCH (d:CourtDecisionDocument {case_number: $cn}) RETURN d',
                    ['cn' => "QUERY-PERF-{$i}/2026"]
                );

                $elapsed = (microtime(true) - $start) * 1000; // Convert to ms
                $times[] = $elapsed;
            }

            $avgTime = array_sum($times) / count($times);
            $maxTime = max($times);

            fwrite(STDOUT, sprintf(
                "\n[QUERY PERF] Simple query - Avg: %.2fms, Max: %.2fms\n",
                $avgTime,
                $maxTime
            ));

            // Assert average is under threshold
            $this->assertLessThan(
                50.0,
                $avgTime,
                "Average simple query time {$avgTime}ms exceeds 50ms threshold"
            );

            // Assert max is under reasonable threshold (2x average target)
            $this->assertLessThan(
                100.0,
                $maxTime,
                "Max simple query time {$maxTime}ms exceeds 100ms threshold"
            );
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'not available') ||
                str_contains($e->getMessage(), 'Connection refused')) {
                $this->markTestSkipped('Neo4j not available: ' . $e->getMessage());
            }
            throw $e;
        }
    }

    #[Test]
    public function complex_query_with_relationships_completes_under_500ms(): void
    {
        try {
            // Warm up query cache
            $this->graph->run(
                'MATCH (d:CourtDecisionDocument)-[:DECIDED_BY]->(c:Court)
                 WHERE c.name CONTAINS $term
                 RETURN d, c
                 ORDER BY d.decision_date DESC
                 LIMIT 10',
                ['term' => 'Query Test']
            );

            // Measure complex query performance
            $times = [];
            for ($i = 0; $i < 5; $i++) {
                $start = microtime(true);

                $result = $this->graph->run(
                    'MATCH (d:CourtDecisionDocument)-[:DECIDED_BY]->(c:Court)
                     WHERE c.name CONTAINS $term
                     RETURN d.case_number as case_number,
                            c.name as court,
                            d.decision_date as date
                     ORDER BY d.decision_date DESC
                     LIMIT 10',
                    ['term' => 'Query Test']
                );

                $elapsed = (microtime(true) - $start) * 1000;
                $times[] = $elapsed;

                // Verify results
                $this->assertGreaterThan(0, count($result));
            }

            $avgTime = array_sum($times) / count($times);
            $maxTime = max($times);

            fwrite(STDOUT, sprintf(
                "\n[QUERY PERF] Complex query - Avg: %.2fms, Max: %.2fms\n",
                $avgTime,
                $maxTime
            ));

            // Assert average is under threshold
            $this->assertLessThan(
                500.0,
                $avgTime,
                "Average complex query time {$avgTime}ms exceeds 500ms threshold"
            );

            // Assert max is under reasonable threshold
            $this->assertLessThan(
                1000.0,
                $maxTime,
                "Max complex query time {$maxTime}ms exceeds 1000ms threshold"
            );
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'not available') ||
                str_contains($e->getMessage(), 'Connection refused')) {
                $this->markTestSkipped('Neo4j not available: ' . $e->getMessage());
            }
            throw $e;
        }
    }

    #[Test]
    public function aggregation_query_completes_under_200ms(): void
    {
        try {
            // Warm up
            $this->graph->run(
                'MATCH (d:CourtDecisionDocument)-[:DECIDED_BY]->(c:Court)
                 RETURN c.name as court, count(d) as decision_count
                 ORDER BY decision_count DESC'
            );

            // Measure aggregation query performance
            $times = [];
            for ($i = 0; $i < 5; $i++) {
                $start = microtime(true);

                $result = $this->graph->run(
                    'MATCH (d:CourtDecisionDocument)-[:DECIDED_BY]->(c:Court)
                     RETURN c.name as court, count(d) as decision_count
                     ORDER BY decision_count DESC'
                );

                $elapsed = (microtime(true) - $start) * 1000;
                $times[] = $elapsed;

                // Verify results
                $this->assertGreaterThan(0, count($result));
            }

            $avgTime = array_sum($times) / count($times);

            fwrite(STDOUT, sprintf(
                "\n[QUERY PERF] Aggregation query - Avg: %.2fms\n",
                $avgTime
            ));

            $this->assertLessThan(
                200.0,
                $avgTime,
                "Average aggregation query time {$avgTime}ms exceeds 200ms threshold"
            );
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'not available') ||
                str_contains($e->getMessage(), 'Connection refused')) {
                $this->markTestSkipped('Neo4j not available: ' . $e->getMessage());
            }
            throw $e;
        }
    }

    #[Test]
    public function multi_hop_relationship_query_completes_under_1_second(): void
    {
        try {
            // Create some multi-hop test data
            // Add judge nodes and relationships for existing decisions
            for ($i = 0; $i < 10; $i++) {
                $docId = "query-perf-doc-{$i}";
                $judgeId = "judge-" . ($i % 3);

                // Create judge node
                $this->graph->upsertNode('Judge', $judgeId, [
                    'name' => "Judge " . ($i % 3),
                ]);

                // Find existing decision document
                $result = $this->graph->run(
                    'MATCH (d:CourtDecisionDocument {case_number: $cn}) RETURN d.id as id',
                    ['cn' => "QUERY-PERF-{$i}/2026"]
                );

                if ($result && count($result) > 0) {
                    $actualDocId = $result->first()->get('id');

                    // Create relationship if doc exists
                    if ($actualDocId) {
                        $this->graph->createRelationship(
                            'CourtDecisionDocument',
                            $actualDocId,
                            'PRESIDED_BY',
                            'Judge',
                            $judgeId
                        );
                    }
                }
            }

            // Warm up
            $this->graph->run(
                'MATCH (d:CourtDecisionDocument)-[:DECIDED_BY]->(c:Court),
                       (d)-[:PRESIDED_BY]->(j:Judge)
                 RETURN d.case_number, c.name, j.name
                 LIMIT 5'
            );

            // Measure multi-hop query performance
            $times = [];
            for ($i = 0; $i < 3; $i++) {
                $start = microtime(true);

                $result = $this->graph->run(
                    'MATCH (d:CourtDecisionDocument)-[:DECIDED_BY]->(c:Court),
                           (d)-[:PRESIDED_BY]->(j:Judge)
                     RETURN d.case_number as case_number,
                            c.name as court,
                            j.name as judge
                     ORDER BY d.decision_date DESC
                     LIMIT 10'
                );

                $elapsed = (microtime(true) - $start) * 1000;
                $times[] = $elapsed;
            }

            $avgTime = array_sum($times) / count($times);

            fwrite(STDOUT, sprintf(
                "\n[QUERY PERF] Multi-hop relationship query - Avg: %.2fms\n",
                $avgTime
            ));

            $this->assertLessThan(
                1000.0,
                $avgTime,
                "Average multi-hop query time {$avgTime}ms exceeds 1000ms threshold"
            );
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'not available') ||
                str_contains($e->getMessage(), 'Connection refused')) {
                $this->markTestSkipped('Neo4j not available: ' . $e->getMessage());
            }
            throw $e;
        }
    }
}
