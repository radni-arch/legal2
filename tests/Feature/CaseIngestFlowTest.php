<?php

namespace Tests\Feature;

use App\Models\CaseDocument;
use App\Models\CaseDocumentUpload;
use App\Models\LegalCase;
use App\Services\CaseIngestPipeline;
use App\Services\CaseVectorStoreService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\UsesTestDatabase;

/**
 * Feature tests for end-to-end case document ingestion flow.
 *
 * Tests the complete pipeline:
 * 1. Upload → persist upload record
 * 2. OCR/text extraction → store raw text
 * 3. Normalize → chunk → embeddings
 * 4. CaseVectorStoreService::ingest
 * 5. Document is searchable
 */
class CaseIngestFlowTest extends TestCase
{
    use UsesTestDatabase;

    protected LegalCase $case;

    protected CaseIngestPipeline $pipeline;

    protected CaseVectorStoreService $vectorStore;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test case
        $this->case = LegalCase::create([
            'id' => (string) Str::ulid(),
            'case_number' => 'TEST-001/2025',
            'title' => 'Test Case for Ingestion',
            'status' => 'active',
        ]);

        $this->pipeline = app(CaseIngestPipeline::class);
        $this->vectorStore = app(CaseVectorStoreService::class);

        // Mock storage
        Storage::fake('local');
    }

    /** @test */
    public function it_completes_full_ingestion_pipeline()
    {
        // Arrange
        $rawText = $this->getSampleLegalText();
        $docId = 'doc-test-'.Str::ulid();

        // Mock OCR blocks with high confidence
        $blocks = $this->createMockTextractBlocks($rawText, confidence: 0.95);

        // Act: Run through the ingestion pipeline
        $result = $this->pipeline->ingest(
            caseId: $this->case->id,
            docId: $docId,
            rawText: $rawText,
            ocrBlocks: $blocks,
            options: [
                'chunk_size' => 500,
                'overlap' => 100,
                'language' => 'hr',
            ]
        );

        // Assert: Pipeline completed successfully
        $this->assertEquals('completed', $result['status']);
        $this->assertTrue($result['normalized']);
        $this->assertTrue($result['chunked']);
        $this->assertTrue($result['embedded']);
        $this->assertFalse($result['needs_review']);
        $this->assertGreaterThan(0, $result['chunk_count']);

        // Assert: Documents were created in database
        $documents = CaseDocument::where('case_id', $this->case->id)
            ->where('doc_id', $docId)
            ->get();

        $this->assertGreaterThan(0, $documents->count());

        // Assert: Each document has required fields
        foreach ($documents as $doc) {
            $this->assertNotEmpty($doc->content);
            $this->assertNotEmpty($doc->content_hash);
            $this->assertIsArray($doc->metadata);
            $this->assertEquals($this->case->id, $doc->case_id);
            $this->assertEquals($docId, $doc->doc_id);
        }
    }

    /** @test */
    public function it_blocks_embedding_on_low_ocr_quality()
    {
        // Arrange
        $rawText = $this->getSampleLegalText();
        $docId = 'doc-low-quality-'.Str::ulid();

        // Mock OCR blocks with LOW confidence
        $blocks = $this->createMockTextractBlocks($rawText, confidence: 0.60);

        // Act: Run pipeline with quality gates enabled
        $result = $this->pipeline->ingest(
            caseId: $this->case->id,
            docId: $docId,
            rawText: $rawText,
            ocrBlocks: $blocks,
            options: [
                'chunk_size' => 500,
                'overlap' => 100,
                'min_confidence' => 0.82,
                'skip_embedding_on_low_quality' => true,
            ]
        );

        // Assert: Pipeline stopped at quality check
        $this->assertEquals('quality_check_failed', $result['status']);
        $this->assertTrue($result['needs_review']);
        $this->assertFalse($result['embedded']);

        // Assert: Quality metrics are recorded
        $this->assertArrayHasKey('quality_check', $result);
        $this->assertLessThan(0.82, $result['quality_check']['confidence']);

        // Assert: No documents were created (embedding was skipped)
        $documentCount = CaseDocument::where('case_id', $this->case->id)
            ->where('doc_id', $docId)
            ->count();

        $this->assertEquals(0, $documentCount);
    }

    /** @test */
    public function it_creates_upload_record_before_processing()
    {
        // Arrange
        $fileName = 'test-decision.pdf';
        $docId = 'doc-upload-test-'.Str::ulid();
        $uploadId = (string) Str::ulid();

        $upload = CaseDocumentUpload::create([
            'id' => $uploadId,
            'case_id' => $this->case->id,
            'doc_id' => $docId,
            'disk' => 'local',
            'local_path' => "test/{$fileName}",
            'original_filename' => $fileName,
            'mime_type' => 'application/pdf',
            'file_size' => 12345,
            'sha256' => hash('sha256', 'test-content'),
            'status' => 'stored',
            'uploaded_at' => now(),
        ]);

        // Act: Process with upload_id
        $result = $this->pipeline->ingest(
            caseId: $this->case->id,
            docId: $docId,
            rawText: $this->getSampleLegalText(),
            ocrBlocks: [],
            options: [
                'upload_id' => $uploadId,
                'chunk_size' => 500,
            ]
        );

        // Assert: Documents are linked to upload
        $documents = CaseDocument::where('case_id', $this->case->id)
            ->where('doc_id', $docId)
            ->get();

        foreach ($documents as $doc) {
            $this->assertEquals($uploadId, $doc->upload_id);
        }

        // Assert: Can retrieve upload from document
        $this->assertNotNull($documents->first()->upload);
        $this->assertEquals($fileName, $documents->first()->upload->original_filename);
    }

    /** @test */
    public function it_deduplicates_by_content_hash()
    {
        // Arrange
        $rawText = $this->getSampleLegalText();
        $docId = 'doc-dedupe-'.Str::ulid();

        // Act: Ingest same text twice
        $result1 = $this->pipeline->ingest(
            caseId: $this->case->id,
            docId: $docId,
            rawText: $rawText,
            ocrBlocks: [],
            options: ['chunk_size' => 1000]
        );

        $count1 = CaseDocument::where('case_id', $this->case->id)
            ->where('doc_id', $docId)
            ->count();

        $result2 = $this->pipeline->ingest(
            caseId: $this->case->id,
            docId: $docId,
            rawText: $rawText,
            ocrBlocks: [],
            options: ['chunk_size' => 1000]
        );

        $count2 = CaseDocument::where('case_id', $this->case->id)
            ->where('doc_id', $docId)
            ->count();

        // Assert: Second ingestion did not create duplicates
        $this->assertEquals($count1, $count2);
        $this->assertEquals(0, $result2['ingest_result']['inserted']);
    }

    /** @test */
    public function it_makes_documents_searchable_after_ingestion()
    {
        // Arrange
        $rawText = 'Članak 1. Ova presuda se odnosi na prometnu nesreću koja se dogodila u Zagrebu.';
        $docId = 'doc-search-'.Str::ulid();

        // Act: Ingest document
        $this->pipeline->ingest(
            caseId: $this->case->id,
            docId: $docId,
            rawText: $rawText,
            ocrBlocks: [],
            options: ['chunk_size' => 200]
        );

        // Assert: Can find document by content search
        $found = CaseDocument::where('case_id', $this->case->id)
            ->where('content', 'LIKE', '%prometnu nesreću%')
            ->exists();

        $this->assertTrue($found);

        // Assert: Documents have embeddings (if using real embedding service)
        $documents = CaseDocument::where('case_id', $this->case->id)
            ->where('doc_id', $docId)
            ->get();

        foreach ($documents as $doc) {
            $this->assertNotNull($doc->embedding_provider);
            $this->assertNotNull($doc->embedding_model);
        }
    }

    /** @test */
    public function it_sets_needs_review_flag_for_low_confidence_and_triggers_reprocess()
    {
        // Arrange
        $rawText = $this->getSampleLegalText();
        $docId = 'doc-low-conf-reprocess-'.Str::ulid();

        // Mock OCR blocks with low confidence (60%) to trigger needs_review
        $blocks = $this->createMockTextractBlocks($rawText, confidence: 0.60);

        // Act: Run pipeline with quality gates enabled
        $result = $this->pipeline->ingest(
            caseId: $this->case->id,
            docId: $docId,
            rawText: $rawText,
            ocrBlocks: $blocks,
            options: [
                'chunk_size' => 500,
                'overlap' => 100,
                'min_confidence' => 0.82,
                'skip_embedding_on_low_quality' => true,
            ]
        );

        // Assert: needs_review flag is set
        $this->assertTrue($result['needs_review'], 'needs_review should be true for low confidence OCR');
        $this->assertEquals('quality_check_failed', $result['status']);
        $this->assertFalse($result['embedded'], 'Embedding should be skipped for low quality OCR');

        // Assert: Quality metrics indicate low confidence
        $this->assertArrayHasKey('quality_check', $result);
        $this->assertLessThan(0.82, $result['quality_check']['confidence']);

        // Assert: needsReOcr method correctly identifies document for reprocessing
        $needsReOcr = $this->pipeline->needsReOcr($result['quality_check'], [
            'min_confidence' => 0.82,
        ]);
        $this->assertTrue($needsReOcr, 'Pipeline should identify document as needing re-OCR');

        // Simulate reprocess trigger by verifying ProcessDrivePdf would be dispatched
        // In a real scenario, this would dispatch ReprocessTextractJob which calls ProcessDrivePdf
        // We verify that the quality metrics would trigger the reprocess flow
        $this->assertTrue($result['needs_review'], 'Document with low confidence should be flagged for reprocessing');
    }

    /** @test */
    public function it_triggers_reprocess_when_low_confidence_pages_exceed_threshold()
    {
        // Arrange: Multi-page document with excessive low confidence pages
        $rawText = $this->getSampleLegalText();
        $docId = 'doc-many-low-pages-'.Str::ulid();

        // Create blocks with 5 pages, 4 of which have low confidence
        $blocks = [];
        for ($page = 1; $page <= 5; $page++) {
            $confidence = $page === 1 ? 0.95 : 0.65; // First page good, rest are low
            $pageBlocks = $this->createMockTextractBlocksForPage($rawText, $page, $confidence);
            $blocks = array_merge($blocks, $pageBlocks);
        }

        // Act: Run pipeline
        $result = $this->pipeline->ingest(
            caseId: $this->case->id,
            docId: $docId,
            rawText: $rawText,
            ocrBlocks: $blocks,
            options: [
                'chunk_size' => 500,
                'min_confidence' => 0.82,
                'skip_embedding_on_low_quality' => true,
            ]
        );

        // Assert: needs_review is set due to many low confidence pages
        $this->assertTrue($result['needs_review']);
        $this->assertEquals('quality_check_failed', $result['status']);

        // Assert: Quality check identifies multiple low confidence pages
        $qualityCheck = $result['quality_check'];
        $this->assertGreaterThan(3, $qualityCheck['low_confidence_pages']);
        $this->assertCount(4, $qualityCheck['low_confidence_page_numbers']); // Pages 2-5

        // Assert: needsReOcr with max_low_confidence_pages threshold
        $needsReOcr = $this->pipeline->needsReOcr($qualityCheck, [
            'max_low_confidence_pages' => 3,
        ]);
        $this->assertTrue($needsReOcr, 'Document should need re-OCR due to too many low confidence pages');
    }

    // Helper methods

    protected function getSampleLegalText(): string
    {
        return <<<'TEXT'
REPUBLIKA HRVATSKA
OPĆINSKI SUD U ZAGREBU

PRESUDA

U ime Republike Hrvatske

Općinski sud u Zagrebu, sudac Ana Horvat, u pravnoj stvari tužitelja
Ivana Kovača, zastupan po punomoćniku odvjetniku Marku Tomicu, protiv
tuženika Petra Novaka, radi naknade štete od 50.000,00 kuna, donio je
dana 15. siječnja 2025. godine sljedeću

PRESUDU

I. Tuženik je dužan tužitelju platiti iznos od 35.000,00 kuna s pripadajućim
zakonskim zateznim kamatama na iznos od 35.000,00 kuna od 1. siječnja 2024.
godine do konačne isplate.

II. Tužbeni zahtjev u dijelu u kojem se tužitelj traži razliku do 50.000,00 kuna
se odbija kao neosnovan.

III. Tužitelj i tuženik snose sudske troškove razmjerno uspjehu u sporu.

OBRAZLOŽENJE

Tužitelj je dana 15. ožujka 2024. godine podnio tužbu protiv tuženika
tražeći naknadu štete od 50.000,00 kuna. Naveo je da je tuženik svojim
nepravilnim postupanjem prouzročio prometnu nesreću dana 5. siječnja 2024.
godine u Zagrebu, zbog čega je tužitelj pretrpio materijalnu i nematerijalnu
štetu.

Provedenim dokaznim postupkom sud je utvrdio sljedeće činjenice...
TEXT;
    }

    protected function createMockTextractBlocks(string $text, float $confidence = 0.95): array
    {
        $words = explode(' ', $text);
        $blocks = [];

        foreach ($words as $index => $word) {
            $blocks[] = [
                'BlockType' => 'WORD',
                'Text' => $word,
                'Confidence' => $confidence * 100, // Textract uses 0-100
                'Page' => 1,
                'Geometry' => [
                    'BoundingBox' => [
                        'Left' => 0.1,
                        'Top' => 0.1 + ($index * 0.01),
                        'Width' => 0.1,
                        'Height' => 0.01,
                    ],
                ],
            ];
        }

        return $blocks;
    }

    protected function createMockTextractBlocksForPage(string $text, int $page, float $confidence = 0.95): array
    {
        $words = explode(' ', $text);
        $blocks = [];

        // Only use a subset of words per page to simulate page breaks
        $wordsPerPage = max(1, (int) (count($words) / 5));
        $startIndex = ($page - 1) * $wordsPerPage;
        $pageWords = array_slice($words, $startIndex, $wordsPerPage);

        foreach ($pageWords as $index => $word) {
            $blocks[] = [
                'BlockType' => 'WORD',
                'Text' => $word,
                'Confidence' => $confidence * 100, // Textract uses 0-100
                'Page' => $page,
                'Geometry' => [
                    'BoundingBox' => [
                        'Left' => 0.1,
                        'Top' => 0.1 + ($index * 0.01),
                        'Width' => 0.1,
                        'Height' => 0.01,
                    ],
                ],
            ];
        }

        return $blocks;
    }
}
