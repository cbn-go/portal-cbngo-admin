<?php

namespace App\Filament\Ormiban\Widgets;

use App\Services\Ormiban\MinisterDemographics;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MinisterSummaryWidget extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static bool $isLazy = false;

    protected static ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $data = app(MinisterDemographics::class)->summarize($this->filters ?? []);

        return [
            Stat::make('Total ativos', (string) $data->activeCount)
                ->description("{$data->activeCount} com status ativo no filtro")
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
            Stat::make('Pastores', (string) $data->pastorCount)
                ->descriptionIcon('heroicon-m-user')
                ->color('primary'),
            Stat::make('Missionários', (string) $data->missionaryCount)
                ->descriptionIcon('heroicon-m-globe-alt')
                ->color('primary'),
            Stat::make('Média de idade', $this->years($data->ageMean))
                ->description('Anos completos')
                ->color('gray'),
            Stat::make('Mediana de idade', $this->years($data->ageMedian))
                ->description('Anos completos')
                ->color('gray'),
            Stat::make('Com diplomação', $this->percent($data->diplomaPercent))
                ->description('Do conjunto filtrado')
                ->color('warning'),
            Stat::make('Com ordenação', $this->percent($data->ordinationPercent))
                ->description('Do conjunto filtrado')
                ->color('warning'),
        ];
    }

    private function years(?float $value): string
    {
        if ($value === null) {
            return '—';
        }

        return number_format($value, 1, ',', '.');
    }

    private function percent(?float $value): string
    {
        if ($value === null) {
            return '—';
        }

        return number_format($value, 1, ',', '.').'%';
    }
}
