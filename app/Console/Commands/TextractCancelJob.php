<?php

namespace App\Console\Commands;

use App\Models\TextractJob;
use App\Services\TextractService;
use Illuminate\Console\Command;

/**
 * Cancel a Textract job in progress
 *
 * Marks the job as cancelled and attempts to stop AWS processing.
 */
class TextractCancelJob extends Command
{
    protected $signature = 'textract:cancel-job {jobId : The TextractJob ID to cancel}';

    protected $description = 'Cancel a Textract job in progress';

    public function __construct(
        protected TextractService $textractService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $jobId = $this->argument('jobId');

        // Find the job
        $job = TextractJob::find($jobId);

        if (! $job) {
            $this->error("Job not found: {$jobId}");

            return Command::FAILURE;
        }

        // Check if job can be cancelled
        $cancellableStatuses = ['queued', 'in_progress', 'analyzing'];

        if (! in_array($job->status, $cancellableStatuses)) {
            $this->error("Job {$jobId} has status '{$job->status}' and cannot be cancelled");

            return Command::FAILURE;
        }

        $this->info("Cancelling Textract job: {$jobId}");
        $this->info("AWS Job ID: {$job->job_id}");

        try {
            // Call the service to cancel on AWS side (if job_id exists)
            if ($job->job_id) {
                $this->textractService->cancelJob($job->job_id);
            }

            // Mark job as cancelled in database
            $job->update([
                'status' => 'cancelled',
                'error' => 'Job cancelled by user',
            ]);

            $this->info('Job cancelled successfully');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to cancel job: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }
}
