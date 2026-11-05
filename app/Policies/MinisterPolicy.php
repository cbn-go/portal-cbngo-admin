<?php

namespace App\Policies;

use App\Models\Minister;
use App\Models\User;

class MinisterPolicy
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

    public function viewAny(User $user): bool
    {
        return $user->role->hasAdminPanelAccess();
    }

    public function view(User $user, Minister $minister): bool
    {
        return $user->role->hasAdminPanelAccess();
    }

    public function create(User $user): bool
    {
        return $user->role->hasAdminPanelAccess();
    }

    public function update(User $user, Minister $minister): bool
    {
        return $user->role->hasAdminPanelAccess();
    }

    public function delete(User $user, Minister $minister): bool
    {
        return $user->role->hasAdminPanelAccess();
    }

    /**
     * Histórico oficial do rol: só o super administrador altera.
     */
    public function manageHistory(User $user, Minister $minister): bool
    {
        return $user->role->isSuperAdmin();
    }
}
