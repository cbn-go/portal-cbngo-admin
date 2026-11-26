<?php

namespace App\Filament\FiscalCouncil\Resources;

use App\Enums\JubanReportInquiryStatus;
use App\Enums\JubanReportStatus;
use App\Filament\FiscalCouncil\Resources\JubanReportResource\Pages;
use App\Models\JubanReport;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class JubanReportResource extends Resource
{
    protected static ?string $model = JubanReport::class;

    protected static ?string $slug = 'relatorios-juban';

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $navigationGroup = 'Pareceres & Relatórios';

    protected static ?string $navigationLabel = 'Relatórios JUBAN';

    protected static ?string $modelLabel = 'Relatório JUBAN';

    protected static ?string $pluralModelLabel = 'Relatórios JUBAN';

    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereIn('status', [
                JubanReportStatus::AGUARDANDO_CONSELHO_FISCAL,
                JubanReportStatus::EM_REVISAO,
                JubanReportStatus::APROVADO,
                JubanReportStatus::APROVADO_COM_RESSALVAS,
                JubanReportStatus::REPROVADO,
                JubanReportStatus::ASSINADO,
            ]);
    }

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

                Tables\Columns\TextColumn::make('inquiries_count')
                    ->label('Esclarecimentos')
                    ->state(function (JubanReport $record): string {
                        $open = $record->inquiries()->where('status', JubanReportInquiryStatus::ABERTO)->count();
                        $total = $record->inquiries()->count();

                        if ($total === 0) {
                            return 'Nenhum';
                        }

                        return $open > 0 ? "{$open} pendente(s)" : "{$total} concluído(s)";
                    })
                    ->badge()
                    ->color(function (JubanReport $record): string {
                        $open = $record->inquiries()->where('status', JubanReportInquiryStatus::ABERTO)->count();

                        return $open > 0 ? 'warning' : 'gray';
                    }),

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
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Action::make('download_pdf')
                    ->label('Baixar PDF')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->color('gray')
                    ->visible(fn (JubanReport $record): bool => (bool) ($record->pdf_path && Storage::disk('local')->exists($record->pdf_path)))
                    ->action(function (JubanReport $record): StreamedResponse {
                        $filename = "relatorio-juban-{$record->year}-q{$record->quarter}".($record->version > 1 ? "-v{$record->version}" : '').'.pdf';

                        /** @var string $pdfPath */
                        $pdfPath = $record->pdf_path;

                        return Storage::disk('local')->download($pdfPath, $filename);
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
