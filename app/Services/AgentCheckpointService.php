<?php

namespace App\Services;

use App\Models\AgentRun;
use Illuminate\Support\Facades\Log;

class AgentCheckpointService
{
    /**
     * Save a checkpoint for an agent run
     */
    public function saveCheckpoint(AgentRun $run, array $additionalState = []): void
    {
        $checkpointState = [
            'iterations' => $run->iterations ?? [],
            'current_iteration' => $run->current_iteration,
            'tokens_used' => $run->tokens_used,
            'cost_spent' => $run->cost_spent,
            'elapsed_seconds' => $run->started_at ? now()->diffInSeconds($run->started_at) : 0,
            'checkpoint_version' => '1.0',
            'timestamp' => now()->toIso8601String(),
        ];

        // Merge any additional state
        $checkpointState = array_merge($checkpointState, $additionalState);

        $run->update([
            'checkpoint_state' => $checkpointState,
            'last_checkpoint_at' => now(),
            'can_resume' => true,
        ]);

        Log::info('AgentCheckpointService - Checkpoint saved', [
            'run_id' => $run->id,
            'iteration' => $run->current_iteration,
            'size' => strlen(json_encode($checkpointState)),
        ]);
    }

    /**
     * Restore state from a checkpoint
     */
    public function restoreCheckpoint(AgentRun $run): ?array
    {
        if (! $run->can_resume || ! $run->checkpoint_state) {
            Log::warning('AgentCheckpointService - Cannot restore checkpoint', [
                'run_id' => $run->id,
                'can_resume' => $run->can_resume,
                'has_checkpoint' => ! empty($run->checkpoint_state),
            ]);

            return null;
        }

        Log::info('AgentCheckpointService - Restoring checkpoint', [
            'run_id' => $run->id,
            'checkpoint_iteration' => $run->checkpoint_state['current_iteration'] ?? 0,
        ]);

        return $run->checkpoint_state;
    }

    /**
     * Clear checkpoint data
     */
    public function clearCheckpoint(AgentRun $run): void
    {
        $run->update([
            'checkpoint_state' => null,
            'last_checkpoint_at' => null,
            'can_resume' => false,
        ]);

        Log::info('AgentCheckpointService - Checkpoint cleared', [
            'run_id' => $run->id,
        ]);
    }

    /**
     * Pause a run and save checkpoint
     */
    public function pauseRun(AgentRun $run, string $reason = 'User requested'): void
    {
        $this->saveCheckpoint($run, [
            'pause_reason' => $reason,
            'paused_at' => now()->toIso8601String(),
        ]);

        $run->update([
            'status' => 'paused',
        ]);

        Log::info('AgentCheckpointService - Run paused', [
            'run_id' => $run->id,
            'reason' => $reason,
        ]);
    }
}
