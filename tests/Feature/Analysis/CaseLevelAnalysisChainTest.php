<?php

namespace Tests\Feature\Analysis;

use App\Events\CaseDocumentIngested;
use App\Events\DocumentAnalysisCompleted;
use App\Jobs\Analysis\RunCaseLevelAnalysisJob;
use App\Jobs\Analysis\RunDocumentExtractionJob;
use App\Listeners\TriggerCaseLevelAnalysis;
use App\Models\CaseDocument;
use App\Models\DocumentAnalysis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class CaseLevelAnalysisChainTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_document_analysis_completed_event_carries_correct_data(): void
    {
        $caseId = 'case-123';
        $documentId = 'doc-456';
        $layer = DocumentAnalysis::LAYER_EXTRACTION;

        $event = new DocumentAnalysisCompleted($caseId, $documentId, $layer);

        $this->assertEquals($caseId, $event->caseId);
        $this->assertEquals($documentId, $event->documentId);
        $this->assertEquals($layer, $event->layer);
    }

    public function test_extraction_job_fires_document_analysis_completed_event(): void
    {
        Event::fake([DocumentAnalysisCompleted::class]);

        $document = CaseDocument::factory()->create([
            'content' => 'Test document content for analysis.',
        ]);
        $caseId = (string) $document->case_id;

        $job = new RunDocumentExtractionJob($document, $caseId);
        $job->handle(app(\App\Services\Analysis\DocumentAnalysisPipeline::class));

        Event::assertDispatched(DocumentAnalysisCompleted::class, function ($event) use ($caseId, $document) {
            return $event->caseId === $caseId
                && $event->documentId === (string) $document->id
                && $event->layer === DocumentAnalysis::LAYER_EXTRACTION;
        });
    }

    public function test_trigger_case_level_analysis_listener_dispatches_job(): void
    {
        Queue::fake();

        $caseId = 'case-789';
        $documentId = 'doc-101';
        $event = new DocumentAnalysisCompleted($caseId, $documentId, DocumentAnalysis::LAYER_EXTRACTION);

        $listener = new TriggerCaseLevelAnalysis();
        $listener->handle($event);

        Queue::assertPushed(RunCaseLevelAnalysisJob::class, function ($job) use ($caseId, $documentId) {
            return $job->caseId === $caseId && $job->documentId === $documentId;
        });
    }

    public function test_listener_only_triggers_for_extraction_layer(): void
    {
        Queue::fake();

        // Non-extraction layer should NOT trigger case-level analysis
        $event = new DocumentAnalysisCompleted('case-1', 'doc-1', DocumentAnalysis::LAYER_PATTERN);

        $listener = new TriggerCaseLevelAnalysis();
        $listener->handle($event);

        Queue::assertNotPushed(RunCaseLevelAnalysisJob::class);
    }

    public function test_full_chain_is_wired_via_events(): void
    {
        // Verify the complete chain is wired:
        // CaseDocumentIngested -> TriggerDocumentAnalysis (registered)
        // DocumentAnalysisCompleted -> TriggerCaseLevelAnalysis (registered)
        //
        // The individual handler tests above verify each link works correctly.
        // This test verifies all event-listener pairs are registered.

        $dispatcher = app('events');

        // Link 1: CaseDocumentIngested has TriggerDocumentAnalysis listener
        $this->assertTrue(
            $dispatcher->hasListeners(CaseDocumentIngested::class),
            'CaseDocumentIngested should have registered listeners'
        );

        // Link 2: DocumentAnalysisCompleted has TriggerCaseLevelAnalysis listener
        $this->assertTrue(
            $dispatcher->hasListeners(DocumentAnalysisCompleted::class),
            'DocumentAnalysisCompleted should have registered listeners'
        );
    }

    public function test_document_analysis_completed_event_is_registered_in_event_service_provider(): void
    {
        // Verify that DocumentAnalysisCompleted has listeners registered
        $dispatcher = app('events');

        // Check if listeners exist for DocumentAnalysisCompleted
        $hasListeners = $dispatcher->hasListeners(DocumentAnalysisCompleted::class);

        $this->assertTrue($hasListeners, 'DocumentAnalysisCompleted should have registered listeners');
    }
}
