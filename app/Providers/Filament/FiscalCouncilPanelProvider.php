<?php

namespace App\Providers\Filament;

use App\Filament\FiscalCouncil\Pages\Dashboard;
use App\Filament\Pages\ManagePasskeys;
use App\Providers\Filament\Concerns\ConfiguresCbngoTheme;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;

/**
 * Painel próprio do Conselho Fiscal da CBN-GO (/conselho-fiscal).
 * O acesso segue o RBAC estrito (SUPER_ADMIN e FISCAL_COUNCIL).
 */
class FiscalCouncilPanelProvider extends PanelProvider
{
    use ConfiguresCbngoTheme;

    public function panel(Panel $panel): Panel
    {
        $panel = $panel
            ->id('conselho-fiscal')
            ->path('conselho-fiscal')
            ->brandName('Conselho Fiscal / CBN-GO')
            ->discoverResources(in: app_path('Filament/FiscalCouncil/Resources'), for: 'App\\Filament\\FiscalCouncil\\Resources')
            ->pages([
                Dashboard::class,
                ManagePasskeys::class,
            ])
            ->navigationGroups([
                'Pareceres & Relatórios',
                'Segurança',
            ])
            ->databaseNotifications()
            ->darkMode(false)
            ->sidebarWidth('16rem')
            ->font('Plus Jakarta Sans')
            ->renderHook(
                PanelsRenderHook::HEAD_START,
                fn (): View => view('filament.components.fonts-head')
            );

        return $this->applyCbngoConfiguration($panel);
    }
}
