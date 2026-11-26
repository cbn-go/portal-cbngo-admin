<?php

namespace App\Notifications;

use App\Models\JubanReport;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class JubanReportArrivedToFiscalCouncilNotification extends Notification
{
    use Queueable;

    public function __construct(
        public JubanReport $report
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
        return FilamentNotification::make()
            ->title('Relatório JUBAN aguardando revisão')
            ->body("O relatório do {$this->report->quarter}º Trimestre/{$this->report->year} (v{$this->report->version}) foi assinado pela presidência e aguarda parecer do Conselho Fiscal.")
            ->icon('heroicon-m-scale')
            ->iconColor('primary')
            ->getDatabaseMessage();
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Relatório JUBAN {$this->report->quarter}º Trimestre/{$this->report->year} aguardando parecer")
            ->greeting("Olá, {$notifiable->name}!")
            ->line("O relatório financeiro da JUBAN referente ao {$this->report->quarter}º Trimestre/{$this->report->year} (versão {$this->report->version}) foi assinado pela tesouraria e pela presidência.")
            ->line('O documento agora está disponível para revisão e emissão de parecer pelo Conselho Fiscal.')
            ->action('Acessar Painel do Conselho Fiscal', url('/conselho-fiscal'))
            ->line('Acesse o painel para conferir os lançamentos, comprovantes e o relatório assinado.');
    }
}
