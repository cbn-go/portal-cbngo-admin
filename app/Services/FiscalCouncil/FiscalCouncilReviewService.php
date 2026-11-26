<?php

namespace App\Services\FiscalCouncil;

use App\Enums\JubanReportStatus;
use App\Enums\UserRole;
use App\Models\JubanReport;
use App\Models\User;
use App\Notifications\JubanReportFiscalReviewResultNotification;
use App\Services\FiscalCouncil\Exceptions\FiscalCouncilReviewException;
use Illuminate\Support\Facades\Notification;

class FiscalCouncilReviewService
{
    /**
     * Inicia a revisão do relatório pelo Conselho Fiscal.
     */
    public function startReview(JubanReport $report, User $user): void
    {
        if ($report->status !== JubanReportStatus::AGUARDANDO_CONSELHO_FISCAL) {
            throw new FiscalCouncilReviewException("O relatório não está aguardando revisão do Conselho Fiscal (status atual: {$report->status->value}).");
        }

        $report->update([
            'status' => JubanReportStatus::EM_REVISAO,
        ]);
    }

    /**
     * Conclui a revisão do relatório pelo Conselho Fiscal com o parecer final (aprovado, aprovado_com_ressalvas, reprovado).
     */
    public function concludeReview(
        JubanReport $report,
        JubanReportStatus $resultStatus,
        User $user,
        ?string $notes = null
    ): void {
        if (! in_array($report->status, [JubanReportStatus::AGUARDANDO_CONSELHO_FISCAL, JubanReportStatus::EM_REVISAO], true)) {
            throw new FiscalCouncilReviewException("O relatório não pode ter a revisão concluída a partir do status {$report->status->value}.");
        }

        if (! in_array($resultStatus, [
            JubanReportStatus::APROVADO,
            JubanReportStatus::APROVADO_COM_RESSALVAS,
            JubanReportStatus::REPROVADO,
        ], true)) {
            throw new FiscalCouncilReviewException("Status de resultado de parecer inválido: {$resultStatus->value}. Deve ser aprovado, aprovado_com_ressalvas ou reprovado.");
        }

        $report->update([
            'status' => $resultStatus,
        ]);

        // Notifica a liderança da JUBAN (Tesouraria e Presidência)
        $recipients = User::query()
            ->whereIn('role', [UserRole::JUBAN_TREASURER, UserRole::JUBAN_PRESIDENT])
            ->where('is_active', true)
            ->get();

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new JubanReportFiscalReviewResultNotification($report, $resultStatus));
        }
    }
}
