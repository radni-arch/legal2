<?php

namespace App\Listeners;

use App\Events\CaseReadyForReview;
use App\Events\TextractJobGraphSynced;
use App\Models\TextractJob;
use Illuminate\Support\Facades\Log;

/**
 * Checks if all TextractJobs for a case have completed graph sync.
 *
 * When the last non-failed TextractJob for a case finishes graph sync,
 * this listener fires the CaseReadyForReview event to trigger
 * downstream case analysis workflows.
 */
class CheckCaseCompleteness
{
    /**
     * Handle the TextractJobGraphSynced event.
     */
    public function handle(TextractJobGraphSynced $event): void
    {
        $job = $event->textractJob;

        if (! $job->case_id) {
            return;
        }

        $incompleteExists = TextractJob::where('case_id', $job->case_id)
            ->where('graph_sync_status', '!=', 'synced')
            ->where('status', '!=', 'failed')
            ->exists();

        if (! $incompleteExists) {
            Log::info('All documents synced for case - triggering review', [
                'case_id' => $job->case_id,
            ]);

            CaseReadyForReview::dispatch($job->case_id);
        }
    }
}
