<?php

namespace App\Providers\Filament;

use App\Enums\UserRole;
use App\Filament\Portal\Pages\EditAuthorProfile;
use App\Filament\Resources\ArticleResource;
use App\Filament\Resources\NewsResource;
use App\Providers\Filament\Concerns\ConfiguresCbngoTheme;
use Filament\Navigation\MenuItem;
use Filament\Navigation\NavigationItem;
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
            ->profile(EditAuthorProfile::class)
            ->userMenuItems([
                'profile' => MenuItem::make()
                    ->label(fn (): string => 'Meu Perfil de Autor')
                    ->url(fn (): string => EditAuthorProfile::getUrl())
                    ->icon('heroicon-o-user-circle')
                    ->visible(fn (): bool => auth()->user()?->role === UserRole::AUTHOR),
            ])
            ->resources([
                ArticleResource::class,
                NewsResource::class,
            ])
            ->navigationItems([
                NavigationItem::make('Meu Perfil de Autor')
                    ->url(fn (): string => EditAuthorProfile::getUrl())
                    ->icon('heroicon-o-user-circle')
                    ->group('Minha Conta')
                    ->sort(10)
                    ->visible(fn (): bool => auth()->user()?->role === UserRole::AUTHOR),
            ])

            ->discoverResources(in: app_path('Filament/Portal/Resources'), for: 'App\\Filament\\Portal\\Resources')
            ->discoverPages(in: app_path('Filament/Portal/Pages'), for: 'App\\Filament\\Portal\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Portal/Widgets'), for: 'App\\Filament\\Portal\\Widgets');

        return $this->applyCbngoConfiguration($panel);
    }
}
