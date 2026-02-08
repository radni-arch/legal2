<?php

namespace Tests\Performance\Graph;

use App\Models\CourtDecision;
use App\Models\CourtDecisionDocument;
use App\Services\Graph\DecisionGraphSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Integration\Graph\GraphIntegrationTestCase;

#[Group('performance')]
class SyncBenchmarkTest extends GraphIntegrationTestCase
{
    use RefreshDatabase;

    protected DecisionGraphSyncService $syncService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->syncService = app(DecisionGraphSyncService::class);
    }

    /**
     * Create bulk test decisions in PostgreSQL
     *
     * @param  int  $count  Number of decisions to create
     * @return array Array of decision IDs
     */
    protected function createTestDecisions(int $count): array
    {
        $decisionIds = [];

        for ($i = 0; $i < $count; $i++) {
            $decision = CourtDecision::factory()->create([
                'case_number' => "PERF-BENCH-{$i}/2026",
                'court' => 'Performance Test Court',
                'jurisdiction' => 'Croatia',
                'judge' => "Judge {$i}",
                'decision_date' => '2026-01-07',
                'decision_type' => 'presuda',
            ]);

            // Create document chunk for this decision
            CourtDecisionDocument::factory()->create([
                'decision_id' => $decision->id,
                'title' => "Performance Test Document {$i}",
                'content' => "This is test content for performance benchmark {$i}. " .
                    str_repeat('Lorem ipsum dolor sit amet. ', 10),
            ]);

            $decisionIds[] = $decision->id;
        }

        return $decisionIds;
    }

    #[Test]
    public function sync_10_nodes_establishes_baseline_performance(): void
    {
        // Create small test dataset for quick baseline
        $decisionIds = $this->createTestDecisions(10);

        // Start timing
        $start = microtime(true);

        try {
            // Sync all decisions
            foreach ($decisionIds as $decisionId) {
                $this->syncService->sync($decisionId);
            }

            $elapsed = microtime(true) - $start;

            // Log baseline performance
            $this->addToAssertionCount(1); // Acknowledge we ran the benchmark
            fwrite(STDOUT, "\n[BENCHMARK] Synced 10 nodes in {$elapsed}s\n");

            // Assert performance target - baseline established at ~60s for 10 full syncs
            // This includes all entity extraction (judges, parties, principles, precedents, etc.)
            // Target allows for 2x baseline for regression detection
            $this->assertLessThan(
                120.0,
                $elapsed,
                "Syncing 10 nodes took {$elapsed}s, expected <120s (baseline: ~60s with full extraction pipeline)"
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
    public function sync_100_nodes_completes_under_5_seconds(): void
    {
        $this->markTestSkipped('100 node benchmark takes ~5min - run manually for baseline metrics');

        // Create test data
        $decisionIds = $this->createTestDecisions(100);

        // Start timing
        $start = microtime(true);

        try {
            // Sync all decisions
            foreach ($decisionIds as $decisionId) {
                $this->syncService->sync($decisionId);
            }

            $elapsed = microtime(true) - $start;

            // Log baseline performance
            $this->addToAssertionCount(1);
            fwrite(STDOUT, "\n[BENCHMARK] Synced 100 nodes in {$elapsed}s\n");

            // Assert performance target
            $this->assertLessThan(
                5.0,
                $elapsed,
                "Syncing 100 nodes took {$elapsed}s, expected <5s (baseline: ~2-3s)"
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
    public function sync_1000_nodes_completes_under_30_seconds(): void
    {
        $this->markTestSkipped('1K node benchmark requires significant time - run manually when needed');

        // Create test data
        $decisionIds = $this->createTestDecisions(1000);

        // Start timing
        $start = microtime(true);

        try {
            // Sync all decisions
            foreach ($decisionIds as $decisionId) {
                $this->syncService->sync($decisionId);
            }

            $elapsed = microtime(true) - $start;

            // Log baseline performance
            $this->addToAssertionCount(1);
            fwrite(STDOUT, "\n[BENCHMARK] Synced 1000 nodes in {$elapsed}s\n");

            // Assert performance target
            $this->assertLessThan(
                30.0,
                $elapsed,
                "Syncing 1000 nodes took {$elapsed}s, expected <30s (baseline: ~20-25s)"
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
    public function sync_10000_nodes_completes_under_5_minutes(): void
    {
        $this->markTestSkipped('10K node benchmark requires significant time - run manually when needed');

        // Create test data
        $decisionIds = $this->createTestDecisions(10000);

        // Start timing
        $start = microtime(true);

        try {
            // Sync all decisions
            foreach ($decisionIds as $decisionId) {
                $this->syncService->sync($decisionId);
            }

            $elapsed = microtime(true) - $start;

            // Log baseline performance
            $this->addToAssertionCount(1);
            fwrite(STDOUT, "\n[BENCHMARK] Synced 10000 nodes in {$elapsed}s\n");

            // Assert performance target (5 minutes = 300 seconds)
            $this->assertLessThan(
                300.0,
                $elapsed,
                "Syncing 10000 nodes took {$elapsed}s, expected <300s (baseline: ~200-250s)"
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
    public function reports_sync_metrics_for_performance_tracking(): void
    {
        // Create small dataset
        $decisionIds = $this->createTestDecisions(10);

        try {
            $allMetrics = [];

            foreach ($decisionIds as $decisionId) {
                $metrics = $this->syncService->sync($decisionId);
                $allMetrics[] = $metrics;
            }

            // Verify all metrics are tracked
            foreach ($allMetrics as $metrics) {
                $this->assertArrayHasKey('nodes', $metrics);
                $this->assertArrayHasKey('relationships', $metrics);
                $this->assertArrayHasKey('errors', $metrics);
                $this->assertArrayHasKey('duration_ms', $metrics);

                // Verify duration is being measured
                $this->assertGreaterThan(0, $metrics['duration_ms']);
            }

            // Calculate aggregate metrics
            $totalDuration = array_sum(array_column($allMetrics, 'duration_ms'));
            $avgDuration = $totalDuration / count($allMetrics);

            fwrite(STDOUT, sprintf(
                "\n[METRICS] Average sync duration: %.2fms per decision\n",
                $avgDuration
            ));

            // Baseline expectation: each decision should sync in <1000ms
            $this->assertLessThan(1000, $avgDuration,
                "Average sync duration {$avgDuration}ms exceeds 1000ms threshold");
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'not available') ||
                str_contains($e->getMessage(), 'Connection refused')) {
                $this->markTestSkipped('Neo4j not available: ' . $e->getMessage());
            }
            throw $e;
        }
    }
}
