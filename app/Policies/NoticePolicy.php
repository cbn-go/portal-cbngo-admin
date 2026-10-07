<?php

namespace App\Policies;

use App\Models\Notice;
use App\Models\User;

class NoticePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        if (! $user->is_active) {
            return false;
        }

        return $user->role->hasAdminPanelAccess();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Notice $notice): bool
    {
        if (! $user->is_active) {
            return false;
        }

        return $user->role->hasAdminPanelAccess();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        if (! $user->is_active) {
            return false;
        }

        return $user->role->hasAdminPanelAccess();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Notice $notice): bool
    {
        if (! $user->is_active) {
            return false;
        }

        return $user->role->hasAdminPanelAccess();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Notice $notice): bool
    {
        if (! $user->is_active) {
            return false;
        }

        return $user->role->hasAdminPanelAccess();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Notice $notice): bool
    {
        if (! $user->is_active) {
            return false;
        }

        return $user->role->hasAdminPanelAccess();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Notice $notice): bool
    {
        if (! $user->is_active) {
            return false;
        }

        return $user->role->hasAdminPanelAccess();
    }
}
