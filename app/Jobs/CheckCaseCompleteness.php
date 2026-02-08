<?php

namespace App\Jobs;

use App\Events\CaseReadyForReview;
use App\Models\TextractJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Task 3.3: Case-Complete Trigger
 *
 * Checks if all TextractJobs for a case have completed graph sync.
 * Fires CaseReadyForReview event when all jobs are synced.
 */
class CheckCaseCompleteness implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $caseId
    ) {}

    public function handle(): void
    {
        Log::info('CheckCaseCompleteness: Checking case', [
            'case_id' => $this->caseId,
        ]);

        // Check if there are any non-synced, non-failed jobs for this case
        $pendingJobs = TextractJob::where('case_id', $this->caseId)
            ->where('status', '!=', 'failed')
            ->where('graph_sync_status', '!=', 'synced')
            ->count();

        if ($pendingJobs === 0) {
            Log::info('CheckCaseCompleteness: All jobs synced, firing event', [
                'case_id' => $this->caseId,
            ]);

            CaseReadyForReview::dispatch($this->caseId);
        } else {
            Log::info('CheckCaseCompleteness: Jobs still pending', [
                'case_id' => $this->caseId,
                'pending_count' => $pendingJobs,
            ]);
        }
    }
}
