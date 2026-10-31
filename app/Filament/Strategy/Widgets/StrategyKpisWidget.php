<?php

namespace App\Filament\Strategy\Widgets;

use App\Enums\StrategyIndicator;
use App\Services\Strategy\ChurchCoverageService;
use App\Services\Strategy\StateIndicatorService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * KPIs de Goiás na Visão de Goiás do Painel de Estratégia.
 * Indicador sem dados aparece como "—" com aviso, nunca como zero.
 */
class StrategyKpisWidget extends BaseWidget
{
    protected static bool $isLazy = false;

    protected static ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $indicators = app(StateIndicatorService::class);
        $state = app(ChurchCoverageService::class)->getStateCoverage();

        $population = $indicators->stateValue(StrategyIndicator::POPULATION_ESTIMATE);
        $populationPeriod = $indicators->periodsFor(StrategyIndicator::POPULATION_ESTIMATE)[0] ?? null;
        $evangelicalPct = $indicators->stateValue(StrategyIndicator::RELIGION_EVANGELICAL_PCT_2022);

        return [
            $this->indicatorStat(
                'População estimada',
                $population,
                StrategyIndicator::POPULATION_ESTIMATE,
                $populationPeriod ? "Estimativa IBGE {$populationPeriod}" : null,
                'heroicon-m-users',
            ),

            $this->indicatorStat(
                'Evangélicos (Censo 2022)',
                $evangelicalPct,
                StrategyIndicator::RELIGION_EVANGELICAL_PCT_2022,
                'Pessoas de 10 anos ou mais',
                'heroicon-m-book-open',
            ),

            Stat::make('Municípios com igreja', "{$state->municipalitiesWithChurchCount} de {$state->totalMunicipalitiesCount}")
                ->description(number_format($state->coveragePercentage, 1, ',', '.')."% de cobertura · {$state->municipalitiesWithoutChurchCount} sem igreja")
                ->descriptionIcon('heroicon-m-map-pin')
                ->color('gray'),

            $state->inhabitantsPerChurch !== null && $population !== null
                ? Stat::make('Habitantes por igreja', StrategyIndicator::POPULATION_ESTIMATE->format($state->inhabitantsPerChurch))
                    ->description("{$state->activeChurchesCount} igrejas ativas")
                    ->descriptionIcon('heroicon-m-building-office-2')
                    ->color('gray')
                : Stat::make('Habitantes por igreja', '—')
                    ->description($state->activeChurchesCount === 0 ? 'Nenhuma igreja ativa vinculada a município' : 'Indicador ainda não sincronizado')
                    ->descriptionIcon('heroicon-m-exclamation-triangle')
                    ->color('warning'),
        ];
    }

    private function indicatorStat(string $label, ?float $value, StrategyIndicator $indicator, ?string $description, string $icon): Stat
    {
        if ($value === null) {
            return Stat::make($label, '—')
                ->description('Indicador ainda não sincronizado')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('warning');
        }

        return Stat::make($label, $indicator->format($value))
            ->description($description)
            ->descriptionIcon($icon)
            ->color('gray');
    }
}
