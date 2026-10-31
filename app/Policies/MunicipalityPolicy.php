<?php

namespace App\Policies;

use App\Models\Municipality;
use App\Models\User;

/**
 * Painel de Estratégia: leitura para SuperAdmin e editor da convenção.
 * Municípios e indicadores vêm do IBGE e só mudam pela sincronização.
 */
class MunicipalityPolicy
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
    public function view(User $user, Municipality $municipality): bool
    {
        return $user->role->hasAdminPanelAccess();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Municipality $municipality): bool
    {
        return false;
    }

    public function delete(User $user, Municipality $municipality): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
