<?php

namespace App\Jobs;

use App\Agents\AutonomousResearchAgent;
use App\Events\ResearchCompleted;
use App\Events\ResearchFailed;
use App\Jobs\Concerns\HasQueuePriority;
use App\Models\AgentRun;
use App\Traits\BroadcastsJobProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * @deprecated since v1.5.0, will be removed in v2.0.0
 * @see \App\Jobs\ExecuteResearchJob Use ExecuteResearchJob instead
 *
 * Migration: Replace `RunAutonomousResearchJob::dispatch($runId)` with
 * `$researchService->researchAsync($objective, $options)` which handles
 * job dispatching internally.
 */
class RunAutonomousResearchJob implements ShouldQueue
{
    use BroadcastsJobProgress, Dispatchable, HasQueuePriority, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800; // 30 minutes max for research

    public int $tries = 1; // Don't retry failed research runs

    protected ?int $userId = null;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $runId,
        public bool $resuming = false,
        ?int $userId = null
    ) {
        $this->userId = $userId ?? auth()->id();
        // Autonomous AI agent research - use dedicated agents queue
        $this->onAgentsQueue();
    }

    public function getJobDisplayName(): string
    {
        return 'Autonomous Research';
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $run = AgentRun::findOrFail($this->runId);

        Log::info('RunAutonomousResearchJob - Starting', [
            'run_id' => $run->id,
            'objective' => $run->objective,
            'resuming' => $this->resuming,
        ]);

        $broadcastJobId = 'auto_research_' . $this->runId;

        try {
            if ($this->userId) {
                $this->broadcastStarted($this->userId, $broadcastJobId, [
                    'run_id' => $this->runId,
                    'resuming' => $this->resuming,
                ]);
            }

            // Check if we should resume BEFORE updating status
            $shouldResume = $this->resuming && $run->canBeResumed();

            // Update run with job information
            $run->update([
                'job_id' => $this->job?->getJobId(),
                'queue' => $this->queue ?? 'default',
                'status' => 'running',
            ]);

            // Resolve agent via container to allow test mocking and proper DI
            $agent = app(AutonomousResearchAgent::class);

            if ($shouldResume) {
                // Resume from checkpoint
                Log::info('RunAutonomousResearchJob - Resuming from checkpoint', [
                    'run_id' => $run->id,
                    'checkpoint_iteration' => $run->current_iteration,
                ]);
                $completed = $agent->resumeRun($run);
            } else {
                // Normal execution
                $completed = $agent->executeRun($run);
            }

            // Broadcast completion event
            event(new ResearchCompleted($completed));

            Log::info('RunAutonomousResearchJob - Completed successfully', [
                'run_id' => $run->id,
                'iterations' => $completed->current_iteration,
                'score' => $completed->score,
            ]);

            if ($this->userId) {
                $this->broadcastCompleted($this->userId, $broadcastJobId, [
                    'run_id' => $run->id,
                    'iterations' => $completed->current_iteration,
                    'score' => $completed->score,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('RunAutonomousResearchJob - Failed', [
                'run_id' => $run->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Update run status
            $run->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
                'completed_at' => now(),
                'elapsed_seconds' => $run->started_at ? (int) abs(now()->diffInSeconds($run->started_at)) : 0,
            ]);

            // Broadcast failure event
            event(new ResearchFailed($run, $e->getMessage()));

            if ($this->userId) {
                $this->broadcastFailed($this->userId, $broadcastJobId, $e->getMessage(), 'Autonomous Research');
            }

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $run = null;
        // Avoid invalid type errors if runId is not a numeric primary key
        if (is_numeric($this->runId)) {
            $run = AgentRun::find((int) $this->runId);
        }

        if ($run) {
            $run->update([
                'status' => 'failed',
                'error' => $exception->getMessage(),
                'completed_at' => now(),
                'elapsed_seconds' => $run->started_at ? (int) abs(now()->diffInSeconds($run->started_at)) : 0,
            ]);

            event(new ResearchFailed($run, $exception->getMessage()));
        }

        Log::error('RunAutonomousResearchJob - Job failed permanently', [
            'run_id' => $this->runId,
            'error' => $exception->getMessage(),
        ]);
    }
}
