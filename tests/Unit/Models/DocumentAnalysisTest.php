<?php

namespace Tests\Unit\Models;

use App\Models\CaseDocument;
use App\Models\DocumentAnalysis;
use App\Models\LegalCase;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class DocumentAnalysisTest extends TestCase
{
    use UsesTestDatabase;

    private function createTestCaseWithDocument(): array
    {
        $case = LegalCase::create([
            'id' => Str::ulid()->toString(),
            'case_number' => 'TEST-'.rand(1000, 9999),
            'title' => 'Test Case',
            'status' => 'active',
        ]);

        $document = CaseDocument::create([
            'id' => Str::ulid()->toString(),
            'case_id' => $case->id,
            'title' => 'Test Document',
            'content' => 'This is test document content for analysis.',
        ]);

        return ['case' => $case, 'document' => $document];
    }

    public function test_document_analysis_can_be_created(): void
    {
        // Arrange
        $data = $this->createTestCaseWithDocument();
        $document = $data['document'];

        // Act
        $analysis = DocumentAnalysis::create([
            'case_document_id' => $document->id,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'analysis_type' => DocumentAnalysis::TYPE_KEYWORDS,
            'status' => DocumentAnalysis::STATUS_PENDING,
            'version' => 1,
        ]);

        // Assert
        $this->assertNotNull($analysis->id);
        $this->assertEquals($document->id, $analysis->case_document_id);
        $this->assertEquals('extraction', $analysis->analysis_layer);
        $this->assertEquals('keywords', $analysis->analysis_type);
        $this->assertEquals('pending', $analysis->status);
    }

    public function test_document_analysis_belongs_to_case_document(): void
    {
        // Arrange
        $data = $this->createTestCaseWithDocument();
        $document = $data['document'];

        $analysis = DocumentAnalysis::create([
            'case_document_id' => $document->id,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'analysis_type' => DocumentAnalysis::TYPE_ENTITIES,
            'status' => DocumentAnalysis::STATUS_PENDING,
        ]);

        // Act
        $relatedDocument = $analysis->caseDocument;

        // Assert
        $this->assertInstanceOf(CaseDocument::class, $relatedDocument);
        $this->assertEquals($document->id, $relatedDocument->id);
    }

    public function test_document_analysis_casts_json_fields(): void
    {
        // Arrange
        $data = $this->createTestCaseWithDocument();
        $document = $data['document'];

        $results = ['keywords' => ['legal', 'contract', 'agreement']];
        $metadata = ['token_count' => 150, 'processing_time' => 0.5];

        // Act
        $analysis = DocumentAnalysis::create([
            'case_document_id' => $document->id,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'analysis_type' => DocumentAnalysis::TYPE_KEYWORDS,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'results' => $results,
            'metadata' => $metadata,
        ]);

        // Refresh from database
        $analysis->refresh();

        // Assert
        $this->assertIsArray($analysis->results);
        $this->assertIsArray($analysis->metadata);
        $this->assertEquals(['legal', 'contract', 'agreement'], $analysis->results['keywords']);
        $this->assertEquals(150, $analysis->metadata['token_count']);
    }

    public function test_document_analysis_casts_datetime_fields(): void
    {
        // Arrange
        $data = $this->createTestCaseWithDocument();
        $document = $data['document'];

        $now = now();

        // Act
        $analysis = DocumentAnalysis::create([
            'case_document_id' => $document->id,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'analysis_type' => DocumentAnalysis::TYPE_DATES,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'started_at' => $now,
            'completed_at' => $now->copy()->addSeconds(2),
        ]);

        // Assert
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $analysis->started_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $analysis->completed_at);
    }

    public function test_mark_processing_updates_status_and_started_at(): void
    {
        // Arrange
        $data = $this->createTestCaseWithDocument();
        $document = $data['document'];

        $analysis = DocumentAnalysis::create([
            'case_document_id' => $document->id,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'analysis_type' => DocumentAnalysis::TYPE_KEYWORDS,
            'status' => DocumentAnalysis::STATUS_PENDING,
        ]);

        // Act
        $result = $analysis->markProcessing();

        // Assert
        $this->assertSame($analysis, $result);
        $this->assertEquals(DocumentAnalysis::STATUS_PROCESSING, $analysis->status);
        $this->assertNotNull($analysis->started_at);
    }

    public function test_mark_completed_updates_status_results_and_completed_at(): void
    {
        // Arrange
        $data = $this->createTestCaseWithDocument();
        $document = $data['document'];

        $analysis = DocumentAnalysis::create([
            'case_document_id' => $document->id,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'analysis_type' => DocumentAnalysis::TYPE_KEYWORDS,
            'status' => DocumentAnalysis::STATUS_PROCESSING,
        ]);

        $results = ['keywords' => ['legal', 'contract']];
        $metadata = ['token_count' => 100];

        // Act
        $result = $analysis->markCompleted($results, $metadata);

        // Assert
        $this->assertSame($analysis, $result);
        $this->assertEquals(DocumentAnalysis::STATUS_COMPLETED, $analysis->status);
        $this->assertEquals($results, $analysis->results);
        $this->assertEquals($metadata, $analysis->metadata);
        $this->assertNotNull($analysis->completed_at);
    }

    public function test_mark_failed_updates_status_error_and_completed_at(): void
    {
        // Arrange
        $data = $this->createTestCaseWithDocument();
        $document = $data['document'];

        $analysis = DocumentAnalysis::create([
            'case_document_id' => $document->id,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'analysis_type' => DocumentAnalysis::TYPE_KEYWORDS,
            'status' => DocumentAnalysis::STATUS_PROCESSING,
        ]);

        // Act
        $result = $analysis->markFailed('Processing timeout');

        // Assert
        $this->assertSame($analysis, $result);
        $this->assertEquals(DocumentAnalysis::STATUS_FAILED, $analysis->status);
        $this->assertEquals('Processing timeout', $analysis->error_message);
        $this->assertNotNull($analysis->completed_at);
    }

    public function test_scope_for_document_filters_by_document_id(): void
    {
        // Arrange
        $data1 = $this->createTestCaseWithDocument();
        $document1 = $data1['document'];

        $data2 = $this->createTestCaseWithDocument();
        $document2 = $data2['document'];

        DocumentAnalysis::create([
            'case_document_id' => $document1->id,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'analysis_type' => DocumentAnalysis::TYPE_KEYWORDS,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
        ]);

        DocumentAnalysis::create([
            'case_document_id' => $document2->id,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'analysis_type' => DocumentAnalysis::TYPE_KEYWORDS,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
        ]);

        // Act
        $analyses = DocumentAnalysis::forDocument($document1->id)->get();

        // Assert
        $this->assertCount(1, $analyses);
        $this->assertEquals($document1->id, $analyses->first()->case_document_id);
    }

    public function test_scope_of_type_filters_by_analysis_type(): void
    {
        // Arrange
        $data = $this->createTestCaseWithDocument();
        $document = $data['document'];

        DocumentAnalysis::create([
            'case_document_id' => $document->id,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'analysis_type' => DocumentAnalysis::TYPE_KEYWORDS,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
        ]);

        DocumentAnalysis::create([
            'case_document_id' => $document->id,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'analysis_type' => DocumentAnalysis::TYPE_ENTITIES,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
        ]);

        // Act
        $analyses = DocumentAnalysis::ofType(DocumentAnalysis::TYPE_KEYWORDS)->get();

        // Assert
        $this->assertCount(1, $analyses);
        $this->assertEquals('keywords', $analyses->first()->analysis_type);
    }

    public function test_scope_completed_filters_by_status(): void
    {
        // Arrange
        $data = $this->createTestCaseWithDocument();
        $document = $data['document'];

        DocumentAnalysis::create([
            'case_document_id' => $document->id,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'analysis_type' => DocumentAnalysis::TYPE_KEYWORDS,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
        ]);

        DocumentAnalysis::create([
            'case_document_id' => $document->id,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'analysis_type' => DocumentAnalysis::TYPE_ENTITIES,
            'status' => DocumentAnalysis::STATUS_PENDING,
        ]);

        // Act
        $analyses = DocumentAnalysis::completed()->get();

        // Assert
        $this->assertCount(1, $analyses);
        $this->assertEquals('completed', $analyses->first()->status);
    }

    public function test_scope_layer_filters_by_analysis_layer(): void
    {
        // Arrange
        $data = $this->createTestCaseWithDocument();
        $document = $data['document'];

        DocumentAnalysis::create([
            'case_document_id' => $document->id,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'analysis_type' => DocumentAnalysis::TYPE_KEYWORDS,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
        ]);

        DocumentAnalysis::create([
            'case_document_id' => $document->id,
            'analysis_layer' => DocumentAnalysis::LAYER_AI_BASIC,
            'analysis_type' => DocumentAnalysis::TYPE_SUMMARY,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
        ]);

        // Act
        $analyses = DocumentAnalysis::layer(DocumentAnalysis::LAYER_EXTRACTION)->get();

        // Assert
        $this->assertCount(1, $analyses);
        $this->assertEquals('extraction', $analyses->first()->analysis_layer);
    }

    public function test_constants_have_correct_values(): void
    {
        // Status constants
        $this->assertEquals('pending', DocumentAnalysis::STATUS_PENDING);
        $this->assertEquals('processing', DocumentAnalysis::STATUS_PROCESSING);
        $this->assertEquals('completed', DocumentAnalysis::STATUS_COMPLETED);
        $this->assertEquals('failed', DocumentAnalysis::STATUS_FAILED);

        // Layer constants
        $this->assertEquals('extraction', DocumentAnalysis::LAYER_EXTRACTION);
        $this->assertEquals('pattern', DocumentAnalysis::LAYER_PATTERN);
        $this->assertEquals('ai_basic', DocumentAnalysis::LAYER_AI_BASIC);
        $this->assertEquals('ai_deep', DocumentAnalysis::LAYER_AI_DEEP);

        // Type constants
        $this->assertEquals('keywords', DocumentAnalysis::TYPE_KEYWORDS);
        $this->assertEquals('entities', DocumentAnalysis::TYPE_ENTITIES);
        $this->assertEquals('dates', DocumentAnalysis::TYPE_DATES);
        $this->assertEquals('citations', DocumentAnalysis::TYPE_CITATIONS);
        $this->assertEquals('statistics', DocumentAnalysis::TYPE_STATISTICS);
        $this->assertEquals('timeline', DocumentAnalysis::TYPE_TIMELINE);
        $this->assertEquals('summary', DocumentAnalysis::TYPE_SUMMARY);
        $this->assertEquals('key_facts', DocumentAnalysis::TYPE_KEY_FACTS);
        $this->assertEquals('contradictions', DocumentAnalysis::TYPE_CONTRADICTIONS);
        $this->assertEquals('strategy', DocumentAnalysis::TYPE_STRATEGY);
    }
}
