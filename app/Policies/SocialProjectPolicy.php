<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\SocialProject;
use App\Models\User;

/**
 * Projetos sociais: o representante gerencia os projetos da própria igreja e
 * envia para revisão; a administração gerencia todos e aprova a publicação.
 */
class SocialProjectPolicy
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
        if ($user->role->hasAdminPanelAccess()) {
            return true;
        }

        return $user->role === UserRole::CHURCH_REPRESENTATIVE && $user->church_id !== null;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, SocialProject $project): bool
    {
        if ($user->role->hasAdminPanelAccess()) {
            return true;
        }

        return $user->role === UserRole::CHURCH_REPRESENTATIVE
            && $user->church_id !== null
            && $project->church_id === $user->church_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        if ($user->role->hasAdminPanelAccess()) {
            return true;
        }

        return $user->role === UserRole::CHURCH_REPRESENTATIVE && $user->church_id !== null;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, SocialProject $project): bool
    {
        if ($user->role->hasAdminPanelAccess()) {
            return true;
        }

        return $user->role === UserRole::CHURCH_REPRESENTATIVE
            && $user->church_id !== null
            && $project->church_id === $user->church_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SocialProject $project): bool
    {
        if ($user->role->hasAdminPanelAccess()) {
            return true;
        }

        return $user->role === UserRole::CHURCH_REPRESENTATIVE
            && $user->church_id !== null
            && $project->church_id === $user->church_id;
    }

    /**
     * Aprovar a publicação ou devolver o projeto para ajustes.
     */
    public function approve(User $user, SocialProject $project): bool
    {
        return $user->role->hasAdminPanelAccess();
    }

    /**
     * Marcar ou remover o destaque do projeto no portal.
     */
    public function feature(User $user, SocialProject $project): bool
    {
        return $user->role->hasAdminPanelAccess();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, SocialProject $project): bool
    {
        return $user->role->hasAdminPanelAccess();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, SocialProject $project): bool
    {
        return $user->role->hasAdminPanelAccess();
    }
}
