<?php

namespace App\Notifications;

use App\Models\JubanReportInquiry;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class JubanReportInquiryAskedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public JubanReportInquiry $inquiry
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
        $report = $this->inquiry->report;

        return FilamentNotification::make()
            ->title('Novo pedido de esclarecimento do Conselho Fiscal')
            ->body("O Conselho Fiscal solicitou esclarecimentos para o relatório do {$report->quarter}º Trimestre/{$report->year} (v{$report->version}).")
            ->icon('heroicon-m-question-mark-circle')
            ->iconColor('warning')
            ->getDatabaseMessage();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $report = $this->inquiry->report;

        $mail = (new MailMessage)
            ->subject("Pedido de esclarecimento - JUBAN {$report->quarter}º Trimestre/{$report->year}")
            ->greeting("Olá, {$notifiable->name}!")
            ->line("O Conselho Fiscal abriu um pedido de esclarecimento referente ao relatório do {$report->quarter}º Trimestre/{$report->year} (v{$report->version}).")
            ->line("**Pergunta:** \"{$this->inquiry->question}\"");

        if ($this->inquiry->transaction) {
            $t = $this->inquiry->transaction;
            $mail->line("**Lançamento associado:** {$t->occurred_on->format('d/m/Y')} - {$t->description} (R$ ".number_format((float) $t->amount, 2, ',', '.').')');
        }

        return $mail
            ->action('Responder no Painel JUBAN', url("/juban/juban-reports/{$report->id}"))
            ->line('Acesse o painel da JUBAN para responder e anexar comprovantes, se necessário.');
    }
}
