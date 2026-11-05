<?php

namespace App\Filament\Ormiban\Widgets;

use App\DTOs\Ormiban\MinisterDemographicsData;
use App\Services\Ormiban\MinisterDemographics;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

abstract class MinisterBandChartWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static bool $isLazy = false;

    protected static ?string $pollingInterval = null;

    protected static ?string $maxHeight = '280px';

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<int|string, int>
     */
    abstract protected function series(MinisterDemographicsData $data): array;

    protected function datasetLabel(): string
    {
        return 'Ministros';
    }

    protected function getData(): array
    {
        $series = $this->series($this->demographics());

        return [
            'datasets' => [
                [
                    'label' => $this->datasetLabel(),
                    'data' => array_values($series),
                    'backgroundColor' => '#e81828',
                    'borderRadius' => 4,
                ],
            ],
            'labels' => array_keys($series),
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

    protected function demographics(): MinisterDemographicsData
    {
        return app(MinisterDemographics::class)->summarize($this->filters ?? []);
    }
}
