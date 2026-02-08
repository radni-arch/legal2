<?php

namespace App\Services;

use App\Agents\AutonomousResearchAgent;
use App\Contracts\Services\AgentRunDispatcherInterface;
use App\Jobs\RunAutonomousResearchJob;
use App\Models\AgentRun;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

class AgentRunDispatcher implements AgentRunDispatcherInterface
{
    /**
     * Start a new research run (async in production, sync on localhost)
     *
     * @param  string  $objective  Research objective
     * @param  array  $context  Additional context
     * @param  array  $constraints  Budget and time constraints
     * @param  bool  $forceSync  Force synchronous execution
     * @return AgentRun The created run
     */
    public function startResearch(
        string $objective,
        array $context = [],
        array $constraints = [],
        bool $forceSync = false
    ): AgentRun {
        $agent = new AutonomousResearchAgent;
        $run = $agent->startRun($objective, $context, $constraints);

        $this->dispatch($run, $forceSync);

        return $run->fresh();
    }

    /**
     * Resume a paused research run
     *
     * @param  AgentRun  $run  The run to resume
     * @param  bool  $forceSync  Force synchronous execution
     * @return AgentRun The resumed run
     */
    public function resumeResearch(AgentRun $run, bool $forceSync = false): AgentRun
    {
        if (! $run->canBeResumed()) {
            throw new \Exception('Run cannot be resumed');
        }

        $this->dispatch($run, $forceSync, true);

        return $run->fresh();
    }

    /**
     * Dispatch the research job (sync or async based on environment)
     *
     * @param  AgentRun  $run  The run to execute
     * @param  bool  $forceSync  Force synchronous execution
     * @param  bool  $resuming  Whether this is a resume operation
     */
    protected function dispatch(AgentRun $run, bool $forceSync = false, bool $resuming = false): void
    {
        $shouldRunSync = $forceSync || $this->shouldRunSynchronously();

        if ($shouldRunSync) {
            Log::info('AgentRunDispatcher - Running synchronously', [
                'run_id' => $run->id,
                'environment' => App::environment(),
            ]);

            // Run synchronously (blocks until complete)
            $agent = new AutonomousResearchAgent;

            try {
                if ($resuming) {
                    $agent->resumeRun($run);
                } else {
                    $agent->executeRun($run);
                }
            } catch (\Exception $e) {
                Log::error('AgentRunDispatcher - Synchronous execution failed', [
                    'run_id' => $run->id,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }
        } else {
            Log::info('AgentRunDispatcher - Dispatching to queue', [
                'run_id' => $run->id,
                'environment' => App::environment(),
            ]);

            // Dispatch to queue (returns immediately)
            RunAutonomousResearchJob::dispatch($run->id, $resuming);
        }
    }

    /**
     * Determine if we should run synchronously
     * Runs sync on localhost, async in production
     */
    protected function shouldRunSynchronously(): bool
    {
        // Check environment
        if (App::environment('local')) {
            return true;
        }

        // Check if we're on localhost
        if (isset($_SERVER['HTTP_HOST'])) {
            $host = $_SERVER['HTTP_HOST'];
            if (str_contains($host, 'localhost') || str_contains($host, '127.0.0.1')) {
                return true;
            }
        }

        // Check config override
        return config('agent.force_sync', false);
    }

    /**
     * Pause a running research job
     *
     * @param  AgentRun  $run  The run to pause
     * @param  string  $reason  Reason for pausing
     */
    public function pauseResearch(AgentRun $run, string $reason = 'User requested'): void
    {
        if ($run->status !== 'running') {
            throw new \Exception('Can only pause running research');
        }

        $checkpoint = app(AgentCheckpointService::class);
        $checkpoint->pauseRun($run, $reason);

        Log::info('AgentRunDispatcher - Research paused', [
            'run_id' => $run->id,
            'reason' => $reason,
        ]);
    }
}
