<?php

namespace App\Policies;

use App\Models\Statute;
use App\Models\User;

class StatutePolicy
{
    /**
     * Perform pre-authorization checks.
     */
    public function before(User $user, string $ability): ?bool
    {
        if (! $user->is_active) {
            return false;
        }

        return null;
    }

    /**
     * Determine whether the user can view any statutes.
     */
    public function viewAny(User $user): bool
    {
        return $user->role->hasAdminPanelAccess();
    }

    /**
     * Determine whether the user can view the statute.
     */
    public function view(User $user, Statute $statute): bool
    {
        return $user->role->hasAdminPanelAccess();
    }

    /**
     * Determine whether the user can create statutes.
     */
    public function create(User $user): bool
    {
        return $user->role->hasAdminPanelAccess();
    }

    /**
     * Determine whether the user can update the statute.
     */
    public function update(User $user, Statute $statute): bool
    {
        return $user->role->hasAdminPanelAccess();
    }

    /**
     * Determine whether the user can publish the statute.
     */
    public function publish(User $user, Statute $statute): bool
    {
        return $user->role->isSuperAdmin();
    }

    /**
     * Determine whether the user can archive the statute.
     */
    public function archive(User $user, Statute $statute): bool
    {
        return $user->role->isSuperAdmin();
    }

    /**
     * Determine whether the user can fork the statute into a new draft.
     */
    public function fork(User $user, Statute $statute): bool
    {
        return $user->role->hasAdminPanelAccess();
    }

    /**
     * Determine whether the user can delete the statute.
     */
    public function delete(User $user, Statute $statute): bool
    {
        if ($statute->isPublished()) {
            return false;
        }

        return $user->role->isSuperAdmin();
    }

    /**
     * Determine whether the user can restore the statute.
     */
    public function restore(User $user, Statute $statute): bool
    {
        return $user->role->isSuperAdmin();
    }

    /**
     * Determine whether the user can permanently delete the statute.
     */
    public function forceDelete(User $user, Statute $statute): bool
    {
        return $user->role->isSuperAdmin();
    }
}
