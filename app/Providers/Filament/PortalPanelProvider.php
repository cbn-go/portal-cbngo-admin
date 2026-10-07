<?php

namespace App\Providers\Filament;

use App\Providers\Filament\Concerns\ConfiguresCbngoTheme;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;

class PortalPanelProvider extends PanelProvider
{
    use ConfiguresCbngoTheme;

    public function panel(Panel $panel): Panel
    {
        $panel = $panel
            ->id('portal')
            ->path('portal')
            ->brandName('CBN Goiás - Portal das Igrejas e Autores')
            ->discoverResources(in: app_path('Filament/Portal/Resources'), for: 'App\\Filament\\Portal\\Resources')
            ->discoverPages(in: app_path('Filament/Portal/Pages'), for: 'App\\Filament\\Portal\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Portal/Widgets'), for: 'App\\Filament\\Portal\\Widgets');

        return $this->applyCbngoConfiguration($panel);
    }
}
