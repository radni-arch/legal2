<?php

namespace App\Policies;

use App\Models\LegalCase;
use App\Models\User;

class CasePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // All authenticated users can view list of cases (filtered by their access)
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, LegalCase $legalCase): bool
    {
        // Admin can view any case
        if ($user->role === 'admin') {
            return true;
        }

        // Owner can view their own case
        if ($legalCase->user_id === $user->id) {
            return true;
        }

        // Team members can view cases in their team
        if ($legalCase->team_id && $legalCase->team_id === $user->team_id) {
            return true;
        }

        // Assigned users can view the case
        if ($legalCase->assignedUsers()->where('user_id', $user->id)->exists()) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Viewers cannot create cases
        if ($user->role === 'viewer') {
            return false;
        }

        // Lawyers, assistants, and admins can create cases
        return in_array($user->role, ['lawyer', 'assistant', 'admin']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, LegalCase $legalCase): bool
    {
        // Admin can update any case
        if ($user->role === 'admin') {
            return true;
        }

        // Viewers cannot update
        if ($user->role === 'viewer') {
            return false;
        }

        // Owner can update their own case
        if ($legalCase->user_id === $user->id) {
            return true;
        }

        // Team members can update cases in their team
        if ($legalCase->team_id && $legalCase->team_id === $user->team_id) {
            return true;
        }

        // Assigned users can update the case
        if ($legalCase->assignedUsers()->where('user_id', $user->id)->exists()) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, LegalCase $legalCase): bool
    {
        // Admin can delete any case
        if ($user->role === 'admin') {
            return true;
        }

        // Only the owner can delete the case
        return $legalCase->user_id === $user->id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, LegalCase $legalCase): bool
    {
        // Same logic as delete
        return $this->delete($user, $legalCase);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, LegalCase $legalCase): bool
    {
        // Only admin can force delete
        return $user->role === 'admin';
    }
}
