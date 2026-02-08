<?php

namespace App\Listeners;

use App\Events\CaseDocumentIngested;
use App\Jobs\Analysis\RunDocumentExtractionJob;
use Illuminate\Contracts\Queue\ShouldQueue;

class TriggerDocumentAnalysis implements ShouldQueue
{
    public string $queue = 'analysis';

    public function handle(CaseDocumentIngested $event): void
    {
        // Dispatch Layer 1 (deterministic extraction) immediately
        RunDocumentExtractionJob::dispatch($event->caseDocument, $event->caseId);
    }
}
