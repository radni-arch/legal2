<?php

namespace Tests\Unit\Services\Graph;

use App\Services\Graph\CaseGraphSyncService;
use App\Services\Graph\GraphCitationLinker;
use App\Services\Graph\GraphKeywordLinker;
use App\Services\Graph\GraphSimilarityLinker;
use App\Services\GraphDatabaseService;
use App\Services\TaggingService;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Tests for CaseGraphSyncService
 *
 * These tests verify that the extracted case syncing service maintains
 * the exact same behavior as the original GraphRagService implementation.
 *
 * Tests are based on the characterization tests for syncCase() method.
 */
class CaseGraphSyncServiceTest extends TestCase
{
    use UsesTestDatabase;

    protected $graphMock;

    protected $keywordLinkerMock;

    protected $citationLinkerMock;

    protected $similarityLinkerMock;

    protected $taggingMock;

    protected CaseGraphSyncService $service;

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
        $this->service = new CaseGraphSyncService(
            $this->graphMock,
            $this->keywordLinkerMock,
            $this->citationLinkerMock,
            $this->similarityLinkerMock,
            $this->taggingMock
        );
    }

    protected function tearDown(): void
    {
        // Count Mockery expectations as PHPUnit assertions to avoid risky test warnings
        $container = Mockery::getContainer();
        if ($container) {
            $this->addToAssertionCount($container->mockery_getExpectationCount());
        }
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
    public function it_supports_case_document_type()
    {
        $this->assertTrue($this->service->supportsType('CaseDocument'));
    }

    /** @test */
    public function it_does_not_support_other_types()
    {
        $this->assertFalse($this->service->supportsType('LawDocument'));
        $this->assertFalse($this->service->supportsType('CourtDecisionDocument'));
        $this->assertFalse($this->service->supportsType('TextractDocument'));
    }

    // ========================================
    // Core Sync Functionality Tests
    // ========================================

    /** @test */
    public function it_syncs_case_with_all_properties()
    {
        $caseDocId = $this->createMinimalCaseDoc([
            'case_id' => 'case-123',
            'doc_id' => 'doc-456',
            'title' => 'Kazneni predmet protiv optuženog X',
            'category' => 'criminal',
            'language' => 'hr',
            'chunk_index' => 3,
            'content_hash' => 'hash-xyz',
            'source' => 'court_submission',
        ]);

        // Verify exact node properties (trim() handles CHAR column padding)
        $this->graphMock
            ->shouldReceive('upsertNode')
            ->once()
            ->with('CaseDocument', $caseDocId, Mockery::on(function ($props) {
                return trim($props['case_id']) === 'case-123'
                    && trim($props['doc_id']) === 'doc-456'
                    && $props['title'] === 'Kazneni predmet protiv optuženog X'
                    && $props['category'] === 'criminal'
                    && $props['language'] === 'hr'
                    && $props['chunk_index'] === 3
                    && $props['content_hash'] === 'hash-xyz'
                    && $props['source'] === 'court_submission';
            }));

        $this->allowLinking();
        $this->allowTagging();

        $this->service->sync($caseDocId);
    }

    /** @test */
    public function it_creates_case_document_node_with_required_properties()
    {
        $caseDocId = $this->createMinimalCaseDoc();

        // Verify all required properties exist in node creation
        $this->graphMock
            ->shouldReceive('upsertNode')
            ->once()
            ->with('CaseDocument', $caseDocId, Mockery::on(function ($props) {
                return array_key_exists('case_id', $props)
                    && array_key_exists('doc_id', $props)
                    && array_key_exists('title', $props)
                    && array_key_exists('category', $props)
                    && array_key_exists('language', $props)
                    && array_key_exists('chunk_index', $props)
                    && array_key_exists('content_hash', $props)
                    && array_key_exists('source', $props);
            }));

        $this->allowLinking();
        $this->allowTagging();

        $this->service->sync($caseDocId);
    }

    /** @test */
    public function it_calls_keyword_linker()
    {
        $caseDocId = $this->createMinimalCaseDoc([
            'content' => 'Kazneni postupak za djelo iz članka 228',
        ]);

        $this->graphMock->shouldReceive('upsertNode')->once();

        // Verify keyword linker is called with correct content
        $this->keywordLinkerMock
            ->shouldReceive('link')
            ->once()
            ->with('CaseDocument', $caseDocId, 'Kazneni postupak za djelo iz članka 228');

        $this->citationLinkerMock->shouldReceive('link')->once();
        $this->similarityLinkerMock->shouldReceive('link')->once();
        $this->allowTagging();

        $this->service->sync($caseDocId);
    }

    /** @test */
    public function it_calls_citation_linker()
    {
        $caseDocId = $this->createMinimalCaseDoc([
            'content' => 'Prema članku 228. Kaznenog zakona (NN 125/11)',
        ]);

        $this->graphMock->shouldReceive('upsertNode')->once();

        // Verify citation linker is called with correct content
        $this->citationLinkerMock
            ->shouldReceive('link')
            ->once()
            ->with('CaseDocument', $caseDocId, 'Prema članku 228. Kaznenog zakona (NN 125/11)');

        $this->keywordLinkerMock->shouldReceive('link')->once();
        $this->similarityLinkerMock->shouldReceive('link')->once();
        $this->allowTagging();

        $this->service->sync($caseDocId);
    }

    /** @test */
    public function it_calls_similarity_linker_with_embedding()
    {
        $embedding = array_fill(0, 1536, 0.5);
        $embeddingJson = json_encode($embedding);

        $caseDocId = $this->createMinimalCaseDoc([
            'embedding_vector' => DB::raw("'{$embeddingJson}'::vector"),
        ]);

        $this->graphMock->shouldReceive('upsertNode')->once();

        // Verify similarity linker is called with embedding
        $this->similarityLinkerMock
            ->shouldReceive('link')
            ->once()
            ->with('CaseDocument', $caseDocId, Mockery::type('string'));

        $this->keywordLinkerMock->shouldReceive('link')->once();
        $this->citationLinkerMock->shouldReceive('link')->once();
        $this->allowTagging();

        $this->service->sync($caseDocId);
    }

    /** @test */
    public function it_calls_similarity_linker_with_null_embedding()
    {
        $caseDocId = $this->createMinimalCaseDoc([
            'embedding_vector' => null,
        ]);

        $this->graphMock->shouldReceive('upsertNode')->once();

        // Should still call similarity linker (it handles null internally)
        $this->similarityLinkerMock
            ->shouldReceive('link')
            ->once()
            ->with('CaseDocument', $caseDocId, null);

        $this->keywordLinkerMock->shouldReceive('link')->once();
        $this->citationLinkerMock->shouldReceive('link')->once();
        $this->allowTagging();

        $this->service->sync($caseDocId);
    }

    /** @test */
    public function it_calls_auto_tag_with_metadata()
    {
        $caseDocId = $this->createMinimalCaseDoc([
            'content' => 'Test case content',
            'metadata' => json_encode(['category' => 'criminal', 'tags' => ['drug_offense'], 'extra' => 'data']),
        ]);

        $this->graphMock->shouldReceive('upsertNode')->once();
        $this->allowLinking();

        // Verify autoTag is called with parsed metadata
        $this->taggingMock
            ->shouldReceive('autoTag')
            ->once()
            ->with('CaseDocument', $caseDocId, 'Test case content', Mockery::on(function ($metadata) {
                return $metadata['category'] === 'criminal'
                    && $metadata['tags'] === ['drug_offense']
                    && $metadata['extra'] === 'data';
            }));

        $this->service->sync($caseDocId);
    }

    /** @test */
    public function it_handles_null_metadata_gracefully()
    {
        $caseDocId = $this->createMinimalCaseDoc([
            'metadata' => null,
        ]);

        $this->graphMock->shouldReceive('upsertNode')->once();
        $this->allowLinking();

        // Should not crash and should pass metadata as-is (null becomes empty array after json_decode)
        $this->taggingMock
            ->shouldReceive('autoTag')
            ->once()
            ->with('CaseDocument', $caseDocId, Mockery::any(), Mockery::any());

        $this->service->sync($caseDocId);
    }

    /** @test */
    public function it_handles_metadata_that_decodes_to_null()
    {
        // PostgreSQL JSON column enforces JSON validity, so test with valid JSON
        // that decodes to null. The service should handle this gracefully by
        // falling back to an empty array.
        $caseDocId = $this->createMinimalCaseDoc([
            'metadata' => 'null', // Valid JSON that decodes to null
        ]);

        $this->graphMock->shouldReceive('upsertNode')->once();
        $this->allowLinking();

        // json_decode('null', true) returns null, service should fallback to []
        $this->taggingMock
            ->shouldReceive('autoTag')
            ->once()
            ->with('CaseDocument', $caseDocId, Mockery::any(), []);

        $this->service->sync($caseDocId);
    }

    /** @test */
    public function it_handles_missing_case_document_gracefully()
    {
        // Should not throw exception or create any nodes
        $this->graphMock->shouldNotReceive('upsertNode');
        $this->taggingMock->shouldNotReceive('autoTag');
        $this->keywordLinkerMock->shouldNotReceive('link');
        $this->citationLinkerMock->shouldNotReceive('link');
        $this->similarityLinkerMock->shouldNotReceive('link');

        $this->service->sync('non-existent-case-id');

        $this->assertTrue(true);
    }

    /** @test */
    public function it_handles_empty_content()
    {
        $caseDocId = $this->createMinimalCaseDoc([
            'content' => '',
        ]);

        $this->graphMock->shouldReceive('upsertNode')->once();

        // Should still call all linkers even with empty content
        $this->keywordLinkerMock
            ->shouldReceive('link')
            ->once()
            ->with('CaseDocument', $caseDocId, '');

        $this->citationLinkerMock
            ->shouldReceive('link')
            ->once()
            ->with('CaseDocument', $caseDocId, '');

        $this->similarityLinkerMock->shouldReceive('link')->once();
        $this->allowTagging();

        $this->service->sync($caseDocId);
    }

    /** @test */
    public function it_handles_null_optional_fields()
    {
        $caseDocId = $this->createMinimalCaseDoc([
            'category' => null,
            'source' => null,
        ]);

        $this->graphMock
            ->shouldReceive('upsertNode')
            ->once()
            ->with('CaseDocument', $caseDocId, Mockery::on(function ($props) {
                return $props['category'] === null
                    && $props['source'] === null;
            }));

        $this->allowLinking();
        $this->allowTagging();

        $this->service->sync($caseDocId);
    }

    // ========================================
    // Batch Sync Tests
    // ========================================

    /** @test */
    public function it_syncs_multiple_cases_in_batch()
    {
        // Create multiple case documents
        $caseDocIds = [];
        for ($i = 1; $i <= 3; $i++) {
            $caseDocIds[] = $this->createMinimalCaseDoc([
                'id' => str_pad("batch-case-$i", 26),
            ]);
        }

        $this->graphMock->shouldReceive('upsertNode')->times(3);
        $this->keywordLinkerMock->shouldReceive('link')->times(3);
        $this->citationLinkerMock->shouldReceive('link')->times(3);
        $this->similarityLinkerMock->shouldReceive('link')->times(3);
        $this->taggingMock->shouldReceive('autoTag')->times(3);

        foreach ($caseDocIds as $caseDocId) {
            $this->service->sync($caseDocId);
        }

        $this->assertTrue(true);
    }

    /** @test */
    public function it_handles_partial_batch_failures()
    {
        $caseDocId1 = $this->createMinimalCaseDoc();
        $caseDocId2 = 'non-existent';
        $caseDocId3 = $this->createMinimalCaseDoc();

        $this->graphMock->shouldReceive('upsertNode')->times(2); // Only for existing cases
        $this->keywordLinkerMock->shouldReceive('link')->times(2);
        $this->citationLinkerMock->shouldReceive('link')->times(2);
        $this->similarityLinkerMock->shouldReceive('link')->times(2);
        $this->taggingMock->shouldReceive('autoTag')->times(2);

        // Should not throw exception on missing case
        $this->service->sync($caseDocId1);
        $this->service->sync($caseDocId2); // Missing - should be skipped
        $this->service->sync($caseDocId3);

        $this->assertTrue(true);
    }

    // ========================================
    // Helper Methods for Test Setup
    // ========================================

    /**
     * Create a minimal case document with default values
     */
    protected function createMinimalCaseDoc(array $overrides = []): string
    {
        $id = $overrides['id'] ?? str_pad('case-'.uniqid(), 26);
        $caseId = $overrides['case_id'] ?? 'case-'.uniqid();
        $embedding = $overrides['embedding_vector'] ?? DB::raw("'[".implode(',', array_fill(0, 1536, 0.1))."]'::vector");

        // Create parent case record first to satisfy foreign key constraint
        $this->createParentCase($caseId);

        $data = array_merge([
            'id' => $id,
            'case_id' => $caseId,
            'doc_id' => 'doc-'.uniqid(),
            'title' => 'Test Case Document',
            'content' => 'Test case content',
            'content_hash' => hash('sha256', 'Test case content'),
            'chunk_index' => 0,
            'category' => 'criminal',
            'language' => 'hr',
            'source' => 'upload',
            'embedding_provider' => 'openai',
            'embedding_model' => 'text-embedding-ada-002',
            'embedding_dimensions' => 1536,
            'embedding_vector' => $embedding,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);

        unset($data['id']); // Remove from data array
        DB::table('cases_documents')->insert(array_merge(['id' => $id], $data));

        return $id;
    }

    /**
     * Create a parent case record to satisfy foreign key constraints
     */
    protected function createParentCase(string $caseId): void
    {
        // Check if case already exists
        if (DB::table('cases')->where('id', $caseId)->exists()) {
            return;
        }

        DB::table('cases')->insert([
            'id' => $caseId,
            'title' => 'Test Case',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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
