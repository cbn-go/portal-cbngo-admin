<?php

namespace App\Filament\FiscalCouncil\Resources\JubanReportResource\Pages;

use App\Filament\FiscalCouncil\Resources\JubanReportResource;
use App\Models\JubanReport;
use App\Models\JubanReportInquiry;
use App\Models\JubanTransaction;
use App\Models\User;
use App\Services\FiscalCouncil\JubanReportInquiryService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @property JubanReport $record
 */
class ViewJubanReport extends ViewRecord
{
    protected static string $resource = JubanReportResource::class;

    protected static string $view = 'filament.fiscal-council.resources.juban-report-resource.pages.view-juban-report';

    public function canAskInquiry(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user || ! $user->is_active) {
            return false;
        }

        return Gate::allows('create', [JubanReportInquiry::class, $this->record]);
    }

    public function canCloseWithoutAnswer(JubanReportInquiry $inquiry): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user || ! $user->is_active) {
            return false;
        }

        return Gate::allows('closeWithoutAnswer', $inquiry);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('askInquiry')
                ->label('Pedir Esclarecimento')
                ->icon('heroicon-m-question-mark-circle')
                ->color('warning')
                ->visible(fn (): bool => $this->canAskInquiry())
                ->form([
                    Forms\Components\Select::make('transaction_id')
                        ->label('Lançamento Contábil Relacionado (opcional)')
                        ->placeholder('Geral (não vinculado a lançamento específico)')
                        ->options(function (): array {
                            return JubanTransaction::query()
                                ->whereDate('occurred_on', '>=', $this->record->period_from)
                                ->whereDate('occurred_on', '<=', $this->record->period_until)
                                ->orderBy('occurred_on')
                                ->get()
                                ->mapWithKeys(function (JubanTransaction $t): array {
                                    $amountFormatted = 'R$ '.number_format((float) $t->amount, 2, ',', '.');
                                    $label = "{$t->occurred_on->format('d/m/Y')} - {$t->description} ({$amountFormatted})";

                                    return [$t->id => $label];
                                })
                                ->all();
                        })
                        ->searchable(),

                    Forms\Components\Textarea::make('question')
                        ->label('Pergunta / Solicitação de Esclarecimento')
                        ->required()
                        ->rows(4)
                        ->placeholder('Descreva detalhadamente a dúvida ou o comprovante solicitado...'),
                ])
                ->action(function (array $data): void {
                    $service = app(JubanReportInquiryService::class);
                    $service->askInquiry(
                        $this->record,
                        auth()->user(),
                        $data['question'],
                        ! empty($data['transaction_id']) ? (int) $data['transaction_id'] : null
                    );

                    Notification::make()
                        ->title('Pedido de esclarecimento enviado com sucesso!')
                        ->body('A Tesouraria da JUBAN foi notificada e o relatório entrou em revisão.')
                        ->success()
                        ->send();

                    $this->record->refresh();
                }),

            Action::make('download_pdf')
                ->label('Baixar PDF')
                ->icon('heroicon-m-arrow-down-tray')
                ->color('gray')
                ->visible(fn (): bool => (bool) ($this->record->pdf_path && Storage::disk('local')->exists($this->record->pdf_path)))
                ->action(function (): StreamedResponse {
                    $filename = "relatorio-juban-{$this->record->year}-q{$this->record->quarter}".($this->record->version > 1 ? "-v{$this->record->version}" : '').'.pdf';

                    /** @var string $pdfPath */
                    $pdfPath = $this->record->pdf_path;

                    return Storage::disk('local')->download($pdfPath, $filename);
                }),
        ];
    }

    public function closeWithoutAnswerAction(): Action
    {
        return Action::make('closeWithoutAnswer')
            ->label('Encerrar sem Resposta')
            ->icon('heroicon-m-x-circle')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Encerrar Pedido de Esclarecimento sem Resposta')
            ->modalDescription('Esta ação registrará formalmente que o pedido foi encerrado pelo Relator sem manifestação da Tesouraria.')
            ->form([
                Forms\Components\Textarea::make('reason')
                    ->label('Justificativa do Encerramento')
                    ->required()
                    ->rows(3)
                    ->placeholder('Informe a justificativa para encerrar sem resposta...'),
            ])
            ->action(function (array $arguments, array $data): void {
                $inquiryId = $arguments['inquiry'];
                $inquiry = JubanReportInquiry::findOrFail($inquiryId);

                Gate::authorize('closeWithoutAnswer', $inquiry);

                $service = app(JubanReportInquiryService::class);
                $service->closeWithoutAnswer($inquiry, auth()->user(), $data['reason']);

                Notification::make()
                    ->title('Pedido encerrado sem resposta.')
                    ->success()
                    ->send();

                $this->record->refresh();
            });
    }
}
