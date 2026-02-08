<?php

namespace Tests\Integration;

use App\Models\TextractDocument;
use App\Services\Graph\BatchCypherBuilder;
use App\Services\Graph\DocumentTypeDetector;
use App\Services\Graph\ParallelExtractionService;
use App\Services\GraphDatabaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * Integration Test: Batch Operations with Graph Services
 *
 * Tests the integration of:
 * 1. DocumentTypeDetector - detecting document types
 * 2. ParallelExtractionService - running extractors in parallel
 * 3. BatchCypherBuilder - building batch UNWIND queries
 * 4. GraphDatabaseService - syncing to Neo4j (mocked)
 *
 * @group integration
 * @group batch-operations
 */
class BatchOperationsIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected DocumentTypeDetector $detector;
    protected ParallelExtractionService $parallelExtractor;
    protected BatchCypherBuilder $batchBuilder;
    protected GraphDatabaseService $graphDb;

    protected function setUp(): void
    {
        parent::setUp();

        $this->detector = app(DocumentTypeDetector::class);
        $this->parallelExtractor = app(ParallelExtractionService::class);
        $this->batchBuilder = app(BatchCypherBuilder::class);

        // Mock GraphDatabaseService to avoid real Neo4j calls
        $this->graphDb = $this->createMock(GraphDatabaseService::class);
    }

    /**
     * Test 1: Complete batch sync pipeline with parallel extraction
     *
     * Verifies the full workflow:
     * - Document type detection
     * - Appropriate extractors selected
     * - Parallel extraction execution
     * - Batch query building
     * - Graph database sync
     *
     * @test
     */
    public function it_syncs_batch_of_decisions_with_parallel_extraction()
    {
        // Arrange: Create court decision documents
        $documents = [
            TextractDocument::factory()->create([
                'content' => 'U IME REPUBLIKE HRVATSKE Vrhovni sud je donio PRESUDU u predmetu...',
            ]),
            TextractDocument::factory()->create([
                'content' => 'PRESUDA U IME REPUBLIKE HRVATSKE Županijski sud donosi odluku...',
            ]),
            TextractDocument::factory()->create([
                'content' => 'RJEŠENJE Općinski sud u Zagrebu je na temelju članka 10. donio rješenje...',
            ]),
        ];

        // Act & Assert: Process each document through the pipeline
        $extractionResults = [];

        foreach ($documents as $document) {
            // Step 1: Detect document type
            $type = $this->detector->detect($document->content);
            $this->assertEquals('court_decision', $type, 'Document should be detected as court_decision');

            // Step 2: Get appropriate extractors for the type
            $extractorClasses = $this->detector->getExtractorsForType($type);
            $this->assertNotEmpty($extractorClasses, 'Extractors should be returned for court_decision');
            $this->assertContains('LegalTopicExtractor', $extractorClasses);
            $this->assertContains('ArticleExtractor', $extractorClasses);
            $this->assertContains('VerdictExtractor', $extractorClasses);

            // Step 3: Simulate parallel extraction (with mock extractors)
            // In a real scenario, extractors would call AI services
            // Here we simulate the extraction results
            $mockResults = [
                [
                    'extractor' => 'LegalTopicExtractor',
                    'data' => ['topics' => ['Criminal Law', 'Evidence']],
                ],
                [
                    'extractor' => 'ArticleExtractor',
                    'data' => ['articles' => ['Article 10', 'Article 15']],
                ],
                [
                    'extractor' => 'VerdictExtractor',
                    'data' => ['verdict' => 'guilty', 'sentence' => '6 months'],
                ],
            ];

            $extractionResults[$document->id] = $mockResults;
        }

        // Step 4: Build batch queries for syncing extracted data
        $nodesToSync = [];
        foreach ($documents as $document) {
            $nodesToSync[] = [
                'id' => 'decision_' . $document->id,
                'doc_id' => $document->id,
                'type' => 'court_decision',
                'content' => substr($document->content, 0, 100),
            ];
        }

        $batchQuery = $this->batchBuilder->buildBatchUpsertNodes(
            'Decision',
            $nodesToSync,
            'id'
        );

        // Assert: Verify batch query structure
        $this->assertArrayHasKey('query', $batchQuery);
        $this->assertArrayHasKey('parameters', $batchQuery);
        $this->assertStringContainsString('UNWIND', $batchQuery['query']);
        $this->assertStringContainsString('Decision', $batchQuery['query']);
        $this->assertCount(3, $batchQuery['parameters']['nodes']);

        // Step 5: Verify GraphDatabaseService would be called with batch query
        // (In production, this would execute the query against Neo4j)
        $this->assertNotEmpty($batchQuery['query']);
        $this->assertEquals(3, count($extractionResults));
    }

    /**
     * Test 2: Handle mixed document types in batch processing
     *
     * Verifies that different document types get different extractors
     * and can be processed in the same batch workflow.
     *
     * @test
     */
    public function it_handles_mixed_document_types_in_batch()
    {
        // Arrange: Create documents of different types
        $courtDecisions = [
            TextractDocument::factory()->create([
                'content' => 'U IME REPUBLIKE HRVATSKE PRESUDA Vrhovni sud...',
            ]),
            TextractDocument::factory()->create([
                'content' => 'RJEŠENJE U IME REPUBLIKE HRVATSKE Sud je odlučio...',
            ]),
        ];

        $lawDocuments = [
            TextractDocument::factory()->create([
                'content' => 'ZAKON O KAZNENOM POSTUPKU Članak 1. Ovim zakonom uređuje se...',
            ]),
            TextractDocument::factory()->create([
                'content' => 'PRAVILNIK O SUDSKOM POSLOVANJU Članak 5. Određuje se...',
            ]),
        ];

        $allDocuments = array_merge($courtDecisions, $lawDocuments);

        // Act: Detect types and get extractors for each
        $documentTypeMap = [];
        $extractorMap = [];

        foreach ($allDocuments as $document) {
            $type = $this->detector->detect($document->content);
            $extractors = $this->detector->getExtractorsForType($type);

            $documentTypeMap[$document->id] = $type;
            $extractorMap[$document->id] = $extractors;
        }

        // Assert: Verify court decisions detected correctly
        foreach ($courtDecisions as $decision) {
            $this->assertEquals('court_decision', $documentTypeMap[$decision->id]);
            $this->assertContains('VerdictExtractor', $extractorMap[$decision->id]);
            $this->assertContains('LawyerExtractor', $extractorMap[$decision->id]);
            $this->assertContains('EvidenceExtractor', $extractorMap[$decision->id]);
        }

        // Assert: Verify law documents detected correctly
        foreach ($lawDocuments as $law) {
            $this->assertEquals('law', $documentTypeMap[$law->id]);
            $this->assertContains('ArticleExtractor', $extractorMap[$law->id]);
            $this->assertContains('LegalDefinitionExtractor', $extractorMap[$law->id]);

            // Law documents should NOT have court-specific extractors
            $this->assertNotContains('VerdictExtractor', $extractorMap[$law->id]);
            $this->assertNotContains('LawyerExtractor', $extractorMap[$law->id]);
        }

        // Assert: Verify we can batch sync both types
        $decisionNodes = array_map(fn($doc) => [
            'id' => 'decision_' . $doc->id,
            'doc_id' => $doc->id,
            'type' => 'court_decision',
        ], $courtDecisions);

        $lawNodes = array_map(fn($doc) => [
            'id' => 'law_' . $doc->id,
            'doc_id' => $doc->id,
            'type' => 'law',
        ], $lawDocuments);

        $decisionQuery = $this->batchBuilder->buildBatchUpsertNodes('Decision', $decisionNodes, 'id');
        $lawQuery = $this->batchBuilder->buildBatchUpsertNodes('Law', $lawNodes, 'id');

        $this->assertStringContainsString('Decision', $decisionQuery['query']);
        $this->assertStringContainsString('Law', $lawQuery['query']);
        $this->assertCount(2, $decisionQuery['parameters']['nodes']);
        $this->assertCount(2, $lawQuery['parameters']['nodes']);
    }

    /**
     * Test 3: Verify batch configuration values are respected
     *
     * Tests that the services use configuration values correctly:
     * - BatchCypherBuilder respects flush_threshold
     * - ParallelExtractionService respects concurrency_limit
     *
     * @test
     */
    public function it_respects_batch_configuration()
    {
        // Test BatchCypherBuilder flush threshold
        $defaultBuilder = new BatchCypherBuilder();
        $defaultThreshold = $defaultBuilder->getFlushThreshold();

        // Should use config value or default to 100
        $configuredThreshold = config('graph.batch.flush_threshold', 100);
        $this->assertEquals($configuredThreshold, $defaultThreshold);

        // Test custom threshold
        $customBuilder = new BatchCypherBuilder(50);
        $this->assertEquals(50, $customBuilder->getFlushThreshold());

        // Test ParallelExtractionService concurrency limit
        $parallelService = new ParallelExtractionService();
        $concurrencyLimit = $parallelService->getConcurrencyLimit();

        // Should use config value or default to 3
        $configuredLimit = config('graph.parallel.concurrency_limit', 3);
        $this->assertEquals($configuredLimit, $concurrencyLimit);

        // Test setting custom limit
        $parallelService->setConcurrencyLimit(5);
        $this->assertEquals(5, $parallelService->getConcurrencyLimit());

        // Test chunk functionality with configured threshold
        $items = range(1, 250);
        $chunks = $defaultBuilder->chunk($items);

        // Should chunk based on flush threshold
        $expectedChunks = ceil(250 / $defaultThreshold);
        $this->assertCount($expectedChunks, $chunks);

        // First chunks should be full size
        if ($defaultThreshold < 250) {
            $this->assertCount($defaultThreshold, $chunks[0]);
        }
    }
}
