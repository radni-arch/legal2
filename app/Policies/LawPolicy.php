<?php

namespace App\Policies;

use App\Models\Law;
use App\Models\User;

class LawPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        // All lawyers can view laws (public data)
        return $this->isLawyer($user);
    }

    public function view(User $user, Law $law): bool
    {
        // Laws are public
        return true;
    }

    public function create(User $user): bool
    {
        // Only admin can create laws (ingestion)
        return $this->isAdmin($user);
    }

    public function update(User $user, Law $law): bool
    {
        // Only admin can update laws
        return $this->isAdmin($user);
    }

    public function delete(User $user, Law $law): bool
    {
        // Only admin can delete laws
        return $this->isAdmin($user);
    }
}
