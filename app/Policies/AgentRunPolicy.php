<?php

namespace App\Policies;

use App\Models\AgentRun;
use App\Models\User;

class AgentRunPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // All authenticated users can view list of agent runs (filtered by their access)
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, AgentRun $agentRun): bool
    {
        // Admin can view any agent run
        if ($user->role === 'admin') {
            return true;
        }

        // Owner can view their own agent run
        if ($agentRun->user_id === $user->id) {
            return true;
        }

        // If no owner set (legacy runs), only admin can view
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Viewers cannot start agent runs
        if ($user->role === 'viewer') {
            return false;
        }

        // Lawyers, assistants, and admins can start agent runs
        return in_array($user->role, ['lawyer', 'assistant', 'admin']);
    }

    /**
     * Determine whether the user can update the model.
     * Only specific updates like pausing/resuming
     */
    public function update(User $user, AgentRun $agentRun): bool
    {
        // Admin can update any agent run
        if ($user->role === 'admin') {
            return true;
        }

        // Owner can update their own agent run (pause, resume, etc.)
        return $agentRun->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, AgentRun $agentRun): bool
    {
        // Admin can delete any agent run
        if ($user->role === 'admin') {
            return true;
        }

        // Owner can delete their own agent run
        return $agentRun->user_id === $user->id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, AgentRun $agentRun): bool
    {
        return $this->delete($user, $agentRun);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, AgentRun $agentRun): bool
    {
        // Only admin can force delete
        return $user->role === 'admin';
    }
}
