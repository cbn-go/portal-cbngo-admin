<?php

namespace App\Filament\Juban\Widgets;

use App\Filament\Juban\Resources\JubanTransactionResource;
use App\Services\Juban\CashSummary;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CashSummaryStatsWidget extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static bool $isLazy = false;

    protected static ?string $pollingInterval = null;

    protected function getStats(): array
    {
        [$from, $until] = CashSummary::periodFromFilters($this->filters);
        $summary = app(CashSummary::class)->forPeriod($from, $until);

        $listUrl = JubanTransactionResource::getUrl('index', [
            'tableFilters' => [
                'periodo' => [
                    'from' => $from->toDateString(),
                    'until' => $until->toDateString(),
                ],
            ],
        ]);

        $lancamentos = $summary->count === 1 ? '1 lançamento' : "{$summary->count} lançamentos";

        return [
            Stat::make('Receitas', JubanTransactionResource::formatBrl($summary->receitas))
                ->description($summary->periodLabel())
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),

            Stat::make('Despesas', JubanTransactionResource::formatBrl($summary->despesas))
                ->description($summary->periodLabel())
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('danger'),

            Stat::make('Saldo do período', ($summary->saldo < 0 ? '- ' : '').JubanTransactionResource::formatBrl(abs($summary->saldo)))
                ->description("{$lancamentos} · ver lançamentos")
                ->descriptionIcon('heroicon-m-arrow-top-right-on-square')
                ->color($summary->saldo < 0 ? 'danger' : 'success')
                ->url($listUrl),
        ];
    }
}
