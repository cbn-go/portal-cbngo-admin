<?php

namespace App\Providers\Filament;

use App\Providers\Filament\Concerns\ConfiguresCbngoTheme;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;

/**
 * Painel da tesouraria da JUBAN: livro-caixa de receitas e despesas.
 * O acesso segue o RBAC da #95 (SuperAdmin, tesouraria JUBAN e Secretaria).
 */
class JubanPanelProvider extends PanelProvider
{
    use ConfiguresCbngoTheme;

    public function panel(Panel $panel): Panel
    {
        $panel = $panel
            ->id('juban')
            ->path('juban')
            ->brandName('Tesouraria JUBAN / CBN-GO')
            ->discoverResources(in: app_path('Filament/Juban/Resources'), for: 'App\\Filament\\Juban\\Resources')
            ->pages([
                Dashboard::class,
            ])
            ->navigationGroups([
                'Tesouraria',
            ])
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
