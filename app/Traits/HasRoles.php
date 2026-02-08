<?php

namespace App\Traits;

trait HasRoles
{
    /**
     * Check if user has a specific role
     */
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Check if user has any of the given roles
     */
    public function hasAnyRole(array $roles): bool
    {
        return in_array($this->role, $roles);
    }

    /**
     * Check if user is an admin
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Check if user is a lawyer (includes admin)
     */
    public function isLawyer(): bool
    {
        return in_array($this->role, ['lawyer', 'admin']);
    }

    /**
     * Check if user is an assistant (includes lawyer and admin)
     */
    public function isAssistant(): bool
    {
        return in_array($this->role, ['assistant', 'lawyer', 'admin']);
    }

    /**
     * Scope query to users with a specific role
     */
    public function scopeWithRole($query, string $role)
    {
        return $query->where('role', $role);
    }

    /**
     * Scope query to users with any of the given roles
     */
    public function scopeWithAnyRole($query, array $roles)
    {
        return $query->whereIn('role', $roles);
    }

    /**
     * Get all available roles
     */
    public static function getAvailableRoles(): array
    {
        return [
            'admin',
            'lawyer',
            'assistant',
            'client',
            'viewer',
        ];
    }
}
