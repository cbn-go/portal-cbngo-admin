<?php

namespace App\Filament\Juban\Widgets;

use App\Services\Juban\CashSummaryResult;

/**
 * Gastos por mês e por categoria, e receita por categoria, no estilo do design da tesouraria.
 */
class CashByCategoryWidget extends CashSummaryStatsWidget
{
    // Altura da barra do mês de maior gasto, como no design.
    public const MAX_BAR_HEIGHT = 150;

    protected static string $view = 'filament.juban.widgets.cash-by-category';

    /**
     * Barras do gráfico mensal. Valores em milhares de reais quando o maior mês passa de R$ 1.000.
     *
     * @return array{unit: string, bars: list<array{label: string, value: string, height: int}>}
     */
    public function getMonthlyChart(CashSummaryResult $summary): array
    {
        $max = max([0.0, ...array_map(fn (array $month): float => $month['total'], $summary->monthlyDespesas)]);
        $inThousands = $max >= 1000;
        $spansYears = $summary->from->year !== $summary->until->year;

        $bars = array_map(function (array $month) use ($max, $inThousands, $spansYears): array {
            $label = $month['month']->copy()->locale('pt_BR')->translatedFormat($spansYears ? 'M/y' : 'M');

            return [
                'label' => mb_strtolower(rtrim($label, '.')),
                'value' => $inThousands
                    ? number_format($month['total'] / 1000, 1, ',', '.')
                    : number_format($month['total'], 0, ',', '.'),
                'height' => $max > 0 ? (int) round($month['total'] / $max * self::MAX_BAR_HEIGHT) : 0,
            ];
        }, $summary->monthlyDespesas);

        return [
            'unit' => $inThousands ? 'em milhares de reais' : 'em reais',
            'bars' => $bars,
        ];
    }

    public function formatPercent(float $percent): string
    {
        return number_format($percent, 0, ',', '.').'%';
    }
}
