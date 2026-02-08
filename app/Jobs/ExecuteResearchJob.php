<?php

namespace App\Jobs;

use App\Models\AgentRun;
use App\Services\ResearchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Execute Research Job
 *
 * Queued job for executing research tasks asynchronously.
 * This is the new job that replaces ExecuteAgentResearch and RunAutonomousResearchJob.
 *
 * @see \App\Services\ResearchService
 */
class ExecuteResearchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Number of seconds to wait before retrying.
     */
    public int $backoff = 60;

    /**
     * Maximum execution time in seconds (1 hour).
     */
    public int $timeout = 3600;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $runId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(ResearchService $service): void
    {
        $run = AgentRun::find($this->runId);

        if (! $run) {
            Log::warning('ExecuteResearchJob: Run not found', ['run_id' => $this->runId]);

            return;
        }

        // Skip if not pending
        if ($run->status !== 'pending') {
            Log::info('ExecuteResearchJob: Skipping non-pending run', [
                'run_id' => $this->runId,
                'status' => $run->status,
            ]);

            return;
        }

        Log::info('ExecuteResearchJob: Starting research', ['run_id' => $this->runId]);

        // Execute research on existing run (no duplicate AgentRun created)
        $service->executeRun($run);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ExecuteResearchJob: Job failed', [
            'run_id' => $this->runId,
            'error' => $exception->getMessage(),
        ]);

        $run = AgentRun::find($this->runId);
        if ($run) {
            $run->update([
                'status' => 'failed',
                'error' => $exception->getMessage(),
                'completed_at' => now(),
            ]);
        }
    }
}
