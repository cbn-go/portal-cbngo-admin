<?php

namespace App\Notifications;

use App\Models\JubanReportInquiry;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class JubanReportInquiryAnsweredNotification extends Notification
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
            ->title('Resposta da Tesouraria ao pedido de esclarecimento')
            ->body("A Tesouraria respondeu à dúvida do {$report->quarter}º Trimestre/{$report->year} (v{$report->version}).")
            ->icon('heroicon-m-chat-bubble-left-right')
            ->iconColor('success')
            ->getDatabaseMessage();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $report = $this->inquiry->report;

        $mail = (new MailMessage)
            ->subject("Resposta ao pedido de esclarecimento - JUBAN {$report->quarter}º Trimestre/{$report->year}")
            ->greeting("Olá, {$notifiable->name}!")
            ->line("A Tesouraria da JUBAN respondeu ao pedido de esclarecimento do {$report->quarter}º Trimestre/{$report->year} (v{$report->version}).")
            ->line("**Pergunta:** \"{$this->inquiry->question}\"")
            ->line("**Resposta:** \"{$this->inquiry->answer}\"");

        if ($this->inquiry->attachment_path) {
            $mail->line('*(Há um comprovante anexado à resposta)*');
        }

        return $mail
            ->action('Visualizar no Conselho Fiscal', url('/conselho-fiscal'))
            ->line('Acesse o painel do Conselho Fiscal para conferir a resposta e os documentos.');
    }
}
