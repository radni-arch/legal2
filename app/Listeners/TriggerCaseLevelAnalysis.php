<?php

namespace App\Listeners;

use App\Events\DocumentAnalysisCompleted;
use App\Jobs\Analysis\RunCaseLevelAnalysisJob;
use App\Models\DocumentAnalysis;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Listens for DocumentAnalysisCompleted events and dispatches
 * case-level AI analysis when Layer 1 (extraction) completes.
 */
class TriggerCaseLevelAnalysis implements ShouldQueue
{
    public string $queue = 'analysis';

    public function handle(DocumentAnalysisCompleted $event): void
    {
        // Only trigger case-level analysis after extraction layer completes
        if ($event->layer !== DocumentAnalysis::LAYER_EXTRACTION) {
            return;
        }

        RunCaseLevelAnalysisJob::dispatch($event->caseId, $event->documentId);
    }
}
