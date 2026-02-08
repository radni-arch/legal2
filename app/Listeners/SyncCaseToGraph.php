<?php

namespace App\Listeners;

use App\Events\CaseUploaded;
use App\Jobs\SyncGraphDataJob;
use Illuminate\Support\Facades\Log;

class SyncCaseToGraph
{
    /**
     * Handle the event.
     */
    public function handle(CaseUploaded $event): void
    {
        if (! config('neo4j.sync.enabled')) {
            Log::debug('Neo4j sync disabled, skipping case sync', ['case_doc_id' => $event->caseDocId]);

            return;
        }

        if (config('neo4j.sync.auto_sync_on_ingest', true)) {
            Log::info('Auto-syncing case to graph', ['case_doc_id' => $event->caseDocId]);

            SyncGraphDataJob::dispatchWithEnvDetection('case', $event->caseDocId);
        }
    }
}
