<?php

namespace Tests\Unit\Jobs\Analysis;

use App\Jobs\Analysis\RunDocumentExtractionJob;
use App\Models\CaseDocument;
use App\Models\DocumentAnalysis;
use App\Services\Analysis\DocumentAnalysisPipeline;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class RunDocumentExtractionJobTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_can_be_dispatched(): void
    {
        Queue::fake();

        $document = CaseDocument::factory()->create([
            'content' => 'Test document content',
        ]);
        $caseId = $document->case_id;

        RunDocumentExtractionJob::dispatch($document, $caseId);

        Queue::assertPushed(RunDocumentExtractionJob::class);
    }

    public function test_it_uses_analysis_queue(): void
    {
        $document = CaseDocument::factory()->create([
            'content' => 'Test document content',
        ]);

        $job = new RunDocumentExtractionJob($document, $document->case_id);

        $this->assertEquals('analysis', $job->queue);
    }

    public function test_it_has_retry_configuration(): void
    {
        $document = CaseDocument::factory()->create([
            'content' => 'Test document content',
        ]);

        $job = new RunDocumentExtractionJob($document, $document->case_id);

        $this->assertEquals(3, $job->tries);
        $this->assertEquals(30, $job->backoff);
    }

    public function test_it_calls_pipeline_run_layer(): void
    {
        $document = CaseDocument::factory()->create([
            'content' => 'Test document content for analysis.',
        ]);

        $mockPipeline = Mockery::mock(DocumentAnalysisPipeline::class);
        $mockPipeline->shouldReceive('runLayer')
            ->once()
            ->with(
                Mockery::on(fn($doc) => $doc->id === $document->id),
                DocumentAnalysis::LAYER_EXTRACTION
            )
            ->andReturn([]);

        $job = new RunDocumentExtractionJob($document, $document->case_id);
        $job->handle($mockPipeline);

        // If we get here without exception, the test passes
        $this->assertTrue(true);
    }

    public function test_it_runs_layer_extraction_on_document(): void
    {
        $document = CaseDocument::factory()->create([
            'content' => 'Dokument sadrzi kljucne rijeci i dokaze koji su bitni za ovaj slucaj.',
        ]);

        $pipeline = new DocumentAnalysisPipeline();
        $job = new RunDocumentExtractionJob($document, $document->case_id);
        $job->handle($pipeline);

        // Verify that DocumentAnalysis records were created
        $this->assertDatabaseHas('document_analyses', [
            'case_document_id' => $document->id,
            'analysis_type' => DocumentAnalysis::TYPE_KEYWORDS,
            'analysis_layer' => DocumentAnalysis::LAYER_EXTRACTION,
            'status' => DocumentAnalysis::STATUS_COMPLETED,
        ]);
    }
}
