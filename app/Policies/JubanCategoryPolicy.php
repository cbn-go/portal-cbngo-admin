<?php

namespace App\Policies;

use App\Models\JubanCategory;
use App\Models\User;

class JubanCategoryPolicy
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
     * Determine whether the user can view any categories.
     */
    public function viewAny(User $user): bool
    {
        return $user->role->canViewJubanTreasury();
    }

    /**
     * Determine whether the user can view the category.
     */
    public function view(User $user, JubanCategory $category): bool
    {
        return $user->role->canViewJubanTreasury();
    }

    /**
     * Determine whether the user can create categories.
     */
    public function create(User $user): bool
    {
        return $user->role->canManageJubanTreasury();
    }

    /**
     * Determine whether the user can update the category.
     */
    public function update(User $user, JubanCategory $category): bool
    {
        return $user->role->canManageJubanTreasury();
    }

    /**
     * Determine whether the user can delete the category.
     * Secretary is strictly forbidden from deleting.
     * Categories with existing transactions cannot be deleted.
     */
    public function delete(User $user, JubanCategory $category): bool
    {
        if (! ($user->role->isSuperAdmin() || $user->role->isJubanTreasurer())) {
            return false;
        }

        if ($category->exists && $category->transactions()->exists()) {
            return false;
        }

        return true;
    }
}
