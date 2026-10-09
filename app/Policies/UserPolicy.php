<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
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
    public function view(User $user, User $model): bool
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
    public function update(User $user, User $model): bool
    {
        if (! $user->role->hasAdminPanelAccess()) {
            return false;
        }

        if ($user->role === UserRole::CONVENTION_EDITOR && $model->role === UserRole::SUPER_ADMIN) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        if (! $user->role->hasAdminPanelAccess()) {
            return false;
        }

        if ($user->id === $model->id) {
            return false;
        }

        if ($user->role === UserRole::CONVENTION_EDITOR && $model->role === UserRole::SUPER_ADMIN) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        return $user->role->hasAdminPanelAccess() && $user->role === UserRole::SUPER_ADMIN;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        return $user->role->hasAdminPanelAccess() && $user->role === UserRole::SUPER_ADMIN && $user->id !== $model->id;
    }

    /**
     * Regra hierárquica: apenas super_admin pode atribuir o perfil SUPER_ADMIN.
     */
    public function assignRole(User $actor, UserRole|string $role): bool
    {
        if (! $actor->role->hasAdminPanelAccess()) {
            return false;
        }

        $roleEnum = is_string($role) ? UserRole::tryFrom($role) : $role;

        if ($roleEnum === UserRole::SUPER_ADMIN) {
            return $actor->role === UserRole::SUPER_ADMIN;
        }

        return true;
    }
}
