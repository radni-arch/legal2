<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * CaseReadyForReview
 *
 * Fired when all documents in a case have been fully processed
 * (i.e., all non-failed TextractJobs have graph_sync_status = 'synced').
 *
 * This event signals that the case is ready for comprehensive analysis
 * or human review, as all document processing pipelines have completed.
 *
 * Listeners can use this event to:
 * - Trigger automated case analysis
 * - Send notifications to case managers
 * - Generate case summary reports
 * - Initiate quality assurance workflows
 */
class CaseReadyForReview
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  string  $caseId  The unique identifier for the case that is ready for review
     */
    public function __construct(
        public string $caseId
    ) {}
}
