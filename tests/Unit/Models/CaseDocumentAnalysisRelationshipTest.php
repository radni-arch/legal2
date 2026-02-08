<?php

namespace Tests\Unit\Models;

use App\Models\CaseDocument;
use App\Models\DocumentAnalysis;
use App\Models\LegalCase;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\UsesTestDatabase;

class CaseDocumentAnalysisRelationshipTest extends TestCase
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

    public function test_case_document_has_many_analyses(): void
    {
        // Arrange
        $data = $this->createTestCaseWithDocument();
        $document = $data['document'];

        // Create multiple analyses for the same document
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

        DocumentAnalysis::create([
            'case_document_id' => $document->id,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'analysis_type' => DocumentAnalysis::TYPE_DATES,
            'status' => DocumentAnalysis::STATUS_PENDING,
        ]);

        // Act
        $analyses = $document->analyses;

        // Assert
        $this->assertCount(3, $analyses);
        $this->assertInstanceOf(DocumentAnalysis::class, $analyses->first());
    }

    public function test_latest_analysis_returns_highest_version_completed(): void
    {
        // Arrange
        $data = $this->createTestCaseWithDocument();
        $document = $data['document'];

        // Create version 1 (completed)
        DocumentAnalysis::create([
            'case_document_id' => $document->id,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'analysis_type' => DocumentAnalysis::TYPE_KEYWORDS,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'version' => 1,
            'results' => ['keywords' => ['old']],
        ]);

        // Create version 2 (completed)
        DocumentAnalysis::create([
            'case_document_id' => $document->id,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'analysis_type' => DocumentAnalysis::TYPE_KEYWORDS,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'version' => 2,
            'results' => ['keywords' => ['new']],
        ]);

        // Create version 3 (pending - should not be returned)
        DocumentAnalysis::create([
            'case_document_id' => $document->id,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'analysis_type' => DocumentAnalysis::TYPE_KEYWORDS,
            'status' => DocumentAnalysis::STATUS_PENDING,
            'version' => 3,
        ]);

        // Act
        $latest = $document->latestAnalysis(DocumentAnalysis::TYPE_KEYWORDS);

        // Assert
        $this->assertNotNull($latest);
        $this->assertEquals(2, $latest->version);
        $this->assertEquals(['keywords' => ['new']], $latest->results);
    }

    public function test_latest_analysis_returns_null_when_none_exist(): void
    {
        // Arrange
        $data = $this->createTestCaseWithDocument();
        $document = $data['document'];

        // Act
        $latest = $document->latestAnalysis(DocumentAnalysis::TYPE_KEYWORDS);

        // Assert
        $this->assertNull($latest);
    }

    public function test_has_completed_analysis_returns_true_when_exists(): void
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

        // Act & Assert
        $this->assertTrue($document->hasCompletedAnalysis(DocumentAnalysis::TYPE_KEYWORDS));
    }

    public function test_has_completed_analysis_returns_false_when_only_pending(): void
    {
        // Arrange
        $data = $this->createTestCaseWithDocument();
        $document = $data['document'];

        DocumentAnalysis::create([
            'case_document_id' => $document->id,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'analysis_type' => DocumentAnalysis::TYPE_KEYWORDS,
            'status' => DocumentAnalysis::STATUS_PENDING,
        ]);

        // Act & Assert
        $this->assertFalse($document->hasCompletedAnalysis(DocumentAnalysis::TYPE_KEYWORDS));
    }

    public function test_has_completed_analysis_returns_false_when_none_exist(): void
    {
        // Arrange
        $data = $this->createTestCaseWithDocument();
        $document = $data['document'];

        // Act & Assert
        $this->assertFalse($document->hasCompletedAnalysis(DocumentAnalysis::TYPE_KEYWORDS));
    }

    public function test_deleting_document_cascades_to_analyses(): void
    {
        // Arrange
        $data = $this->createTestCaseWithDocument();
        $document = $data['document'];
        $documentId = $document->id;

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

        $this->assertEquals(2, DocumentAnalysis::where('case_document_id', $documentId)->count());

        // Act
        $document->delete();

        // Assert - All analyses should be deleted due to cascade
        $this->assertEquals(0, DocumentAnalysis::where('case_document_id', $documentId)->count());
    }
}
