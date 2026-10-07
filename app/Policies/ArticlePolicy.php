<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Article;
use App\Models\User;

class ArticlePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        if (! $user->is_active) {
            return false;
        }

        return $user->role->hasAdminPanelAccess() || $user->role === UserRole::AUTHOR;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Article $article): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->role->hasAdminPanelAccess()) {
            return true;
        }

        if ($user->role === UserRole::AUTHOR) {
            return $article->user_id === $user->id;
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

        return $user->role->hasAdminPanelAccess() || $user->role === UserRole::AUTHOR;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Article $article): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->role->hasAdminPanelAccess()) {
            return true;
        }

        if ($user->role === UserRole::AUTHOR) {
            return $article->user_id === $user->id;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Article $article): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->role->hasAdminPanelAccess()) {
            return true;
        }

        if ($user->role === UserRole::AUTHOR) {
            return $article->user_id === $user->id;
        }

        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Article $article): bool
    {
        if (! $user->is_active) {
            return false;
        }

        return $user->role->hasAdminPanelAccess();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Article $article): bool
    {
        if (! $user->is_active) {
            return false;
        }

        return $user->role->hasAdminPanelAccess();
    }
}
