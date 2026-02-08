<?php

namespace App\Policies;

use App\Models\CaseDocument;
use App\Models\User;

class DocumentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // All authenticated users can view list of documents (filtered by their case access)
        return true;
    }

    /**
     * Determine whether the user can view the model.
     * Documents inherit permissions from their parent case.
     */
    public function view(User $user, CaseDocument $caseDocument): bool
    {
        // Load the case if not already loaded
        if (! $caseDocument->relationLoaded('case')) {
            $caseDocument->load('case');
        }

        $case = $caseDocument->case;

        // If no case, only admin can view
        if (! $case) {
            return $user->role === 'admin';
        }

        // Use case policy to determine access
        return $user->can('view', $case);
    }

    /**
     * Determine whether the user can create models.
     * Must be able to update the parent case.
     */
    public function create(User $user): bool
    {
        // Viewers cannot create documents
        if ($user->role === 'viewer') {
            return false;
        }

        // Lawyers, assistants, and admins can create documents
        return in_array($user->role, ['lawyer', 'assistant', 'admin']);
    }

    /**
     * Determine whether the user can update the model.
     * Must be able to update the parent case.
     */
    public function update(User $user, CaseDocument $caseDocument): bool
    {
        // Load the case if not already loaded
        if (! $caseDocument->relationLoaded('case')) {
            $caseDocument->load('case');
        }

        $case = $caseDocument->case;

        // If no case, only admin can update
        if (! $case) {
            return $user->role === 'admin';
        }

        // Use case policy to determine update access
        return $user->can('update', $case);
    }

    /**
     * Determine whether the user can delete the model.
     * Must be able to update the parent case.
     */
    public function delete(User $user, CaseDocument $caseDocument): bool
    {
        // Load the case if not already loaded
        if (! $caseDocument->relationLoaded('case')) {
            $caseDocument->load('case');
        }

        $case = $caseDocument->case;

        // If no case, only admin can delete
        if (! $case) {
            return $user->role === 'admin';
        }

        // Use case policy to determine delete access
        return $user->can('update', $case);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, CaseDocument $caseDocument): bool
    {
        return $this->delete($user, $caseDocument);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, CaseDocument $caseDocument): bool
    {
        // Only admin can force delete
        return $user->role === 'admin';
    }
}
