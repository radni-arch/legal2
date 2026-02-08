<?php

namespace App\Policies;

use App\Models\CourtDecision;
use App\Models\User;

class CourtDecisionPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        // All lawyers can view court decisions (public data)
        return $this->isLawyer($user);
    }

    public function view(User $user, CourtDecision $decision): bool
    {
        // Court decisions are public
        return true;
    }

    public function create(User $user): bool
    {
        // Only admin can create decisions (ingestion)
        return $this->isAdmin($user);
    }

    public function update(User $user, CourtDecision $decision): bool
    {
        // Only admin can update decisions
        return $this->isAdmin($user);
    }

    public function delete(User $user, CourtDecision $decision): bool
    {
        // Only admin can delete decisions
        return $this->isAdmin($user);
    }
}
