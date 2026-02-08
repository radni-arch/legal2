<?php

namespace Tests\Unit\Services\Graph;

use App\Services\Graph\GraphCitationLinker;
use App\Services\Graph\GraphKeywordLinker;
use App\Services\Graph\GraphSimilarityLinker;
use App\Services\Graph\LawGraphSyncService;
use App\Services\GraphDatabaseService;
use App\Services\TaggingService;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Tests for LawGraphSyncService
 *
 * These tests verify that the extracted law syncing service maintains
 * the exact same behavior as the original GraphRagService implementation.
 *
 * Tests are based on the characterization tests for syncLaw() method.
 */
class LawGraphSyncServiceTest extends TestCase
{
    use UsesTestDatabase;

    protected $graphMock;

    protected $keywordLinkerMock;

    protected $citationLinkerMock;

    protected $similarityLinkerMock;

    protected $taggingMock;

    protected LawGraphSyncService $service;

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
        $this->service = new LawGraphSyncService(
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

        // Rollback any stale transactions to prevent cascade failures
        try {
            $pdo = DB::connection()->getPdo();
            while ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
        } catch (\Exception $e) {
            // Ignore - just ensuring cleanup
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
            \App\Services\Graph\GraphSyncServiceInterface::class,
            $this->service
        );
    }

    /** @test */
    public function it_supports_law_document_type()
    {
        $this->assertTrue($this->service->supportsType('LawDocument'));
    }

    /** @test */
    public function it_does_not_support_other_types()
    {
        $this->assertFalse($this->service->supportsType('CaseDocument'));
        $this->assertFalse($this->service->supportsType('CourtDecisionDocument'));
        $this->assertFalse($this->service->supportsType('TextractDocument'));
    }

    // ========================================
    // Core Sync Functionality Tests
    // ========================================

    /** @test */
    public function it_syncs_law_with_all_properties()
    {
        $lawId = $this->createMinimalLaw([
            'doc_id' => 'doc-123',
            'title' => 'Zakon o kaznenom postupku',
            'law_number' => 'NN 152/08',
            'jurisdiction' => 'Republika Hrvatska',
            'country' => 'Croatia',
            'language' => 'hr',
            'chunk_index' => 2,
            'content_hash' => 'hash-abc',
            'effective_date' => '2008-12-01',
            'promulgation_date' => '2008-11-15',
        ]);

        // Verify exact node properties
        $this->graphMock
            ->shouldReceive('upsertNode')
            ->once()
            ->with('LawDocument', $lawId, Mockery::on(function ($props) {
                return $props['doc_id'] === 'doc-123'
                    && $props['title'] === 'Zakon o kaznenom postupku'
                    && $props['law_number'] === 'NN 152/08'
                    && $props['jurisdiction'] === 'Republika Hrvatska'
                    && $props['country'] === 'Croatia'
                    && $props['language'] === 'hr'
                    && $props['chunk_index'] === 2
                    && $props['content_hash'] === 'hash-abc'
                    && $props['effective_date'] === '2008-12-01'
                    && $props['promulgation_date'] === '2008-11-15';
            }));

        $this->allowJurisdictionNode();
        $this->allowLinking();
        $this->allowTagging();

        $this->service->sync($lawId);
    }

    /** @test */
    public function it_creates_jurisdiction_node_and_relationship()
    {
        $lawId = $this->createMinimalLaw([
            'jurisdiction' => 'Republika Hrvatska',
        ]);

        $this->graphMock
            ->shouldReceive('upsertNode')
            ->with('LawDocument', $lawId, Mockery::any())
            ->once();

        // Verify jurisdiction node creation
        $this->graphMock
            ->shouldReceive('upsertNode')
            ->once()
            ->with('Jurisdiction', 'jurisdiction_Republika Hrvatska', Mockery::on(function ($props) {
                return $props['name'] === 'Republika Hrvatska';
            }));

        // Verify relationship creation
        $this->graphMock
            ->shouldReceive('createRelationship')
            ->once()
            ->with(
                'LawDocument',
                $lawId,
                'BELONGS_TO_JURISDICTION',
                'Jurisdiction',
                'jurisdiction_Republika Hrvatska'
            );

        $this->allowLinking();
        $this->allowTagging();

        $this->service->sync($lawId);
    }

    /** @test */
    public function it_skips_jurisdiction_when_null()
    {
        $lawId = $this->createMinimalLaw([
            'jurisdiction' => null,
        ]);

        $this->graphMock
            ->shouldReceive('upsertNode')
            ->with('LawDocument', $lawId, Mockery::any())
            ->once();

        // Should NOT create jurisdiction node or relationship
        $this->graphMock
            ->shouldNotReceive('upsertNode')
            ->with('Jurisdiction', Mockery::any(), Mockery::any());

        $this->graphMock
            ->shouldNotReceive('createRelationship')
            ->with('LawDocument', $lawId, 'BELONGS_TO_JURISDICTION', Mockery::any(), Mockery::any());

        $this->allowLinking();
        $this->allowTagging();

        $this->service->sync($lawId);
    }

    /** @test */
    public function it_calls_keyword_linker()
    {
        $lawId = $this->createMinimalLaw([
            'content' => 'Zakon o kaznenom postupku sadrži važne odredbe',
        ]);

        $this->graphMock->shouldReceive('upsertNode')->zeroOrMoreTimes();
        $this->graphMock->shouldReceive('createRelationship')->zeroOrMoreTimes();

        // Verify keyword linker is called
        $this->keywordLinkerMock
            ->shouldReceive('link')
            ->once()
            ->with('LawDocument', $lawId, 'Zakon o kaznenom postupku sadrži važne odredbe');

        $this->citationLinkerMock->shouldReceive('link')->once();
        $this->similarityLinkerMock->shouldReceive('link')->once();
        $this->allowTagging();

        $this->service->sync($lawId);
    }

    /** @test */
    public function it_calls_citation_linker()
    {
        $lawId = $this->createMinimalLaw([
            'content' => 'Prema članku 5. Zakona (NN 152/08) i NN 100/2024',
        ]);

        $this->graphMock->shouldReceive('upsertNode')->zeroOrMoreTimes();
        $this->graphMock->shouldReceive('createRelationship')->zeroOrMoreTimes();

        // Verify citation linker is called
        $this->citationLinkerMock
            ->shouldReceive('link')
            ->once()
            ->with('LawDocument', $lawId, 'Prema članku 5. Zakona (NN 152/08) i NN 100/2024');

        $this->keywordLinkerMock->shouldReceive('link')->once();
        $this->similarityLinkerMock->shouldReceive('link')->once();
        $this->allowTagging();

        $this->service->sync($lawId);
    }

    /** @test */
    public function it_calls_similarity_linker_with_embedding()
    {
        $embedding = array_fill(0, 1536, 0.5);
        $embeddingJson = json_encode($embedding);

        $lawId = $this->createMinimalLaw([
            'embedding' => DB::raw("'{$embeddingJson}'::vector"),
        ]);

        $this->graphMock->shouldReceive('upsertNode')->zeroOrMoreTimes();
        $this->graphMock->shouldReceive('createRelationship')->zeroOrMoreTimes();

        // Verify similarity linker is called with embedding
        $this->similarityLinkerMock
            ->shouldReceive('link')
            ->once()
            ->with('LawDocument', $lawId, Mockery::type('string'));

        $this->keywordLinkerMock->shouldReceive('link')->once();
        $this->citationLinkerMock->shouldReceive('link')->once();
        $this->allowTagging();

        $this->service->sync($lawId);
    }

    /** @test */
    public function it_calls_auto_tag_with_merged_metadata()
    {
        $lawId = $this->createMinimalLaw([
            'jurisdiction' => 'HR',
            'law_number' => 'NN 100/2024',
            'content' => 'Test law content',
            'metadata' => json_encode(['category' => 'criminal', 'extra' => 'data']),
        ]);

        $this->graphMock->shouldReceive('upsertNode')->zeroOrMoreTimes();
        $this->graphMock->shouldReceive('createRelationship')->zeroOrMoreTimes();
        $this->allowLinking();

        // Verify autoTag is called with metadata including jurisdiction and law_number
        $this->taggingMock
            ->shouldReceive('autoTag')
            ->once()
            ->with('LawDocument', $lawId, 'Test law content', Mockery::on(function ($metadata) {
                return $metadata['jurisdiction'] === 'HR'
                    && $metadata['law_number'] === 'NN 100/2024'
                    && $metadata['category'] === 'criminal'
                    && $metadata['extra'] === 'data';
            }));

        $this->service->sync($lawId);
    }

    /** @test */
    public function it_handles_null_metadata_gracefully()
    {
        $lawId = $this->createMinimalLaw([
            'metadata' => null,
        ]);

        $this->graphMock->shouldReceive('upsertNode')->zeroOrMoreTimes();
        $this->graphMock->shouldReceive('createRelationship')->zeroOrMoreTimes();
        $this->allowLinking();

        // Should not crash and should pass empty array as metadata
        $this->taggingMock
            ->shouldReceive('autoTag')
            ->once()
            ->with('LawDocument', $lawId, Mockery::any(), Mockery::type('array'));

        $this->service->sync($lawId);
    }

    /** @test */
    public function it_handles_invalid_json_metadata()
    {
        $lawId = $this->createMinimalLaw([
            'metadata' => 'invalid-json{broken',
        ]);

        $this->graphMock->shouldReceive('upsertNode')->zeroOrMoreTimes();
        $this->graphMock->shouldReceive('createRelationship')->zeroOrMoreTimes();
        $this->allowLinking();

        // Should not crash and should pass empty array as metadata (json_decode returns null)
        $this->taggingMock
            ->shouldReceive('autoTag')
            ->once();

        $this->service->sync($lawId);
    }

    /** @test */
    public function it_handles_missing_law_gracefully()
    {
        // Should not throw exception or create any nodes
        $this->graphMock->shouldNotReceive('upsertNode');
        $this->taggingMock->shouldNotReceive('autoTag');
        $this->keywordLinkerMock->shouldNotReceive('link');
        $this->citationLinkerMock->shouldNotReceive('link');
        $this->similarityLinkerMock->shouldNotReceive('link');

        $this->service->sync('non-existent-law-id');

        $this->assertTrue(true);
    }

    /** @test */
    public function it_handles_null_embedding_vector()
    {
        $lawId = $this->createMinimalLaw([
            'embedding' => null,
        ]);

        $this->graphMock->shouldReceive('upsertNode')->zeroOrMoreTimes();
        $this->graphMock->shouldReceive('createRelationship')->zeroOrMoreTimes();

        // Should still call similarity linker (it handles null internally)
        $this->similarityLinkerMock
            ->shouldReceive('link')
            ->once()
            ->with('LawDocument', $lawId, null);

        $this->keywordLinkerMock->shouldReceive('link')->once();
        $this->citationLinkerMock->shouldReceive('link')->once();
        $this->allowTagging();

        $this->service->sync($lawId);
    }

    /** @test */
    public function it_creates_all_required_node_properties()
    {
        $lawId = $this->createMinimalLaw();

        // Verify all required properties are set in node creation
        $this->graphMock
            ->shouldReceive('upsertNode')
            ->once()
            ->with('LawDocument', $lawId, Mockery::on(function ($props) {
                return array_key_exists('doc_id', $props)
                    && array_key_exists('title', $props)
                    && array_key_exists('law_number', $props)
                    && array_key_exists('jurisdiction', $props)
                    && array_key_exists('country', $props)
                    && array_key_exists('language', $props)
                    && array_key_exists('chunk_index', $props)
                    && array_key_exists('content_hash', $props)
                    && array_key_exists('effective_date', $props)
                    && array_key_exists('promulgation_date', $props);
            }));

        $this->allowJurisdictionNode();
        $this->allowLinking();
        $this->allowTagging();

        $this->service->sync($lawId);
    }

    // ========================================
    // Phase 4: Amendment Tracking Properties
    // ========================================

    /** @test */
    public function it_syncs_amendment_tracking_properties_to_graph()
    {
        $lawId = $this->createMinimalLaw([
            'amendments' => json_encode(['NN 123/21', 'NN 45/22']),
            'repeal_date' => '2025-12-31',
            'repealed_by' => 'NN 100/25',
            'parent_law_number' => 'NN 50/10',
            'consolidation_date' => '2024-06-15',
        ]);

        // Verify amendment properties are included in node
        $this->graphMock
            ->shouldReceive('upsertNode')
            ->once()
            ->with('LawDocument', $lawId, Mockery::on(function ($props) {
                return isset($props['amendments'])
                    && is_array($props['amendments'])
                    && in_array('NN 123/21', $props['amendments'])
                    && in_array('NN 45/22', $props['amendments'])
                    && $props['repeal_date'] === '2025-12-31'
                    && $props['repealed_by'] === 'NN 100/25'
                    && $props['parent_law_number'] === 'NN 50/10'
                    && $props['consolidation_date'] === '2024-06-15';
            }));

        $this->allowJurisdictionNode();
        $this->allowLinking();
        $this->allowTagging();

        $this->service->sync($lawId);
    }

    /** @test */
    public function it_defaults_amendment_properties_to_null_when_not_set()
    {
        $lawId = $this->createMinimalLaw([
            // No amendment properties set
        ]);

        // Verify properties default to null/empty
        $this->graphMock
            ->shouldReceive('upsertNode')
            ->once()
            ->with('LawDocument', $lawId, Mockery::on(function ($props) {
                return array_key_exists('amendments', $props)
                    && array_key_exists('repeal_date', $props)
                    && array_key_exists('repealed_by', $props)
                    && array_key_exists('parent_law_number', $props)
                    && array_key_exists('consolidation_date', $props)
                    && ($props['amendments'] === null || $props['amendments'] === [])
                    && $props['repealed_by'] === null
                    && $props['parent_law_number'] === null;
            }));

        $this->allowJurisdictionNode();
        $this->allowLinking();
        $this->allowTagging();

        $this->service->sync($lawId);
    }

    // ========================================
    // Batch Sync Tests
    // ========================================

    /** @test */
    public function it_syncs_multiple_laws_in_batch()
    {
        // Create multiple laws
        $lawIds = [];
        for ($i = 1; $i <= 3; $i++) {
            $lawIds[] = $this->createMinimalLaw([
                'id' => str_pad("batch-law-$i", 26),
            ]);
        }

        $this->graphMock->shouldReceive('upsertNode')->times(3);
        $this->graphMock->shouldReceive('createRelationship')->zeroOrMoreTimes();
        $this->keywordLinkerMock->shouldReceive('link')->times(3);
        $this->citationLinkerMock->shouldReceive('link')->times(3);
        $this->similarityLinkerMock->shouldReceive('link')->times(3);
        $this->taggingMock->shouldReceive('autoTag')->times(3);

        foreach ($lawIds as $lawId) {
            $this->service->sync($lawId);
        }

        $this->assertTrue(true);
    }

    /** @test */
    public function it_handles_partial_batch_failures()
    {
        $lawId1 = $this->createMinimalLaw();
        $lawId2 = 'non-existent';
        $lawId3 = $this->createMinimalLaw();

        $this->graphMock->shouldReceive('upsertNode')->times(2); // Only for existing laws
        $this->graphMock->shouldReceive('createRelationship')->zeroOrMoreTimes();
        $this->keywordLinkerMock->shouldReceive('link')->times(2);
        $this->citationLinkerMock->shouldReceive('link')->times(2);
        $this->similarityLinkerMock->shouldReceive('link')->times(2);
        $this->taggingMock->shouldReceive('autoTag')->times(2);

        // Should not throw exception on missing law
        $this->service->sync($lawId1);
        $this->service->sync($lawId2); // Missing - should be skipped
        $this->service->sync($lawId3);

        $this->assertTrue(true);
    }

    // ========================================
    // Helper Methods for Test Setup
    // ========================================

    /**
     * Create a minimal law with default values
     */
    protected function createMinimalLaw(array $overrides = []): string
    {
        $id = $overrides['id'] ?? str_pad('law-'.uniqid(), 26);
        $embedding = $overrides['embedding'] ?? DB::raw("'".json_encode(array_fill(0, 1536, 0.1))."'::vector");

        $data = array_merge([
            'id' => $id,
            'doc_id' => 'doc-'.uniqid(),
            'title' => 'Test Law',
            'content' => 'Test content',
            'content_hash' => hash('sha256', 'Test content'),
            'chunk_index' => 0,
            'embedding_provider' => 'openai',
            'embedding_model' => 'text-embedding-ada-002',
            'embedding_dimensions' => 1536,
            'embedding' => $embedding,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);

        unset($data['id']); // Remove from data array
        DB::table('laws')->insert(array_merge(['id' => $id], $data));

        return $id;
    }

    /**
     * Allow jurisdiction node creation in mocks
     */
    protected function allowJurisdictionNode(): void
    {
        $this->graphMock
            ->shouldReceive('upsertNode')
            ->with('Jurisdiction', Mockery::type('string'), Mockery::type('array'))
            ->zeroOrMoreTimes();

        $this->graphMock
            ->shouldReceive('createRelationship')
            ->with('LawDocument', Mockery::any(), 'BELONGS_TO_JURISDICTION', 'Jurisdiction', Mockery::any())
            ->zeroOrMoreTimes();
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
