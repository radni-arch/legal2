<?php

namespace App\Jobs;

use App\Agents\AutonomousResearchAgent;
use App\Models\AgentRun;
use App\Traits\BroadcastsJobProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Background job for executing autonomous agent research runs
 *
 * @deprecated since v1.5.0, will be removed in v2.0.0
 * @see \App\Jobs\ExecuteResearchJob Use ExecuteResearchJob instead
 *
 * Migration: Replace `ExecuteAgentResearch::dispatch($run)` with
 * `$researchService->researchAsync($objective, $options)` which handles
 * job dispatching internally.
 */
class ExecuteAgentResearch implements ShouldQueue
{
    use BroadcastsJobProgress, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout;

    /**
     * The agent run to execute
     */
    public AgentRun $run;

    protected ?int $userId = null;

    /**
     * Create a new job instance.
     */
    public function __construct(AgentRun $run, ?int $userId = null)
    {
        $this->run = $run;
        $this->userId = $userId ?? auth()->id();

        // Set timeout based on run's time limit, with safety buffer
        $this->timeout = $run->time_limit_seconds
            ? $run->time_limit_seconds + 60
            : config('agent.safety.max_time_per_run', 3600) + 60;

        // Set queue if specified
        if ($queue = config('agent.queue.name')) {
            $this->onQueue($queue);
        }
    }

    public function getJobDisplayName(): string
    {
        return 'Agent Research: ' . mb_substr($this->run->objective ?? 'Research', 0, 50);
    }

    /**
     * Execute the job.
     */
    public function handle(AutonomousResearchAgent $agent): void
    {
        Log::info('Starting async agent research', [
            'job_id' => $this->job?->getJobId(),
            'run_id' => $this->run->id,
            'objective' => $this->run->objective,
        ]);

        $broadcastJobId = 'agent_research_' . $this->run->id;

        try {
            if ($this->userId) {
                $this->broadcastStarted($this->userId, $broadcastJobId, [
                    'run_id' => $this->run->id,
                    'objective' => $this->run->objective,
                ]);
            }

            // Check if run is still in running status
            $this->run->refresh();

            if ($this->run->status !== 'running') {
                Log::warning('Agent run is not in running status, skipping', [
                    'run_id' => $this->run->id,
                    'status' => $this->run->status,
                ]);

                return;
            }

            // Execute the research
            $agent->executeRun($this->run);

            Log::info('Async agent research completed', [
                'run_id' => $this->run->id,
                'status' => $this->run->fresh()->status,
                'score' => $this->run->fresh()->score,
            ]);

            if ($this->userId) {
                $this->broadcastCompleted($this->userId, $broadcastJobId, [
                    'run_id' => $this->run->id,
                    'status' => $this->run->fresh()->status,
                    'score' => $this->run->fresh()->score,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Async agent research failed', [
                'run_id' => $this->run->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Update run with error
            $this->run->update([
                'status' => 'failed',
                'error' => 'Job execution failed: '.$e->getMessage(),
                'completed_at' => now(),
                'elapsed_seconds' => $this->run->started_at
                    ? (int) abs(now()->diffInSeconds($this->run->started_at))
                    : 0,
            ]);

            if ($this->userId) {
                $this->broadcastFailed($this->userId, $broadcastJobId, $e->getMessage(), 'Agent Research');
            }

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Agent research job failed permanently', [
            'run_id' => $this->run->id,
            'error' => $exception->getMessage(),
        ]);

        // Update run status
        $this->run->update([
            'status' => 'failed',
            'error' => 'Job failed: '.$exception->getMessage(),
            'completed_at' => now(),
            'elapsed_seconds' => $this->run->started_at
                ? (int) abs(now()->diffInSeconds($this->run->started_at))
                : 0,
        ]);
    }

    /**
     * Get the tags for the job.
     */
    public function tags(): array
    {
        return [
            'agent:research',
            'run:'.$this->run->id,
            'agent:'.($this->run->agent_name ?? 'unknown'),
        ];
    }
}
