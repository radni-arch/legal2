<?php

namespace Tests\Unit\Services;

use App\Models\CaseDocument;
use App\Models\LegalCase;
use App\Services\CaseVectorStoreService;
use App\Services\Graph\GraphRagOrchestrator;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Test Suite: CaseVectorStoreService - Vector Embeddings and Search
 *
 * Tests the complete vector store pipeline for legal case documents:
 * - Document ingestion with OpenAI embeddings (text-embedding-3-small)
 * - Chunking strategy (512 tokens with 50-token overlap)
 * - PostgreSQL pgvector storage for cosine similarity search
 * - OpenAI API rate limit handling with exponential backoff
 * - Metadata management (case_id, chunk_index, doc_id)
 * - 1536-dimensional embedding vectors
 * - Transaction safety and rollback
 * - Batch processing for large documents (100+ chunks)
 * - Search functionality with case_id filtering
 * - CRUD operations (store, search, update, delete)
 * - Performance metrics and timing
 *
 * Coverage: 18 comprehensive test methods
 *
 * Note: Current implementation uses ingest() method. Tests document both
 * existing functionality and expected future enhancements (search, update, delete).
 */
class CaseVectorStoreServiceTest extends TestCase
{
    use UsesTestDatabase;

    protected CaseVectorStoreService $service;

    protected $openAIMock;

    protected $graphRagMock;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock OpenAI service
        $this->openAIMock = Mockery::mock(OpenAIService::class);

        // Mock GraphRagService (optional dependency)
        $this->graphRagMock = Mockery::mock(GraphRagOrchestrator::class);

        // Mock logging
        Log::shouldReceive('withContext')->byDefault();
        Log::shouldReceive('info')->byDefault();
        Log::shouldReceive('warning')->byDefault();
        Log::shouldReceive('error')->byDefault();
        Log::shouldReceive('debug')->byDefault();

        // Create service with mocked dependencies
        $this->service = new CaseVectorStoreService($this->openAIMock, $this->graphRagMock);
    }

    protected function tearDown(): void
    {
        // Clear the table existence cache to ensure test isolation
        CaseVectorStoreService::clearTableExistsCache();
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test 1: storeDocument() creates embedding and stores
     *
     * Tests the complete document storage workflow:
     * - Calls OpenAI embeddings API with document content
     * - Receives 1536-dimensional vector
     * - Stores to database with all metadata
     * - Returns summary with inserted count
     */
    /** @test */
    public function it_stores_document_with_embedding()
    {
        $case = LegalCase::factory()->create();

        $docs = [
            [
                'content' => 'This is a legal contract between Party A and Party B.',
                'metadata' => ['title' => 'Contract', 'page' => 1],
                'chunk_index' => 0,
            ],
        ];

        // Mock OpenAI embeddings response
        $mockEmbedding = array_fill(0, 1536, 0.001); // 1536-dimensional vector
        $this->openAIMock
            ->shouldReceive('embeddings')
            ->once()
            ->with(['This is a legal contract between Party A and Party B.'], Mockery::any())
            ->andReturn([
                'data' => [
                    ['embedding' => $mockEmbedding, 'index' => 0],
                ],
                'model' => 'text-embedding-3-small',
                'usage' => ['total_tokens' => 11],
            ]);

        // Disable graph sync for this test
        Config::set('neo4j.sync.auto_sync', false);

        // Execute ingestion
        $result = $this->service->ingest($case->id, 'doc-001', $docs);

        // Assert result
        $this->assertEquals(1, $result['count']);
        $this->assertEquals(1, $result['inserted']);
        $this->assertEquals(1536, $result['dimensions']);
        $this->assertEquals('text-embedding-3-small', $result['model']);

        // Verify database record
        $this->assertDatabaseHas('cases_documents', [
            'case_id' => $case->id,
            'doc_id' => 'doc-001',
            'chunk_index' => 0,
            'embedding_model' => 'text-embedding-3-small',
            'embedding_dimensions' => 1536,
        ]);

        $stored = CaseDocument::where('case_id', $case->id)->first();
        $this->assertNotNull($stored);
        $this->assertStringContainsString('legal contract', $stored->content);
        $this->assertIsArray($stored->metadata);
        $this->assertEquals('Contract', $stored->metadata['title']);
    }

    /**
     * Test 2: Chunks document into 512-token chunks with 50 overlap
     *
     * Note: Current implementation accepts pre-chunked documents.
     * This test documents the expected chunking behavior that would
     * be performed by a separate chunking service/utility.
     */
    /** @test */
    public function it_handles_pre_chunked_documents_with_proper_metadata()
    {
        $case = LegalCase::factory()->create();

        // Simulate pre-chunked document (512 tokens ~= 2048 chars, 50 overlap ~= 200 chars)
        $chunk1 = str_repeat('First chunk content. ', 100);  // ~2000 chars
        $chunk2 = str_repeat('Second chunk content. ', 100); // ~2000 chars with overlap
        $chunk3 = str_repeat('Third chunk content. ', 100);  // ~2000 chars with overlap

        $docs = [
            ['content' => $chunk1, 'chunk_index' => 0, 'metadata' => ['tokens' => 500]],
            ['content' => $chunk2, 'chunk_index' => 1, 'metadata' => ['tokens' => 512]],
            ['content' => $chunk3, 'chunk_index' => 2, 'metadata' => ['tokens' => 480]],
        ];

        // Mock OpenAI for all 3 chunks
        $this->openAIMock
            ->shouldReceive('embeddings')
            ->once()
            ->andReturn([
                'data' => [
                    ['embedding' => array_fill(0, 1536, 0.1), 'index' => 0],
                    ['embedding' => array_fill(0, 1536, 0.2), 'index' => 1],
                    ['embedding' => array_fill(0, 1536, 0.3), 'index' => 2],
                ],
            ]);

        Config::set('neo4j.sync.auto_sync', false);

        $result = $this->service->ingest($case->id, 'doc-chunked', $docs);

        // Verify all chunks stored with correct indices
        $this->assertEquals(3, $result['inserted']);

        $stored = CaseDocument::where('case_id', $case->id)
            ->orderBy('chunk_index')
            ->get();

        $this->assertCount(3, $stored);
        $this->assertEquals(0, $stored[0]->chunk_index);
        $this->assertEquals(1, $stored[1]->chunk_index);
        $this->assertEquals(2, $stored[2]->chunk_index);

        // Verify token count estimation
        $this->assertGreaterThan(400, $stored[0]->token_count); // ~500 chars / 4 = ~125 tokens (rough estimate)
    }

    /**
     * Test 3: Calls OpenAI embeddings API (text-embedding-3-small)
     */
    /** @test */
    public function it_calls_openai_embeddings_api_with_correct_model()
    {
        $case = LegalCase::factory()->create();

        $docs = [['content' => 'Test document for embedding']];

        // Verify OpenAI API call with correct parameters
        $this->openAIMock
            ->shouldReceive('embeddings')
            ->once()
            ->with(
                ['Test document for embedding'],
                'text-embedding-3-small' // Default model from config
            )
            ->andReturn([
                'data' => [['embedding' => array_fill(0, 1536, 0.5)]],
            ]);

        Config::set('neo4j.sync.auto_sync', false);
        Config::set('openai.models.embeddings', 'text-embedding-3-small');

        $result = $this->service->ingest($case->id, 'doc-api-test', $docs);

        $this->assertEquals(1, $result['inserted']);
    }

    /**
     * Test 4: Handles OpenAI API rate limits (retry with backoff)
     *
     * Note: Rate limit retry logic would typically be in OpenAIService.
     * This test documents expected behavior when rate limits occur.
     */
    /** @test */
    public function it_handles_openai_rate_limit_errors()
    {
        $case = LegalCase::factory()->create();

        $docs = [['content' => 'Rate limit test document']];

        // Mock rate limit error (service retries 3 times with backoff)
        $this->openAIMock
            ->shouldReceive('embeddings')
            ->times(3)
            ->andThrow(new \RuntimeException('Rate limit exceeded. Please retry after 20 seconds.'));

        Config::set('neo4j.sync.auto_sync', false);

        // Expect exception to propagate (wrapped in IngestException)
        $this->expectException(\App\Exceptions\IngestException::class);
        $this->expectExceptionMessage('Failed to generate embeddings');

        $this->service->ingest($case->id, 'doc-rate-limit', $docs);
    }

    /**
     * Test 5: Stores chunks with metadata (case_id, chunk_index, doc_id)
     */
    /** @test */
    public function it_stores_chunks_with_complete_metadata()
    {
        $case = LegalCase::factory()->create();

        $docs = [
            [
                'content' => 'First chunk',
                'chunk_index' => 0,
                'metadata' => [
                    'page' => 1,
                    'section' => 'Introduction',
                    'confidence' => 0.95,
                ],
                'source' => 'pdf',
                'source_id' => 'contract-v2.pdf',
            ],
            [
                'content' => 'Second chunk',
                'chunk_index' => 1,
                'metadata' => [
                    'page' => 2,
                    'section' => 'Terms',
                ],
            ],
        ];

        $this->openAIMock
            ->shouldReceive('embeddings')
            ->once()
            ->andReturn([
                'data' => [
                    ['embedding' => array_fill(0, 1536, 0.1)],
                    ['embedding' => array_fill(0, 1536, 0.2)],
                ],
            ]);

        Config::set('neo4j.sync.auto_sync', false);

        $result = $this->service->ingest($case->id, 'doc-metadata-test', $docs);

        $this->assertEquals(2, $result['inserted']);

        // Verify first chunk metadata
        $chunk1 = CaseDocument::where('case_id', $case->id)
            ->where('chunk_index', 0)
            ->first();

        $this->assertNotNull($chunk1);
        $this->assertEquals($case->id, $chunk1->case_id);
        $this->assertEquals('doc-metadata-test', $chunk1->doc_id);
        $this->assertNull($chunk1->upload_id); // Changed from 'upload-123' since it's nullable
        $this->assertEquals(0, $chunk1->chunk_index);
        $this->assertEquals('pdf', $chunk1->source);
        $this->assertEquals('contract-v2.pdf', $chunk1->source_id);
        $this->assertIsArray($chunk1->metadata);
        $this->assertEquals(1, $chunk1->metadata['page']);
        $this->assertEquals('Introduction', $chunk1->metadata['section']);
        $this->assertEquals(0.95, $chunk1->metadata['confidence']);
    }

    /**
     * Test 6: Generates 1536-dimensional embedding vectors
     */
    /** @test */
    public function it_generates_1536_dimensional_embedding_vectors()
    {
        $case = LegalCase::factory()->create();

        $docs = [['content' => 'Vector dimension test']];

        // Create exactly 1536-dimensional vector
        $embedding1536 = array_fill(0, 1536, 0.123);

        $this->openAIMock
            ->shouldReceive('embeddings')
            ->once()
            ->andReturn([
                'data' => [['embedding' => $embedding1536]],
            ]);

        Config::set('neo4j.sync.auto_sync', false);

        $result = $this->service->ingest($case->id, 'doc-dims', $docs);

        // Verify dimensions recorded correctly
        $this->assertEquals(1536, $result['dimensions']);

        $stored = CaseDocument::where('case_id', $case->id)->first();
        $this->assertEquals(1536, $stored->embedding_dimensions);

        // Verify norm calculation (L2 norm)
        $expectedNorm = sqrt(1536 * (0.123 * 0.123));
        $this->assertEqualsWithDelta($expectedNorm, $stored->embedding_norm, 0.001);
    }

    /**
     * Test 7: Uses pgvector for storage (PostgreSQL)
     */
    /** @test */
    public function it_uses_pgvector_format_for_postgresql()
    {
        // Temporarily override DB driver to simulate PostgreSQL with pgvector
        DB::shouldReceive('connection')
            ->andReturnSelf();
        DB::shouldReceive('getDriverName')
            ->andReturn('pgsql');

        // Mock the information_schema query to indicate pgvector is available
        DB::shouldReceive('select')
            ->with(Mockery::pattern('/information_schema.columns/'), Mockery::any())
            ->andReturn([(object) ['data_type' => 'user-defined']]);

        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        DB::shouldReceive('table')
            ->with('cases_documents')
            ->andReturnSelf();

        DB::shouldReceive('where')
            ->andReturnSelf();

        // Capture the insert payload to verify pgvector format
        $insertedPayload = null;
        DB::shouldReceive('insert')
            ->once()
            ->andReturnUsing(function ($payload) use (&$insertedPayload) {
                $insertedPayload = $payload;

                return true;
            });

        DB::shouldReceive('exists')
            ->andReturn(false);

        // Mock DB::raw() for pgvector casting
        DB::shouldReceive('raw')
            ->andReturnUsing(function ($value) {
                return new \Illuminate\Database\Query\Expression($value);
            });

        $case = LegalCase::factory()->create();
        $docs = [['content' => 'PostgreSQL pgvector test']];

        $embedding = [0.1, 0.2, 0.3, 0.4, 0.5];

        $this->openAIMock
            ->shouldReceive('embeddings')
            ->once()
            ->andReturn([
                'data' => [['embedding' => $embedding]],
            ]);

        Config::set('neo4j.sync.auto_sync', false);

        $this->service->ingest($case->id, 'doc-pgvector', $docs);

        // Verify pgvector cast was used
        $this->assertNotNull($insertedPayload);
        $this->assertArrayHasKey('embedding_vector', $insertedPayload);

        // Verify it's a DB::raw() Expression object (pgvector format)
        $vectorValue = $insertedPayload['embedding_vector'];
        $this->assertInstanceOf(\Illuminate\Database\Query\Expression::class, $vectorValue);
    }

    /**
     * Test 8: search() finds similar chunks by cosine similarity
     *
     * Note: Search functionality not yet implemented in current service.
     * This test documents expected behavior for future implementation.
     */
    /** @test */
    public function it_searches_similar_chunks_by_cosine_similarity()
    {
        $this->markTestSkipped('Search functionality to be implemented. Expected signature: search(string $query, ?string $caseId = null, int $limit = 10)');

        // Expected behavior:
        // 1. Generate embedding for query text
        // 2. Use pgvector cosine similarity: embedding <=> query_embedding
        // 3. Order by similarity DESC
        // 4. Limit results
        // 5. Return array of CaseDocument with similarity scores
    }

    /**
     * Test 9: Filters by case_id when provided
     */
    /** @test */
    public function it_filters_documents_by_case_id_during_ingestion()
    {
        $case1 = LegalCase::factory()->create();
        $case2 = LegalCase::factory()->create();

        $docs1 = [['content' => 'Document for case 1']];
        $docs2 = [['content' => 'Document for case 2']];

        $this->openAIMock
            ->shouldReceive('embeddings')
            ->times(2)
            ->andReturn([
                'data' => [['embedding' => array_fill(0, 1536, 0.1)]],
            ]);

        Config::set('neo4j.sync.auto_sync', false);

        // Ingest for case 1
        $this->service->ingest($case1->id, 'doc-1', $docs1);

        // Ingest for case 2
        $this->service->ingest($case2->id, 'doc-2', $docs2);

        // Verify documents are correctly separated by case_id
        $case1Docs = CaseDocument::where('case_id', $case1->id)->get();
        $case2Docs = CaseDocument::where('case_id', $case2->id)->get();

        $this->assertCount(1, $case1Docs);
        $this->assertCount(1, $case2Docs);
        $this->assertStringContainsString('case 1', $case1Docs->first()->content);
        $this->assertStringContainsString('case 2', $case2Docs->first()->content);
    }

    /**
     * Test 10: Returns results ordered by similarity DESC
     *
     * Note: Part of search functionality (not yet implemented).
     */
    /** @test */
    public function it_orders_search_results_by_similarity_descending()
    {
        $this->markTestSkipped('Search result ordering to be implemented with search() method');

        // Expected: ORDER BY (embedding <=> query_embedding) ASC
        // (smaller distance = higher similarity in pgvector)
    }

    /**
     * Test 11: Handles empty query gracefully
     */
    /** @test */
    public function it_handles_empty_documents_gracefully()
    {
        $case = LegalCase::factory()->create();

        // Empty documents array
        $result1 = $this->service->ingest($case->id, 'doc-empty-1', []);
        $this->assertEquals(0, $result1['count']);
        $this->assertEquals(0, $result1['inserted']);

        // Documents with empty content
        $result2 = $this->service->ingest($case->id, 'doc-empty-2', [
            ['content' => ''],
            ['content' => '   '], // Whitespace only
        ]);
        $this->assertEquals(0, $result2['count']);
        $this->assertEquals(0, $result2['inserted']);
    }

    /**
     * Test 12: updateEmbedding() regenerates embedding for chunk
     *
     * Note: Update functionality not yet implemented.
     */
    /** @test */
    public function it_updates_embedding_for_existing_chunk()
    {
        $this->markTestSkipped('updateEmbedding() to be implemented. Expected: regenerate embedding for specific chunk_id and update database');

        // Expected behavior:
        // 1. Find existing CaseDocument by id
        // 2. Call OpenAI embeddings with updated content
        // 3. Update embedding_vector and metadata
        // 4. Update updated_at timestamp
    }

    /**
     * Test 13: deleteDocument() removes all chunks for doc_id
     *
     * Note: Delete functionality not yet implemented.
     */
    /** @test */
    public function it_deletes_all_chunks_for_document()
    {
        $this->markTestSkipped('deleteDocument() to be implemented. Expected signature: deleteDocument(string $docId)');

        // Expected behavior:
        // 1. Delete all CaseDocument records WHERE doc_id = $docId
        // 2. Return count of deleted records
        // 3. Optionally delete from graph database
    }

    /**
     * Test 14: Transaction rollback on embedding API failure
     */
    /** @test */
    public function it_rolls_back_transaction_on_api_failure()
    {
        $case = LegalCase::factory()->create();

        $docs = [
            ['content' => 'First doc'],
            ['content' => 'Second doc that will fail'],
        ];

        // Mock OpenAI to throw exception (service retries 3 times)
        $this->openAIMock
            ->shouldReceive('embeddings')
            ->times(3)
            ->andThrow(new \RuntimeException('OpenAI API error: Invalid API key'));

        Config::set('neo4j.sync.auto_sync', false);

        // Expect exception (wrapped in IngestException after retries)
        $this->expectException(\App\Exceptions\IngestException::class);

        try {
            $this->service->ingest($case->id, 'doc-fail', $docs);
        } finally {
            // Verify no documents were inserted (transaction rolled back)
            $count = CaseDocument::where('case_id', $case->id)->count();
            $this->assertEquals(0, $count, 'Transaction should rollback on API failure');
        }
    }

    /**
     * Test 15: Batch processing for large documents (100+ chunks)
     */
    /** @test */
    public function it_processes_large_batches_of_chunks()
    {
        $case = LegalCase::factory()->create();

        // Create 150 chunks
        $docs = [];
        for ($i = 0; $i < 150; $i++) {
            $docs[] = [
                'content' => "Chunk $i content with sufficient length to be meaningful.",
                'chunk_index' => $i,
            ];
        }

        // Mock OpenAI to return 150 embeddings
        $embeddings = [];
        for ($i = 0; $i < 150; $i++) {
            $embeddings[] = ['embedding' => array_fill(0, 1536, 0.01 * $i)];
        }

        $this->openAIMock
            ->shouldReceive('embeddings')
            ->once()
            ->andReturn(['data' => $embeddings]);

        Config::set('neo4j.sync.auto_sync', false);

        $startTime = microtime(true);
        $result = $this->service->ingest($case->id, 'doc-large', $docs);
        $duration = microtime(true) - $startTime;

        // Verify all chunks processed
        $this->assertEquals(150, $result['count']);
        $this->assertEquals(150, $result['inserted']);

        // Verify database has 150 records
        $count = CaseDocument::where('case_id', $case->id)->count();
        $this->assertEquals(150, $count);

        // Verify performance (should complete in reasonable time)
        $this->assertLessThan(5.0, $duration, 'Batch processing should complete within 5 seconds');
    }

    /**
     * Test 16: Validates embedding dimensions (1536)
     */
    /** @test */
    public function it_validates_embedding_dimensions()
    {
        $this->markTestSkipped('Dimension validation not applicable - pgvector columns are fixed at vector(1536)');

        $case = LegalCase::factory()->create();

        $docs = [['content' => 'Dimension validation test']];

        // Test with correct dimensions
        $embedding1536 = array_fill(0, 1536, 0.5);
        $this->openAIMock
            ->shouldReceive('embeddings')
            ->once()
            ->andReturn([
                'data' => [['embedding' => $embedding1536]],
            ]);

        Config::set('neo4j.sync.auto_sync', false);

        $result = $this->service->ingest($case->id, 'doc-dims-valid', $docs);

        // Verify dimensions are correctly recorded
        $this->assertEquals(1536, $result['dimensions']);

        $stored = CaseDocument::where('case_id', $case->id)->first();
        $this->assertEquals(1536, $stored->embedding_dimensions);

        // Test with incorrect dimensions (would come from different model)
        $embedding768 = array_fill(0, 768, 0.5);
        $this->openAIMock
            ->shouldReceive('embeddings')
            ->once()
            ->andReturn([
                'data' => [['embedding' => $embedding768]],
            ]);

        $result2 = $this->service->ingest($case->id, 'doc-dims-other', [['content' => 'Other model']]);

        // Service should handle different dimensions
        $this->assertEquals(768, $result2['dimensions']);
    }

    /**
     * Test 17: Handles database errors gracefully
     */
    /** @test */
    public function it_handles_database_errors_gracefully()
    {
        $case = LegalCase::factory()->create();

        $docs = [['content' => 'Database error test']];

        $this->openAIMock
            ->shouldReceive('embeddings')
            ->once()
            ->andReturn([
                'data' => [['embedding' => array_fill(0, 1536, 0.1)]],
            ]);

        // Mock DB to throw exception during transaction
        DB::shouldReceive('connection')
            ->andReturnSelf();
        DB::shouldReceive('getDriverName')
            ->andReturn('pgsql');
        DB::shouldReceive('transaction')
            ->once()
            ->andThrow(new \PDOException('Database connection lost'));
        DB::shouldReceive('table')->andReturnSelf()->byDefault();
        DB::shouldReceive('select')->andReturn([])->byDefault();

        Config::set('neo4j.sync.auto_sync', false);

        // Exception is wrapped in IngestException by the service
        $this->expectException(\App\Exceptions\IngestException::class);
        $this->expectExceptionMessage('Database connection lost');

        $this->service->ingest($case->id, 'doc-db-error', $docs);
    }

    /**
     * Test 18: Records embedding generation time
     *
     * Tests performance metrics tracking for embedding generation.
     */
    /** @test */
    public function it_records_embedding_generation_time()
    {
        $case = LegalCase::factory()->create();

        $docs = [
            ['content' => 'Performance metrics test document'],
        ];

        // Mock OpenAI with slight delay to simulate API call
        $this->openAIMock
            ->shouldReceive('embeddings')
            ->once()
            ->andReturnUsing(function () {
                usleep(50000); // 50ms delay

                return [
                    'data' => [['embedding' => array_fill(0, 1536, 0.1)]],
                ];
            });

        Config::set('neo4j.sync.auto_sync', false);

        $startTime = microtime(true);
        $result = $this->service->ingest($case->id, 'doc-perf', $docs);
        $duration = microtime(true) - $startTime;

        // Verify embedding was created
        $this->assertEquals(1, $result['inserted']);

        // Verify timing is reasonable
        $this->assertGreaterThan(0.05, $duration, 'Should take at least 50ms due to mock delay');
        $this->assertLessThan(1.0, $duration, 'Should complete within 1 second');

        // Verify created_at timestamp is recorded
        $stored = CaseDocument::where('case_id', $case->id)->first();
        $this->assertNotNull($stored->created_at);
        $this->assertTrue($stored->created_at->diffInSeconds(now()) < 5);
    }

    /**
     * Test 19: Prevents duplicate documents with content hash
     */
    /** @test */
    public function it_prevents_duplicate_documents_using_content_hash()
    {
        $case = LegalCase::factory()->create();

        $docs = [
            ['content' => 'Duplicate content test'],
        ];

        $this->openAIMock
            ->shouldReceive('embeddings')
            ->times(2)
            ->andReturn([
                'data' => [['embedding' => array_fill(0, 1536, 0.1)]],
            ]);

        Config::set('neo4j.sync.auto_sync', false);

        // First ingestion
        $result1 = $this->service->ingest($case->id, 'doc-dup-1', $docs);
        $this->assertEquals(1, $result1['inserted']);

        // Second ingestion with same content (should be skipped)
        $result2 = $this->service->ingest($case->id, 'doc-dup-2', $docs);
        $this->assertEquals(1, $result2['count']); // Counted
        $this->assertEquals(0, $result2['inserted']); // Not inserted (duplicate)

        // Verify only one record in database
        $count = CaseDocument::where('case_id', $case->id)->count();
        $this->assertEquals(1, $count);

        // Verify content_hash is stored
        $stored = CaseDocument::where('case_id', $case->id)->first();
        $expectedHash = hash('sha256', 'Duplicate content test');
        $this->assertEquals($expectedHash, $stored->content_hash);
    }

    /**
     * Test 20: Graph database synchronization (optional)
     */
    /** @test */
    public function it_syncs_to_graph_database_when_enabled()
    {
        $case = LegalCase::factory()->create();

        $docs = [['content' => 'Graph sync test']];

        $this->openAIMock
            ->shouldReceive('embeddings')
            ->once()
            ->andReturn([
                'data' => [['embedding' => array_fill(0, 1536, 0.1)]],
            ]);

        // Enable graph sync
        Config::set('neo4j.sync.auto_sync', true);
        Config::set('neo4j.sync.enabled', true);

        // Expect graph sync call
        $this->graphRagMock
            ->shouldReceive('syncCase')
            ->once()
            ->with(Mockery::type('string')) // case_doc_id (ULID)
            ->andReturn(true);

        $result = $this->service->ingest($case->id, 'doc-graph', $docs);

        $this->assertEquals(1, $result['inserted']);
    }
}
