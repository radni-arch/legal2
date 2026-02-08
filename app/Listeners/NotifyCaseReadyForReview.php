<?php

namespace App\Listeners;

use App\Events\CaseReadyForReview;
use App\Models\LegalCase;
use Illuminate\Support\Facades\Log;

/**
 * Handles the CaseReadyForReview event.
 *
 * This listener logs that a case is ready for review (all documents processed)
 * and optionally triggers notifications to the case owner.
 *
 * Future enhancements could include:
 * - Triggering automated case analysis
 * - Sending notifications via email/Slack
 * - Initiating quality assurance workflows
 */
class NotifyCaseReadyForReview
{
    /**
     * Handle the CaseReadyForReview event.
     */
    public function handle(CaseReadyForReview $event): void
    {
        $case = LegalCase::find($event->caseId);

        if (! $case) {
            Log::warning('CaseReadyForReview: Case not found', ['case_id' => $event->caseId]);

            return;
        }

        Log::info('Case ready for review - all documents processed', [
            'case_id' => $case->id,
            'case_name' => $case->title ?? $case->name ?? 'Unknown',
            'document_count' => $case->textractJobs()->count(),
        ]);

        // Notify case owner if exists and notifications enabled
        if ($case->user && config('textract.notifications.enabled', false)) {
            Log::info('Would notify user about case ready', ['user_id' => $case->user_id]);
            // Future: dispatch actual notification
            // $case->user->notify(new CaseReadyNotification($case));
        }
    }
}
