<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

abstract class BasePolicy
{
    use HandlesAuthorization;

    /**
     * Determine if user is admin
     */
    protected function isAdmin(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine if user is lawyer
     */
    protected function isLawyer(User $user): bool
    {
        return $user->hasRole('lawyer') || $user->hasRole('admin');
    }

    /**
     * Admin can do anything
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return null;
    }
}
