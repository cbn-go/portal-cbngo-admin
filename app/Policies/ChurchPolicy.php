<?php

namespace App\Policies;

use App\Models\Church;
use App\Models\User;

class ChurchPolicy
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
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->role->hasAdminPanelAccess();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Church $church): bool
    {
        return $user->role->hasAdminPanelAccess();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->role->hasAdminPanelAccess();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Church $church): bool
    {
        return $user->role->hasAdminPanelAccess();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Church $church): bool
    {
        if (! $user->role->hasAdminPanelAccess()) {
            return false;
        }

        if ($church->users()->exists() || $church->news()->exists()) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Church $church): bool
    {
        return $user->role->hasAdminPanelAccess();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Church $church): bool
    {
        return $user->role->hasAdminPanelAccess();
    }
}
