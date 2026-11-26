<?php

namespace App\Filament\Juban\Resources\JubanReportResource\Pages;

use App\Enums\JubanReportStatus;
use App\Enums\JubanSigningRole;
use App\Filament\Juban\Resources\JubanReportResource;
use App\Models\JubanReport;
use App\Models\JubanReportInquiry;
use App\Models\User;
use App\Services\FiscalCouncil\JubanReportInquiryService;
use App\Services\Juban\Exceptions\JubanSigningException;
use App\Services\Juban\Exceptions\QuarterlyClosingException;
use App\Services\Juban\JubanReportSigningService;
use App\Services\Juban\QuarterlyClosingService;
use App\Services\Juban\SignatureReceiptPdfService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * @property JubanReport $record
 */
class ViewJubanReport extends ViewRecord
{
    protected static string $resource = JubanReportResource::class;

    protected static string $view = 'filament.juban.resources.juban-report-resource.pages.view-juban-report';

    public function canSignReport(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user || ! $user->is_active) {
            return false;
        }

        if ($this->record->signatures()->where('user_id', $user->id)->exists()) {
            return false;
        }

        if ($this->record->status === JubanReportStatus::AGUARDANDO_TESOURARIA) {
            return $user->role->isJubanTreasurer() || $user->role->isSuperAdmin();
        }

        if ($this->record->status === JubanReportStatus::AGUARDANDO_PRESIDENCIA) {
            return $user->role->isJubanPresident() || $user->role->isSuperAdmin();
        }

        return false;
    }

    public function userHasPasskeys(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return (bool) ($user && $user->passkeys()->exists());
    }

    public function getSigningRole(): ?JubanSigningRole
    {
        if ($this->record->status === JubanReportStatus::AGUARDANDO_TESOURARIA) {
            return JubanSigningRole::TESOURARIA;
        }

        if ($this->record->status === JubanReportStatus::AGUARDANDO_PRESIDENCIA) {
            return JubanSigningRole::PRESIDENCIA;
        }

        return null;
    }

    public function answerInquiryAction(): Action
    {
        return Action::make('answerInquiry')
            ->label('Responder')
            ->icon('heroicon-m-chat-bubble-left-ellipsis')
            ->color('success')
            ->form([
                Forms\Components\Textarea::make('answer')
                    ->label('Resposta ao Conselho Fiscal')
                    ->required()
                    ->rows(4)
                    ->placeholder('Escreva os esclarecimentos detalhados...'),
                Forms\Components\FileUpload::make('attachment')
                    ->label('Comprovante Anexo (opcional)')
                    ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(10240)
                    ->disk('local')
                    ->directory('juban-inquiries/temp'),
            ])
            ->action(function (array $arguments, array $data): void {
                $inquiryId = $arguments['inquiry'];
                $inquiry = JubanReportInquiry::findOrFail($inquiryId);

                Gate::authorize('answer', $inquiry);

                $service = app(JubanReportInquiryService::class);
                $service->answerInquiry($inquiry, auth()->user(), $data['answer'], $data['attachment'] ?? null);

                Notification::make()
                    ->title('Resposta enviada com sucesso!')
                    ->body('O Conselho Fiscal foi notificado.')
                    ->success()
                    ->send();

                $this->record->refresh();
            });
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sign')
                ->label('Assinar com passkey')
                ->icon('heroicon-m-finger-print')
                ->color('success')
                ->visible(fn (): bool => $this->canSignReport() && $this->userHasPasskeys())
                ->action(fn () => $this->startSigning()),

            Action::make('downloadPdf')
                ->label('Baixar Relatório (PDF)')
                ->icon('heroicon-m-arrow-down-tray')
                ->color('gray')
                ->visible(fn (): bool => (bool) ($this->record->pdf_path && Storage::disk('local')->exists($this->record->pdf_path)))
                ->action(function (): StreamedResponse {
                    $filename = "relatorio-juban-{$this->record->year}-q{$this->record->quarter}.pdf";
                    /** @var string $pdfPath */
                    $pdfPath = $this->record->pdf_path;

                    return Storage::disk('local')->download($pdfPath, $filename);
                }),

            Action::make('downloadReceiptPdf')
                ->label('Baixar Comprovante')
                ->icon('heroicon-m-document-check')
                ->color('gray')
                ->visible(fn (): bool => $this->record->signatures()->exists())
                ->action(function (SignatureReceiptPdfService $receiptService): StreamedResponse {
                    $pdfContent = $receiptService->generateReceiptPdf($this->record);
                    $filename = "comprovante-assinaturas-juban-{$this->record->year}-q{$this->record->quarter}.pdf";

                    return response()->streamDownload(
                        fn () => print ($pdfContent),
                        $filename,
                        ['Content-Type' => 'application/pdf']
                    );
                }),

            Action::make('verifyPdf')
                ->label('Conferir PDF')
                ->icon('heroicon-m-shield-check')
                ->color('info')
                ->modalHeading('Conferir Autenticidade do PDF')
                ->modalDescription('Envie o arquivo PDF do relatório para verificar se o hash SHA-256 é idêntico ao do relatório oficial homologado.')
                ->form([
                    Forms\Components\FileUpload::make('pdf_file')
                        ->label('Arquivo PDF para conferência')
                        ->required()
                        ->acceptedFileTypes(['application/pdf'])
                        ->storeFiles(false),
                ])
                ->action(function (array $data): void {
                    $file = $data['pdf_file'] ?? null;
                    $realPath = null;

                    if ($file instanceof TemporaryUploadedFile) {
                        $realPath = $file->getRealPath();
                    } elseif (is_string($file)) {
                        $realPath = Storage::disk('local')->path($file);
                    }

                    if (! $realPath || ! file_exists($realPath)) {
                        Notification::make()
                            ->title('Erro ao ler arquivo enviado')
                            ->danger()
                            ->send();

                        return;
                    }

                    $uploadedHash = hash_file('sha256', $realPath);

                    if (hash_equals((string) $this->record->pdf_sha256, (string) $uploadedHash)) {
                        Notification::make()
                            ->title('PDF autêntico e íntegro!')
                            ->body('O hash SHA-256 do arquivo confere exatamente com o relatório oficial homologado.')
                            ->success()
                            ->persistent()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Atenção: Hash divergente!')
                            ->body('O hash do arquivo enviado não confere com o relatório oficial. O arquivo pode ter sido adulterado ou pertence a outro período.')
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),

            Action::make('reopen')
                ->label('Reabrir')
                ->icon('heroicon-m-arrow-path')
                ->color('danger')
                ->visible(fn (): bool => auth()->user()?->can('reopen', $this->record) ?? false)
                ->requiresConfirmation()
                ->modalHeading('Reabrir Trimestre Contábil')
                ->modalDescription('A reabertura cancelará o fechamento, excluirá o PDF atual e destravará todos os lançamentos do trimestre para ajustes.')
                ->form([
                    Forms\Components\Textarea::make('reason')
                        ->label('Motivo da reabertura')
                        ->required()
                        ->placeholder('Descreva detalhadamente o motivo da reabertura...'),
                ])
                ->action(function (array $data): void {
                    $service = app(QuarterlyClosingService::class);

                    try {
                        $service->reopenQuarter($this->record, auth()->user(), $data['reason'] ?? null);

                        Notification::make()
                            ->title('Trimestre reaberto com sucesso!')
                            ->body('Os lançamentos do período foram destravados para edições.')
                            ->success()
                            ->send();

                        $this->redirect(JubanReportResource::getUrl('index'));
                    } catch (QuarterlyClosingException $e) {
                        Notification::make()
                            ->title('Não foi possível reabrir o relatório')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('create_new_version')
                ->label('Criar Nova Versão')
                ->icon('heroicon-m-arrow-path-rounded-square')
                ->color('warning')
                ->visible(fn (): bool => auth()->user()?->can('createNewVersion', $this->record) ?? false)
                ->requiresConfirmation()
                ->modalHeading('Abrir Nova Versão após Reprovação')
                ->modalDescription('Uma nova versão será aberta (em status Reaberto), permitindo à Tesouraria ajustar os lançamentos do trimestre. A versão atual reprovada e seu PDF permanecerão intactos no histórico.')
                ->form([
                    Forms\Components\Textarea::make('reason')
                        ->label('Motivo / Justificativa da nova versão')
                        ->required()
                        ->placeholder('Descreva os ajustes solicitados pelo Conselho Fiscal que serão realizados...'),
                ])
                ->action(function (array $data): void {
                    $service = app(QuarterlyClosingService::class);

                    try {
                        $newVersion = $service->createNewVersionAfterRejection($this->record, auth()->user(), (string) $data['reason']);

                        Notification::make()
                            ->title("Nova versão v{$newVersion->version} criada com sucesso!")
                            ->body('Os lançamentos foram destravados para correções pela Tesouraria.')
                            ->success()
                            ->send();

                        $this->redirect(JubanReportResource::getUrl('index'));
                    } catch (QuarterlyClosingException $e) {
                        Notification::make()
                            ->title('Não foi possível criar nova versão')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    public function startSigning(): void
    {
        /** @var User $user */
        $user = auth()->user();
        $role = $this->getSigningRole();

        if (! $role) {
            Notification::make()
                ->title('Este relatório não está disponível para assinatura no momento.')
                ->warning()
                ->send();

            return;
        }

        $signingService = app(JubanReportSigningService::class);

        try {
            $options = $signingService->generateSigningOptions($this->record, $user, $role);

            $this->dispatch('init-webauthn-signing', options: $options, role: $role->value);
        } catch (JubanSigningException $e) {
            Notification::make()
                ->title('Não foi possível iniciar a assinatura')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function finishSigning(array $payload): void
    {
        /** @var User $user */
        $user = auth()->user();
        $role = $this->getSigningRole();

        if (! $role) {
            Notification::make()
                ->title('Papel de assinatura não identificado.')
                ->danger()
                ->send();

            return;
        }

        $signingService = app(JubanReportSigningService::class);

        try {
            $signingService->verifyAndSign(
                $this->record,
                $user,
                $role,
                $payload,
                request()->ip(),
                request()->userAgent()
            );

            Notification::make()
                ->title('Relatório assinado com sucesso!')
                ->body('Sua assinatura digital com passkey foi homologada e registrada.')
                ->success()
                ->send();

            $this->record->refresh();
        } catch (Throwable $e) {
            Notification::make()
                ->title('Falha ao processar assinatura com passkey')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function handleSigningError(string $message): void
    {
        Notification::make()
            ->title('Assinatura não concluída')
            ->body($message)
            ->warning()
            ->send();
    }
}
