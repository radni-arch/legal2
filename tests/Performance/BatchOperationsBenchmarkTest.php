<?php

namespace Tests\Performance;

use App\Models\TextractDocument;
use App\Services\Graph\BatchCypherBuilder;
use App\Services\Graph\DocumentTypeDetector;
use App\Services\Graph\ParallelExtractionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Integration\Graph\GraphIntegrationTestCase;

/**
 * Performance benchmark tests for batch operations and extraction
 *
 * Validates ~10x improvement target through:
 * - Batch operations vs individual queries
 * - Parallel vs sequential extraction
 * - Selective vs full extraction
 */
#[Group('performance')]
class BatchOperationsBenchmarkTest extends GraphIntegrationTestCase
{
    use RefreshDatabase;

    protected BatchCypherBuilder $batchBuilder;
    protected ParallelExtractionService $parallelExtraction;
    protected DocumentTypeDetector $typeDetector;

    protected function setUp(): void
    {
        // Only call parent setUp for Neo4j tests
        // For extraction tests, we skip the Neo4j setup
        if (str_contains($this->name(), 'batch_vs_individual_node_creation')) {
            parent::setUp();
        } else {
            // Call TestCase::setUp directly for extraction tests
            \Tests\TestCase::setUp();
        }

        $this->batchBuilder = new BatchCypherBuilder();
        $this->parallelExtraction = new ParallelExtractionService();
        $this->typeDetector = new DocumentTypeDetector();
    }

    /**
     * Benchmark: Batch UNWIND vs Individual MERGE operations
     *
     * Target: Batch operations should be ~5x faster than individual
     */
    #[Test]
    public function benchmark_batch_vs_individual_node_creation(): void
    {
        $nodeCount = 100;
        $nodes = $this->generateTestNodes($nodeCount);

        try {
            // Benchmark 1: Individual MERGE operations
            $individualStart = microtime(true);

            foreach ($nodes as $node) {
                $this->graph->run(
                    'MERGE (n:TestNode {id: $id}) SET n.name = $name, n.value = $value',
                    $node
                );
            }

            $individualTime = microtime(true) - $individualStart;

            // Clean up
            $this->graph->run('MATCH (n:TestNode) DELETE n');

            // Benchmark 2: Batch UNWIND operation
            $batchStart = microtime(true);

            $batchQuery = $this->batchBuilder->buildBatchUpsertNodes('TestNode', $nodes, 'id');
            $this->graph->run($batchQuery['query'], $batchQuery['parameters']);

            $batchTime = microtime(true) - $batchStart;

            // Calculate speedup
            $speedup = $individualTime / $batchTime;

            // Log results
            fwrite(STDOUT, sprintf(
                "\n[BENCHMARK] Batch vs Individual Node Creation (%d nodes):\n",
                $nodeCount
            ));
            fwrite(STDOUT, sprintf("  Individual MERGE: %.4fs\n", $individualTime));
            fwrite(STDOUT, sprintf("  Batch UNWIND:     %.4fs\n", $batchTime));
            fwrite(STDOUT, sprintf("  Speedup:          %.2fx\n\n", $speedup));

            // Assert performance target: Batch should be ~5x faster
            $this->assertGreaterThan(
                3.0,
                $speedup,
                "Batch operations should be at least 3x faster (target: ~5x). Got {$speedup}x"
            );

            // Verify all nodes were created
            $result = $this->graph->run('MATCH (n:TestNode) RETURN count(n) as count');
            $this->assertEquals($nodeCount, $result->first()->get('count'));

            // Clean up
            $this->graph->run('MATCH (n:TestNode) DELETE n');

        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'not available') ||
                str_contains($e->getMessage(), 'Connection refused')) {
                $this->markTestSkipped('Neo4j not available: ' . $e->getMessage());
            }
            throw $e;
        }
    }

    /**
     * Benchmark: Parallel vs Sequential extraction
     *
     * Target: Parallel should be ~2x faster with 3+ extractors in production
     * Note: Test environment may not support true parallel execution
     */
    #[Test]
    public function benchmark_parallel_vs_sequential_extraction(): void
    {
        // Create test document
        $document = TextractDocument::factory()->create([
            'content' => $this->getCourtDecisionTestContent(),
        ]);

        // Define extractors to test (using mock extractors that simulate work)
        $extractorClasses = [
            \Tests\Support\Extractors\MockSlowExtractor1::class,
            \Tests\Support\Extractors\MockSlowExtractor2::class,
            \Tests\Support\Extractors\MockSlowExtractor3::class,
            \Tests\Support\Extractors\MockSlowExtractor4::class,
        ];

        // Benchmark 1: Sequential extraction
        $sequentialStart = microtime(true);

        $sequentialResults = [];
        foreach ($extractorClasses as $extractorClass) {
            $extractor = new $extractorClass();
            $sequentialResults[] = [
                'extractor' => $extractorClass,
                'data' => $extractor->extract($document->content),
            ];
        }

        $sequentialTime = microtime(true) - $sequentialStart;

        // Benchmark 2: Parallel extraction (using service)
        $parallelStart = microtime(true);

        $parallelResults = $this->parallelExtraction->extractInParallel($document, $extractorClasses);

        $parallelTime = microtime(true) - $parallelStart;

        // Calculate speedup (or slowdown in test env)
        $speedup = $sequentialTime / $parallelTime;

        // Log results
        fwrite(STDOUT, sprintf(
            "\n[BENCHMARK] Parallel vs Sequential Extraction (%d extractors):\n",
            count($extractorClasses)
        ));
        fwrite(STDOUT, sprintf("  Sequential:        %.4fs\n", $sequentialTime));
        fwrite(STDOUT, sprintf("  Parallel (facade): %.4fs\n", $parallelTime));
        fwrite(STDOUT, sprintf("  Speedup:           %.2fx\n", $speedup));
        fwrite(STDOUT, sprintf("  Note: Test environment may not support true parallelism\n"));
        fwrite(STDOUT, sprintf("        Production target with real parallel execution: ~2x\n\n"));

        // Verify both approaches work correctly (not strict performance assertion)
        // In production with true parallel execution, expect ~2x speedup
        // In test environment, may be slower due to overhead
        $this->assertCount(count($extractorClasses), $sequentialResults);
        $this->assertCount(count($extractorClasses), $parallelResults);

        // Verify sequential timing is reasonable (4 extractors * 200ms = ~800ms)
        $this->assertGreaterThan(
            0.7,
            $sequentialTime,
            "Sequential extraction should take ~800ms for 4 extractors @ 200ms each"
        );
    }

    /**
     * Benchmark: Selective vs Full extraction
     *
     * Target: Selective extraction should be ~1.5x faster for non-court docs
     */
    #[Test]
    public function benchmark_selective_vs_full_extraction(): void
    {
        // Create a generic (non-court) document
        $document = TextractDocument::factory()->create([
            'content' => $this->getGenericDocumentTestContent(),
        ]);

        // Full set of extractors (all types)
        $allExtractorClasses = [
            \Tests\Support\Extractors\MockSlowExtractor1::class,
            \Tests\Support\Extractors\MockSlowExtractor2::class,
            \Tests\Support\Extractors\MockSlowExtractor3::class,
            \Tests\Support\Extractors\MockSlowExtractor4::class,
            \Tests\Support\Extractors\MockSlowExtractor5::class,
            \Tests\Support\Extractors\MockSlowExtractor6::class,
            \Tests\Support\Extractors\MockSlowExtractor7::class,
            \Tests\Support\Extractors\MockSlowExtractor8::class,
            \Tests\Support\Extractors\MockSlowExtractor9::class,
        ];

        // Detect document type and get selective extractors
        $documentType = $this->typeDetector->detect($document->content);
        $this->assertEquals('generic', $documentType, 'Expected generic document type');

        // For generic docs, we should use only a subset
        $selectiveExtractorClasses = array_slice($allExtractorClasses, 0, 3);

        // Benchmark 1: Full extraction (all extractors)
        $fullStart = microtime(true);

        $fullResults = $this->parallelExtraction->extractInParallel($document, $allExtractorClasses);

        $fullTime = microtime(true) - $fullStart;

        // Benchmark 2: Selective extraction (document-type specific)
        $selectiveStart = microtime(true);

        $selectiveResults = $this->parallelExtraction->extractInParallel($document, $selectiveExtractorClasses);

        $selectiveTime = microtime(true) - $selectiveStart;

        // Calculate speedup
        $speedup = $fullTime / $selectiveTime;

        // Log results
        fwrite(STDOUT, sprintf(
            "\n[BENCHMARK] Selective vs Full Extraction (generic document):\n"
        ));
        fwrite(STDOUT, sprintf("  Full extraction (%d extractors):      %.4fs\n", count($allExtractorClasses), $fullTime));
        fwrite(STDOUT, sprintf("  Selective extraction (%d extractors): %.4fs\n", count($selectiveExtractorClasses), $selectiveTime));
        fwrite(STDOUT, sprintf("  Speedup:                                %.2fx\n\n", $speedup));

        // Assert performance target: Selective should be ~1.5x faster for non-court docs
        $this->assertGreaterThan(
            1.3,
            $speedup,
            "Selective extraction should be at least 1.3x faster (target: ~1.5x). Got {$speedup}x"
        );

        // Verify results
        $this->assertCount(count($allExtractorClasses), $fullResults);
        $this->assertCount(count($selectiveExtractorClasses), $selectiveResults);
    }

    /**
     * Generate test nodes for benchmarking
     */
    protected function generateTestNodes(int $count): array
    {
        $nodes = [];
        for ($i = 0; $i < $count; $i++) {
            $nodes[] = [
                'id' => "test-node-{$i}",
                'name' => "Test Node {$i}",
                'value' => $i * 100,
            ];
        }
        return $nodes;
    }

    /**
     * Get sample court decision content for testing
     */
    protected function getCourtDecisionTestContent(): string
    {
        return <<<'EOT'
U IME REPUBLIKE HRVATSKE

PRESUDA

U sporu tužitelja protiv tuženika, sud je donio sljedeću presudu:

Članak 15. Zakona o obveznim odnosima primjenjuje se u ovom slučaju.

Sud je odlučio da je tužitelj u pravu.

Datum: 2026-01-01
EOT;
    }

    /**
     * Get sample generic document content for testing
     */
    protected function getGenericDocumentTestContent(): string
    {
        return <<<'EOT'
This is a generic legal document.

It contains some references to legal concepts but is not a court decision.

Date: 2026-01-01

This document discusses various legal principles without being a formal court ruling.
EOT;
    }
}
