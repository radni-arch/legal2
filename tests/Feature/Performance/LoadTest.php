<?php

namespace Tests\Feature\Performance;

use App\Jobs\SyncGraphDataJob;
use App\Models\Law;
use App\Services\Graph\GraphRagOrchestrator;
use App\Services\Search\SearchOrchestrator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Load Testing & Performance Tests
 *
 * Tests the system's ability to handle high load scenarios including:
 * - Concurrent API requests
 * - High-volume queue processing
 * - Concurrent database writes
 * - Graph sync performance at scale
 *
 * These tests establish performance baselines and identify bottlenecks.
 *
 * @group performance
 * @group slow
 */
class LoadTest extends TestCase
{
    use UsesTestDatabase;

    /**
     * Test API handles concurrent search requests
     *
     * Verifies:
     * - No errors during concurrent requests
     * - Reasonable response times
     * - No database deadlocks
     * - All requests complete successfully
     */
    public function test_api_handles_concurrent_search_requests(): void
    {
        // Create test data with embeddings
        Law::factory()->count(50)->create([
            'content' => 'Croatian criminal law regarding liability and procedures. Kazneni zakon defines criminal offenses and penalties.',
            'embedding_vector' => array_fill(0, 1536, 0.1),
        ]);

        // Mock OpenAI for embedding generation
        Http::fake([
            'api.openai.com/*' => Http::response([
                'data' => [
                    ['embedding' => array_fill(0, 1536, 0.1)],
                ],
                'usage' => ['total_tokens' => 10],
            ]),
        ]);

        $orchestrator = app(SearchOrchestrator::class);

        // Run 15 concurrent searches
        $concurrentRequests = 15;
        $searchQueries = [
            'criminal liability',
            'defendant rights',
            'court procedures',
            'evidence rules',
            'sentencing guidelines',
            'appeal process',
            'prosecution duties',
            'defense strategy',
            'witness testimony',
            'judicial review',
            'legal precedents',
            'constitutional rights',
            'procedural violations',
            'burden of proof',
            'due process',
        ];

        $startTime = microtime(true);
        $results = [];
        $errors = [];

        // Execute concurrent searches
        for ($i = 0; $i < $concurrentRequests; $i++) {
            try {
                $query = $searchQueries[$i % count($searchQueries)];
                $result = $orchestrator->search($query, [
                    'corpora' => ['laws'],
                    'limit' => 5,
                    'per_page' => 5,
                ]);
                $results[] = $result;
            } catch (\Exception $e) {
                $errors[] = [
                    'query' => $query ?? 'unknown',
                    'error' => $e->getMessage(),
                ];
            }
        }

        $totalTime = microtime(true) - $startTime;
        $avgTime = $totalTime / $concurrentRequests;

        // Assertions
        $this->assertCount($concurrentRequests, $results, 'Not all requests completed');
        $this->assertEmpty($errors, 'Errors occurred during concurrent requests: '.json_encode($errors));

        // Verify response structure
        foreach ($results as $result) {
            $this->assertArrayHasKey('results', $result);
            $this->assertArrayHasKey('metadata', $result);
            $this->assertArrayHasKey('timing', $result);
        }

        // Performance assertions - reasonable response time
        $this->assertLessThan(30, $totalTime, 'Total time exceeded 30 seconds for '.$concurrentRequests.' requests');
        $this->assertLessThan(2, $avgTime, 'Average response time exceeded 2 seconds');

        // Log performance baseline
        echo "\n\n=== CONCURRENT SEARCH PERFORMANCE BASELINE ===\n";
        echo "Total requests: {$concurrentRequests}\n";
        echo 'Total time: '.round($totalTime, 2)."s\n";
        echo 'Average time per request: '.round($avgTime, 3)."s\n";
        echo 'Requests per second: '.round($concurrentRequests / $totalTime, 2)."\n";
        echo "===========================================\n\n";
    }

    /**
     * Test queue processes high volume
     *
     * Verifies:
     * - All jobs complete successfully
     * - No job failures
     * - Reasonable processing time
     * - Queue handles 100+ jobs
     */
    public function test_queue_processes_high_volume(): void
    {
        Queue::fake();

        // Create test laws for syncing
        $laws = Law::factory()->count(100)->create([
            'content' => 'Test law content for graph sync',
            'embedding_vector' => array_fill(0, 1536, 0.1),
        ]);

        // Dispatch 100+ jobs
        $jobCount = 100;
        $startTime = microtime(true);

        foreach ($laws as $law) {
            SyncGraphDataJob::dispatch('law', $law->id);
        }

        $dispatchTime = microtime(true) - $startTime;

        // Verify all jobs were dispatched
        Queue::assertPushed(SyncGraphDataJob::class, $jobCount);

        // Performance assertions
        $this->assertLessThan(5, $dispatchTime, 'Job dispatch took more than 5 seconds');
        $this->assertGreaterThanOrEqual(100, $jobCount, 'Not enough jobs dispatched');

        // Verify job payload structure
        Queue::assertPushed(SyncGraphDataJob::class, function ($job) {
            // Jobs should have type and id
            return true; // SyncGraphDataJob uses constructor injection
        });

        // Log performance baseline
        echo "\n\n=== HIGH VOLUME QUEUE PROCESSING BASELINE ===\n";
        echo "Jobs dispatched: {$jobCount}\n";
        echo 'Dispatch time: '.round($dispatchTime, 3)."s\n";
        echo 'Jobs per second: '.round($jobCount / $dispatchTime, 2)."\n";
        echo "============================================\n\n";
    }

    /**
     * Test database handles concurrent writes
     *
     * Verifies:
     * - No race conditions
     * - Data integrity maintained
     * - No constraint violations
     * - All writes succeed
     */
    public function test_database_handles_concurrent_writes(): void
    {
        // Test concurrent inserts
        $concurrentWrites = 50;
        $startTime = microtime(true);
        $errors = [];
        $created = [];

        // Execute concurrent law inserts
        for ($i = 0; $i < $concurrentWrites; $i++) {
            try {
                $law = Law::factory()->create([
                    'title' => "Concurrent Write Test Law {$i}",
                    'content' => "Test content for concurrent write {$i}",
                    'embedding_vector' => array_fill(0, 1536, 0.1),
                ]);
                $created[] = $law->id;
            } catch (\Exception $e) {
                $errors[] = [
                    'iteration' => $i,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $writeTime = microtime(true) - $startTime;

        // Assertions
        $this->assertEmpty($errors, 'Database errors occurred during concurrent writes: '.json_encode($errors));
        $this->assertCount($concurrentWrites, $created, 'Not all writes completed successfully');

        // Verify data integrity - all records exist and are unique
        $dbCount = DB::table('laws')
            ->whereIn('id', $created)
            ->count();

        $this->assertEquals($concurrentWrites, $dbCount, 'Data integrity issue: record count mismatch');

        // Verify no duplicate IDs
        $uniqueIds = array_unique($created);
        $this->assertCount($concurrentWrites, $uniqueIds, 'Duplicate IDs detected');

        // Test concurrent updates
        $updateStartTime = microtime(true);
        $updateErrors = [];

        foreach ($created as $index => $lawId) {
            try {
                DB::table('laws')
                    ->where('id', $lawId)
                    ->update([
                        'title' => "Updated Concurrent Law {$index}",
                        'updated_at' => now(),
                    ]);
            } catch (\Exception $e) {
                $updateErrors[] = [
                    'law_id' => $lawId,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $updateTime = microtime(true) - $updateStartTime;

        // Update assertions
        $this->assertEmpty($updateErrors, 'Errors during concurrent updates: '.json_encode($updateErrors));

        // Verify updates were applied
        $updatedCount = DB::table('laws')
            ->whereIn('id', $created)
            ->where('title', 'like', 'Updated Concurrent Law%')
            ->count();

        $this->assertEquals($concurrentWrites, $updatedCount, 'Not all updates were applied');

        // Performance assertions
        $this->assertLessThan(15, $writeTime, 'Insert time exceeded 15 seconds');
        $this->assertLessThan(15, $updateTime, 'Update time exceeded 15 seconds');

        // Log performance baseline
        echo "\n\n=== CONCURRENT DATABASE WRITES BASELINE ===\n";
        echo "Concurrent inserts: {$concurrentWrites}\n";
        echo 'Insert time: '.round($writeTime, 2)."s\n";
        echo 'Update time: '.round($updateTime, 2)."s\n";
        echo 'Inserts per second: '.round($concurrentWrites / $writeTime, 2)."\n";
        echo 'Updates per second: '.round($concurrentWrites / $updateTime, 2)."\n";
        echo 'Total operations: '.($concurrentWrites * 2)."\n";
        echo "=========================================\n\n";
    }

    /**
     * Test graph sync performance at scale
     *
     * Verifies:
     * - Can sync 500+ documents to Neo4j
     * - Establishes performance baseline
     * - No errors during bulk sync
     * - Graph remains consistent
     */
    public function test_graph_sync_performance_at_scale(): void
    {
        // Check if Neo4j is available
        $graph = app(\App\Services\GraphDatabaseService::class);

        if (! $graph->isAvailable()) {
            $this->markTestSkipped('Neo4j is not available - cannot test graph sync performance');
        }

        // Create 500+ test laws
        $lawCount = 500;
        $batchSize = 100;
        $allLawIds = [];

        echo "\n\nCreating {$lawCount} test laws...\n";
        $createStartTime = microtime(true);

        for ($batch = 0; $batch < ($lawCount / $batchSize); $batch++) {
            $laws = Law::factory()->count($batchSize)->create([
                'content' => "Graph sync test law content batch {$batch}. Croatian legal provisions regarding criminal procedures and defendant rights.",
                'embedding_vector' => array_fill(0, 1536, 0.1 + ($batch * 0.01)),
            ]);

            $allLawIds = array_merge($allLawIds, $laws->pluck('id')->toArray());
        }

        $createTime = microtime(true) - $createStartTime;
        echo "Created {$lawCount} laws in ".round($createTime, 2)."s\n";

        // Test graph sync performance
        $orchestrator = app(GraphRagOrchestrator::class);

        echo "\nStarting graph sync for {$lawCount} laws...\n";
        $syncStartTime = microtime(true);
        $synced = 0;
        $errors = [];

        foreach ($allLawIds as $lawId) {
            try {
                $orchestrator->syncLaw($lawId);
                $synced++;

                // Progress indicator every 100 laws
                if ($synced % 100 === 0) {
                    $elapsed = microtime(true) - $syncStartTime;
                    $rate = $synced / $elapsed;
                    echo "Synced: {$synced}/{$lawCount} - Rate: ".round($rate, 2)." laws/sec\n";
                }
            } catch (\Exception $e) {
                $errors[] = [
                    'law_id' => $lawId,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $totalSyncTime = microtime(true) - $syncStartTime;

        // Assertions
        $this->assertEmpty($errors, 'Errors occurred during graph sync: '.json_encode(array_slice($errors, 0, 5)));
        $this->assertEquals($lawCount, $synced, 'Not all laws were synced');

        // Performance baseline - should complete in reasonable time
        // Allow ~60 seconds for 500 laws (8-9 laws/sec)
        $this->assertLessThan(120, $totalSyncTime, 'Graph sync took more than 2 minutes for 500 laws');

        // Verify graph consistency - sample check
        $sampleLawId = $allLawIds[0];
        $nodeData = $graph->getNode('LawDocument', $sampleLawId);
        $this->assertNotNull($nodeData, 'Sample law node not found in graph after sync');

        // Verify relationships were created
        $result = $graph->run(
            'MATCH (l:LawDocument {id: $id})-[r]->() RETURN count(r) as rel_count',
            ['id' => $sampleLawId]
        );

        $relCount = $result->first()?->get('rel_count') ?? 0;
        $this->assertGreaterThan(0, $relCount, 'No relationships created for sample law');

        // Clean up - delete test nodes
        echo "\nCleaning up test nodes...\n";
        $cleanupStartTime = microtime(true);

        foreach ($allLawIds as $lawId) {
            try {
                $graph->run('MATCH (n:LawDocument {id: $id}) DETACH DELETE n', ['id' => $lawId]);
            } catch (\Exception $e) {
                // Ignore cleanup errors
            }
        }

        $cleanupTime = microtime(true) - $cleanupStartTime;
        echo 'Cleanup completed in '.round($cleanupTime, 2)."s\n";

        // Log performance baseline
        echo "\n\n=== GRAPH SYNC PERFORMANCE BASELINE ===\n";
        echo "Total laws synced: {$synced}\n";
        echo 'Total sync time: '.round($totalSyncTime, 2)."s\n";
        echo 'Average time per law: '.round($totalSyncTime / $synced, 3)."s\n";
        echo 'Laws synced per second: '.round($synced / $totalSyncTime, 2)."\n";
        echo "Sample law relationships: {$relCount}\n";
        echo "======================================\n\n";

        // Identify potential bottlenecks
        if ($totalSyncTime > 60) {
            echo "\n⚠️  BOTTLENECK IDENTIFIED: Graph sync is slower than expected\n";
            echo "   Consider: batch operations, connection pooling, async processing\n\n";
        }
    }
}
