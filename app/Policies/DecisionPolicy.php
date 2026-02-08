<?php

namespace App\Policies;

use App\Models\CourtDecision;
use App\Models\User;

class DecisionPolicy
{
    /**
     * Determine whether the user can view any models.
     * Court decisions are public data from odluke.sudovi.hr
     */
    public function viewAny(User $user): bool
    {
        // All authenticated users can view court decisions
        return true;
    }

    /**
     * Determine whether the user can view the model.
     * Court decisions are public data from odluke.sudovi.hr
     */
    public function view(User $user, CourtDecision $courtDecision): bool
    {
        // All authenticated users can view court decisions
        return true;
    }

    /**
     * Determine whether the user can create models.
     * Only admins can manually create decisions (usually imported via agents)
     */
    public function create(User $user): bool
    {
        // Only admins can create court decisions
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can update the model.
     * Only admins can update decisions
     */
    public function update(User $user, CourtDecision $courtDecision): bool
    {
        // Only admins can update court decisions
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can delete the model.
     * Only admins can delete decisions
     */
    public function delete(User $user, CourtDecision $courtDecision): bool
    {
        // Only admins can delete court decisions
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, CourtDecision $courtDecision): bool
    {
        return $this->delete($user, $courtDecision);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, CourtDecision $courtDecision): bool
    {
        // Only admin can force delete
        return $user->role === 'admin';
    }
}
