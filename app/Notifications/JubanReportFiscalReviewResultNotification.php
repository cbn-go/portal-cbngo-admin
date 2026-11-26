<?php

namespace App\Notifications;

use App\Enums\JubanReportStatus;
use App\Models\JubanReport;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class JubanReportFiscalReviewResultNotification extends Notification
{
    use Queueable;

    public function __construct(
        public JubanReport $report,
        public JubanReportStatus $resultStatus
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $color = match ($this->resultStatus) {
            JubanReportStatus::APROVADO => 'success',
            JubanReportStatus::APROVADO_COM_RESSALVAS => 'warning',
            JubanReportStatus::REPROVADO => 'danger',
            default => 'info',
        };

        return FilamentNotification::make()
            ->title("Parecer do Conselho Fiscal: {$this->resultStatus->getLabel()}")
            ->body("O Conselho Fiscal concluiu a revisão do relatório da JUBAN referente ao {$this->report->quarter}º Trimestre/{$this->report->year} (v{$this->report->version}) com o resultado: {$this->resultStatus->getLabel()}.")
            ->icon($this->resultStatus->getIcon())
            ->iconColor($color)
            ->getDatabaseMessage();
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Resultado do Parecer Fiscal - JUBAN {$this->report->quarter}º Trimestre/{$this->report->year}")
            ->greeting("Olá, {$notifiable->name}!")
            ->line("O Conselho Fiscal concluiu a avaliação do relatório da JUBAN referente ao {$this->report->quarter}º Trimestre/{$this->report->year} (versão {$this->report->version}).")
            ->line("Resultado: {$this->resultStatus->getLabel()}")
            ->action('Visualizar Relatório na JUBAN', url('/juban/juban-reports/'.$this->report->id))
            ->line('Consulte o painel da JUBAN para mais detalhes.');
    }
}
