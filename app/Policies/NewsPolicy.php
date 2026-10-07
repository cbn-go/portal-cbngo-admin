<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\News;
use App\Models\User;

class NewsPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->role->hasAdminPanelAccess()) {
            return true;
        }

        return $user->role === UserRole::CHURCH_REPRESENTATIVE && $user->church_id !== null;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, News $news): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->role->hasAdminPanelAccess()) {
            return true;
        }

        if ($user->role === UserRole::CHURCH_REPRESENTATIVE && $user->church_id !== null) {
            return $news->church_id === $user->church_id;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->role->hasAdminPanelAccess()) {
            return true;
        }

        return $user->role === UserRole::CHURCH_REPRESENTATIVE && $user->church_id !== null;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, News $news): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->role->hasAdminPanelAccess()) {
            return true;
        }

        if ($user->role === UserRole::CHURCH_REPRESENTATIVE && $user->church_id !== null) {
            return $news->church_id === $user->church_id;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, News $news): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->role->hasAdminPanelAccess()) {
            return true;
        }

        if ($user->role === UserRole::CHURCH_REPRESENTATIVE && $user->church_id !== null) {
            return $news->church_id === $user->church_id;
        }

        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, News $news): bool
    {
        if (! $user->is_active) {
            return false;
        }

        return $user->role->hasAdminPanelAccess();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, News $news): bool
    {
        if (! $user->is_active) {
            return false;
        }

        return $user->role->hasAdminPanelAccess();
    }
}
