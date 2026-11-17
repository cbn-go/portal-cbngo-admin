<?php

namespace App\Filament\Juban\Pages;

use App\Filament\Juban\Widgets\CashByCategoryWidget;
use App\Filament\Juban\Widgets\CashSummaryStatsWidget;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;

/**
 * Página inicial do painel /juban: resumo de caixa do período (#97).
 */
class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    protected static ?string $navigationIcon = 'heroicon-o-chart-pie';

    protected static ?string $navigationLabel = 'Resumo de caixa';

    protected static ?string $title = 'Resumo de caixa';

    // O padrão é sempre o mês corrente; o período escolhido fica só na URL.
    public function persistsFiltersInSession(): bool
    {
        return false;
    }

    public function filtersForm(Form $form): Form
    {
        return $form
            ->schema([
                DatePicker::make('from')
                    ->label('De')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->default(fn (): string => now()->startOfMonth()->toDateString())
                    ->maxDate(fn (Get $get): ?string => $get('until')),

                DatePicker::make('until')
                    ->label('Até')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->default(fn (): string => now()->endOfMonth()->toDateString())
                    ->minDate(fn (Get $get): ?string => $get('from')),
            ])
            ->columns(['md' => 2, 'xl' => 4]);
    }

    public function getWidgets(): array
    {
        return [
            CashSummaryStatsWidget::class,
            CashByCategoryWidget::class,
        ];
    }

    public function getColumns(): int|string|array
    {
        return 1;
    }
}
