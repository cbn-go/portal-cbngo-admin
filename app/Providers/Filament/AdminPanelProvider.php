<?php

namespace App\Providers\Filament;

use App\Providers\Filament\Concerns\ConfiguresCbngoTheme;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;

class AdminPanelProvider extends PanelProvider
{
    use ConfiguresCbngoTheme;

    public function panel(Panel $panel): Panel
    {
        $panel = $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName('CBN Goiás - Administração Central')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets');

        return $this->applyCbngoConfiguration($panel);
    }
}
