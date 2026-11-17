<?php

namespace App\Policies;

use App\Models\JubanTransaction;
use App\Models\User;

class JubanTransactionPolicy
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
     * Determine whether the user can view any transactions.
     */
    public function viewAny(User $user): bool
    {
        return $user->role->canViewJubanTreasury();
    }

    /**
     * Determine whether the user can view the transaction.
     */
    public function view(User $user, JubanTransaction $transaction): bool
    {
        return $user->role->canViewJubanTreasury();
    }

    /**
     * Determine whether the user can create transactions.
     */
    public function create(User $user): bool
    {
        return $user->role->canManageJubanTreasury();
    }

    /**
     * Determine whether the user can update the transaction.
     */
    public function update(User $user, JubanTransaction $transaction): bool
    {
        return $user->role->canManageJubanTreasury();
    }

    /**
     * Determine whether the user can delete the transaction.
     * Secretary (Convention Editor) is strictly forbidden from deleting.
     */
    public function delete(User $user, JubanTransaction $transaction): bool
    {
        return $user->role->isSuperAdmin() || $user->role->isJubanTreasurer();
    }

    /**
     * Determine whether the user can restore the transaction.
     */
    public function restore(User $user, JubanTransaction $transaction): bool
    {
        return $user->role->isSuperAdmin();
    }

    /**
     * Determine whether the user can permanently delete the transaction.
     */
    public function forceDelete(User $user, JubanTransaction $transaction): bool
    {
        return $user->role->isSuperAdmin();
    }

    /**
     * Determine whether the user can export transactions.
     */
    public function export(User $user): bool
    {
        return $user->role->canViewJubanTreasury();
    }
}
