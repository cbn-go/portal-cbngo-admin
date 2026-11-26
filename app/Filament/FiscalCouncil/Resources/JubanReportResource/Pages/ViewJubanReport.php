<?php

namespace App\Filament\FiscalCouncil\Resources\JubanReportResource\Pages;

use App\Enums\JubanReportOpinionMemberRole;
use App\Enums\JubanReportOpinionResult;
use App\Filament\FiscalCouncil\Resources\JubanReportResource;
use App\Models\FiscalCouncilMember;
use App\Models\JubanReport;
use App\Models\JubanReportInquiry;
use App\Models\JubanTransaction;
use App\Models\User;
use App\Services\FiscalCouncil\FiscalCouncilOpinionService;
use App\Services\FiscalCouncil\FiscalCouncilOpinionSigningService;
use App\Services\FiscalCouncil\JubanReportInquiryService;
use App\Services\Juban\Exceptions\JubanSigningException;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * @property JubanReport $record
 */
class ViewJubanReport extends ViewRecord
{
    public ?string $divergentVote = null;

    protected static string $resource = JubanReportResource::class;

    protected static string $view = 'filament.fiscal-council.resources.juban-report-resource.pages.view-juban-report';

    public function canSignOpinion(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user || ! $user->is_active) {
            return false;
        }

        $opinion = $this->record->opinion;
        if (! $opinion || ! $opinion->isIssued()) {
            return false;
        }

        // Não pode assinar novamente se já assinou este parecer
        if ($opinion->signatures()->where('user_id', $user->id)->exists()) {
            return false;
        }

        // Deve ser indicado no parecer
        $opinion->loadMissing('members.member');
        $isIndicated = $opinion->members->contains(fn ($m) => $m->member->user_id === $user->id);
        if (! $isIndicated) {
            return false;
        }

        // Segregação de funções: não pode ter assinado como Tesouraria ou Presidência
        if ($this->record->signatures()->where('user_id', $user->id)->whereNull('opinion_id')->exists()) {
            return false;
        }

        // Mandato ativo
        $member = FiscalCouncilMember::where('user_id', $user->id)->first();
        if (! $member || ! $member->active || ! $member->isTermActive()) {
            return false;
        }

        return true;
    }

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

    public function getFiscalCouncilMemberOptions(): array
    {
        return FiscalCouncilMember::query()
            ->where('active', true)
            ->with('user')
            ->get()
            ->mapWithKeys(function (FiscalCouncilMember $m): array {
                $title = trim((string) $m->title);
                $name = $title !== '' ? "{$title} {$m->user->name}" : $m->user->name;
                $label = "{$m->seat->getLabel()} - {$name}";

                return [$m->id => $label];
            })
            ->all();
    }

    protected function getHeaderActions(): array
    {
        /** @var User|null $user */
        $user = auth()->user();

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

            Action::make('draftOpinion')
                ->label('Redigir Parecer Fiscal')
                ->icon('heroicon-m-document-text')
                ->color('primary')
                ->visible(fn (): bool => $this->record->opinion === null && (bool) $user?->is_active)
                ->modalHeading('Novo Parecer Fiscal (Rascunho)')
                ->modalDescription('Inicie a redação do parecer oficial indicando o resultado preliminar e os conselheiros presentes na reunião.')
                ->form([
                    Forms\Components\Select::make('result')
                        ->label('Resultado do Parecer')
                        ->options(JubanReportOpinionResult::class)
                        ->default(JubanReportOpinionResult::APROVADO->value)
                        ->required()
                        ->live(),

                    Forms\Components\Grid::make(2)
                        ->schema([
                            Forms\Components\DatePicker::make('meeting_date')
                                ->label('Data da Reunião')
                                ->default(now())
                                ->required(),
                            Forms\Components\DatePicker::make('issued_on')
                                ->label('Data de Emissão')
                                ->default(now())
                                ->required(),
                        ]),

                    Forms\Components\Grid::make(2)
                        ->schema([
                            Forms\Components\TextInput::make('meeting_place')
                                ->label('Local da Reunião')
                                ->default('sede da Convenção Batista Nacional do Estado de Goiás em Goiânia (GO)')
                                ->required(),
                            Forms\Components\TextInput::make('city')
                                ->label('Cidade')
                                ->default('Goiânia (GO)')
                                ->required(),
                        ]),

                    Forms\Components\Section::make('Membros Participantes da Reunião')
                        ->description('Indique os 3 conselheiros ativos que examinaram as contas (1 Relator e 2 Vogais sem repetição).')
                        ->schema([
                            Forms\Components\Select::make('relator_member_id')
                                ->label('Conselheiro Relator')
                                ->options($this->getFiscalCouncilMemberOptions())
                                ->required()
                                ->searchable(),
                            Forms\Components\Select::make('vogal1_member_id')
                                ->label('1º Conselheiro Vogal')
                                ->options($this->getFiscalCouncilMemberOptions())
                                ->required()
                                ->searchable(),
                            Forms\Components\Select::make('vogal2_member_id')
                                ->label('2º Conselheiro Vogal')
                                ->options($this->getFiscalCouncilMemberOptions())
                                ->required()
                                ->searchable(),
                        ]),

                    Forms\Components\Textarea::make('reservations')
                        ->label('Ressalvas ou Motivos (obrigatório se não aprovado integralmente)')
                        ->rows(4)
                        ->visible(fn (Forms\Get $get): bool => $get('result') !== JubanReportOpinionResult::APROVADO->value)
                        ->required(fn (Forms\Get $get): bool => $get('result') !== JubanReportOpinionResult::APROVADO->value)
                        ->placeholder('Descreva detalhadamente as ressalvas ou os motivos da reprovação...'),
                ])
                ->action(function (array $data): void {
                    $service = app(FiscalCouncilOpinionService::class);
                    $service->createDraft(
                        $this->record,
                        auth()->user(),
                        [
                            'result' => $data['result'],
                            'meeting_date' => $data['meeting_date'],
                            'meeting_place' => $data['meeting_place'],
                            'city' => $data['city'],
                            'issued_on' => $data['issued_on'],
                            'reservations' => $data['reservations'] ?? null,
                        ],
                        [
                            'relator_member_id' => (int) $data['relator_member_id'],
                            'vogal1_member_id' => (int) $data['vogal1_member_id'],
                            'vogal2_member_id' => (int) $data['vogal2_member_id'],
                        ]
                    );

                    Notification::make()
                        ->title('Rascunho do parecer fiscal criado com sucesso!')
                        ->success()
                        ->send();

                    $this->record->refresh();
                }),

            Action::make('editOpinionDraft')
                ->label('Editar Rascunho')
                ->icon('heroicon-m-pencil-square')
                ->color('warning')
                ->visible(fn (): bool => (bool) ($this->record->opinion?->isDraft() && $this->record->opinion->canBeEditedBy($user)))
                ->fillForm(function (): array {
                    $opinion = $this->record->opinion;
                    if (! $opinion) {
                        return [];
                    }

                    $relator = $opinion->members->first(fn ($m) => $m->role === JubanReportOpinionMemberRole::RELATOR);
                    $vogais = $opinion->members->filter(fn ($m) => $m->role === JubanReportOpinionMemberRole::VOGAL)->values();

                    return [
                        'result' => $opinion->result->value,
                        'meeting_date' => $opinion->meeting_date->toDateString(),
                        'meeting_place' => $opinion->meeting_place,
                        'city' => $opinion->city,
                        'issued_on' => $opinion->issued_on->toDateString(),
                        'relator_member_id' => $relator?->member_id,
                        'vogal1_member_id' => $vogais->get(0)?->member_id,
                        'vogal2_member_id' => $vogais->get(1)?->member_id,
                        'reservations' => $opinion->reservations,
                    ];
                })
                ->form([
                    Forms\Components\Select::make('result')
                        ->label('Resultado do Parecer')
                        ->options(JubanReportOpinionResult::class)
                        ->required()
                        ->live(),

                    Forms\Components\Grid::make(2)
                        ->schema([
                            Forms\Components\DatePicker::make('meeting_date')
                                ->label('Data da Reunião')
                                ->required(),
                            Forms\Components\DatePicker::make('issued_on')
                                ->label('Data de Emissão')
                                ->required(),
                        ]),

                    Forms\Components\Grid::make(2)
                        ->schema([
                            Forms\Components\TextInput::make('meeting_place')
                                ->label('Local da Reunião')
                                ->required(),
                            Forms\Components\TextInput::make('city')
                                ->label('Cidade')
                                ->required(),
                        ]),

                    Forms\Components\Section::make('Membros Participantes da Reunião')
                        ->schema([
                            Forms\Components\Select::make('relator_member_id')
                                ->label('Conselheiro Relator')
                                ->options($this->getFiscalCouncilMemberOptions())
                                ->required()
                                ->searchable(),
                            Forms\Components\Select::make('vogal1_member_id')
                                ->label('1º Conselheiro Vogal')
                                ->options($this->getFiscalCouncilMemberOptions())
                                ->required()
                                ->searchable(),
                            Forms\Components\Select::make('vogal2_member_id')
                                ->label('2º Conselheiro Vogal')
                                ->options($this->getFiscalCouncilMemberOptions())
                                ->required()
                                ->searchable(),
                        ]),

                    Forms\Components\Textarea::make('reservations')
                        ->label('Ressalvas ou Motivos')
                        ->rows(4)
                        ->visible(fn (Forms\Get $get): bool => $get('result') !== JubanReportOpinionResult::APROVADO->value)
                        ->required(fn (Forms\Get $get): bool => $get('result') !== JubanReportOpinionResult::APROVADO->value),
                ])
                ->action(function (array $data): void {
                    $service = app(FiscalCouncilOpinionService::class);
                    $service->updateDraft(
                        $this->record->opinion,
                        auth()->user(),
                        [
                            'result' => $data['result'],
                            'meeting_date' => $data['meeting_date'],
                            'meeting_place' => $data['meeting_place'],
                            'city' => $data['city'],
                            'issued_on' => $data['issued_on'],
                            'reservations' => $data['reservations'] ?? null,
                        ],
                        [
                            'relator_member_id' => (int) $data['relator_member_id'],
                            'vogal1_member_id' => (int) $data['vogal1_member_id'],
                            'vogal2_member_id' => (int) $data['vogal2_member_id'],
                        ]
                    );

                    Notification::make()
                        ->title('Rascunho atualizado com sucesso!')
                        ->success()
                        ->send();

                    $this->record->refresh();
                }),

            Action::make('commentOpinion')
                ->label('Comentar Rascunho')
                ->icon('heroicon-m-chat-bubble-bottom-center-text')
                ->color('gray')
                ->visible(fn (): bool => (bool) ($this->record->opinion?->isDraft() && $user?->is_active))
                ->modalHeading('Adicionar Comentário ao Rascunho')
                ->form([
                    Forms\Components\Textarea::make('comment')
                        ->label('Seu Comentário / Observação')
                        ->required()
                        ->rows(3)
                        ->placeholder('Escreva suas observações para o Relator antes da emissão formal...'),
                ])
                ->action(function (array $data): void {
                    $service = app(FiscalCouncilOpinionService::class);
                    $service->addComment($this->record->opinion, auth()->user(), $data['comment']);

                    Notification::make()
                        ->title('Comentário registrado com sucesso!')
                        ->success()
                        ->send();

                    $this->record->refresh();
                }),

            Action::make('issueOpinion')
                ->label('Emitir Parecer')
                ->icon('heroicon-m-check-badge')
                ->color('success')
                ->visible(fn (): bool => (bool) ($this->record->opinion?->isDraft() && $this->record->opinion->canBeIssuedBy($user)))
                ->requiresConfirmation()
                ->modalHeading('Emitir Parecer Fiscal Definitivo')
                ->modalDescription('Ao emitir o parecer, o PDF oficial será gerado com hash criptográfico SHA-256 e a redação ficará imutável para a coleta de assinaturas.')
                ->action(function (): void {
                    $service = app(FiscalCouncilOpinionService::class);
                    $service->issueOpinion($this->record->opinion, auth()->user());

                    Notification::make()
                        ->title('Parecer fiscal emitido com sucesso!')
                        ->body('O PDF e o hash SHA-256 foram gerados. O parecer está pronto para assinatura.')
                        ->success()
                        ->send();

                    $this->record->refresh();
                }),

            Action::make('signOpinion')
                ->label('Assinar Parecer com Passkey')
                ->icon('heroicon-m-finger-print')
                ->color('success')
                ->visible(fn (): bool => $this->canSignOpinion())
                ->form([
                    Forms\Components\Textarea::make('divergent_vote')
                        ->label('Voto com Ressalva / Divergência (opcional)')
                        ->placeholder('Se concordar integralmente com o parecer, deixe este campo em branco. Caso tenha divergência quanto ao voto ou ressalva pontual, registre aqui sua manifestação individual.')
                        ->rows(3),
                ])
                ->modalHeading('Assinatura Eletrônica do Parecer Fiscal')
                ->modalDescription('Você está prestes a assinar o Parecer Fiscal com sua passkey (WebAuthn). Esta assinatura possui validade jurídica plena (Lei 14.063/2020) e ficará registrada permanentemente no manifesto.')
                ->action(function (array $data): void {
                    $this->startOpinionSigning($data['divergent_vote'] ?? null);
                }),

            Action::make('downloadSignedOpinionPdf')
                ->label('Baixar Via Assinada (PDF)')
                ->icon('heroicon-m-arrow-down-tray')
                ->color('success')
                ->visible(fn (): bool => (bool) ($this->record->opinion?->signed_pdf_path && Storage::disk('local')->exists($this->record->opinion->signed_pdf_path)))
                ->action(function (): StreamedResponse {
                    $filename = "parecer-fiscal-assinado-juban-{$this->record->year}-q{$this->record->quarter}".($this->record->version > 1 ? "-v{$this->record->version}" : '').'.pdf';

                    /** @var string $pdfPath */
                    $pdfPath = (string) $this->record->opinion->signed_pdf_path;

                    return Storage::disk('local')->download($pdfPath, $filename);
                }),

            Action::make('cancelOpinion')
                ->label('Cancelar Emissão')
                ->icon('heroicon-m-arrow-uturn-left')
                ->color('danger')
                ->visible(fn (): bool => (bool) ($this->record->opinion?->isIssued() && $this->record->opinion->canBeCancelledBy($user)))
                ->requiresConfirmation()
                ->modalHeading('Cancelar Emissão do Parecer Fiscal')
                ->modalDescription('O parecer retornará ao status de rascunho para correções. O PDF atual e seu hash serão arquivados no histórico.')
                ->form([
                    Forms\Components\Textarea::make('reason')
                        ->label('Motivo do Cancelamento')
                        ->required()
                        ->rows(3)
                        ->placeholder('Informe a justificativa para o cancelamento da emissão...'),
                ])
                ->action(function (array $data): void {
                    $service = app(FiscalCouncilOpinionService::class);
                    $service->cancelIssuance($this->record->opinion, auth()->user(), $data['reason']);

                    Notification::make()
                        ->title('Emissão do parecer cancelada.')
                        ->body('O parecer voltou para o status de rascunho.')
                        ->warning()
                        ->send();

                    $this->record->refresh();
                }),

            Action::make('downloadOpinionPdf')
                ->label('Baixar Parecer (PDF)')
                ->icon('heroicon-m-arrow-down-tray')
                ->color('primary')
                ->visible(fn (): bool => (bool) ($this->record->opinion?->pdf_path && Storage::disk('local')->exists($this->record->opinion->pdf_path)))
                ->action(function (): StreamedResponse {
                    $filename = "parecer-fiscal-juban-{$this->record->year}-q{$this->record->quarter}.pdf";

                    /** @var string $pdfPath */
                    $pdfPath = $this->record->opinion->pdf_path;

                    return Storage::disk('local')->download($pdfPath, $filename);
                }),

            Action::make('download_pdf')
                ->label('Baixar Relatório (PDF)')
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

    public function startOpinionSigning(?string $divergentVote = null): void
    {
        $this->divergentVote = $divergentVote;

        /** @var User|null $user */
        $user = auth()->user();
        $opinion = $this->record->opinion;

        if (! $user || ! $opinion || ! $opinion->isIssued()) {
            Notification::make()
                ->title('Parecer fiscal indisponível para assinatura.')
                ->warning()
                ->send();

            return;
        }

        $signingService = app(FiscalCouncilOpinionSigningService::class);

        try {
            $options = $signingService->generateSigningOptions($opinion, $user);
            $role = $signingService->determineSigningRole($opinion, $user);

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
        /** @var User|null $user */
        $user = auth()->user();
        $opinion = $this->record->opinion;

        if (! $user || ! $opinion) {
            Notification::make()
                ->title('Parecer não encontrado.')
                ->danger()
                ->send();

            return;
        }

        $signingService = app(FiscalCouncilOpinionSigningService::class);

        try {
            $signingService->verifyAndSign(
                $opinion,
                $user,
                $payload,
                $this->divergentVote,
                request()->ip(),
                request()->userAgent()
            );

            Notification::make()
                ->title('Parecer assinado com sucesso!')
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
