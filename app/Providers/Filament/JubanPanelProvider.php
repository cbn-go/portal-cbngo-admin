<?php

namespace App\Providers\Filament;

use App\Filament\Juban\Pages\Dashboard;
use App\Filament\Juban\Pages\ManagePasskeys;
use App\Filament\Juban\Widgets\CashByCategoryWidget;
use App\Filament\Juban\Widgets\CashSummaryStatsWidget;
use App\Providers\Filament\Concerns\ConfiguresCbngoTheme;
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
                ManagePasskeys::class,
            ])
            ->navigationGroups([
                'Tesouraria',
                'Segurança',
            ])
            ->databaseNotifications()
            // Widgets do resumo de caixa: registrados aqui para o Livewire (senão volta 419);
            // a página inicial escolhe quais exibir em getWidgets().
            ->widgets([
                CashSummaryStatsWidget::class,
                CashByCategoryWidget::class,
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
