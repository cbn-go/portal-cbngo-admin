<?php

namespace App\Filament\Strategy\Widgets;

use App\Enums\StrategyIndicator;
use App\Services\Strategy\StateIndicatorService;
use Filament\Widgets\ChartWidget;

/**
 * População de Goiás por faixa etária (Censo 2022).
 */
class AgeGroupChartWidget extends ChartWidget
{
    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected static ?string $heading = 'População por faixa etária (Censo 2022)';

    protected static ?string $maxHeight = '280px';

    protected int|string|array $columnSpan = 'full';

    /**
     * @var array<string, StrategyIndicator>
     */
    public const AGE_GROUPS = [
        '0 a 14 anos' => StrategyIndicator::AGE_0_14,
        '15 a 29 anos' => StrategyIndicator::AGE_15_29,
        '30 a 59 anos' => StrategyIndicator::AGE_30_59,
        '60 anos ou mais' => StrategyIndicator::AGE_60_PLUS,
    ];

    public function getDescription(): ?string
    {
        return $this->hasData() ? null : 'Faixas etárias ainda não sincronizadas com o IBGE.';
    }

    protected function getData(): array
    {
        $service = app(StateIndicatorService::class);

        return [
            'datasets' => [
                [
                    'label' => 'Habitantes',
                    'data' => array_values(array_map(
                        fn (StrategyIndicator $indicator): ?float => $service->stateValue($indicator),
                        self::AGE_GROUPS,
                    )),
                    'backgroundColor' => '#e81828',
                    'borderRadius' => 4,
                ],
            ],
            'labels' => array_keys(self::AGE_GROUPS),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
            'scales' => ['y' => ['beginAtZero' => true]],
        ];
    }

    private function hasData(): bool
    {
        $service = app(StateIndicatorService::class);

        foreach (self::AGE_GROUPS as $indicator) {
            if ($service->stateValue($indicator) !== null) {
                return true;
            }
        }

        return false;
    }
}
