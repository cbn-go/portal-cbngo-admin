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
        return $user->role->canViewJubanTreasury() || $user->role->isFiscalCouncil();
    }

    public function view(User $user, JubanReport $report): bool
    {
        if ($user->role->isFiscalCouncil()) {
            return $report->status->isVisibleToFiscalCouncil();
        }

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
            && in_array($report->status, [
                JubanReportStatus::AGUARDANDO_TESOURARIA,
                JubanReportStatus::AGUARDANDO_PRESIDENCIA,
            ], true);
    }

    public function createNewVersion(User $user, JubanReport $report): bool
    {
        return $user->role->isSuperAdmin()
            && $report->status === JubanReportStatus::REPROVADO
            && ! JubanReport::where('year', $report->year)
                ->where('quarter', $report->quarter)
                ->where('version', '>', $report->version)
                ->exists();
    }
}
