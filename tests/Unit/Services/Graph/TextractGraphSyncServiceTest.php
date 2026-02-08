<?php

namespace Tests\Unit\Services\Graph;

use App\Services\Graph\GraphCitationLinker;
use App\Services\Graph\GraphKeywordLinker;
use App\Services\Graph\GraphSimilarityLinker;
use App\Services\Graph\TextractGraphSyncService;
use App\Services\GraphDatabaseService;
use App\Services\TaggingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Tests for TextractGraphSyncService
 *
 * Verifies the Textract document synchronization service properly:
 * - Creates TextractDocument nodes in Neo4j
 * - Delegates keyword, citation, and similarity linking
 * - Handles batch operations and error cases
 * - Implements the GraphSyncServiceInterface contract
 */
class TextractGraphSyncServiceTest extends TestCase
{
    use UsesTestDatabase;

    protected $graphMock;

    protected $keywordLinkerMock;

    protected $citationLinkerMock;

    protected $similarityLinkerMock;

    protected $taggingMock;

    protected TextractGraphSyncService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock dependencies
        $this->graphMock = Mockery::mock(GraphDatabaseService::class);
        $this->keywordLinkerMock = Mockery::mock(GraphKeywordLinker::class);
        $this->citationLinkerMock = Mockery::mock(GraphCitationLinker::class);
        $this->similarityLinkerMock = Mockery::mock(GraphSimilarityLinker::class);
        $this->taggingMock = Mockery::mock(TaggingService::class);

        // Create service with mocked dependencies
        $this->service = new TextractGraphSyncService(
            $this->graphMock,
            $this->keywordLinkerMock,
            $this->citationLinkerMock,
            $this->similarityLinkerMock,
            $this->taggingMock
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ========================================
    // Interface Implementation Tests
    // ========================================

    /** @test */
    public function it_implements_graph_sync_service_interface()
    {
        $this->assertInstanceOf(
            \App\Contracts\GraphSyncServiceInterface::class,
            $this->service
        );
    }

    /** @test */
    public function it_supports_textract_document_type()
    {
        $this->assertTrue($this->service->supportsType('TextractDocument'));
    }

    /** @test */
    public function it_does_not_support_other_types()
    {
        $this->assertFalse($this->service->supportsType('LawDocument'));
        $this->assertFalse($this->service->supportsType('CourtDecisionDocument'));
        $this->assertFalse($this->service->supportsType('CaseDocument'));
    }

    // ========================================
    // Core Sync Functionality Tests
    // ========================================

    /** @test */
    public function it_syncs_textract_document_with_all_properties()
    {
        $textractDocId = $this->createMinimalTextractDoc([
            'textract_job_id' => 123,
            'case_id' => 'case-456',
            'chunk_index' => 2,
            'chunk_overlap' => 100,
            'token_count' => 500,
            'processing_status' => 'completed',
            'embedding_provider' => 'openai',
            'embedding_model' => 'text-embedding-3-small',
            'embedding_dimensions' => 1536,
            'embedded_at' => now()->toDateTimeString(),
        ]);

        $this->graphMock->shouldReceive('isAvailable')->andReturn(true);

        // Verify exact node properties
        $this->graphMock
            ->shouldReceive('upsertNode')
            ->once()
            ->with('TextractDocument', $textractDocId, Mockery::on(function ($props) {
                return $props['textract_job_id'] === 123
                    && $props['case_id'] === 'case-456'
                    && $props['chunk_index'] === 2
                    && $props['chunk_overlap'] === 100
                    && $props['token_count'] === 500
                    && $props['processing_status'] === 'completed'
                    && $props['embedding_provider'] === 'openai'
                    && $props['embedding_model'] === 'text-embedding-3-small'
                    && $props['embedding_dimensions'] === 1536;
            }));

        $this->allowLinking();
        $this->allowTagging();

        $this->service->sync($textractDocId);
    }

    /** @test */
    public function it_creates_textract_document_node_with_required_properties()
    {
        $textractDocId = $this->createMinimalTextractDoc();

        $this->graphMock->shouldReceive('isAvailable')->andReturn(true);

        // Verify all required properties exist in node creation
        $this->graphMock
            ->shouldReceive('upsertNode')
            ->once()
            ->with('TextractDocument', $textractDocId, Mockery::on(function ($props) {
                return array_key_exists('textract_job_id', $props)
                    && array_key_exists('case_id', $props)
                    && array_key_exists('chunk_index', $props)
                    && array_key_exists('chunk_overlap', $props)
                    && array_key_exists('token_count', $props)
                    && array_key_exists('processing_status', $props)
                    && array_key_exists('embedding_provider', $props)
                    && array_key_exists('embedding_model', $props)
                    && array_key_exists('embedding_dimensions', $props);
            }));

        $this->allowLinking();
        $this->allowTagging();

        $this->service->sync($textractDocId);
    }

    /** @test */
    public function it_calls_keyword_linker()
    {
        $textractDocId = $this->createMinimalTextractDoc([
            'content' => 'Textract extracted: članak 228 Kaznenog zakona',
        ]);

        $this->graphMock->shouldReceive('isAvailable')->andReturn(true);
        $this->graphMock->shouldReceive('upsertNode')->once();

        // Verify keyword linker is called with correct content
        $this->keywordLinkerMock
            ->shouldReceive('link')
            ->once()
            ->with('TextractDocument', $textractDocId, 'Textract extracted: članak 228 Kaznenog zakona');

        $this->citationLinkerMock->shouldReceive('link')->once();
        $this->similarityLinkerMock->shouldReceive('link')->once();
        $this->allowTagging();

        $this->service->sync($textractDocId);
    }

    /** @test */
    public function it_calls_citation_linker()
    {
        $textractDocId = $this->createMinimalTextractDoc([
            'content' => 'OCR text: Prema članku 228. Kaznenog zakona (NN 125/11)',
        ]);

        $this->graphMock->shouldReceive('isAvailable')->andReturn(true);
        $this->graphMock->shouldReceive('upsertNode')->once();

        // Verify citation linker is called with correct content
        $this->citationLinkerMock
            ->shouldReceive('link')
            ->once()
            ->with('TextractDocument', $textractDocId, 'OCR text: Prema članku 228. Kaznenog zakona (NN 125/11)');

        $this->keywordLinkerMock->shouldReceive('link')->once();
        $this->similarityLinkerMock->shouldReceive('link')->once();
        $this->allowTagging();

        $this->service->sync($textractDocId);
    }

    /** @test */
    public function it_calls_similarity_linker_with_embedding()
    {
        $embedding = array_fill(0, 1536, 0.5);
        $embeddingJson = json_encode($embedding);

        $textractDocId = $this->createMinimalTextractDoc([
            'embedding' => $embeddingJson,
        ]);

        $this->graphMock->shouldReceive('isAvailable')->andReturn(true);
        $this->graphMock->shouldReceive('upsertNode')->once();

        // Verify similarity linker is called with embedding array
        $this->similarityLinkerMock
            ->shouldReceive('link')
            ->once()
            ->with('TextractDocument', $textractDocId, Mockery::on(function ($emb) {
                return is_array($emb) && count($emb) === 1536;
            }));

        $this->keywordLinkerMock->shouldReceive('link')->once();
        $this->citationLinkerMock->shouldReceive('link')->once();
        $this->allowTagging();

        $this->service->sync($textractDocId);
    }

    /** @test */
    public function it_calls_similarity_linker_with_null_embedding()
    {
        $textractDocId = $this->createMinimalTextractDoc([
            'embedding' => null,
        ]);

        $this->graphMock->shouldReceive('isAvailable')->andReturn(true);
        $this->graphMock->shouldReceive('upsertNode')->once();

        // Should still call similarity linker (it handles null internally)
        $this->similarityLinkerMock
            ->shouldReceive('link')
            ->once()
            ->with('TextractDocument', $textractDocId, null);

        $this->keywordLinkerMock->shouldReceive('link')->once();
        $this->citationLinkerMock->shouldReceive('link')->once();
        $this->allowTagging();

        $this->service->sync($textractDocId);
    }

    /** @test */
    public function it_calls_auto_tag_with_metadata()
    {
        $textractDocId = $this->createMinimalTextractDoc([
            'content' => 'OCR text from PDF document',
            'metadata' => json_encode([
                'page' => 5,
                'confidence' => 0.98,
                'document_type' => 'legal_brief',
            ]),
        ]);

        $this->graphMock->shouldReceive('isAvailable')->andReturn(true);
        $this->graphMock->shouldReceive('upsertNode')->once();
        $this->allowLinking();

        // Verify autoTag is called with parsed metadata
        $this->taggingMock
            ->shouldReceive('autoTag')
            ->once()
            ->with('TextractDocument', $textractDocId, 'OCR text from PDF document', Mockery::on(function ($metadata) {
                return $metadata['page'] === 5
                    && $metadata['confidence'] === 0.98
                    && $metadata['document_type'] === 'legal_brief';
            }));

        $this->service->sync($textractDocId);
    }

    /** @test */
    public function it_handles_null_metadata_gracefully()
    {
        $textractDocId = $this->createMinimalTextractDoc([
            'metadata' => null,
        ]);

        $this->graphMock->shouldReceive('isAvailable')->andReturn(true);
        $this->graphMock->shouldReceive('upsertNode')->once();
        $this->allowLinking();

        // Should not crash
        $this->taggingMock
            ->shouldReceive('autoTag')
            ->once()
            ->with('TextractDocument', $textractDocId, Mockery::any(), []);

        $this->service->sync($textractDocId);
    }

    /** @test */
    public function it_handles_non_array_json_metadata()
    {
        // Use a valid JSON value that is not an array/object (a JSON string)
        // json_decode returns a string, not an array, triggering the fallback
        $textractDocId = $this->createMinimalTextractDoc([
            'metadata' => '"just a plain string"',
        ]);

        $this->graphMock->shouldReceive('isAvailable')->andReturn(true);
        $this->graphMock->shouldReceive('upsertNode')->once();
        $this->allowLinking();

        // Should not crash - falls back to empty array when json_decode gives non-array
        $this->taggingMock
            ->shouldReceive('autoTag')
            ->once();

        $this->service->sync($textractDocId);
    }

    /** @test */
    public function it_handles_missing_textract_document_gracefully()
    {
        Log::shouldReceive('warning')->once();

        // Should not throw exception or create any nodes
        $this->graphMock->shouldNotReceive('upsertNode');
        $this->taggingMock->shouldNotReceive('autoTag');
        $this->keywordLinkerMock->shouldNotReceive('link');
        $this->citationLinkerMock->shouldNotReceive('link');
        $this->similarityLinkerMock->shouldNotReceive('link');

        $this->service->sync('99999');

        $this->assertTrue(true);
    }

    /** @test */
    public function it_skips_sync_when_neo4j_unavailable()
    {
        $textractDocId = $this->createMinimalTextractDoc();

        $this->graphMock->shouldReceive('isAvailable')->andReturn(false);
        Log::shouldReceive('info')->once();

        // Should not create nodes or call linkers
        $this->graphMock->shouldNotReceive('upsertNode');
        $this->keywordLinkerMock->shouldNotReceive('link');
        $this->citationLinkerMock->shouldNotReceive('link');
        $this->similarityLinkerMock->shouldNotReceive('link');
        $this->taggingMock->shouldNotReceive('autoTag');

        $this->service->sync($textractDocId);

        $this->assertTrue(true);
    }

    /** @test */
    public function it_handles_empty_content()
    {
        $textractDocId = $this->createMinimalTextractDoc([
            'content' => '',
        ]);

        $this->graphMock->shouldReceive('isAvailable')->andReturn(true);
        $this->graphMock->shouldReceive('upsertNode')->once();

        // Should still call all linkers even with empty content
        $this->keywordLinkerMock
            ->shouldReceive('link')
            ->once()
            ->with('TextractDocument', $textractDocId, '');

        $this->citationLinkerMock
            ->shouldReceive('link')
            ->once()
            ->with('TextractDocument', $textractDocId, '');

        $this->similarityLinkerMock->shouldReceive('link')->once();
        $this->allowTagging();

        $this->service->sync($textractDocId);
    }

    // ========================================
    // Batch Sync Tests
    // ========================================

    /** @test */
    public function it_syncs_multiple_textract_documents_in_batch()
    {
        // Create multiple textract documents
        $textractDocIds = [];
        for ($i = 1; $i <= 3; $i++) {
            $textractDocIds[] = $this->createMinimalTextractDoc();
        }

        $this->graphMock->shouldReceive('isAvailable')->andReturn(true);
        $this->graphMock->shouldReceive('upsertNode')->times(3);
        $this->keywordLinkerMock->shouldReceive('link')->times(3);
        $this->citationLinkerMock->shouldReceive('link')->times(3);
        $this->similarityLinkerMock->shouldReceive('link')->times(3);
        $this->taggingMock->shouldReceive('autoTag')->times(3);

        Log::shouldReceive('info')->times(4); // 3 individual + 1 batch summary

        $results = $this->service->syncBatch($textractDocIds);

        $this->assertCount(3, $results);
        $this->assertTrue($results[$textractDocIds[0]]);
        $this->assertTrue($results[$textractDocIds[1]]);
        $this->assertTrue($results[$textractDocIds[2]]);
    }

    /** @test */
    public function it_handles_partial_batch_failures()
    {
        // Create all 3 docs in DB; the second one will fail during graph upsert
        $textractDocId1 = $this->createMinimalTextractDoc();
        $textractDocId2 = $this->createMinimalTextractDoc();
        $textractDocId3 = $this->createMinimalTextractDoc();

        $this->graphMock->shouldReceive('isAvailable')->andReturn(true);

        // First upsert succeeds, second throws, third succeeds (ordered)
        $this->graphMock->shouldReceive('upsertNode')->once()->ordered();
        $this->graphMock->shouldReceive('upsertNode')->once()->ordered()
            ->andThrow(new \Exception('Graph upsert failed'));
        $this->graphMock->shouldReceive('upsertNode')->once()->ordered();

        // Linkers called only for successful syncs (doc1 and doc3)
        $this->keywordLinkerMock->shouldReceive('link')->times(2);
        $this->citationLinkerMock->shouldReceive('link')->times(2);
        $this->similarityLinkerMock->shouldReceive('link')->times(2);
        $this->taggingMock->shouldReceive('autoTag')->times(2);

        Log::shouldReceive('info')->times(3); // 2 successful syncs + 1 batch summary
        Log::shouldReceive('warning')->once(); // 1 for batch catch of failed doc2

        $results = $this->service->syncBatch([$textractDocId1, $textractDocId2, $textractDocId3]);

        $this->assertCount(3, $results);
        $this->assertTrue($results[$textractDocId1]);
        $this->assertFalse($results[$textractDocId2]);
        $this->assertTrue($results[$textractDocId3]);
    }

    /** @test */
    public function it_returns_empty_array_for_empty_batch()
    {
        Log::shouldReceive('info')->once();

        $results = $this->service->syncBatch([]);

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    // ========================================
    // Unsync Tests
    // ========================================

    /** @test */
    public function it_removes_textract_document_from_graph()
    {
        $textractDocId = 'textract-doc-123';

        $this->graphMock->shouldReceive('isAvailable')->andReturn(true);
        $this->graphMock
            ->shouldReceive('deleteNode')
            ->once()
            ->with('TextractDocument', $textractDocId);

        Log::shouldReceive('info')->once();

        $result = $this->service->unsync($textractDocId);

        $this->assertTrue($result);
    }

    /** @test */
    public function it_returns_true_when_delete_node_completes_without_error()
    {
        $textractDocId = 'non-existent-doc';

        $this->graphMock->shouldReceive('isAvailable')->andReturn(true);
        $this->graphMock
            ->shouldReceive('deleteNode')
            ->once()
            ->with('TextractDocument', $textractDocId);

        Log::shouldReceive('info')->once();

        $result = $this->service->unsync($textractDocId);

        // deleteNode is void; if it doesn't throw, unsync returns true
        $this->assertTrue($result);
    }

    /** @test */
    public function it_returns_false_for_empty_document_id_during_unsync()
    {
        $result = $this->service->unsync('');

        $this->assertFalse($result);
    }

    /** @test */
    public function it_skips_unsync_when_neo4j_unavailable()
    {
        $textractDocId = 'textract-doc-123';

        $this->graphMock->shouldReceive('isAvailable')->andReturn(false);
        $this->graphMock->shouldNotReceive('deleteNode');

        Log::shouldReceive('info')->once();

        $result = $this->service->unsync($textractDocId);

        $this->assertFalse($result);
    }

    /** @test */
    public function it_throws_exception_on_unsync_error()
    {
        $textractDocId = 'textract-doc-123';

        $this->graphMock->shouldReceive('isAvailable')->andReturn(true);
        $this->graphMock
            ->shouldReceive('deleteNode')
            ->once()
            ->andThrow(new \Exception('Neo4j connection error'));

        Log::shouldReceive('error')->once();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to unsync Textract document');

        $this->service->unsync($textractDocId);
    }

    // ========================================
    // Helper Methods for Test Setup
    // ========================================

    /**
     * Create a minimal textract document with default values.
     *
     * Returns the auto-generated integer ID as a string (matching the service's string parameter).
     * Creates a parent textract_jobs record to satisfy FK constraints.
     */
    protected function createMinimalTextractDoc(array $overrides = []): string
    {
        // Determine textract_job_id: use override or create a new parent record
        $textractJobId = $overrides['textract_job_id'] ?? null;

        if ($textractJobId !== null) {
            // Ensure the referenced textract_job record exists
            if (! DB::table('textract_jobs')->where('id', $textractJobId)->exists()) {
                DB::table('textract_jobs')->insert([
                    'id' => $textractJobId,
                    'drive_file_id' => 'file-'.uniqid(),
                    'drive_file_name' => 'test.pdf',
                    'status' => 'succeeded',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } else {
            $textractJobId = DB::table('textract_jobs')->insertGetId([
                'drive_file_id' => 'file-'.uniqid(),
                'drive_file_name' => 'test.pdf',
                'status' => 'succeeded',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Handle case_id FK: create a cases record if non-null case_id provided
        $caseId = $overrides['case_id'] ?? null;
        if ($caseId !== null && ! DB::table('cases')->where('id', $caseId)->exists()) {
            DB::table('cases')->insert(['id' => $caseId]);
        }

        $data = array_merge([
            'textract_job_id' => $textractJobId,
            'case_id' => null, // Nullable - avoids cases FK constraint
            'content' => 'Test OCR extracted text',
            'chunk_index' => 0,
            'chunk_overlap' => 100,
            'embedding' => null,
            'embedding_provider' => 'openai',
            'embedding_model' => 'text-embedding-3-small',
            'embedding_dimensions' => 1536,
            'token_count' => 250,
            'processing_status' => 'completed',
            'processing_error' => null,
            'embedded_at' => now(),
            'metadata' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);

        // Remove string 'id' if present - column is bigint auto-increment
        unset($data['id']);

        $id = DB::table('textract_documents')->insertGetId($data);

        return (string) $id;
    }

    /**
     * Allow all linking operations in mocks
     */
    protected function allowLinking(): void
    {
        $this->keywordLinkerMock
            ->shouldReceive('link')
            ->zeroOrMoreTimes();

        $this->citationLinkerMock
            ->shouldReceive('link')
            ->zeroOrMoreTimes();

        $this->similarityLinkerMock
            ->shouldReceive('link')
            ->zeroOrMoreTimes();
    }

    /**
     * Allow tagging operations in mocks
     */
    protected function allowTagging(): void
    {
        $this->taggingMock
            ->shouldReceive('autoTag')
            ->zeroOrMoreTimes();
    }
}
