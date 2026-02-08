<?php

namespace App\Policies;

use App\Models\LegalCase;
use App\Models\User;

class LegalCasePolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isLawyer($user);
    }

    public function view(User $user, LegalCase $case): bool
    {
        // Can view if:
        // 1. Owns the case
        // 2. Is assigned to the case
        // 3. Is in the same team
        return $user->id === $case->user_id
            || $case->assignedUsers->contains($user)
            || ($user->team_id && $user->team_id === $case->team_id);
    }

    public function create(User $user): bool
    {
        return $this->isLawyer($user);
    }

    public function update(User $user, LegalCase $case): bool
    {
        return $user->id === $case->user_id
            || $case->assignedUsers->contains($user);
    }

    public function delete(User $user, LegalCase $case): bool
    {
        return $user->id === $case->user_id;
    }

    public function forceDelete(User $user, LegalCase $case): bool
    {
        return $this->isAdmin($user);
    }

    public function restore(User $user, LegalCase $case): bool
    {
        return $user->id === $case->user_id || $this->isAdmin($user);
    }
}
