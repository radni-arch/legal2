<?php

namespace Tests\Unit\Services\Analysis;

use App\Models\CaseDocument;
use App\Models\DocumentAnalysis;
use App\Services\Analysis\Contracts\DocumentAnalyzerInterface;
use App\Services\Analysis\DocumentAnalysisPipeline;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Tests\TestCase;

class DocumentAnalysisPipelineTest extends TestCase
{
    use DatabaseTransactions;

    private DocumentAnalysisPipeline $pipeline;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pipeline = new DocumentAnalysisPipeline();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_returns_empty_array_when_document_has_no_text(): void
    {
        // Create a CaseDocument without content
        $document = CaseDocument::factory()->create([
            'content' => null,
        ]);

        $results = $this->pipeline->runLayer($document, DocumentAnalysis::LAYER_EXTRACTION);

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    /** @test */
    public function it_returns_empty_array_for_unknown_layer(): void
    {
        $document = CaseDocument::factory()->create([
            'content' => 'Some test content for analysis.',
        ]);

        $results = $this->pipeline->runLayer($document, 'unknown_layer');

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    /** @test */
    public function it_can_run_layer_extraction(): void
    {
        $document = CaseDocument::factory()->create([
            'content' => 'Test document content for keyword and entity extraction.',
        ]);

        // The pipeline should return an array of DocumentAnalysis results
        // (may be empty if no analyzers are registered)
        $results = $this->pipeline->runLayer($document, DocumentAnalysis::LAYER_EXTRACTION);

        $this->assertIsArray($results);
    }

    /** @test */
    public function document_analysis_model_has_required_constants(): void
    {
        $this->assertEquals('extraction', DocumentAnalysis::LAYER_EXTRACTION);
        $this->assertEquals('pattern', DocumentAnalysis::LAYER_PATTERN);
        $this->assertEquals('ai_basic', DocumentAnalysis::LAYER_AI_BASIC);
        $this->assertEquals('ai_deep', DocumentAnalysis::LAYER_AI_DEEP);

        $this->assertEquals('pending', DocumentAnalysis::STATUS_PENDING);
        $this->assertEquals('processing', DocumentAnalysis::STATUS_PROCESSING);
        $this->assertEquals('completed', DocumentAnalysis::STATUS_COMPLETED);
        $this->assertEquals('failed', DocumentAnalysis::STATUS_FAILED);

        $this->assertEquals('keywords', DocumentAnalysis::TYPE_KEYWORDS);
        $this->assertEquals('dates', DocumentAnalysis::TYPE_DATES);
        $this->assertEquals('entities', DocumentAnalysis::TYPE_ENTITIES);
        $this->assertEquals('statistics', DocumentAnalysis::TYPE_STATISTICS);
    }

    /** @test */
    public function document_analysis_can_be_created_and_updated(): void
    {
        $document = CaseDocument::factory()->create([
            'content' => 'Test content',
        ]);

        $analysis = DocumentAnalysis::create([
            'case_document_id' => $document->id,
            'analysis_type' => DocumentAnalysis::TYPE_KEYWORDS,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'status' => DocumentAnalysis::STATUS_PENDING,
            'version' => 1,
        ]);

        $this->assertDatabaseHas('document_analyses', [
            'case_document_id' => $document->id,
            'analysis_type' => DocumentAnalysis::TYPE_KEYWORDS,
            'status' => DocumentAnalysis::STATUS_PENDING,
        ]);

        // Test markProcessing
        $analysis->markProcessing();
        $this->assertEquals(DocumentAnalysis::STATUS_PROCESSING, $analysis->fresh()->status);

        // Test markCompleted
        $analysis->markCompleted(
            ['keywords' => ['test', 'content']],
            ['processing_time_seconds' => 0.5]
        );
        $freshAnalysis = $analysis->fresh();
        $this->assertEquals(DocumentAnalysis::STATUS_COMPLETED, $freshAnalysis->status);
        $this->assertEquals(['keywords' => ['test', 'content']], $freshAnalysis->results);
        $this->assertEquals(['processing_time_seconds' => 0.5], $freshAnalysis->metadata);

        // Test markFailed
        $analysis2 = DocumentAnalysis::create([
            'case_document_id' => $document->id,
            'analysis_type' => DocumentAnalysis::TYPE_DATES,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'status' => DocumentAnalysis::STATUS_PENDING,
            'version' => 1,
        ]);
        $analysis2->markFailed('Test error message');
        $freshAnalysis2 = $analysis2->fresh();
        $this->assertEquals(DocumentAnalysis::STATUS_FAILED, $freshAnalysis2->status);
        $this->assertEquals('Test error message', $freshAnalysis2->error_message);
    }

    /** @test */
    public function case_document_has_analyses_relationship(): void
    {
        $document = CaseDocument::factory()->create([
            'content' => 'Test content',
        ]);

        DocumentAnalysis::create([
            'case_document_id' => $document->id,
            'analysis_type' => DocumentAnalysis::TYPE_KEYWORDS,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
            'version' => 1,
        ]);

        $this->assertCount(1, $document->fresh()->analyses);
    }

    /** @test */
    public function case_document_has_completed_analysis_method(): void
    {
        $document = CaseDocument::factory()->create([
            'content' => 'Test content',
        ]);

        // Should return false when no analyses exist
        $this->assertFalse($document->hasCompletedAnalysis(DocumentAnalysis::TYPE_KEYWORDS));

        // Create a pending analysis
        DocumentAnalysis::create([
            'case_document_id' => $document->id,
            'analysis_type' => DocumentAnalysis::TYPE_KEYWORDS,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'status' => DocumentAnalysis::STATUS_PENDING,
            'version' => 1,
        ]);

        // Should still return false when analysis is not completed
        $this->assertFalse($document->fresh()->hasCompletedAnalysis(DocumentAnalysis::TYPE_KEYWORDS));

        // Update to completed
        $document->analyses()->update(['status' => DocumentAnalysis::STATUS_COMPLETED]);

        // Should return true when analysis is completed
        $this->assertTrue($document->fresh()->hasCompletedAnalysis(DocumentAnalysis::TYPE_KEYWORDS));
    }
}
