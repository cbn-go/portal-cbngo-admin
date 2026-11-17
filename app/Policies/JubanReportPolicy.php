<?php

namespace App\Policies;

use App\Enums\JubanReportStatus;
use App\Models\JubanReport;
use App\Models\User;

class JubanReportPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if (! $user->is_active) {
            return false;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->role->canViewJubanTreasury();
    }

    public function view(User $user, JubanReport $report): bool
    {
        return $user->role->canViewJubanTreasury();
    }

    public function create(User $user): bool
    {
        return $user->role->canManageJubanTreasury();
    }

    public function update(User $user, JubanReport $report): bool
    {
        return false;
    }

    public function delete(User $user, JubanReport $report): bool
    {
        return false;
    }

    public function reopen(User $user, JubanReport $report): bool
    {
        return $user->role->isSuperAdmin()
            && $report->status !== JubanReportStatus::ASSINADO
            && $report->status !== JubanReportStatus::REABERTO;
    }
}
