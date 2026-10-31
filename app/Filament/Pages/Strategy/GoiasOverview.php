<?php

namespace App\Filament\Pages\Strategy;

use App\DTOs\Strategy\RegionalCoverageData;
use App\Filament\Concerns\HasStrategySourceFooter;
use App\Filament\Resources\MunicipalityResource;
use App\Filament\Strategy\Widgets\AgeGroupChartWidget;
use App\Filament\Strategy\Widgets\MunicipalityMapWidget;
use App\Filament\Strategy\Widgets\StrategyKpisWidget;
use App\Services\Strategy\ChurchCoverageService;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

/**
 * Painel de Estratégia: visão geral de Goiás com KPIs, faixas etárias e
 * cobertura por região imediata.
 */
class GoiasOverview extends Page
{
    use HasStrategySourceFooter;

    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static ?string $navigationGroup = 'Estratégia';

    protected static ?string $navigationLabel = 'Visão de Goiás';

    protected static ?string $title = 'Visão de Goiás';

    protected static ?string $slug = 'estrategia';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.strategy.goias-overview';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->is_active && $user->role->hasAdminPanelAccess();
    }

    public function getSubheading(): string
    {
        return 'População, perfil religioso e cobertura da CBN-GO nos 246 municípios do estado.';
    }

    protected function getHeaderWidgets(): array
    {
        return [
            StrategyKpisWidget::class,
            MunicipalityMapWidget::class,
            AgeGroupChartWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    /**
     * Regiões imediatas da menor para a maior cobertura.
     *
     * @return Collection<string, RegionalCoverageData>
     */
    public function regions(): Collection
    {
        return app(ChurchCoverageService::class)
            ->getImmediateRegionsCoverage()
            ->sortBy([['coveragePercentage', 'asc'], ['totalPopulation', 'desc']]);
    }

    public function regionUrl(RegionalCoverageData $region): string
    {
        return MunicipalityResource::getUrl('index', [
            'tableFilters' => ['immediate_region_code' => ['value' => $region->regionCode]],
        ]);
    }
}
