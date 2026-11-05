<?php

namespace App\Providers\Filament;

use App\Filament\Ormiban\Pages\Auth\OrmibanLogin;
use App\Filament\Ormiban\Pages\Auth\SetInitialPassword;
use App\Filament\Ormiban\Pages\EditMinisterProfile;
use App\Filament\Ormiban\Pages\EditMyChurch;
use App\Filament\Pages\Strategy\GoiasOverview;
use App\Filament\Pages\Strategy\Methodology;
use App\Filament\Resources\MunicipalityResource;
use App\Filament\Strategy\Widgets\AgeGroupChartWidget;
use App\Filament\Strategy\Widgets\MunicipalityMapWidget;
use App\Filament\Strategy\Widgets\StrategyKpisWidget;
use App\Http\Controllers\Strategy\MunicipalityMapGeoJsonController;
use App\Http\Middleware\EnsurePasswordIsSet;
use App\Providers\Filament\Concerns\ConfiguresCbngoTheme;
use Filament\Http\Middleware\Authenticate;
use Filament\Navigation\MenuItem;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Support\Facades\Route;

class OrmibanPanelProvider extends PanelProvider
{
    use ConfiguresCbngoTheme;

    public function panel(Panel $panel): Panel
    {
        $panel = $panel
            ->id('ormiban')
            ->path('ormiban')
            ->brandName('Ormiban / CBN-GO')
            ->profile(EditMinisterProfile::class)
            ->userMenuItems([
                'profile' => MenuItem::make()
                    ->label('Meu Perfil')
                    ->url(fn (): string => EditMinisterProfile::getUrl())
                    ->icon('heroicon-o-user-circle'),
            ])
            ->pages([
                Pages\Dashboard::class,
                SetInitialPassword::class,
                EditMyChurch::class,
                GoiasOverview::class,
                Methodology::class,
            ])
            ->resources([
                MunicipalityResource::class,
            ])
            ->navigationGroups([
                'Estratégia',
            ])
            // @phpstan-ignore argument.type
            ->livewireComponents([
                StrategyKpisWidget::class,
                MunicipalityMapWidget::class,
                AgeGroupChartWidget::class,
            ])
            ->authenticatedRoutes(function (): void {
                Route::get('estrategia/malha.geojson', MunicipalityMapGeoJsonController::class)
                    ->name('strategy.map-geojson');
            });

        $panel = $this->applyCbngoConfiguration($panel);

        return $panel
            ->login(OrmibanLogin::class)
            ->authMiddleware([
                Authenticate::class,
                EnsurePasswordIsSet::class,
            ]);
    }
}
