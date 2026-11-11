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
        return $user->role->hasAdminPanelAccess() || $user->role->isMinister();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Church $church): bool
    {
        if ($user->role->hasAdminPanelAccess()) {
            return true;
        }

        if ($user->role->isMinister()) {
            return $this->canMinisterAccessChurch($user, $church);
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        if ($user->role->hasAdminPanelAccess()) {
            return true;
        }

        if ($user->role->isMinister()) {
            return $user->contentChurchId() !== null;
        }

        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Church $church): bool
    {
        if ($user->role->hasAdminPanelAccess()) {
            return true;
        }

        if ($user->role->isMinister()) {
            return $this->canMinisterAccessChurch($user, $church);
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Church $church): bool
    {
        if ($user->role->hasAdminPanelAccess()) {
            if ($church->users()->exists() || $church->news()->exists()) {
                return false;
            }

            return true;
        }

        if ($user->role->isMinister() && $church->isMission()) {
            $churchId = $user->contentChurchId();
            if ($churchId !== null && $church->parent_church_id === $churchId) {
                if ($church->users()->exists() || $church->news()->exists()) {
                    return false;
                }

                return true;
            }
        }

        return false;
    }

    private function canMinisterAccessChurch(User $user, Church $church): bool
    {
        $churchId = $user->contentChurchId();

        if ($churchId !== null && $church->id === $churchId) {
            return true;
        }

        if ($church->isMission()) {
            if ($churchId !== null && $church->parent_church_id === $churchId) {
                return true;
            }

            $ministerId = $user->minister?->id;
            if ($ministerId !== null && $church->leader_minister_id === $ministerId) {
                return true;
            }
        }

        return false;
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
