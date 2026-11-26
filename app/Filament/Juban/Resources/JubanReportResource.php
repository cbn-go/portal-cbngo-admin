<?php

namespace App\Filament\Juban\Resources;

use App\Filament\Juban\Resources\JubanReportResource\Pages;
use App\Models\JubanReport;
use App\Services\Juban\QuarterlyClosingService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class JubanReportResource extends Resource
{
    protected static ?string $model = JubanReport::class;

    protected static ?string $slug = 'relatorios';

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $navigationGroup = 'Tesouraria';

    protected static ?string $navigationLabel = 'Relatórios Trimestrais';

    protected static ?string $modelLabel = 'Relatório Trimestral';

    protected static ?string $pluralModelLabel = 'Relatórios Trimestrais';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('quarter_label')
                    ->label('Trimestre')
                    ->state(fn (JubanReport $record): string => "{$record->year} - Q{$record->quarter}".($record->version > 1 ? " (v{$record->version})" : ''))
                    ->weight('bold')
                    ->searchable(query: function ($query, string $search) {
                        return $query->where('year', 'like', "%{$search}%");
                    }),

                Tables\Columns\TextColumn::make('period_range')
                    ->label('Período')
                    ->state(fn (JubanReport $record): string => $record->period_from->format('d/m/Y').' a '.$record->period_until->format('d/m/Y')),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge(),

                Tables\Columns\TextColumn::make('saldo')
                    ->label('Saldo Líquido')
                    ->state(function (JubanReport $record): string {
                        $val = (float) ($record->snapshot['summary']['saldo'] ?? 0);

                        return 'R$ '.number_format($val, 2, ',', '.');
                    })
                    ->color(function (JubanReport $record): string {
                        $val = (float) ($record->snapshot['summary']['saldo'] ?? 0);

                        return $val >= 0 ? 'success' : 'danger';
                    })
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('closed_at')
                    ->label('Fechado em')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('N/A'),

                Tables\Columns\TextColumn::make('closedBy.name')
                    ->label('Fechado por')
                    ->placeholder('N/A'),
            ])
            ->defaultSort('year', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),

                Action::make('downloadPdf')
                    ->label('Baixar PDF')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->color('gray')
                    ->visible(fn (JubanReport $record): bool => (bool) ($record->pdf_path && Storage::disk('local')->exists($record->pdf_path)))
                    ->action(function (JubanReport $record): StreamedResponse {
                        $filename = "relatorio-juban-{$record->year}-q{$record->quarter}.pdf";

                        /** @var string $pdfPath */
                        $pdfPath = $record->pdf_path;

                        return Storage::disk('local')->download($pdfPath, $filename);
                    }),

                Action::make('reopen')
                    ->label('Reabrir')
                    ->icon('heroicon-m-arrow-path')
                    ->color('danger')
                    ->visible(fn (JubanReport $record): bool => auth()->user()?->can('reopen', $record) ?? false)
                    ->requiresConfirmation()
                    ->modalHeading('Reabrir Trimestre Contábil')
                    ->modalDescription('A reabertura cancelará o fechamento, excluirá o PDF atual e destravará todos os lançamentos do trimestre para ajustes.')
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Motivo da reabertura')
                            ->required()
                            ->placeholder('Descreva detalhadamente o motivo da reabertura...'),
                    ])
                    ->action(function (JubanReport $record, array $data): void {
                        $service = app(QuarterlyClosingService::class);
                        $service->reopenQuarter($record, auth()->user(), $data['reason'] ?? null);

                        Notification::make()
                            ->title('Trimestre reaberto com sucesso!')
                            ->body('Os lançamentos do período foram destravados para edições.')
                            ->success()
                            ->send();
                    }),

                Action::make('create_new_version')
                    ->label('Criar Nova Versão')
                    ->icon('heroicon-m-arrow-path-rounded-square')
                    ->color('warning')
                    ->visible(fn (JubanReport $record): bool => auth()->user()?->can('createNewVersion', $record) ?? false)
                    ->requiresConfirmation()
                    ->modalHeading('Abrir Nova Versão após Reprovação')
                    ->modalDescription('Uma nova versão será aberta (em status Reaberto), permitindo à Tesouraria ajustar os lançamentos do trimestre. A versão atual reprovada e seu PDF permanecerão intactos no histórico.')
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Motivo / Justificativa da nova versão')
                            ->required()
                            ->placeholder('Descreva os ajustes solicitados pelo Conselho Fiscal que serão realizados...'),
                    ])
                    ->action(function (JubanReport $record, array $data): void {
                        $service = app(QuarterlyClosingService::class);
                        $newVersion = $service->createNewVersionAfterRejection($record, auth()->user(), (string) $data['reason']);

                        Notification::make()
                            ->title("Nova versão v{$newVersion->version} criada com sucesso!")
                            ->body('Os lançamentos foram destravados para correções pela Tesouraria.')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListJubanReports::route('/'),
            'view' => Pages\ViewJubanReport::route('/{record}'),
        ];
    }
}
