<?php

namespace App\Filament\Juban\Widgets;

use App\Filament\Juban\Resources\JubanTransactionResource;
use App\Services\Juban\CashSummary;
use App\Services\Juban\CashSummaryResult;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

/**
 * Cards de gastos, receita e saldo do período, no estilo do design da tesouraria.
 */
class CashSummaryStatsWidget extends Widget
{
    use InteractsWithPageFilters;

    protected static bool $isLazy = false;

    protected static string $view = 'filament.juban.widgets.cash-summary-cards';

    protected int|string|array $columnSpan = 'full';

    public function getSummary(): CashSummaryResult
    {
        [$from, $until] = CashSummary::periodFromFilters($this->filters);

        return app(CashSummary::class)->forPeriod($from, $until);
    }

    /**
     * Lista de lançamentos já filtrada pelo mesmo período, para conferência.
     */
    public function getTransactionsUrl(CashSummaryResult $summary): string
    {
        return JubanTransactionResource::getUrl('index', [
            'tableFilters' => [
                'periodo' => [
                    'from' => $summary->from->toDateString(),
                    'until' => $summary->until->toDateString(),
                ],
            ],
        ]);
    }

    public static function countLabel(int $count): string
    {
        return $count === 1 ? '1 lançamento' : "{$count} lançamentos";
    }
}
