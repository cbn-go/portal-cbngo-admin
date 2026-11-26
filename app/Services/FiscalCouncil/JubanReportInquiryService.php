<?php

namespace App\Services\FiscalCouncil;

use App\Enums\FiscalCouncilSeat;
use App\Enums\JubanReportInquiryStatus;
use App\Enums\JubanReportStatus;
use App\Enums\UserRole;
use App\Models\FiscalCouncilMember;
use App\Models\JubanReport;
use App\Models\JubanReportInquiry;
use App\Models\JubanTransaction;
use App\Models\User;
use App\Notifications\JubanReportInquiryAnsweredNotification;
use App\Notifications\JubanReportInquiryAskedNotification;
use App\Services\FiscalCouncil\Exceptions\FiscalCouncilReviewException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;

class JubanReportInquiryService
{
    /**
     * Abre um novo pedido de esclarecimento pelo Conselho Fiscal.
     */
    public function askInquiry(
        JubanReport $report,
        User $user,
        string $question,
        ?int $transactionId = null
    ): JubanReportInquiry {
        $isSuperAdmin = $user->role->isSuperAdmin();
        $isActiveFiscal = $user->role->isFiscalCouncil()
            && FiscalCouncilMember::query()->where('user_id', $user->id)->active()->exists();

        if (! $isSuperAdmin && ! $isActiveFiscal) {
            throw new FiscalCouncilReviewException('Apenas membros ativos do Conselho Fiscal podem abrir pedidos de esclarecimento.');
        }

        if (! in_array($report->status, [JubanReportStatus::AGUARDANDO_CONSELHO_FISCAL, JubanReportStatus::EM_REVISAO], true)) {
            throw new FiscalCouncilReviewException("Pedidos de esclarecimento só podem ser abertos durante a revisão do Conselho Fiscal (status atual: {$report->status->value}).");
        }

        if ($transactionId !== null) {
            $transaction = JubanTransaction::find($transactionId);
            if (! $transaction) {
                throw new FiscalCouncilReviewException('Lançamento contábil não encontrado.');
            }

            $occurredOn = $transaction->occurred_on->toDateString();
            if ($occurredOn < $report->period_from->toDateString() || $occurredOn > $report->period_until->toDateString()) {
                throw new FiscalCouncilReviewException('O lançamento selecionado não pertence ao período do relatório.');
            }
        }

        $inquiry = JubanReportInquiry::create([
            'report_id' => $report->id,
            'transaction_id' => $transactionId,
            'asked_by' => $user->id,
            'question' => $question,
            'asked_at' => now(),
            'status' => JubanReportInquiryStatus::ABERTO,
        ]);

        if ($report->status === JubanReportStatus::AGUARDANDO_CONSELHO_FISCAL) {
            $report->update(['status' => JubanReportStatus::EM_REVISAO]);
        }

        // Notifica a Tesouraria da JUBAN e SuperAdmin
        $recipients = User::query()
            ->whereIn('role', [UserRole::JUBAN_TREASURER, UserRole::SUPER_ADMIN])
            ->where('is_active', true)
            ->get();

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new JubanReportInquiryAskedNotification($inquiry));
        }

        return $inquiry;
    }

    /**
     * Responde a um pedido de esclarecimento (pela Tesouraria).
     */
    public function answerInquiry(
        JubanReportInquiry $inquiry,
        User $user,
        string $answer,
        UploadedFile|string|null $attachment = null
    ): JubanReportInquiry {
        if (! $user->role->isSuperAdmin() && $user->role !== UserRole::JUBAN_TREASURER) {
            throw new FiscalCouncilReviewException('Apenas a Tesouraria pode responder a pedidos de esclarecimento.');
        }

        if ($inquiry->status !== JubanReportInquiryStatus::ABERTO) {
            throw new FiscalCouncilReviewException('Este pedido de esclarecimento não está aberto para resposta.');
        }

        $attachmentPath = null;
        if ($attachment instanceof UploadedFile) {
            $allowedMimes = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
            if (! in_array($attachment->getMimeType(), $allowedMimes, true)) {
                throw new FiscalCouncilReviewException('Formato de anexo inválido. Permitido apenas PDF ou imagens (JPG, PNG, WebP).');
            }

            if ($attachment->getSize() > 10 * 1024 * 1024) {
                throw new FiscalCouncilReviewException('O comprovante anexo excede o tamanho máximo de 10 MB.');
            }

            $report = $inquiry->report;
            $dir = "juban-inquiries/{$report->year}-q{$report->quarter}";
            $attachmentPath = $attachment->store($dir, 'local');
        } elseif (is_string($attachment) && $attachment !== '') {
            $attachmentPath = $attachment;
        }

        $inquiry->update([
            'answer' => $answer,
            'answered_by' => $user->id,
            'answered_at' => now(),
            'status' => JubanReportInquiryStatus::RESPONDIDO,
            'attachment_path' => $attachmentPath,
        ]);

        // Notifica membros ativos do Conselho Fiscal
        $activeFiscalUsers = FiscalCouncilMember::query()
            ->active()
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter(fn ($u) => $u instanceof User && $u->is_active);

        $superAdmins = User::query()->where('role', UserRole::SUPER_ADMIN)->where('is_active', true)->get();
        $recipients = $activeFiscalUsers->merge($superAdmins)->unique('id')->values();

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new JubanReportInquiryAnsweredNotification($inquiry));
        }

        return $inquiry->fresh();
    }

    /**
     * Encerra um pedido de esclarecimento sem resposta (privilégio exclusivo do Relator do Conselho Fiscal ou SuperAdmin).
     */
    public function closeWithoutAnswer(
        JubanReportInquiry $inquiry,
        User $user,
        string $reason
    ): JubanReportInquiry {
        $isSuperAdmin = $user->role->isSuperAdmin();
        $isRelator = FiscalCouncilMember::query()
            ->where('user_id', $user->id)
            ->where('seat', FiscalCouncilSeat::RELATOR)
            ->active()
            ->exists();

        if (! $isSuperAdmin && ! $isRelator) {
            throw new FiscalCouncilReviewException('Apenas o Relator do Conselho Fiscal ou Super Administrador pode encerrar um pedido sem resposta.');
        }

        if ($inquiry->status !== JubanReportInquiryStatus::ABERTO) {
            throw new FiscalCouncilReviewException('Apenas pedidos em aberto podem ser encerrados sem resposta.');
        }

        $inquiry->update([
            'status' => JubanReportInquiryStatus::ENCERRADO_SEM_RESPOSTA,
            'closed_without_answer_by' => $user->id,
            'closed_without_answer_at' => now(),
            'closed_without_answer_reason' => $reason,
        ]);

        return $inquiry->fresh();
    }
}
