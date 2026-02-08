<?php

namespace Tests\Unit\Services;

use App\Exceptions\VectorStoreException;
use App\Models\LegalCase;
use App\Models\TextractDocument;
use App\Models\TextractJob;
use App\Services\Graph\GraphRagOrchestrator;
use App\Services\OpenAIService;
use App\Services\TextractVectorStoreService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Test Suite: TextractVectorStoreService Search - pgvector-only Implementation
 *
 * Dedicated tests for the searchSimilar() method using pgvector's native
 * <=> cosine distance operator. PostgreSQL with pgvector is REQUIRED.
 *
 * Tests cover:
 * - RuntimeException when pgvector unavailable (PostgreSQL-only)
 * - Case ID filtering at the database level
 * - minSimilarity threshold filtering
 * - Result ordering by similarity descending
 * - Empty result handling
 * - Vector literal formatting for pgvector
 * - Similarity score clamping [0, 1]
 * - Error handling for embedding generation failures
 *
 * NOTE: Most search tests are skipped when not running on PostgreSQL with
 * pgvector. Use integration testing with PostgreSQL for full coverage.
 */
class TextractVectorStoreServiceSearchTest extends TestCase
{
    use UsesTestDatabase;

    protected TextractVectorStoreService $service;

    protected $openAIMock;

    protected $graphRagMock;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('textract.auto_sync', false);
        Config::set('neo4j.sync.auto_sync', false);
        Config::set('neo4j.sync.enabled', false);

        $this->openAIMock = Mockery::mock(OpenAIService::class);
        $this->graphRagMock = Mockery::mock(GraphRagOrchestrator::class);

        Log::shouldReceive('info')->byDefault();
        Log::shouldReceive('warning')->byDefault();
        Log::shouldReceive('error')->byDefault();
        Log::shouldReceive('debug')->byDefault();

        $this->service = new TextractVectorStoreService($this->openAIMock, $this->graphRagMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ──────────────────────────────────────────────────────────────────
    // Helper: create test documents with known embeddings
    // ──────────────────────────────────────────────────────────────────

    /**
     * Create a TextractDocument with a specific embedding vector.
     */
    private function createDocumentWithEmbedding(
        TextractJob $job,
        string $content,
        array $embedding,
        string $status = 'completed',
        ?string $caseId = null,
    ): TextractDocument {
        return TextractDocument::factory()->create([
            'textract_job_id' => $job->id,
            'case_id' => $caseId ?? $job->case_id,
            'content' => $content,
            'embedding' => $embedding,
            'processing_status' => $status,
        ]);
    }

    /**
     * Mock OpenAI to return a specific embedding for a search query.
     */
    private function mockQueryEmbedding(array $embedding): void
    {
        $this->openAIMock
            ->shouldReceive('embeddings')
            ->once()
            ->andReturn([
                'data' => [['embedding' => $embedding]],
                'model' => 'text-embedding-3-small',
            ]);
    }

    /**
     * Check if pgvector is available in the test database.
     * Skip the test if not available.
     */
    private function skipIfNoPgVector(): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver !== 'pgsql') {
            $this->markTestSkipped('Test requires PostgreSQL with pgvector extension');
        }

        try {
            $result = DB::select("SELECT 1 FROM pg_extension WHERE extname = 'vector' LIMIT 1");
            if (empty($result)) {
                $this->markTestSkipped('Test requires pgvector extension to be installed');
            }
        } catch (\Exception $e) {
            $this->markTestSkipped('Test requires pgvector extension: '.$e->getMessage());
        }
    }

    // ──────────────────────────────────────────────────────────────────
    // Test 1: searchSimilar throws RuntimeException when pgvector unavailable
    // ──────────────────────────────────────────────────────────────────

    /** @test */
    public function it_throws_runtime_exception_when_pgvector_is_unavailable(): void
    {
        // PostgreSQL with pgvector is required. When unavailable, a RuntimeException
        // should be thrown rather than falling back to an inefficient PHP implementation.

        // Create a partial mock that simulates pgvector being unavailable
        $serviceMock = Mockery::mock(TextractVectorStoreService::class, [$this->openAIMock, $this->graphRagMock])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $serviceMock->shouldReceive('isPgVectorAvailable')
            ->once()
            ->andReturn(false);

        // The service should throw a RuntimeException with a clear message.
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('pgvector extension is required');

        $serviceMock->searchSimilar('test query', [
            'limit' => 10,
            'threshold' => 0.5,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────
    // Test 2: Case ID filtering — only matching case returned
    // ──────────────────────────────────────────────────────────────────

    /** @test */
    public function it_filters_results_by_case_id(): void
    {
        $this->skipIfNoPgVector();

        $case1 = LegalCase::factory()->create();
        $case2 = LegalCase::factory()->create();

        $job1 = TextractJob::factory()->create([
            'case_id' => $case1->id,
            'status' => 'succeeded',
        ]);
        $job2 = TextractJob::factory()->create([
            'case_id' => $case2->id,
            'status' => 'succeeded',
        ]);

        $embedding = array_fill(0, 1536, 0.5);

        $this->createDocumentWithEmbedding($job1, 'Case 1 doc', $embedding, 'completed', $case1->id);
        $this->createDocumentWithEmbedding($job2, 'Case 2 doc', $embedding, 'completed', $case2->id);

        $this->mockQueryEmbedding($embedding);

        $results = $this->service->searchSimilar('test query', [
            'case_id' => $case1->id,
            'limit' => 10,
            'threshold' => 0.5,
        ]);

        $this->assertIsArray($results);

        // All results must belong to case1
        foreach ($results as $result) {
            $this->assertArrayHasKey('document', $result);
            $this->assertEquals($case1->id, $result['document']->case_id,
                'Results should only include documents from the specified case');
        }
    }

    // ──────────────────────────────────────────────────────────────────
    // Test 3: Case ID null returns documents from all cases
    // ──────────────────────────────────────────────────────────────────

    /** @test */
    public function it_returns_all_cases_when_case_id_is_null(): void
    {
        $this->skipIfNoPgVector();

        $case1 = LegalCase::factory()->create();
        $case2 = LegalCase::factory()->create();

        $job1 = TextractJob::factory()->create([
            'case_id' => $case1->id,
            'status' => 'succeeded',
        ]);
        $job2 = TextractJob::factory()->create([
            'case_id' => $case2->id,
            'status' => 'succeeded',
        ]);

        $embedding = array_fill(0, 1536, 0.5);

        $this->createDocumentWithEmbedding($job1, 'Case 1 doc', $embedding, 'completed', $case1->id);
        $this->createDocumentWithEmbedding($job2, 'Case 2 doc', $embedding, 'completed', $case2->id);

        $this->mockQueryEmbedding($embedding);

        // No case_id filter
        $results = $this->service->searchSimilar('test query', [
            'limit' => 10,
            'threshold' => 0.5,
        ]);

        $this->assertIsArray($results);
        // Should return documents from both cases
        $this->assertGreaterThanOrEqual(2, count($results),
            'Without case_id filter, results should include documents from all cases');
    }

    // ──────────────────────────────────────────────────────────────────
    // Test 4: minSimilarity threshold filters low-similarity documents
    // ──────────────────────────────────────────────────────────────────

    /** @test */
    public function it_filters_results_below_min_similarity_threshold(): void
    {
        $this->skipIfNoPgVector();

        $case = LegalCase::factory()->create();
        $job = TextractJob::factory()->create([
            'case_id' => $case->id,
            'status' => 'succeeded',
        ]);

        // Create document with an embedding in a specific direction
        $docEmbedding = array_fill(0, 1536, 0.5);
        $this->createDocumentWithEmbedding($job, 'Matching doc', $docEmbedding);

        // Query with the opposite direction => cosine similarity close to -1
        $queryEmbedding = array_fill(0, 1536, -0.5);
        $this->mockQueryEmbedding($queryEmbedding);

        // High threshold should exclude the dissimilar document
        $results = $this->service->searchSimilar('test query', [
            'limit' => 10,
            'threshold' => 0.9,
        ]);

        $this->assertIsArray($results);
        $this->assertCount(0, $results,
            'Documents with similarity below threshold should be excluded');
    }

    // ──────────────────────────────────────────────────────────────────
    // Test 5: Results ordered by similarity descending
    // ──────────────────────────────────────────────────────────────────

    /** @test */
    public function it_orders_results_by_similarity_descending(): void
    {
        $this->skipIfNoPgVector();

        $case = LegalCase::factory()->create();
        $job = TextractJob::factory()->create([
            'case_id' => $case->id,
            'status' => 'succeeded',
        ]);

        // Create query direction
        $queryEmbedding = array_fill(0, 1536, 0.0);
        $queryEmbedding[0] = 1.0;
        $queryEmbedding[1] = 0.0;

        // Doc A: very close to query direction
        $embeddingA = array_fill(0, 1536, 0.0);
        $embeddingA[0] = 1.0;
        $embeddingA[1] = 0.01;

        // Doc B: moderately different
        $embeddingB = array_fill(0, 1536, 0.0);
        $embeddingB[0] = 0.7;
        $embeddingB[1] = 0.7;

        // Doc C: quite different
        $embeddingC = array_fill(0, 1536, 0.0);
        $embeddingC[0] = 0.1;
        $embeddingC[1] = 1.0;

        // Create in reverse order to ensure ordering is not insertion-order
        $this->createDocumentWithEmbedding($job, 'Doc C - least similar', $embeddingC);
        $this->createDocumentWithEmbedding($job, 'Doc B - moderately similar', $embeddingB);
        $this->createDocumentWithEmbedding($job, 'Doc A - most similar', $embeddingA);

        $this->mockQueryEmbedding($queryEmbedding);

        $results = $this->service->searchSimilar('test query', [
            'limit' => 10,
            'threshold' => 0.0,
        ]);

        $this->assertIsArray($results);
        $this->assertGreaterThanOrEqual(2, count($results));

        // Verify descending similarity order
        for ($i = 1; $i < count($results); $i++) {
            $this->assertGreaterThanOrEqual(
                $results[$i]['similarity'],
                $results[$i - 1]['similarity'],
                "Result at index " . ($i - 1) . " (similarity={$results[$i-1]['similarity']}) " .
                "should have >= similarity than result at index {$i} (similarity={$results[$i]['similarity']})"
            );
        }
    }

    // ──────────────────────────────────────────────────────────────────
    // Test 6: Empty results when no matches above threshold
    // ──────────────────────────────────────────────────────────────────

    /** @test */
    public function it_returns_empty_array_when_no_matches_above_threshold(): void
    {
        $this->skipIfNoPgVector();

        $case = LegalCase::factory()->create();
        $job = TextractJob::factory()->create([
            'case_id' => $case->id,
            'status' => 'succeeded',
        ]);

        // Create document with embedding pointing in one direction
        $docEmbedding = array_fill(0, 1536, 0.0);
        $docEmbedding[0] = 1.0;
        $this->createDocumentWithEmbedding($job, 'Unrelated doc', $docEmbedding);

        // Query embedding pointing in the opposite direction
        $queryEmbedding = array_fill(0, 1536, 0.0);
        $queryEmbedding[0] = -1.0;
        $this->mockQueryEmbedding($queryEmbedding);

        $results = $this->service->searchSimilar('unrelated query', [
            'limit' => 10,
            'threshold' => 0.8,
        ]);

        $this->assertIsArray($results);
        $this->assertCount(0, $results, 'Should return empty array when no documents match threshold');
    }

    // ──────────────────────────────────────────────────────────────────
    // Test 7: Empty results when no documents exist
    // ──────────────────────────────────────────────────────────────────

    /** @test */
    public function it_returns_empty_array_when_no_documents_exist(): void
    {
        $this->skipIfNoPgVector();

        $embedding = array_fill(0, 1536, 0.5);
        $this->mockQueryEmbedding($embedding);

        $results = $this->service->searchSimilar('test query', [
            'limit' => 10,
            'threshold' => 0.0,
        ]);

        $this->assertIsArray($results);
        $this->assertCount(0, $results, 'Should return empty array when no documents exist');
    }

    // ──────────────────────────────────────────────────────────────────
    // Test 8: Similarity scores are between 0 and 1
    // ──────────────────────────────────────────────────────────────────

    /** @test */
    public function it_returns_similarity_scores_between_zero_and_one(): void
    {
        $this->skipIfNoPgVector();

        $case = LegalCase::factory()->create();
        $job = TextractJob::factory()->create([
            'case_id' => $case->id,
            'status' => 'succeeded',
        ]);

        $embedding = array_fill(0, 1536, 0.5);
        $this->createDocumentWithEmbedding($job, 'Test document', $embedding);

        $this->mockQueryEmbedding($embedding);

        $results = $this->service->searchSimilar('test query', [
            'limit' => 10,
            'threshold' => 0.0,
        ]);

        $this->assertGreaterThan(0, count($results));

        foreach ($results as $result) {
            $this->assertArrayHasKey('similarity', $result);
            $this->assertGreaterThanOrEqual(0.0, $result['similarity'],
                'Similarity score should be >= 0');
            $this->assertLessThanOrEqual(1.0, $result['similarity'],
                'Similarity score should be <= 1');
        }
    }

    // ──────────────────────────────────────────────────────────────────
    // Test 9: Identical embeddings produce similarity of 1.0
    // ──────────────────────────────────────────────────────────────────

    /** @test */
    public function it_returns_similarity_of_one_for_identical_embeddings(): void
    {
        $this->skipIfNoPgVector();

        $case = LegalCase::factory()->create();
        $job = TextractJob::factory()->create([
            'case_id' => $case->id,
            'status' => 'succeeded',
        ]);

        $embedding = array_fill(0, 1536, 0.5);
        $this->createDocumentWithEmbedding($job, 'Identical document', $embedding);

        // Query with the exact same embedding
        $this->mockQueryEmbedding($embedding);

        $results = $this->service->searchSimilar('test query', [
            'limit' => 10,
            'threshold' => 0.0,
        ]);

        $this->assertGreaterThan(0, count($results));

        // With identical embeddings, cosine similarity should be 1.0
        $this->assertEqualsWithDelta(
            1.0,
            $results[0]['similarity'],
            0.001,
            'Identical embeddings should have similarity of 1.0'
        );
    }

    // ──────────────────────────────────────────────────────────────────
    // Test 10: Search handles embedding generation failure gracefully
    // ──────────────────────────────────────────────────────────────────

    /** @test */
    public function it_throws_exception_when_embedding_generation_fails(): void
    {
        $this->openAIMock
            ->shouldReceive('embeddings')
            ->once()
            ->andThrow(new \Exception('OpenAI API connection failed'));

        $this->expectException(VectorStoreException::class);

        $this->service->searchSimilar('test query', [
            'limit' => 10,
            'threshold' => 0.5,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────
    // Test 11: Only completed documents are included in search
    // ──────────────────────────────────────────────────────────────────

    /** @test */
    public function it_only_includes_completed_documents_in_search(): void
    {
        $this->skipIfNoPgVector();

        $case = LegalCase::factory()->create();
        $job = TextractJob::factory()->create([
            'case_id' => $case->id,
            'status' => 'succeeded',
        ]);

        $embedding = array_fill(0, 1536, 0.5);

        // Create completed document
        $this->createDocumentWithEmbedding($job, 'Completed doc', $embedding, 'completed');

        // Create pending and failed documents
        $this->createDocumentWithEmbedding($job, 'Pending doc', $embedding, 'pending');
        $this->createDocumentWithEmbedding($job, 'Failed doc', $embedding, 'failed');

        $this->mockQueryEmbedding($embedding);

        $results = $this->service->searchSimilar('test query', [
            'limit' => 10,
            'threshold' => 0.0,
        ]);

        $this->assertCount(1, $results,
            'Only completed documents should appear in search results');
        $this->assertEquals('Completed doc', $results[0]['document']->content);
    }

    // ──────────────────────────────────────────────────────────────────
    // Test 12: Documents without embeddings are excluded
    // ──────────────────────────────────────────────────────────────────

    /** @test */
    public function it_excludes_documents_without_embeddings(): void
    {
        $this->skipIfNoPgVector();

        $case = LegalCase::factory()->create();
        $job = TextractJob::factory()->create([
            'case_id' => $case->id,
            'status' => 'succeeded',
        ]);

        $embedding = array_fill(0, 1536, 0.5);

        // Document with embedding
        $this->createDocumentWithEmbedding($job, 'Has embedding', $embedding, 'completed');

        // Document without embedding
        TextractDocument::factory()->create([
            'textract_job_id' => $job->id,
            'case_id' => $case->id,
            'content' => 'No embedding',
            'embedding' => null,
            'processing_status' => 'completed',
        ]);

        $this->mockQueryEmbedding($embedding);

        $results = $this->service->searchSimilar('test query', [
            'limit' => 10,
            'threshold' => 0.0,
        ]);

        $this->assertCount(1, $results,
            'Documents without embeddings should be excluded from results');
        $this->assertEquals('Has embedding', $results[0]['document']->content);
    }

    // ──────────────────────────────────────────────────────────────────
    // Test 13: Limit parameter constrains result count
    // ──────────────────────────────────────────────────────────────────

    /** @test */
    public function it_respects_limit_parameter(): void
    {
        $this->skipIfNoPgVector();

        $case = LegalCase::factory()->create();
        $job = TextractJob::factory()->create([
            'case_id' => $case->id,
            'status' => 'succeeded',
        ]);

        $embedding = array_fill(0, 1536, 0.5);

        // Create 5 documents
        for ($i = 0; $i < 5; $i++) {
            $this->createDocumentWithEmbedding($job, "Document {$i}", $embedding, 'completed');
        }

        $this->mockQueryEmbedding($embedding);

        $results = $this->service->searchSimilar('test query', [
            'limit' => 3,
            'threshold' => 0.0,
        ]);

        $this->assertLessThanOrEqual(3, count($results),
            'Results should not exceed the specified limit');
    }

    // ──────────────────────────────────────────────────────────────────
    // Test 14: toPgVectorLiteral formats vector correctly
    // ──────────────────────────────────────────────────────────────────

    /** @test */
    public function it_formats_vector_as_pgvector_literal(): void
    {
        // Use reflection to access the protected method
        $reflection = new \ReflectionMethod($this->service, 'toPgVectorLiteral');

        $vector = [0.1, 0.25, -0.5, 0.0, 1.0];
        $result = $reflection->invoke($this->service, $vector);

        // Should produce pgvector literal format: [val,val,val]
        $this->assertStringStartsWith('[', $result);
        $this->assertStringEndsWith(']', $result);

        // Parse back and verify values
        $parsed = explode(',', trim($result, '[]'));
        $this->assertCount(5, $parsed);

        $this->assertEqualsWithDelta(0.1, (float) $parsed[0], 0.001);
        $this->assertEqualsWithDelta(0.25, (float) $parsed[1], 0.001);
        $this->assertEqualsWithDelta(-0.5, (float) $parsed[2], 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $parsed[3], 0.001);
        $this->assertEqualsWithDelta(1.0, (float) $parsed[4], 0.001);
    }

    // ──────────────────────────────────────────────────────────────────
    // Test 15: Search result structure includes expected keys
    // ──────────────────────────────────────────────────────────────────

    /** @test */
    public function it_returns_results_with_expected_structure(): void
    {
        $this->skipIfNoPgVector();

        $case = LegalCase::factory()->create();
        $job = TextractJob::factory()->create([
            'case_id' => $case->id,
            'status' => 'succeeded',
        ]);

        $embedding = array_fill(0, 1536, 0.5);
        $this->createDocumentWithEmbedding($job, 'Structure test', $embedding);

        $this->mockQueryEmbedding($embedding);

        $results = $this->service->searchSimilar('test query', [
            'limit' => 10,
            'threshold' => 0.0,
        ]);

        $this->assertGreaterThan(0, count($results));

        $result = $results[0];

        // Verify expected keys exist
        $this->assertArrayHasKey('document', $result);
        $this->assertArrayHasKey('similarity', $result);
        $this->assertArrayHasKey('textract_job_id', $result);

        // Verify document is a TextractDocument model
        $this->assertInstanceOf(TextractDocument::class, $result['document']);

        // Verify textract_job_id matches
        $this->assertEquals($job->id, $result['textract_job_id']);
    }
}
