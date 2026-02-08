<?php

namespace App\Contracts\Services;

use App\Models\AgentRun;

/**
 * AgentRunDispatcherInterface
 *
 * Orchestrates autonomous agent run lifecycle including:
 * - Starting new research runs
 * - Resuming paused runs
 * - Pausing active runs
 */
interface AgentRunDispatcherInterface
{
    /**
     * Start a new autonomous research run
     *
     * Creates a new AgentRun record and dispatches execution
     * based on environment configuration (sync/async).
     *
     * @param  string  $objective  Research objective
     * @param  array  $context  Additional context and constraints
     * @param  int|null  $maxIterations  Maximum iterations allowed
     * @param  float|null  $targetQuality  Target quality score (0-100)
     * @param  int|null  $tokenBudget  Maximum tokens to consume
     * @param  bool  $async  Force async execution
     * @return AgentRun Created agent run instance
     */
    public function startResearch(
        string $objective,
        array $context = [],
        ?int $maxIterations = null,
        ?float $targetQuality = null,
        ?int $tokenBudget = null,
        bool $async = false
    ): AgentRun;

    /**
     * Resume a paused or failed research run
     *
     * @param  AgentRun  $run  Run to resume
     * @param  bool  $forceSync  Force synchronous execution
     * @return AgentRun Resumed agent run
     */
    public function resumeResearch(AgentRun $run, bool $forceSync = false): AgentRun;

    /**
     * Pause an active research run
     *
     * @param  AgentRun  $run  Run to pause
     * @param  string  $reason  Reason for pausing
     */
    public function pauseResearch(AgentRun $run, string $reason = 'User requested'): void;
}
