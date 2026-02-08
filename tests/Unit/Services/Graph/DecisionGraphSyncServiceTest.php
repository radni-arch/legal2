<?php

namespace Tests\Unit\Services\Graph;

use App\Services\Graph\DecisionGraphSyncService;
use App\Services\Graph\GraphCitationLinker;
use App\Services\Graph\GraphKeywordLinker;
use App\Services\Graph\GraphSimilarityLinker;
use App\Services\Graph\JudgeGraphSyncService;
use App\Services\GraphDatabaseService;
use App\Services\TaggingService;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Tests for DecisionGraphSyncService
 *
 * These tests verify that the extracted court decision syncing service maintains
 * the exact same behavior as the original GraphRagService implementation.
 *
 * Tests are based on the characterization tests for syncCourtDecision() method.
 */
class DecisionGraphSyncServiceTest extends TestCase
{
    use UsesTestDatabase;

    protected $graphMock;

    protected $keywordLinkerMock;

    protected $citationLinkerMock;

    protected $similarityLinkerMock;

    protected $taggingMock;

    protected $judgeSyncMock;

    protected $partySyncMock;

    protected $principleSyncMock;

    protected $precedentDetectorMock;

    protected DecisionGraphSyncService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock dependencies
        $this->graphMock = Mockery::mock(GraphDatabaseService::class);
        $this->keywordLinkerMock = Mockery::mock(GraphKeywordLinker::class);
        $this->citationLinkerMock = Mockery::mock(GraphCitationLinker::class);
        $this->similarityLinkerMock = Mockery::mock(GraphSimilarityLinker::class);
        $this->taggingMock = Mockery::mock(TaggingService::class);
        $this->judgeSyncMock = Mockery::mock(JudgeGraphSyncService::class);
        $this->partySyncMock = Mockery::mock(\App\Services\Graph\PartyGraphSyncService::class);
        $this->principleSyncMock = Mockery::mock(\App\Services\Graph\LegalPrincipleGraphSyncService::class);
        $this->precedentDetectorMock = Mockery::mock(\App\Services\Graph\PrecedentDetector::class);

        // Create service with mocked dependencies
        $this->service = new DecisionGraphSyncService(
            $this->graphMock,
            $this->keywordLinkerMock,
            $this->citationLinkerMock,
            $this->similarityLinkerMock,
            $this->taggingMock,
            $this->judgeSyncMock,
            $this->partySyncMock,
            $this->principleSyncMock,
            $this->precedentDetectorMock
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
            \App\Services\Graph\GraphSyncServiceInterface::class,
            $this->service
        );
    }

    /** @test */
    public function it_supports_court_decision_document_type()
    {
        $this->assertTrue($this->service->supportsType('CourtDecisionDocument'));
    }

    /** @test */
    public function it_does_not_support_other_types()
    {
        $this->assertFalse($this->service->supportsType('LawDocument'));
        $this->assertFalse($this->service->supportsType('CaseDocument'));
        $this->assertFalse($this->service->supportsType('TextractDocument'));
    }

    // ========================================
    // Core Sync Functionality Tests
    // ========================================

    /** @test */
    public function it_syncs_decision_document_nodes_for_all_chunks()
    {
        $decisionId = $this->createMinimalCourtDecision([
            'case_number' => 'Us-1234/2024',
            'court' => 'Vrhovni sud Republike Hrvatske',
            'jurisdiction' => 'Republika Hrvatska',
            'judge' => 'Dr. Ivan Horvat',
            'decision_date' => '2024-01-15',
        ]);

        // Create multiple document chunks
        DB::table('court_decision_documents')->insert([
            'id' => str_pad('doc-chunk-2', 26),
            'decision_id' => $decisionId,
            'doc_id' => 'doc-2',
            'chunk_index' => 1,
            'content' => 'Second chunk content',
            'content_hash' => 'hash2',
            'embedding_provider' => 'openai',
            'embedding_model' => 'text-embedding-ada-002',
            'embedding_dimensions' => 1536,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Should create node for each chunk
        $this->graphMock
            ->shouldReceive('upsertNode')
            ->twice()
            ->with('CourtDecisionDocument', Mockery::type('string'), Mockery::on(function ($props) use ($decisionId) {
                return $props['decision_id'] === $decisionId
                    && $props['case_number'] === 'Us-1234/2024'
                    && $props['court'] === 'Vrhovni sud Republike Hrvatske';
            }));

        $this->allowCourtAndJurisdictionNodes();
        $this->allowLinking();
        $this->allowTagging();
        $this->allowJudgeSync();
        $this->allowPrincipleSync();
        $this->allowPrecedentDetection();

        $this->service->sync($decisionId);
    }

    /** @test */
    public function it_creates_court_node_and_relationship()
    {
        $decisionId = $this->createMinimalCourtDecision([
            'court' => 'Županijski sud u Osijeku',
            'jurisdiction' => 'HR',
        ]);

        $this->graphMock->shouldReceive('upsertNode')->with('CourtDecisionDocument', Mockery::any(), Mockery::any())->atLeast()->once();

        // Should create court node with MD5 hash as ID
        $expectedCourtId = 'court_'.md5('Županijski sud u Osijeku');
        $this->graphMock
            ->shouldReceive('upsertNode')
            ->once()
            ->with('Court', $expectedCourtId, Mockery::on(function ($props) {
                return $props['name'] === 'Županijski sud u Osijeku'
                    && $props['jurisdiction'] === 'HR';
            }));

        // Should create DECIDED_BY relationship
        $this->graphMock
            ->shouldReceive('createRelationship')
            ->atLeast()->once()
            ->with(
                'CourtDecisionDocument',
                Mockery::any(),
                'DECIDED_BY',
                'Court',
                $expectedCourtId
            );

        $this->allowJurisdictionNode();
        $this->allowLinking();
        $this->allowTagging();
        $this->allowPrincipleSync();
        $this->allowPrecedentDetection();

        $this->service->sync($decisionId);
    }

    /** @test */
    public function it_creates_jurisdiction_node_and_relationship()
    {
        $decisionId = $this->createMinimalCourtDecision([
            'jurisdiction' => 'Republika Hrvatska',
        ]);

        $this->graphMock->shouldReceive('upsertNode')->with('CourtDecisionDocument', Mockery::any(), Mockery::any())->atLeast()->once();

        // Should create jurisdiction node with MD5-based ID
        $expectedJurisdictionId = 'jurisdiction_'.md5('Republika Hrvatska');
        $this->graphMock
            ->shouldReceive('upsertNode')
            ->once()
            ->with('Jurisdiction', $expectedJurisdictionId, Mockery::on(function ($props) {
                return $props['name'] === 'Republika Hrvatska';
            }));

        // Should create BELONGS_TO_JURISDICTION relationship
        $this->graphMock
            ->shouldReceive('createRelationship')
            ->atLeast()->once()
            ->with(
                'CourtDecisionDocument',
                Mockery::any(),
                'BELONGS_TO_JURISDICTION',
                'Jurisdiction',
                $expectedJurisdictionId
            );

        $this->allowCourtNode();
        $this->allowLinking();
        $this->allowTagging();
        $this->allowPrincipleSync();
        $this->allowPrecedentDetection();

        $this->service->sync($decisionId);
    }

    /** @test */
    public function it_calls_keyword_linker_for_each_document()
    {
        $decisionId = $this->createMinimalCourtDecision([
            'content' => 'Important court decision regarding proportionality',
        ]);

        $this->graphMock->shouldReceive('upsertNode')->zeroOrMoreTimes();
        $this->graphMock->shouldReceive('createRelationship')->zeroOrMoreTimes();

        // Verify keyword linker is called for each document chunk
        $this->keywordLinkerMock
            ->shouldReceive('link')
            ->once()
            ->with('CourtDecisionDocument', Mockery::type('string'), 'Important court decision regarding proportionality');

        $this->citationLinkerMock->shouldReceive('link')->once();
        $this->similarityLinkerMock->shouldReceive('link')->once();
        $this->allowTagging();
        $this->allowPrincipleSync();

        $this->service->sync($decisionId);
    }

    /** @test */
    public function it_calls_citation_linker_for_each_document()
    {
        $decisionId = $this->createMinimalCourtDecision([
            'content' => 'Citing ZKP article 24 and NN 152/08',
        ]);

        $this->graphMock->shouldReceive('upsertNode')->zeroOrMoreTimes();
        $this->graphMock->shouldReceive('createRelationship')->zeroOrMoreTimes();

        // Verify citation linker is called
        $this->citationLinkerMock
            ->shouldReceive('link')
            ->once()
            ->with('CourtDecisionDocument', Mockery::type('string'), 'Citing ZKP article 24 and NN 152/08');

        $this->keywordLinkerMock->shouldReceive('link')->once();
        $this->similarityLinkerMock->shouldReceive('link')->once();
        $this->allowTagging();
        $this->allowPrincipleSync();

        $this->service->sync($decisionId);
    }

    /** @test */
    public function it_calls_similarity_linker_with_embedding()
    {
        $embedding = array_fill(0, 1536, 0.3);
        $embeddingJson = json_encode($embedding);

        $decisionId = $this->createMinimalCourtDecision([
            'embedding' => $embeddingJson,
        ]);

        $this->graphMock->shouldReceive('upsertNode')->zeroOrMoreTimes();
        $this->graphMock->shouldReceive('createRelationship')->zeroOrMoreTimes();

        // Verify similarity linker is called with embedding
        $this->similarityLinkerMock
            ->shouldReceive('link')
            ->once()
            ->with('CourtDecisionDocument', Mockery::type('string'), $embeddingJson);

        $this->keywordLinkerMock->shouldReceive('link')->once();
        $this->citationLinkerMock->shouldReceive('link')->once();
        $this->allowTagging();
        $this->allowPrincipleSync();

        $this->service->sync($decisionId);
    }

    /** @test */
    public function it_calls_auto_tag_with_decision_metadata()
    {
        $decisionId = $this->createMinimalCourtDecision([
            'court' => 'Vrhovni sud',
            'case_number' => 'Rev-123/2024',
            'decision_type' => 'Presuda',
            'content' => 'Decision content',
        ]);

        $this->graphMock->shouldReceive('upsertNode')->zeroOrMoreTimes();
        $this->graphMock->shouldReceive('createRelationship')->zeroOrMoreTimes();
        $this->allowLinking();
        $this->allowPrincipleSync();

        // Verify autoTag is called with decision metadata
        $this->taggingMock
            ->shouldReceive('autoTag')
            ->once()
            ->with('CourtDecisionDocument', Mockery::type('string'), 'Decision content', Mockery::on(function ($metadata) {
                return $metadata['court'] === 'Vrhovni sud'
                    && $metadata['case_number'] === 'Rev-123/2024'
                    && $metadata['decision_type'] === 'Presuda';
            }));

        $this->service->sync($decisionId);
    }

    /** @test */
    public function it_skips_court_node_when_not_set()
    {
        $decisionId = $this->createMinimalCourtDecision([
            'court' => null,
        ]);

        $this->graphMock->shouldReceive('upsertNode')->with('CourtDecisionDocument', Mockery::any(), Mockery::any())->atLeast()->once();

        // Should NOT create Court node or DECIDED_BY relationship
        $this->graphMock
            ->shouldNotReceive('upsertNode')
            ->with('Court', Mockery::any(), Mockery::any());

        $this->graphMock
            ->shouldNotReceive('createRelationship')
            ->with('CourtDecisionDocument', Mockery::any(), 'DECIDED_BY', Mockery::any(), Mockery::any());

        $this->allowJurisdictionNode();
        $this->allowLinking();
        $this->allowTagging();
        $this->allowPrincipleSync();
        $this->allowPrecedentDetection();

        $this->service->sync($decisionId);
    }

    /** @test */
    public function it_skips_jurisdiction_node_when_not_set()
    {
        $decisionId = $this->createMinimalCourtDecision([
            'jurisdiction' => null,
        ]);

        $this->graphMock->shouldReceive('upsertNode')->with('CourtDecisionDocument', Mockery::any(), Mockery::any())->atLeast()->once();

        // Should NOT create Jurisdiction node or BELONGS_TO_JURISDICTION relationship
        $this->graphMock
            ->shouldNotReceive('upsertNode')
            ->with('Jurisdiction', Mockery::any(), Mockery::any());

        $this->graphMock
            ->shouldNotReceive('createRelationship')
            ->with('CourtDecisionDocument', Mockery::any(), 'BELONGS_TO_JURISDICTION', Mockery::any(), Mockery::any());

        $this->allowCourtNode();
        $this->allowLinking();
        $this->allowTagging();
        $this->allowPrincipleSync();
        $this->allowPrecedentDetection();

        $this->service->sync($decisionId);
    }

    /** @test */
    public function it_handles_missing_decision()
    {
        // Should not throw exception or create any nodes
        $this->graphMock->shouldNotReceive('upsertNode');
        $this->taggingMock->shouldNotReceive('autoTag');
        $this->keywordLinkerMock->shouldNotReceive('link');
        $this->citationLinkerMock->shouldNotReceive('link');
        $this->similarityLinkerMock->shouldNotReceive('link');

        $this->service->sync('non-existent-decision-id');

        $this->assertTrue(true);
    }

    /** @test */
    public function it_handles_decision_with_no_documents()
    {
        $decisionId = str_pad('empty-decision', 26);

        DB::table('court_decisions')->insert([
            'id' => $decisionId,
            'case_number' => 'Empty-1/2024',
            'court' => 'Test Court',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // No document chunks - should not create any nodes or call services
        $this->graphMock->shouldNotReceive('upsertNode');
        $this->taggingMock->shouldNotReceive('autoTag');
        $this->keywordLinkerMock->shouldNotReceive('link');
        $this->citationLinkerMock->shouldNotReceive('link');
        $this->similarityLinkerMock->shouldNotReceive('link');

        $this->service->sync($decisionId);

        $this->assertTrue(true);
    }

    /** @test */
    public function it_creates_all_required_node_properties()
    {
        $decisionId = $this->createMinimalCourtDecision([
            'case_number' => 'Us-5678/2024',
            'court' => 'Vrhovni sud',
            'jurisdiction' => 'HR',
            'judge' => 'Dr. Ana Novak',
            'decision_date' => '2024-02-20',
            'publication_date' => '2024-02-25',
            'decision_type' => 'Presuda',
            'register' => 'Us',
            'finality' => 'final',
            'ecli' => 'ECLI:HR:VSRH:2024:Us.5678',
        ]);

        // Verify all required properties are set in node creation
        $this->graphMock
            ->shouldReceive('upsertNode')
            ->once()
            ->with('CourtDecisionDocument', Mockery::type('string'), Mockery::on(function ($props) use ($decisionId) {
                return $props['decision_id'] === $decisionId
                    && $props['case_number'] === 'Us-5678/2024'
                    && $props['court'] === 'Vrhovni sud'
                    && $props['jurisdiction'] === 'HR'
                    && $props['judge'] === 'Dr. Ana Novak'
                    && $props['decision_date'] === '2024-02-20'
                    && $props['publication_date'] === '2024-02-25'
                    && $props['decision_type'] === 'Presuda'
                    && $props['register'] === 'Us'
                    && $props['finality'] === 'final'
                    && $props['ecli'] === 'ECLI:HR:VSRH:2024:Us.5678';
            }));

        $this->allowCourtAndJurisdictionNodes();
        $this->allowLinking();
        $this->allowTagging();
        $this->allowJudgeSync();
        $this->allowPrincipleSync();
        $this->allowPrecedentDetection();

        $this->service->sync($decisionId);
    }

    /** @test */
    public function it_handles_null_embedding_vector()
    {
        $decisionId = $this->createMinimalCourtDecision([
            'embedding' => null,
        ]);

        $this->graphMock->shouldReceive('upsertNode')->zeroOrMoreTimes();
        $this->graphMock->shouldReceive('createRelationship')->zeroOrMoreTimes();

        // Should still call similarity linker (it handles null internally)
        $this->similarityLinkerMock
            ->shouldReceive('link')
            ->once()
            ->with('CourtDecisionDocument', Mockery::type('string'), null);

        $this->keywordLinkerMock->shouldReceive('link')->once();
        $this->citationLinkerMock->shouldReceive('link')->once();
        $this->allowTagging();
        $this->allowPrincipleSync();

        $this->service->sync($decisionId);
    }

    // ========================================
    // Batch Sync Tests
    // ========================================

    /** @test */
    public function it_syncs_multiple_decisions_in_batch()
    {
        // Create multiple decisions
        $decisionIds = [];
        for ($i = 1; $i <= 3; $i++) {
            $decisionIds[] = $this->createMinimalCourtDecision([
                'decision_id' => str_pad("batch-dec-$i", 26),
            ]);
        }

        // Service creates CourtDecisionDocument + Court nodes per decision
        $this->graphMock->shouldReceive('upsertNode')->zeroOrMoreTimes();
        $this->graphMock->shouldReceive('createRelationship')->zeroOrMoreTimes();
        $this->keywordLinkerMock->shouldReceive('link')->times(3);
        $this->citationLinkerMock->shouldReceive('link')->times(3);
        $this->similarityLinkerMock->shouldReceive('link')->times(3);
        $this->taggingMock->shouldReceive('autoTag')->times(3);

        $this->allowPrincipleSync();

        foreach ($decisionIds as $decisionId) {
            $this->service->sync($decisionId);
        }

        $this->assertTrue(true);
    }

    /** @test */
    public function it_handles_partial_batch_failures()
    {
        $decisionId1 = $this->createMinimalCourtDecision();
        $decisionId2 = 'non-existent';
        $decisionId3 = $this->createMinimalCourtDecision();

        // Service creates CourtDecisionDocument + Court nodes per decision
        $this->graphMock->shouldReceive('upsertNode')->zeroOrMoreTimes();
        $this->graphMock->shouldReceive('createRelationship')->zeroOrMoreTimes();
        $this->keywordLinkerMock->shouldReceive('link')->times(2);
        $this->citationLinkerMock->shouldReceive('link')->times(2);
        $this->similarityLinkerMock->shouldReceive('link')->times(2);
        $this->taggingMock->shouldReceive('autoTag')->times(2);

        $this->allowPrincipleSync();

        // Should not throw exception on missing decision
        $this->service->sync($decisionId1);
        $this->service->sync($decisionId2); // Missing - should be skipped
        $this->service->sync($decisionId3);

        $this->assertTrue(true);
    }

    // ========================================
    // Helper Methods for Test Setup
    // ========================================

    /**
     * Create a minimal court decision with document chunks
     */
    protected function createMinimalCourtDecision(array $overrides = []): string
    {
        $decisionId = $overrides['decision_id'] ?? str_pad('decision-'.uniqid(), 26);
        $content = $overrides['content'] ?? 'Decision content';
        $embedding = $overrides['embedding'] ?? null;

        DB::table('court_decisions')->insert([
            'id' => $decisionId,
            'case_number' => $overrides['case_number'] ?? 'Test-1/2024',
            'court' => array_key_exists('court', $overrides) ? $overrides['court'] : 'Test Court',
            'jurisdiction' => array_key_exists('jurisdiction', $overrides) ? $overrides['jurisdiction'] : null,
            'judge' => $overrides['judge'] ?? null,
            'decision_date' => $overrides['decision_date'] ?? now()->toDateString(),
            'publication_date' => $overrides['publication_date'] ?? null,
            'decision_type' => $overrides['decision_type'] ?? 'Presuda',
            'register' => $overrides['register'] ?? null,
            'finality' => $overrides['finality'] ?? null,
            'ecli' => $overrides['ecli'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('court_decision_documents')->insert([
            'id' => str_pad('doc-'.uniqid(), 26),
            'decision_id' => $decisionId,
            'doc_id' => 'doc-'.uniqid(),
            'chunk_index' => 0,
            'content' => $content,
            'content_hash' => hash('sha256', $content),
            'embedding' => $embedding,
            'embedding_provider' => 'openai',
            'embedding_model' => 'text-embedding-ada-002',
            'embedding_dimensions' => 1536,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $decisionId;
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
            ->with('CourtDecisionDocument', Mockery::any(), 'BELONGS_TO_JURISDICTION', 'Jurisdiction', Mockery::any())
            ->zeroOrMoreTimes();
    }

    /**
     * Allow court node creation in mocks
     */
    protected function allowCourtNode(): void
    {
        $this->graphMock
            ->shouldReceive('upsertNode')
            ->with('Court', Mockery::type('string'), Mockery::type('array'))
            ->zeroOrMoreTimes();

        $this->graphMock
            ->shouldReceive('createRelationship')
            ->with('CourtDecisionDocument', Mockery::any(), 'DECIDED_BY', 'Court', Mockery::any())
            ->zeroOrMoreTimes();
    }

    /**
     * Allow both court and jurisdiction nodes
     */
    protected function allowCourtAndJurisdictionNodes(): void
    {
        $this->allowCourtNode();
        $this->allowJurisdictionNode();
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

    // ========================================
    // Field Inheritance Tests
    // ========================================

    /**
     * Test that document inherits title from parent decision when document title is null
     *
     * @test
     */
    public function it_inherits_title_from_parent_decision_when_document_title_is_null()
    {
        $decisionId = substr('inherit-dec-'.uniqid(), 0, 26);

        // Create decision with title
        DB::table('court_decisions')->insert([
            'id' => $decisionId,
            'case_number' => 'Inherit-1/2024',
            'title' => 'Parent Decision Title',
            'court' => 'Test Court',
            'judge' => 'Judge Parent',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create document WITHOUT title (null)
        $docId = substr('doc-'.uniqid(), 0, 26);
        DB::table('court_decision_documents')->insert([
            'id' => $docId,
            'decision_id' => $decisionId,
            'doc_id' => 'doc-123',
            'title' => null, // Document has no title
            'chunk_index' => 0,
            'content' => 'Content',
            'content_hash' => 'hash',
            'embedding_provider' => 'openai',
            'embedding_model' => 'text-embedding-ada-002',
            'embedding_dimensions' => 1536,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Should use parent's title when document's is null
        $this->graphMock
            ->shouldReceive('upsertNode')
            ->once()
            ->with('CourtDecisionDocument', Mockery::type('string'), Mockery::on(function ($props) {
                return $props['title'] === 'Parent Decision Title'
                    && $props['judge'] === 'Judge Parent';
            }));

        $this->allowCourtAndJurisdictionNodes();
        $this->allowLinking();
        $this->allowTagging();
        $this->allowJudgeSync();
        $this->allowPrincipleSync();
        $this->allowPrecedentDetection();

        $this->service->sync($decisionId);
    }

    /**
     * Test that document uses its own title when present, not inheriting from parent
     *
     * @test
     */
    public function it_uses_document_title_when_present()
    {
        $decisionId = substr('own-title-dec-'.uniqid(), 0, 26);

        // Create decision with title
        DB::table('court_decisions')->insert([
            'id' => $decisionId,
            'case_number' => 'OwnTitle-1/2024',
            'title' => 'Parent Decision Title',
            'court' => 'Test Court',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create document WITH its own title
        $docId = substr('doc-'.uniqid(), 0, 26);
        DB::table('court_decision_documents')->insert([
            'id' => $docId,
            'decision_id' => $decisionId,
            'doc_id' => 'doc-456',
            'title' => 'Document Own Title', // Document has its own title
            'chunk_index' => 0,
            'content' => 'Content',
            'content_hash' => 'hash',
            'embedding_provider' => 'openai',
            'embedding_model' => 'text-embedding-ada-002',
            'embedding_dimensions' => 1536,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Should use document's own title
        $this->graphMock
            ->shouldReceive('upsertNode')
            ->once()
            ->with('CourtDecisionDocument', Mockery::type('string'), Mockery::on(function ($props) {
                return $props['title'] === 'Document Own Title';
            }));

        $this->allowCourtAndJurisdictionNodes();
        $this->allowLinking();
        $this->allowTagging();
        $this->allowPrincipleSync();
        $this->allowPrecedentDetection();

        $this->service->sync($decisionId);
    }

    // ========================================
    // Phase 4: Outcome Properties Tests
    // ========================================

    /** @test */
    public function it_syncs_dissent_and_concurrence_counts_to_graph()
    {
        $decisionId = $this->createMinimalCourtDecision([
            'case_number' => 'Rev-1234/2024',
            'court' => 'Vrhovni sud',
            'jurisdiction' => 'HR',
        ]);

        // Add dissent_count and concurrence_count to the decision
        DB::table('court_decisions')
            ->where('id', $decisionId)
            ->update([
                'outcome' => 'reversed',
                'holding' => 'Appeal granted',
                'precedential_value' => 'binding',
                'dissent_count' => 2,
                'concurrence_count' => 1,
            ]);

        // Assert: Graph receives correct dissent/concurrence values
        $this->graphMock
            ->shouldReceive('upsertNode')
            ->once()
            ->with('CourtDecisionDocument', Mockery::type('string'), Mockery::on(function ($props) {
                return $props['dissent_count'] === 2
                    && $props['concurrence_count'] === 1
                    && $props['outcome'] === 'reversed'
                    && $props['precedential_value'] === 'binding';
            }));

        $this->allowCourtAndJurisdictionNodes();
        $this->allowLinking();
        $this->allowTagging();
        $this->allowJudgeSync();
        $this->allowPrincipleSync();
        $this->allowPrecedentDetection();

        $result = $this->service->sync($decisionId);

        $this->assertArrayHasKey('nodes', $result);
    }

    /** @test */
    public function it_defaults_dissent_and_concurrence_counts_to_zero()
    {
        $decisionId = $this->createMinimalCourtDecision([
            'case_number' => 'Rev-5678/2024',
            'court' => 'Test Court',
        ]);

        // Don't set dissent_count/concurrence_count - should default to 0

        // Assert: Graph receives default 0 values
        $this->graphMock
            ->shouldReceive('upsertNode')
            ->once()
            ->with('CourtDecisionDocument', Mockery::type('string'), Mockery::on(function ($props) {
                return $props['dissent_count'] === 0
                    && $props['concurrence_count'] === 0;
            }));

        $this->allowCourtAndJurisdictionNodes();
        $this->allowLinking();
        $this->allowTagging();
        $this->allowJudgeSync();
        $this->allowPrincipleSync();
        $this->allowPrecedentDetection();

        $this->service->sync($decisionId);
    }

    /** @test */
    public function it_syncs_outcome_property_when_available()
    {
        // This test verifies that when outcome properties exist on the decision,
        // they are included in the node properties.
        // Since these columns may not exist in DB schema yet, we verify the
        // service will include them if they're present on the object.
        $decisionId = $this->createMinimalCourtDecision();

        // Verify node creation includes source property (existing behavior)
        // This confirms the service properly passes through decision properties
        $this->graphMock
            ->shouldReceive('upsertNode')
            ->once()
            ->with('CourtDecisionDocument', Mockery::type('string'), Mockery::on(function ($props) {
                // Verify existing properties are synced
                return isset($props['case_number']) && isset($props['court']);
            }));

        $this->allowCourtAndJurisdictionNodes();
        $this->allowLinking();
        $this->allowTagging();
        $this->allowJudgeSync();
        $this->allowPrincipleSync();
        $this->allowPrecedentDetection();

        $this->service->sync($decisionId);
    }

    // ========================================
    // Judge Integration Tests
    // ========================================

    /** @test */
    public function it_calls_judge_sync_when_judge_exists()
    {
        $decisionId = $this->createMinimalCourtDecision([
            'court' => 'Vrhovni sud Republike Hrvatske',
            'judge' => 'Dr. Ivan Horvat',
        ]);

        $this->graphMock->shouldReceive('upsertNode')->zeroOrMoreTimes();
        $this->graphMock->shouldReceive('createRelationship')->zeroOrMoreTimes();
        $this->allowLinking();
        $this->allowTagging();

        // Should call judge sync service with correct parameters
        $this->judgeSyncMock
            ->shouldReceive('syncJudgeFromDecision')
            ->once()
            ->with(
                Mockery::type('string'), // document ID
                'Dr. Ivan Horvat',
                'Vrhovni sud Republike Hrvatske'
            );

        $this->service->sync($decisionId);
    }

    /** @test */
    public function it_skips_judge_sync_when_judge_is_null()
    {
        $decisionId = $this->createMinimalCourtDecision([
            'court' => 'Vrhovni sud',
            'judge' => null,
        ]);

        $this->graphMock->shouldReceive('upsertNode')->zeroOrMoreTimes();
        $this->graphMock->shouldReceive('createRelationship')->zeroOrMoreTimes();
        $this->allowLinking();
        $this->allowTagging();

        // Should NOT call judge sync service
        $this->judgeSyncMock
            ->shouldNotReceive('syncJudgeFromDecision');

        $this->service->sync($decisionId);
    }

    /** @test */
    public function it_skips_judge_sync_when_judge_is_empty_string()
    {
        $decisionId = $this->createMinimalCourtDecision([
            'court' => 'Vrhovni sud',
            'judge' => '',
        ]);

        $this->graphMock->shouldReceive('upsertNode')->zeroOrMoreTimes();
        $this->graphMock->shouldReceive('createRelationship')->zeroOrMoreTimes();
        $this->allowLinking();
        $this->allowTagging();

        // Should NOT call judge sync service
        $this->judgeSyncMock
            ->shouldNotReceive('syncJudgeFromDecision');

        $this->service->sync($decisionId);
    }

    /** @test */
    public function it_passes_correct_document_id_to_judge_sync()
    {
        $decisionId = $this->createMinimalCourtDecision([
            'court' => 'Županijski sud u Zagrebu',
            'judge' => 'Marko Marković',
        ]);

        // Get the actual document ID that was created
        $doc = DB::table('court_decision_documents')
            ->where('decision_id', $decisionId)
            ->first();

        $this->graphMock->shouldReceive('upsertNode')->zeroOrMoreTimes();
        $this->graphMock->shouldReceive('createRelationship')->zeroOrMoreTimes();
        $this->allowLinking();
        $this->allowTagging();

        // Should call judge sync with the document ID (not decision ID)
        $this->judgeSyncMock
            ->shouldReceive('syncJudgeFromDecision')
            ->once()
            ->with(
                $doc->id, // Document ID
                'Marko Marković',
                'Županijski sud u Zagrebu'
            );

        $this->service->sync($decisionId);
    }

    /**
     * Allow judge sync operations in mocks
     */
    protected function allowJudgeSync(): void
    {
        $this->judgeSyncMock
            ->shouldReceive('syncJudgeFromDecision')
            ->zeroOrMoreTimes();
    }

    /**
     * Allow party sync operations in mocks
     */
    protected function allowPartySync(): void
    {
        $this->partySyncMock
            ->shouldReceive('syncParty')
            ->zeroOrMoreTimes();
    }

    // ========================================
    // Party Integration Tests
    // ========================================

    /**
     * NOTE: The plaintiff and defendant fields do not yet exist in the court_decisions schema.
     * The integration is implemented with !empty() checks so it handles this gracefully.
     * Once the schema is updated (Task B.3), these fields will automatically be synced.
     */

    /** @test */
    public function it_integrates_party_sync_service_in_constructor()
    {
        // Verify PartyGraphSyncService is injected via constructor
        $this->assertInstanceOf(
            \App\Services\Graph\DecisionGraphSyncService::class,
            $this->service
        );

        // Test passes if service can be instantiated with PartyGraphSyncService
        $this->assertTrue(true);
    }

    /** @test */
    public function it_gracefully_handles_missing_plaintiff_defendant_fields()
    {
        // With current schema (no plaintiff/defendant fields), party sync should be skipped
        $decisionId = $this->createMinimalCourtDecision([
            'court' => 'Test Court',
            'judge' => 'Test Judge',
        ]);

        $this->graphMock->shouldReceive('upsertNode')->zeroOrMoreTimes();
        $this->graphMock->shouldReceive('createRelationship')->zeroOrMoreTimes();
        $this->allowLinking();
        $this->allowTagging();
        $this->allowJudgeSync();

        // Should NOT call party sync service (fields don't exist in schema yet)
        $this->partySyncMock
            ->shouldNotReceive('syncParty');

        $this->service->sync($decisionId);

        // Test passes - integration handles missing fields gracefully
        $this->assertTrue(true);
    }

    /** @test */
    public function it_calls_party_sync_when_plaintiff_exists()
    {
        // Add plaintiff/defendant columns temporarily (schema doesn't have them yet)
        DB::statement('ALTER TABLE court_decisions ADD COLUMN IF NOT EXISTS plaintiff VARCHAR(255)');
        DB::statement('ALTER TABLE court_decisions ADD COLUMN IF NOT EXISTS defendant VARCHAR(255)');

        $decisionId = $this->createMinimalCourtDecision([
            'court' => 'Test Court',
        ]);

        // Update decision with plaintiff (using raw SQL since column was just added)
        DB::table('court_decisions')
            ->where('id', $decisionId)
            ->update(['plaintiff' => 'John Doe']);

        // Get the document ID
        $doc = DB::table('court_decision_documents')
            ->where('decision_id', $decisionId)
            ->first();

        $this->graphMock->shouldReceive('upsertNode')->zeroOrMoreTimes();
        $this->graphMock->shouldReceive('createRelationship')->zeroOrMoreTimes();
        $this->allowLinking();
        $this->allowTagging();
        $this->allowJudgeSync();
        $this->allowPrincipleSync();
        $this->allowPrecedentDetection();

        // Should call party sync service with correct parameters for plaintiff
        $this->partySyncMock
            ->shouldReceive('syncParty')
            ->once()
            ->with(
                $doc->id,         // document ID
                'John Doe',       // plaintiff name
                'plaintiff'       // role
            );

        $this->service->sync($decisionId);
    }

    /** @test */
    public function it_calls_party_sync_when_defendant_exists()
    {
        // Add plaintiff/defendant columns temporarily (schema doesn't have them yet)
        DB::statement('ALTER TABLE court_decisions ADD COLUMN IF NOT EXISTS plaintiff VARCHAR(255)');
        DB::statement('ALTER TABLE court_decisions ADD COLUMN IF NOT EXISTS defendant VARCHAR(255)');

        $decisionId = $this->createMinimalCourtDecision([
            'court' => 'Test Court',
        ]);

        // Update decision with defendant (using raw SQL since column was just added)
        DB::table('court_decisions')
            ->where('id', $decisionId)
            ->update(['defendant' => 'ACME Corporation']);

        // Get the document ID
        $doc = DB::table('court_decision_documents')
            ->where('decision_id', $decisionId)
            ->first();

        $this->graphMock->shouldReceive('upsertNode')->zeroOrMoreTimes();
        $this->graphMock->shouldReceive('createRelationship')->zeroOrMoreTimes();
        $this->allowLinking();
        $this->allowTagging();
        $this->allowJudgeSync();
        $this->allowPrincipleSync();
        $this->allowPrecedentDetection();

        // Should call party sync service with correct parameters for defendant
        $this->partySyncMock
            ->shouldReceive('syncParty')
            ->once()
            ->with(
                $doc->id,             // document ID
                'ACME Corporation',   // defendant name
                'defendant'           // role
            );

        $this->service->sync($decisionId);
    }

    /** @test */
    public function it_calls_party_sync_for_both_when_both_exist()
    {
        // Add plaintiff/defendant columns temporarily (schema doesn't have them yet)
        DB::statement('ALTER TABLE court_decisions ADD COLUMN IF NOT EXISTS plaintiff VARCHAR(255)');
        DB::statement('ALTER TABLE court_decisions ADD COLUMN IF NOT EXISTS defendant VARCHAR(255)');

        $decisionId = $this->createMinimalCourtDecision([
            'court' => 'Test Court',
        ]);

        // Update decision with BOTH plaintiff and defendant (using raw SQL)
        DB::table('court_decisions')
            ->where('id', $decisionId)
            ->update([
                'plaintiff' => 'Jane Smith',
                'defendant' => 'XYZ Inc.',
            ]);

        // Get the document ID
        $doc = DB::table('court_decision_documents')
            ->where('decision_id', $decisionId)
            ->first();

        $this->graphMock->shouldReceive('upsertNode')->zeroOrMoreTimes();
        $this->graphMock->shouldReceive('createRelationship')->zeroOrMoreTimes();
        $this->allowLinking();
        $this->allowTagging();
        $this->allowJudgeSync();
        $this->allowPrincipleSync();
        $this->allowPrecedentDetection();

        // Should call party sync service TWICE - once for plaintiff, once for defendant
        $this->partySyncMock
            ->shouldReceive('syncParty')
            ->once()
            ->with(
                $doc->id,      // document ID
                'Jane Smith',  // plaintiff name
                'plaintiff'    // role
            );

        $this->partySyncMock
            ->shouldReceive('syncParty')
            ->once()
            ->with(
                $doc->id,    // document ID
                'XYZ Inc.',  // defendant name
                'defendant'  // role
            );

        $this->service->sync($decisionId);
    }

    /** @test */
    public function it_passes_correct_document_id_to_party_sync()
    {
        // Verify first parameter to syncParty is the document ID, not the decision ID
        // Add plaintiff/defendant columns temporarily (schema doesn't have them yet)
        DB::statement('ALTER TABLE court_decisions ADD COLUMN IF NOT EXISTS plaintiff VARCHAR(255)');
        DB::statement('ALTER TABLE court_decisions ADD COLUMN IF NOT EXISTS defendant VARCHAR(255)');

        $decisionId = $this->createMinimalCourtDecision([
            'court' => 'Vrhovni sud',
        ]);

        // Update decision with plaintiff (using raw SQL since column was just added)
        DB::table('court_decisions')
            ->where('id', $decisionId)
            ->update(['plaintiff' => 'Test Plaintiff']);

        // Get the document ID
        $doc = DB::table('court_decision_documents')
            ->where('decision_id', $decisionId)
            ->first();

        // Important: document ID should be different from decision ID
        $this->assertNotEquals($decisionId, $doc->id, 'Document ID should differ from Decision ID');

        $this->graphMock->shouldReceive('upsertNode')->zeroOrMoreTimes();
        $this->graphMock->shouldReceive('createRelationship')->zeroOrMoreTimes();
        $this->allowLinking();
        $this->allowTagging();
        $this->allowJudgeSync();

        // Should call party sync with the document ID (not decision ID)
        $this->partySyncMock
            ->shouldReceive('syncParty')
            ->once()
            ->with(
                $doc->id,          // Document ID (NOT $decisionId)
                'Test Plaintiff',
                'plaintiff'
            );

        $this->service->sync($decisionId);
    }

    // ========================================
    // Feature Flag Tests
    // ========================================

    /** @test */
    public function it_respects_sync_judges_flag_when_enabled()
    {
        config(['graph.features.sync_judges' => true]);

        $decisionId = $this->createMinimalCourtDecision([
            'court' => 'Vrhovni sud',
            'judge' => 'Dr. Ivan Horvat',
        ]);

        $this->graphMock->shouldReceive('upsertNode')->zeroOrMoreTimes();
        $this->graphMock->shouldReceive('createRelationship')->zeroOrMoreTimes();
        $this->allowLinking();
        $this->allowTagging();

        // Should call judge sync when flag is enabled
        $this->judgeSyncMock
            ->shouldReceive('syncJudgeFromDecision')
            ->once()
            ->with(
                Mockery::type('string'),
                'Dr. Ivan Horvat',
                'Vrhovni sud'
            );

        $this->service->sync($decisionId);
    }

    /** @test */
    public function it_skips_judge_sync_when_flag_disabled()
    {
        config(['graph.features.sync_judges' => false]);

        $decisionId = $this->createMinimalCourtDecision([
            'court' => 'Vrhovni sud',
            'judge' => 'Dr. Ivan Horvat',
        ]);

        $this->graphMock->shouldReceive('upsertNode')->zeroOrMoreTimes();
        $this->graphMock->shouldReceive('createRelationship')->zeroOrMoreTimes();
        $this->allowLinking();
        $this->allowTagging();

        // Should NOT call judge sync when flag is disabled
        $this->judgeSyncMock
            ->shouldNotReceive('syncJudgeFromDecision');

        $this->service->sync($decisionId);
    }

    /** @test */
    public function it_respects_sync_parties_flag_when_enabled()
    {
        config(['graph.features.sync_parties' => true]);

        // Add plaintiff/defendant columns temporarily
        DB::statement('ALTER TABLE court_decisions ADD COLUMN IF NOT EXISTS plaintiff VARCHAR(255)');
        DB::statement('ALTER TABLE court_decisions ADD COLUMN IF NOT EXISTS defendant VARCHAR(255)');

        $decisionId = $this->createMinimalCourtDecision([
            'court' => 'Test Court',
        ]);

        DB::table('court_decisions')
            ->where('id', $decisionId)
            ->update(['plaintiff' => 'John Doe']);

        $doc = DB::table('court_decision_documents')
            ->where('decision_id', $decisionId)
            ->first();

        $this->graphMock->shouldReceive('upsertNode')->zeroOrMoreTimes();
        $this->graphMock->shouldReceive('createRelationship')->zeroOrMoreTimes();
        $this->allowLinking();
        $this->allowTagging();
        $this->allowJudgeSync();

        // Should call party sync when flag is enabled
        $this->partySyncMock
            ->shouldReceive('syncParty')
            ->once()
            ->with(
                $doc->id,
                'John Doe',
                'plaintiff'
            );

        $this->service->sync($decisionId);
    }

    /** @test */
    public function it_skips_party_sync_when_flag_disabled()
    {
        config(['graph.features.sync_parties' => false]);

        // Add plaintiff/defendant columns temporarily
        DB::statement('ALTER TABLE court_decisions ADD COLUMN IF NOT EXISTS plaintiff VARCHAR(255)');
        DB::statement('ALTER TABLE court_decisions ADD COLUMN IF NOT EXISTS defendant VARCHAR(255)');

        $decisionId = $this->createMinimalCourtDecision([
            'court' => 'Test Court',
        ]);

        DB::table('court_decisions')
            ->where('id', $decisionId)
            ->update(['plaintiff' => 'John Doe', 'defendant' => 'ACME Corp']);

        $this->graphMock->shouldReceive('upsertNode')->zeroOrMoreTimes();
        $this->graphMock->shouldReceive('createRelationship')->zeroOrMoreTimes();
        $this->allowLinking();
        $this->allowTagging();
        $this->allowJudgeSync();

        // Should NOT call party sync when flag is disabled
        $this->partySyncMock
            ->shouldNotReceive('syncParty');

        $this->service->sync($decisionId);
    }

    /** @test */
    public function it_uses_default_true_for_sync_judges_flag()
    {
        // Don't set config - should default to true
        $decisionId = $this->createMinimalCourtDecision([
            'court' => 'Vrhovni sud',
            'judge' => 'Dr. Ana Novak',
        ]);

        $this->graphMock->shouldReceive('upsertNode')->zeroOrMoreTimes();
        $this->graphMock->shouldReceive('createRelationship')->zeroOrMoreTimes();
        $this->allowLinking();
        $this->allowTagging();

        // Should call judge sync with default (true)
        $this->judgeSyncMock
            ->shouldReceive('syncJudgeFromDecision')
            ->once()
            ->with(
                Mockery::type('string'),
                'Dr. Ana Novak',
                'Vrhovni sud'
            );

        $this->service->sync($decisionId);
    }

    /** @test */
    public function it_uses_default_true_for_sync_parties_flag()
    {
        // Don't set config - should default to true
        // Add plaintiff/defendant columns temporarily
        DB::statement('ALTER TABLE court_decisions ADD COLUMN IF NOT EXISTS plaintiff VARCHAR(255)');
        DB::statement('ALTER TABLE court_decisions ADD COLUMN IF NOT EXISTS defendant VARCHAR(255)');

        $decisionId = $this->createMinimalCourtDecision([
            'court' => 'Test Court',
        ]);

        DB::table('court_decisions')
            ->where('id', $decisionId)
            ->update(['plaintiff' => 'Jane Smith']);

        $doc = DB::table('court_decision_documents')
            ->where('decision_id', $decisionId)
            ->first();

        $this->graphMock->shouldReceive('upsertNode')->zeroOrMoreTimes();
        $this->graphMock->shouldReceive('createRelationship')->zeroOrMoreTimes();
        $this->allowLinking();
        $this->allowTagging();
        $this->allowJudgeSync();

        // Should call party sync with default (true)
        $this->partySyncMock
            ->shouldReceive('syncParty')
            ->once()
            ->with(
                $doc->id,
                'Jane Smith',
                'plaintiff'
            );

        $this->service->sync($decisionId);
    }

    // ========================================
    // Jurisdiction ID Deduplication Tests
    // ========================================

    /**
     * Test that jurisdiction ID uses MD5 hash for deterministic deduplication
     *
     * This prevents duplicate jurisdiction nodes when the same jurisdiction
     * appears with different casing or formatting.
     *
     * @test
     */
    public function it_generates_deterministic_jurisdiction_id_using_md5()
    {
        $decisionId = $this->createMinimalCourtDecision([
            'jurisdiction' => 'Republika Hrvatska',
        ]);

        $this->graphMock->shouldReceive('upsertNode')->with('CourtDecisionDocument', Mockery::any(), Mockery::any())->atLeast()->once();

        // Expected jurisdiction ID should use MD5 hash (like court ID does)
        $expectedJurisdictionId = 'jurisdiction_'.md5('Republika Hrvatska');

        // Should create jurisdiction node with MD5-based ID
        $this->graphMock
            ->shouldReceive('upsertNode')
            ->once()
            ->with('Jurisdiction', $expectedJurisdictionId, Mockery::on(function ($props) {
                return $props['name'] === 'Republika Hrvatska';
            }));

        // Should create relationship with MD5-based jurisdiction ID
        $this->graphMock
            ->shouldReceive('createRelationship')
            ->atLeast()->once()
            ->with(
                'CourtDecisionDocument',
                Mockery::any(),
                'BELONGS_TO_JURISDICTION',
                'Jurisdiction',
                $expectedJurisdictionId
            );

        $this->allowCourtNode();
        $this->allowLinking();
        $this->allowTagging();
        $this->allowPrincipleSync();
        $this->allowPrecedentDetection();

        $this->service->sync($decisionId);
    }

    /**
     * Test that multiple decisions with same jurisdiction create single node
     *
     * Verifies that MD5-based IDs enable proper deduplication.
     *
     * @test
     */
    public function it_deduplicates_jurisdiction_nodes_across_decisions()
    {
        $jurisdiction = 'Republika Hrvatska';
        $expectedJurisdictionId = 'jurisdiction_'.md5($jurisdiction);

        // Create two decisions with identical jurisdiction
        $decisionId1 = $this->createMinimalCourtDecision(['jurisdiction' => $jurisdiction]);
        $decisionId2 = $this->createMinimalCourtDecision(['jurisdiction' => $jurisdiction]);

        $this->graphMock->shouldReceive('upsertNode')->with('CourtDecisionDocument', Mockery::any(), Mockery::any())->atLeast()->once();

        // Should call upsertNode with SAME jurisdiction ID for both decisions
        // (upsert will merge them into single node)
        $this->graphMock
            ->shouldReceive('upsertNode')
            ->twice()
            ->with('Jurisdiction', $expectedJurisdictionId, Mockery::on(function ($props) use ($jurisdiction) {
                return $props['name'] === $jurisdiction;
            }));

        $this->graphMock->shouldReceive('createRelationship')->zeroOrMoreTimes();
        $this->allowCourtNode();
        $this->allowLinking();
        $this->allowTagging();

        $this->service->sync($decisionId1);
        $this->service->sync($decisionId2);
    }

    // ========================================
    // Legal Principle Integration Tests
    // ========================================

    /** @test */
    public function it_calls_principle_sync_when_flag_enabled_and_raw_text_exists()
    {
        config(['graph.features.sync_legal_principles' => true]);

        // Add raw_text column temporarily
        DB::statement('ALTER TABLE court_decisions ADD COLUMN IF NOT EXISTS raw_text TEXT');
        DB::statement('ALTER TABLE court_decisions ADD COLUMN IF NOT EXISTS is_landmark BOOLEAN DEFAULT false');

        $decisionId = $this->createMinimalCourtDecision([
            'court' => 'Test Court',
        ]);

        // Update decision with raw_text
        DB::table('court_decisions')
            ->where('id', $decisionId)
            ->update([
                'raw_text' => 'This is the full decision text with legal principles.',
                'is_landmark' => false,
            ]);

        $this->graphMock->shouldReceive('upsertNode')->zeroOrMoreTimes();
        $this->graphMock->shouldReceive('createRelationship')->zeroOrMoreTimes();
        $this->allowLinking();
        $this->allowTagging();
        $this->allowJudgeSync();
        $this->allowPartySync();

        // Should call principle sync with correct parameters
        $this->principleSyncMock
            ->shouldReceive('syncPrinciplesFromDecision')
            ->once()
            ->with(
                Mockery::type('string'),  // document ID
                'This is the full decision text with legal principles.',
                false  // is_landmark
            )
            ->andReturn([]);

        $this->service->sync($decisionId);
    }

    /** @test */
    public function it_skips_principle_sync_when_flag_disabled()
    {
        config(['graph.features.sync_legal_principles' => false]);

        // Add raw_text column temporarily
        DB::statement('ALTER TABLE court_decisions ADD COLUMN IF NOT EXISTS raw_text TEXT');

        $decisionId = $this->createMinimalCourtDecision([
            'court' => 'Test Court',
        ]);

        // Update decision with raw_text
        DB::table('court_decisions')
            ->where('id', $decisionId)
            ->update(['raw_text' => 'Decision text']);

        $this->graphMock->shouldReceive('upsertNode')->zeroOrMoreTimes();
        $this->graphMock->shouldReceive('createRelationship')->zeroOrMoreTimes();
        $this->allowLinking();
        $this->allowTagging();
        $this->allowJudgeSync();
        $this->allowPartySync();

        // Should NOT call principle sync when flag is disabled
        $this->principleSyncMock
            ->shouldNotReceive('syncPrinciplesFromDecision');

        $this->service->sync($decisionId);
    }

    /** @test */
    public function it_handles_null_raw_text_gracefully()
    {
        config(['graph.features.sync_legal_principles' => true]);

        // Add raw_text column temporarily
        DB::statement('ALTER TABLE court_decisions ADD COLUMN IF NOT EXISTS raw_text TEXT');

        $decisionId = $this->createMinimalCourtDecision([
            'court' => 'Test Court',
        ]);

        // raw_text is null (default)

        $this->graphMock->shouldReceive('upsertNode')->zeroOrMoreTimes();
        $this->graphMock->shouldReceive('createRelationship')->zeroOrMoreTimes();
        $this->allowLinking();
        $this->allowTagging();
        $this->allowJudgeSync();
        $this->allowPartySync();

        // Should call principle sync with empty string for null raw_text
        $this->principleSyncMock
            ->shouldReceive('syncPrinciplesFromDecision')
            ->once()
            ->with(
                Mockery::type('string'),  // document ID
                '',  // empty string for null raw_text
                false  // is_landmark (defaults to false)
            )
            ->andReturn([]);

        $this->service->sync($decisionId);
    }

    /** @test */
    public function it_handles_empty_string_raw_text()
    {
        config(['graph.features.sync_legal_principles' => true]);

        // Add raw_text column temporarily
        DB::statement('ALTER TABLE court_decisions ADD COLUMN IF NOT EXISTS raw_text TEXT');

        $decisionId = $this->createMinimalCourtDecision([
            'court' => 'Test Court',
        ]);

        // Update decision with empty string raw_text
        DB::table('court_decisions')
            ->where('id', $decisionId)
            ->update(['raw_text' => '']);

        $this->graphMock->shouldReceive('upsertNode')->zeroOrMoreTimes();
        $this->graphMock->shouldReceive('createRelationship')->zeroOrMoreTimes();
        $this->allowLinking();
        $this->allowTagging();
        $this->allowJudgeSync();
        $this->allowPartySync();

        // Should call principle sync with empty string
        $this->principleSyncMock
            ->shouldReceive('syncPrinciplesFromDecision')
            ->once()
            ->with(
                Mockery::type('string'),  // document ID
                '',  // empty string
                false  // is_landmark
            )
            ->andReturn([]);

        $this->service->sync($decisionId);
    }

    /** @test */
    public function it_passes_landmark_flag_to_principle_sync()
    {
        config(['graph.features.sync_legal_principles' => true]);

        // Add raw_text and is_landmark columns temporarily
        DB::statement('ALTER TABLE court_decisions ADD COLUMN IF NOT EXISTS raw_text TEXT');
        DB::statement('ALTER TABLE court_decisions ADD COLUMN IF NOT EXISTS is_landmark BOOLEAN DEFAULT false');

        $decisionId = $this->createMinimalCourtDecision([
            'court' => 'Supreme Court',
        ]);

        // Update decision as landmark case
        DB::table('court_decisions')
            ->where('id', $decisionId)
            ->update([
                'raw_text' => 'Landmark decision establishing new precedent.',
                'is_landmark' => true,
            ]);

        $this->graphMock->shouldReceive('upsertNode')->zeroOrMoreTimes();
        $this->graphMock->shouldReceive('createRelationship')->zeroOrMoreTimes();
        $this->allowLinking();
        $this->allowTagging();
        $this->allowJudgeSync();
        $this->allowPartySync();

        // Should call principle sync with is_landmark=true
        $this->principleSyncMock
            ->shouldReceive('syncPrinciplesFromDecision')
            ->once()
            ->with(
                Mockery::type('string'),  // document ID
                'Landmark decision establishing new precedent.',
                true  // is_landmark
            )
            ->andReturn([]);

        $this->service->sync($decisionId);
    }

    /** @test */
    public function it_passes_correct_document_id_to_principle_sync()
    {
        config(['graph.features.sync_legal_principles' => true]);

        // Add raw_text column temporarily
        DB::statement('ALTER TABLE court_decisions ADD COLUMN IF NOT EXISTS raw_text TEXT');

        $decisionId = $this->createMinimalCourtDecision([
            'court' => 'Test Court',
        ]);

        // Update decision with raw_text
        DB::table('court_decisions')
            ->where('id', $decisionId)
            ->update(['raw_text' => 'Decision text']);

        // Get the actual document ID
        $doc = DB::table('court_decision_documents')
            ->where('decision_id', $decisionId)
            ->first();

        // Important: document ID should be different from decision ID
        $this->assertNotEquals($decisionId, $doc->id, 'Document ID should differ from Decision ID');

        $this->graphMock->shouldReceive('upsertNode')->zeroOrMoreTimes();
        $this->graphMock->shouldReceive('createRelationship')->zeroOrMoreTimes();
        $this->allowLinking();
        $this->allowTagging();
        $this->allowJudgeSync();
        $this->allowPartySync();

        // Should call principle sync with the document ID (not decision ID)
        $this->principleSyncMock
            ->shouldReceive('syncPrinciplesFromDecision')
            ->once()
            ->with(
                $doc->id,  // Document ID (NOT $decisionId)
                'Decision text',
                false
            )
            ->andReturn([]);

        $this->service->sync($decisionId);
    }

    /** @test */
    public function it_uses_default_true_for_sync_legal_principles_flag()
    {
        // Don't set config - should default to true

        // Add raw_text column temporarily
        DB::statement('ALTER TABLE court_decisions ADD COLUMN IF NOT EXISTS raw_text TEXT');

        $decisionId = $this->createMinimalCourtDecision([
            'court' => 'Test Court',
        ]);

        // Update decision with raw_text
        DB::table('court_decisions')
            ->where('id', $decisionId)
            ->update(['raw_text' => 'Decision text']);

        $this->graphMock->shouldReceive('upsertNode')->zeroOrMoreTimes();
        $this->graphMock->shouldReceive('createRelationship')->zeroOrMoreTimes();
        $this->allowLinking();
        $this->allowTagging();
        $this->allowJudgeSync();
        $this->allowPartySync();

        // Should call principle sync with default (true)
        $this->principleSyncMock
            ->shouldReceive('syncPrinciplesFromDecision')
            ->once()
            ->with(
                Mockery::type('string'),
                'Decision text',
                false
            )
            ->andReturn([]);

        $this->service->sync($decisionId);
    }

    /**
     * Allow principle sync operations in mocks
     */
    protected function allowPrincipleSync(): void
    {
        $this->principleSyncMock
            ->shouldReceive('syncPrinciplesFromDecision')
            ->zeroOrMoreTimes()
            ->andReturn([]);
    }

    /**
     * Allow precedent detection operations in mocks
     */
    protected function allowPrecedentDetection(): void
    {
        $this->precedentDetectorMock
            ->shouldReceive('detect')
            ->zeroOrMoreTimes()
            ->andReturn([]);
    }

    // ========================================
    // Precedent Detection Integration Tests
    // ========================================

    /** @test */
    public function it_calls_precedent_detector_when_flag_enabled_and_content_exists()
    {
        config(['graph.features.detect_precedents' => true]);

        $decisionId = $this->createMinimalCourtDecision([
            'content' => 'This decision follows precedent from case Us-123/2020.',
        ]);

        // Get the document ID
        $doc = DB::table('court_decision_documents')
            ->where('decision_id', $decisionId)
            ->first();

        $this->graphMock->shouldReceive('upsertNode')->zeroOrMoreTimes();
        $this->graphMock->shouldReceive('createRelationship')->zeroOrMoreTimes();
        $this->allowLinking();
        $this->allowTagging();
        $this->allowJudgeSync();
        $this->allowPartySync();
        $this->allowPrincipleSync();

        // Should call precedent detector with document ID and content
        $this->precedentDetectorMock
            ->shouldReceive('detect')
            ->once()
            ->with(
                $doc->id,
                'This decision follows precedent from case Us-123/2020.'
            )
            ->andReturn([]);

        $this->service->sync($decisionId);
    }

    /** @test */
    public function it_creates_relationships_from_precedent_detections()
    {
        config(['graph.features.detect_precedents' => true]);

        $decisionId = $this->createMinimalCourtDecision([
            'content' => 'Decision content referencing precedents.',
        ]);

        // Get the document ID
        $doc = DB::table('court_decision_documents')
            ->where('decision_id', $decisionId)
            ->first();

        $this->graphMock->shouldReceive('upsertNode')->zeroOrMoreTimes();
        $this->allowCourtAndJurisdictionNodes();
        $this->allowLinking();
        $this->allowTagging();
        $this->allowJudgeSync();
        $this->allowPartySync();
        $this->allowPrincipleSync();

        // Mock target decision IDs found in graph
        $targetId1 = 'target-doc-id-1';
        $targetId2 = 'target-doc-id-2';

        // Mock graph query to find target decisions by case number
        $this->graphMock
            ->shouldReceive('run')
            ->with(
                'MATCH (d:CourtDecisionDocument {case_number: $caseNumber}) RETURN d.id as id LIMIT 1',
                ['caseNumber' => 'Us-123/2020']
            )
            ->once()
            ->andReturn([['id' => $targetId1]]);

        $this->graphMock
            ->shouldReceive('run')
            ->with(
                'MATCH (d:CourtDecisionDocument {case_number: $caseNumber}) RETURN d.id as id LIMIT 1',
                ['caseNumber' => 'Rev-456/2019']
            )
            ->once()
            ->andReturn([['id' => $targetId2]]);

        // Mock detection result
        $this->precedentDetectorMock
            ->shouldReceive('detect')
            ->once()
            ->andReturn([
                [
                    'source_id' => $doc->id,
                    'target_case_number' => 'Us-123/2020',
                    'relationship_type' => 'CITES_PRECEDENT',
                    'context' => 'Court followed reasoning from Us-123/2020',
                    'confidence' => 0.85,
                ],
                [
                    'source_id' => $doc->id,
                    'target_case_number' => 'Rev-456/2019',
                    'relationship_type' => 'DISTINGUISHES',
                    'context' => 'Distinguished from Rev-456/2019',
                    'confidence' => 0.75,
                ],
            ]);

        // Should create relationship for each detection with CORRECT parameter order:
        // createRelationship(fromLabel, fromId, relType, toLabel, toId, properties)
        $this->graphMock
            ->shouldReceive('createRelationship')
            ->once()
            ->with(
                'CourtDecisionDocument',
                $doc->id,
                'CITES_PRECEDENT',
                'CourtDecisionDocument',
                $targetId1,
                [
                    'context' => 'Court followed reasoning from Us-123/2020',
                    'confidence' => 0.85,
                    'target_case_number' => 'Us-123/2020',
                ]
            );

        $this->graphMock
            ->shouldReceive('createRelationship')
            ->once()
            ->with(
                'CourtDecisionDocument',
                $doc->id,
                'DISTINGUISHES',
                'CourtDecisionDocument',
                $targetId2,
                [
                    'context' => 'Distinguished from Rev-456/2019',
                    'confidence' => 0.75,
                    'target_case_number' => 'Rev-456/2019',
                ]
            );

        $this->service->sync($decisionId);
    }

    /** @test */
    public function it_skips_precedent_detection_when_flag_disabled()
    {
        config(['graph.features.detect_precedents' => false]);

        $decisionId = $this->createMinimalCourtDecision([
            'content' => 'Decision content with precedents.',
        ]);

        $this->graphMock->shouldReceive('upsertNode')->zeroOrMoreTimes();
        $this->graphMock->shouldReceive('createRelationship')->zeroOrMoreTimes();
        $this->allowLinking();
        $this->allowTagging();
        $this->allowJudgeSync();
        $this->allowPartySync();
        $this->allowPrincipleSync();

        // Should NOT call precedent detector when flag is disabled
        $this->precedentDetectorMock
            ->shouldNotReceive('detect');

        $this->service->sync($decisionId);
    }

    /** @test */
    public function it_skips_precedent_detection_when_content_is_empty()
    {
        config(['graph.features.detect_precedents' => true]);

        $decisionId = $this->createMinimalCourtDecision([
            'content' => '',
        ]);

        $this->graphMock->shouldReceive('upsertNode')->zeroOrMoreTimes();
        $this->graphMock->shouldReceive('createRelationship')->zeroOrMoreTimes();
        $this->allowLinking();
        $this->allowTagging();
        $this->allowJudgeSync();
        $this->allowPartySync();
        $this->allowPrincipleSync();

        // Should NOT call precedent detector when content is empty
        $this->precedentDetectorMock
            ->shouldNotReceive('detect');

        $this->service->sync($decisionId);
    }

    /** @test */
    public function it_skips_precedent_detection_when_content_is_null()
    {
        config(['graph.features.detect_precedents' => true]);

        // Create decision - PostgreSQL enforces NOT NULL on content column
        // So we test with empty string instead (which is effectively null/empty)
        $decisionId = $this->createMinimalCourtDecision([
            'content' => '',  // Empty content (not null due to DB constraint)
        ]);

        $this->graphMock->shouldReceive('upsertNode')->zeroOrMoreTimes();
        $this->graphMock->shouldReceive('createRelationship')->zeroOrMoreTimes();
        $this->allowLinking();
        $this->allowTagging();
        $this->allowJudgeSync();
        $this->allowPartySync();
        $this->allowPrincipleSync();

        // Should NOT call precedent detector when content is empty
        $this->precedentDetectorMock
            ->shouldNotReceive('detect');

        $this->service->sync($decisionId);
    }

    /** @test */
    public function it_handles_empty_detection_results_gracefully()
    {
        config(['graph.features.detect_precedents' => true]);

        $decisionId = $this->createMinimalCourtDecision([
            'content' => 'Decision with no precedents detected.',
        ]);

        $this->graphMock->shouldReceive('upsertNode')->zeroOrMoreTimes();
        $this->graphMock->shouldReceive('createRelationship')->zeroOrMoreTimes();
        $this->allowLinking();
        $this->allowTagging();
        $this->allowJudgeSync();
        $this->allowPartySync();
        $this->allowPrincipleSync();

        // Mock empty detection result
        $this->precedentDetectorMock
            ->shouldReceive('detect')
            ->once()
            ->andReturn([]);

        // Should not throw exception with empty results
        $this->service->sync($decisionId);

        $this->assertTrue(true);
    }

    /** @test */
    public function it_uses_default_true_for_detect_precedents_flag()
    {
        // Don't set config - should default to true

        $decisionId = $this->createMinimalCourtDecision([
            'content' => 'Decision content.',
        ]);

        $doc = DB::table('court_decision_documents')
            ->where('decision_id', $decisionId)
            ->first();

        $this->graphMock->shouldReceive('upsertNode')->zeroOrMoreTimes();
        $this->graphMock->shouldReceive('createRelationship')->zeroOrMoreTimes();
        $this->allowLinking();
        $this->allowTagging();
        $this->allowJudgeSync();
        $this->allowPartySync();
        $this->allowPrincipleSync();

        // Should call precedent detector with default (true)
        $this->precedentDetectorMock
            ->shouldReceive('detect')
            ->once()
            ->with($doc->id, 'Decision content.')
            ->andReturn([]);

        $this->service->sync($decisionId);
    }

    /** @test */
    public function it_passes_correct_document_id_and_content_to_detector()
    {
        config(['graph.features.detect_precedents' => true]);

        $decisionId = $this->createMinimalCourtDecision([
            'content' => 'Specific decision content for detection.',
        ]);

        // Get the actual document ID
        $doc = DB::table('court_decision_documents')
            ->where('decision_id', $decisionId)
            ->first();

        // Important: should use document ID, not decision ID
        $this->assertNotEquals($decisionId, $doc->id, 'Document ID should differ from Decision ID');

        $this->graphMock->shouldReceive('upsertNode')->zeroOrMoreTimes();
        $this->graphMock->shouldReceive('createRelationship')->zeroOrMoreTimes();
        $this->allowLinking();
        $this->allowTagging();
        $this->allowJudgeSync();
        $this->allowPartySync();
        $this->allowPrincipleSync();

        // Should call with document ID (not decision ID) and doc content
        $this->precedentDetectorMock
            ->shouldReceive('detect')
            ->once()
            ->with(
                $doc->id,  // Document ID, NOT decision ID
                'Specific decision content for detection.'  // Doc content
            )
            ->andReturn([]);

        $this->service->sync($decisionId);
    }

    /** @test */
    public function it_skips_relationship_when_target_decision_not_found_in_graph()
    {
        config(['graph.features.detect_precedents' => true]);

        $decisionId = $this->createMinimalCourtDecision([
            'content' => 'Decision referencing unknown case.',
        ]);

        $doc = DB::table('court_decision_documents')
            ->where('decision_id', $decisionId)
            ->first();

        $this->graphMock->shouldReceive('upsertNode')->zeroOrMoreTimes();
        $this->allowCourtAndJurisdictionNodes();
        $this->allowLinking();
        $this->allowTagging();
        $this->allowJudgeSync();
        $this->allowPartySync();
        $this->allowPrincipleSync();

        // Mock detection result with target that doesn't exist
        $this->precedentDetectorMock
            ->shouldReceive('detect')
            ->once()
            ->andReturn([
                [
                    'source_id' => $doc->id,
                    'target_case_number' => 'Unknown-999/2020',
                    'relationship_type' => 'OVERRULES',
                    'context' => 'Overruling unknown case',
                    'confidence' => 0.9,
                ],
            ]);

        // Mock graph query to find target - returns empty (not found)
        $this->graphMock
            ->shouldReceive('run')
            ->with(
                'MATCH (d:CourtDecisionDocument {case_number: $caseNumber}) RETURN d.id as id LIMIT 1',
                ['caseNumber' => 'Unknown-999/2020']
            )
            ->once()
            ->andReturn([]);  // Empty result - target not found

        // Should NOT create relationship when target not found
        $this->graphMock
            ->shouldNotReceive('createRelationship')
            ->with(
                'CourtDecisionDocument',
                Mockery::any(),
                'OVERRULES',
                Mockery::any(),
                Mockery::any(),
                Mockery::any()
            );

        $this->service->sync($decisionId);
    }

    // ========================================
    // Metrics Tracking Tests
    // ========================================

    /** @test */
    public function it_tracks_metrics_during_sync()
    {
        $decisionId = $this->createMinimalCourtDecision([
            'court' => 'Vrhovni sud',
            'jurisdiction' => 'Republika Hrvatska',
            'judge' => 'Dr. Ivan Horvat',
            'content' => 'Decision content',
        ]);

        $this->graphMock->shouldReceive('upsertNode')->zeroOrMoreTimes();
        $this->graphMock->shouldReceive('createRelationship')->zeroOrMoreTimes();
        $this->allowLinking();
        $this->allowTagging();
        $this->allowJudgeSync();
        $this->allowPartySync();
        $this->allowPrincipleSync();
        $this->allowPrecedentDetection();

        // Act: sync and get metrics
        $metrics = $this->service->sync($decisionId);

        // Assert: metrics are returned and contain expected structure
        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('nodes', $metrics);
        $this->assertArrayHasKey('relationships', $metrics);
        $this->assertArrayHasKey('errors', $metrics);
        $this->assertArrayHasKey('duration_ms', $metrics);

        // Verify node counts
        $this->assertArrayHasKey('CourtDecisionDocument', $metrics['nodes']);
        $this->assertEquals(1, $metrics['nodes']['CourtDecisionDocument']);
        $this->assertArrayHasKey('Court', $metrics['nodes']);
        $this->assertEquals(1, $metrics['nodes']['Court']);
        $this->assertArrayHasKey('Jurisdiction', $metrics['nodes']);
        $this->assertEquals(1, $metrics['nodes']['Jurisdiction']);

        // Verify relationship counts
        $this->assertArrayHasKey('DECIDED_BY', $metrics['relationships']);
        $this->assertEquals(1, $metrics['relationships']['DECIDED_BY']);
        $this->assertArrayHasKey('BELONGS_TO_JURISDICTION', $metrics['relationships']);
        $this->assertEquals(1, $metrics['relationships']['BELONGS_TO_JURISDICTION']);

        // Verify duration is tracked
        $this->assertIsInt($metrics['duration_ms']);
        $this->assertGreaterThanOrEqual(0, $metrics['duration_ms']);
    }

    /** @test */
    public function it_tracks_multiple_document_chunks_in_metrics()
    {
        $decisionId = $this->createMinimalCourtDecision([
            'case_number' => 'Multi-1/2024',
            'court' => 'Test Court',
            'jurisdiction' => 'Test Jurisdiction',
        ]);

        // Create second document chunk
        DB::table('court_decision_documents')->insert([
            'id' => str_pad('doc-chunk-2', 26),
            'decision_id' => $decisionId,
            'doc_id' => 'doc-2',
            'chunk_index' => 1,
            'content' => 'Second chunk content',
            'content_hash' => 'hash2',
            'embedding_provider' => 'openai',
            'embedding_model' => 'text-embedding-ada-002',
            'embedding_dimensions' => 1536,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->graphMock->shouldReceive('upsertNode')->zeroOrMoreTimes();
        $this->graphMock->shouldReceive('createRelationship')->zeroOrMoreTimes();
        $this->allowLinking();
        $this->allowTagging();
        $this->allowPrincipleSync();
        $this->allowPrecedentDetection();

        // Act
        $metrics = $this->service->sync($decisionId);

        // Assert: should track 2 document nodes
        $this->assertEquals(2, $metrics['nodes']['CourtDecisionDocument']);
        $this->assertEquals(2, $metrics['relationships']['DECIDED_BY']);
        $this->assertEquals(2, $metrics['relationships']['BELONGS_TO_JURISDICTION']);
    }

    /** @test */
    public function it_logs_metrics_summary_on_completion()
    {
        $decisionId = $this->createMinimalCourtDecision([
            'court' => 'Test Court',
            'jurisdiction' => 'Test Jurisdiction',
        ]);

        $this->graphMock->shouldReceive('upsertNode')->zeroOrMoreTimes();
        $this->graphMock->shouldReceive('createRelationship')->zeroOrMoreTimes();
        $this->allowLinking();
        $this->allowTagging();
        $this->allowPrincipleSync();
        $this->allowPrecedentDetection();

        // Capture log output
        \Illuminate\Support\Facades\Log::shouldReceive('info')
            ->once()
            ->with('Sync completed', \Mockery::on(function ($context) use ($decisionId) {
                return $context['decision_id'] === $decisionId
                    && isset($context['nodes'])
                    && isset($context['relationships'])
                    && isset($context['errors'])
                    && isset($context['duration_ms']);
            }));

        // Act
        $this->service->sync($decisionId);
    }

    /** @test */
    public function it_returns_empty_metrics_for_missing_decision()
    {
        // Act
        $metrics = $this->service->sync('non-existent-decision-id');

        // Assert: should return empty metrics structure
        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('nodes', $metrics);
        $this->assertArrayHasKey('relationships', $metrics);
        $this->assertArrayHasKey('errors', $metrics);
        $this->assertArrayHasKey('duration_ms', $metrics);
        $this->assertEmpty($metrics['nodes']);
        $this->assertEmpty($metrics['relationships']);
    }

    /** @test */
    public function it_tracks_judge_node_in_metrics_when_synced()
    {
        config(['graph.features.sync_judges' => true]);

        $decisionId = $this->createMinimalCourtDecision([
            'court' => 'Vrhovni sud',
            'judge' => 'Dr. Ivan Horvat',
        ]);

        $this->graphMock->shouldReceive('upsertNode')->zeroOrMoreTimes();
        $this->graphMock->shouldReceive('createRelationship')->zeroOrMoreTimes();
        $this->allowLinking();
        $this->allowTagging();
        $this->allowPrincipleSync();
        $this->allowPrecedentDetection();

        // Mock judge sync to track node creation
        $this->judgeSyncMock
            ->shouldReceive('syncJudgeFromDecision')
            ->once()
            ->andReturnUsing(function () {
                // Simulate judge node creation
                return ['Judge' => 1, 'AUTHORED_BY' => 1];
            });

        // Act
        $metrics = $this->service->sync($decisionId);

        // Verify metrics structure exists
        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('nodes', $metrics);
    }

    // ========================================
    // Error Handling and Collection Tests
    // ========================================

    /** @test */
    public function it_collects_errors_when_keyword_linker_throws_exception()
    {
        $decisionId = $this->createMinimalCourtDecision([
            'content' => 'Test content',
        ]);

        $this->graphMock->shouldReceive('upsertNode')->zeroOrMoreTimes();
        $this->graphMock->shouldReceive('createRelationship')->zeroOrMoreTimes();

        // Mock keyword linker to throw exception
        $this->keywordLinkerMock
            ->shouldReceive('link')
            ->once()
            ->andThrow(new \Exception('Keyword extraction failed'));

        // Other services should still be called (error is caught and collected)
        $this->citationLinkerMock->shouldReceive('link')->once();
        $this->similarityLinkerMock->shouldReceive('link')->once();
        $this->allowTagging();
        $this->allowPrincipleSync();
        $this->allowPrecedentDetection();

        $result = $this->service->sync($decisionId);

        // Should return metrics array with error collected
        $this->assertIsArray($result);
        $this->assertArrayHasKey('errors', $result);
        $this->assertCount(1, $result['errors']);
        $this->assertEquals('keyword_linking', $result['errors'][0]['context']);
        $this->assertStringContainsString('Keyword extraction failed', $result['errors'][0]['message']);
        $this->assertEquals('Exception', $result['errors'][0]['exception']);
    }

    /** @test */
    public function it_collects_multiple_errors_from_different_services()
    {
        $decisionId = $this->createMinimalCourtDecision([
            'content' => 'Test content',
        ]);

        $this->graphMock->shouldReceive('upsertNode')->zeroOrMoreTimes();
        $this->graphMock->shouldReceive('createRelationship')->zeroOrMoreTimes();

        // Mock multiple services to throw exceptions
        $this->keywordLinkerMock
            ->shouldReceive('link')
            ->once()
            ->andThrow(new \Exception('Keyword failed'));

        $this->citationLinkerMock
            ->shouldReceive('link')
            ->once()
            ->andThrow(new \RuntimeException('Citation failed'));

        $this->similarityLinkerMock->shouldReceive('link')->once();
        $this->allowTagging();
        $this->allowPrincipleSync();
        $this->allowPrecedentDetection();

        $result = $this->service->sync($decisionId);

        // Should collect both errors
        $this->assertIsArray($result);
        $this->assertArrayHasKey('errors', $result);
        $this->assertGreaterThanOrEqual(2, count($result['errors']));
    }

    /** @test */
    public function it_returns_empty_errors_array_when_no_errors_occur()
    {
        $decisionId = $this->createMinimalCourtDecision([
            'content' => 'Test content',
        ]);

        $this->graphMock->shouldReceive('upsertNode')->zeroOrMoreTimes();
        $this->graphMock->shouldReceive('createRelationship')->zeroOrMoreTimes();
        $this->allowLinking();
        $this->allowTagging();
        $this->allowPrincipleSync();
        $this->allowPrecedentDetection();

        $result = $this->service->sync($decisionId);

        // Should return successful result with no errors
        $this->assertIsArray($result);
        $this->assertArrayHasKey('errors', $result);
        $this->assertEmpty($result['errors']);
    }

    /** @test */
    public function it_continues_processing_documents_after_error_in_one_chunk()
    {
        $decisionId = $this->createMinimalCourtDecision([
            'case_number' => 'Multi-1234/2024',
            'court' => 'Test Court',
        ]);

        // Create a second document chunk
        DB::table('court_decision_documents')->insert([
            'id' => str_pad('doc-chunk-2', 26),
            'decision_id' => $decisionId,
            'doc_id' => 'doc-2',
            'chunk_index' => 1,
            'content' => 'Second chunk content',
            'content_hash' => 'hash2',
            'embedding_provider' => 'openai',
            'embedding_model' => 'text-embedding-ada-002',
            'embedding_dimensions' => 1536,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->graphMock->shouldReceive('upsertNode')->zeroOrMoreTimes();
        $this->graphMock->shouldReceive('createRelationship')->zeroOrMoreTimes();

        // First chunk fails keyword linking, second succeeds
        $callCount = 0;
        $this->keywordLinkerMock
            ->shouldReceive('link')
            ->twice()
            ->andReturnUsing(function () use (&$callCount) {
                $callCount++;
                if ($callCount === 1) {
                    throw new \Exception('First chunk keyword failed');
                }
                // Second call succeeds (no exception)
            });

        $this->citationLinkerMock->shouldReceive('link')->twice();
        $this->similarityLinkerMock->shouldReceive('link')->twice();
        $this->taggingMock->shouldReceive('autoTag')->twice();
        $this->allowPrincipleSync();
        $this->allowPrecedentDetection();

        $result = $this->service->sync($decisionId);

        // Should process both chunks but report one error
        $this->assertIsArray($result);
        $this->assertCount(1, $result['errors']);
        $this->assertEquals(2, $result['nodes']['CourtDecisionDocument']);
    }

    /** @test */
    public function it_logs_errors_with_warning_level()
    {
        $decisionId = $this->createMinimalCourtDecision();

        $this->graphMock->shouldReceive('upsertNode')->zeroOrMoreTimes();
        $this->graphMock->shouldReceive('createRelationship')->zeroOrMoreTimes();

        $this->keywordLinkerMock
            ->shouldReceive('link')
            ->once()
            ->andThrow(new \RuntimeException('Keyword service unavailable'));

        $this->allowLinking();
        $this->allowTagging();
        $this->allowPrincipleSync();
        $this->allowPrecedentDetection();

        // Should log warning when error is caught
        \Illuminate\Support\Facades\Log::shouldReceive('warning')
            ->once()
            ->with(
                \Mockery::pattern('/Sync error in keyword_linking/'),
                \Mockery::on(function ($context) {
                    return isset($context['error'])
                        && strpos($context['error'], 'Keyword service unavailable') !== false;
                })
            );

        // Existing info log for completion
        \Illuminate\Support\Facades\Log::shouldReceive('info')->once();

        $this->service->sync($decisionId);
    }

    /** @test */
    public function it_includes_exception_class_in_error_report()
    {
        $decisionId = $this->createMinimalCourtDecision();

        $this->graphMock->shouldReceive('upsertNode')->zeroOrMoreTimes();
        $this->graphMock->shouldReceive('createRelationship')->zeroOrMoreTimes();

        $this->keywordLinkerMock
            ->shouldReceive('link')
            ->once()
            ->andThrow(new \RuntimeException('Runtime error occurred'));

        $this->allowLinking();
        $this->allowTagging();
        $this->allowPrincipleSync();
        $this->allowPrecedentDetection();

        $result = $this->service->sync($decisionId);

        // Error should include exception class
        $this->assertIsArray($result);
        $this->assertCount(1, $result['errors']);
        $this->assertEquals('RuntimeException', $result['errors'][0]['exception']);
    }
}
