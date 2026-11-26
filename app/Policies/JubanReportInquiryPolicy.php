<?php

namespace App\Policies;

use App\Enums\FiscalCouncilSeat;
use App\Enums\UserRole;
use App\Models\FiscalCouncilMember;
use App\Models\JubanReport;
use App\Models\JubanReportInquiry;
use App\Models\User;

class JubanReportInquiryPolicy
{
    /**
     * Determina se o usuário pode visualizar o pedido de esclarecimento.
     */
    public function view(User $user, JubanReportInquiry $inquiry): bool
    {
        return $user->role->isSuperAdmin()
            || $user->role->isFiscalCouncil()
            || $user->role === UserRole::JUBAN_TREASURER
            || $user->role === UserRole::JUBAN_PRESIDENT;
    }

    /**
     * Determina se o usuário pode criar um pedido de esclarecimento.
     */
    public function create(User $user, JubanReport $report): bool
    {
        if ($user->role->isSuperAdmin()) {
            return true;
        }

        return $user->role->isFiscalCouncil()
            && FiscalCouncilMember::query()->where('user_id', $user->id)->active()->exists();
    }

    /**
     * Determina se o usuário pode responder ao pedido de esclarecimento.
     */
    public function answer(User $user, JubanReportInquiry $inquiry): bool
    {
        if (! $inquiry->isOpen()) {
            return false;
        }

        return $user->role->isSuperAdmin() || $user->role === UserRole::JUBAN_TREASURER;
    }

    /**
     * Determina se o usuário pode encerrar o pedido sem resposta.
     */
    public function closeWithoutAnswer(User $user, JubanReportInquiry $inquiry): bool
    {
        if (! $inquiry->isOpen()) {
            return false;
        }

        if ($user->role->isSuperAdmin()) {
            return true;
        }

        return FiscalCouncilMember::query()
            ->where('user_id', $user->id)
            ->where('seat', FiscalCouncilSeat::RELATOR)
            ->active()
            ->exists();
    }

    /**
     * Pedidos e respostas são imutáveis após o envio.
     */
    public function update(User $user, JubanReportInquiry $inquiry): bool
    {
        return false;
    }

    /**
     * Pedidos e respostas não podem ser excluídos.
     */
    public function delete(User $user, JubanReportInquiry $inquiry): bool
    {
        return false;
    }

    /**
     * Determina se o usuário pode baixar o comprovante anexo.
     */
    public function downloadAttachment(User $user, JubanReportInquiry $inquiry): bool
    {
        if (! $inquiry->attachment_path) {
            return false;
        }

        return $this->view($user, $inquiry);
    }
}
