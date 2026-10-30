<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Providers\Filament\Concerns\ConfiguresCbngoTheme;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;

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
                Dashboard::class,
            ])
            ->navigationGroups([
                'Secretaria',
                'Portal',
                'Sistema',
            ])
            ->databaseNotifications()
            ->font('Plus Jakarta Sans')
            ->renderHook(
                PanelsRenderHook::HEAD_START,
                fn (): View => view('filament.components.fonts-head')
            )
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_AFTER,
                fn (): View => view('filament.components.view-site-link')
            )
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets');

        return $this->applyCbngoConfiguration($panel);
    }
}
