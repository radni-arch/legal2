<?php

namespace Tests\Unit\Events;

use App\Events\CaseDocumentIngested;
use App\Jobs\Analysis\RunDocumentExtractionJob;
use App\Listeners\TriggerDocumentAnalysis;
use App\Models\CaseDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CaseDocumentIngestedEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_case_document_ingested_event_contains_document_and_case_id(): void
    {
        $document = CaseDocument::factory()->create();
        $caseId = 'case-123';

        $event = new CaseDocumentIngested($document, $caseId);

        $this->assertEquals($document->id, $event->caseDocument->id);
        $this->assertEquals($caseId, $event->caseId);
    }

    public function test_event_can_be_dispatched(): void
    {
        Event::fake([CaseDocumentIngested::class]);

        $document = CaseDocument::factory()->create();
        $caseId = 'case-456';

        CaseDocumentIngested::dispatch($document, $caseId);

        Event::assertDispatched(CaseDocumentIngested::class, function ($event) use ($document, $caseId) {
            return $event->caseDocument->id === $document->id
                && $event->caseId === $caseId;
        });
    }

    public function test_listener_handles_event_and_dispatches_extraction_job(): void
    {
        Queue::fake([RunDocumentExtractionJob::class]);

        $document = CaseDocument::factory()->create();
        $caseId = 'case-789';
        $event = new CaseDocumentIngested($document, $caseId);

        $listener = new TriggerDocumentAnalysis();
        $listener->handle($event);

        Queue::assertPushed(RunDocumentExtractionJob::class, function ($job) use ($document, $caseId) {
            return $job->caseDocument->id === $document->id
                && $job->caseId === $caseId;
        });
    }

    public function test_listener_queues_on_analysis_queue(): void
    {
        $listener = new TriggerDocumentAnalysis();

        $this->assertEquals('analysis', $listener->queue);
    }

    public function test_event_is_registered_in_event_service_provider(): void
    {
        // Get the EventServiceProvider and check its $listen property
        $eventServiceProvider = app()->getProvider(\App\Providers\EventServiceProvider::class);

        // Use reflection to access the protected $listen property
        $reflection = new \ReflectionClass($eventServiceProvider);
        $listenProperty = $reflection->getProperty('listen');
        $listenProperty->setAccessible(true);
        $listen = $listenProperty->getValue($eventServiceProvider);

        // Check that CaseDocumentIngested is registered with TriggerDocumentAnalysis
        $this->assertArrayHasKey(CaseDocumentIngested::class, $listen);
        $this->assertContains(
            TriggerDocumentAnalysis::class,
            $listen[CaseDocumentIngested::class],
            'TriggerDocumentAnalysis listener is not registered for CaseDocumentIngested event'
        );
    }
}
