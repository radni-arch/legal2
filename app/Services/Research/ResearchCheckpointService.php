<?php

namespace App\Services\Research;

use App\Models\AgentRun;
use Illuminate\Support\Facades\Log;

/**
 * ResearchCheckpointService
 *
 * Manages checkpoint/resume capability for long-running research tasks.
 * Allows pausing and resuming research at iteration boundaries.
 */
class ResearchCheckpointService
{
    /**
     * Save a checkpoint for the current research state
     *
     * @param AgentRun $run The agent run to checkpoint
     * @param array $state Current research state (iterations, insights, etc.)
     * @return void
     */
    public function saveCheckpoint(AgentRun $run, array $state): void
    {
        try {
            $checkpointData = [
                'checkpoint_iteration' => $run->current_iteration,
                'checkpoint_timestamp' => now()->toIso8601String(),
                ...$state,
            ];

            $run->checkpoint_state = $checkpointData;
            $run->save();

            Log::info('ResearchCheckpointService: Checkpoint saved', [
                'run_id' => $run->id,
                'iteration' => $run->current_iteration,
            ]);

        } catch (\Exception $e) {
            Log::error('ResearchCheckpointService: Checkpoint save failed', [
                'run_id' => $run->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Restore research state from a checkpoint
     *
     * @param AgentRun $run The agent run to restore
     * @return array|null The restored state or null if no checkpoint exists
     */
    public function restoreCheckpoint(AgentRun $run): ?array
    {
        if (!$this->canResume($run)) {
            return null;
        }

        try {
            $state = $run->checkpoint_state;

            Log::info('ResearchCheckpointService: Checkpoint restored', [
                'run_id' => $run->id,
                'checkpoint_iteration' => $state['checkpoint_iteration'] ?? 'unknown',
            ]);

            return $state;

        } catch (\Exception $e) {
            Log::error('ResearchCheckpointService: Checkpoint restore failed', [
                'run_id' => $run->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Check if a run can be resumed from checkpoint
     *
     * @param AgentRun $run The agent run to check
     * @return bool True if resumable
     */
    public function canResume(AgentRun $run): bool
    {
        // Can resume if status is paused and has checkpoint state
        return $run->status === 'paused' && !empty($run->checkpoint_state);
    }

    /**
     * Clear a checkpoint
     *
     * @param AgentRun $run The agent run to clear
     * @return void
     */
    public function clearCheckpoint(AgentRun $run): void
    {
        try {
            $run->checkpoint_state = null;
            $run->save();

            Log::info('ResearchCheckpointService: Checkpoint cleared', [
                'run_id' => $run->id,
            ]);

        } catch (\Exception $e) {
            Log::error('ResearchCheckpointService: Checkpoint clear failed', [
                'run_id' => $run->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
