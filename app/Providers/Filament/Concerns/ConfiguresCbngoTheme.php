<?php

namespace App\Providers\Filament\Concerns;

use App\Filament\Pages\Auth\Login;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

trait ConfiguresCbngoTheme
{
    /**
     * Aplica as configurações institucionais CBN-GO comuns a todos os painéis.
     */
    protected function applyCbngoConfiguration(Panel $panel): Panel
    {
        return $panel
            ->login(Login::class)
            ->passwordReset()
            ->brandLogo(fn () => asset('images/logo-cbngo.svg'))
            ->darkModeBrandLogo(fn () => asset('images/logo-cbngo-dark.svg'))
            ->brandLogoHeight('2.5rem')
            ->colors([
                'primary' => [
                    50 => '#fbf8ed',
                    100 => '#f5eed2',
                    200 => '#ecdc9e',
                    300 => '#e0c464',
                    400 => '#d5aa37',
                    500 => '#c5a059', // Dourado CBN-GO
                    600 => '#a8803a',
                    700 => '#86602e',
                    800 => '#6d4c28',
                    900 => '#5b3f23',
                    950 => '#342111',
                ],
                'gray' => [
                    50 => '#f0f4f8',
                    100 => '#d9e2ec',
                    200 => '#bcccdc',
                    300 => '#9fb3c8',
                    400 => '#627d98',
                    500 => '#486581',
                    600 => '#334e68',
                    700 => '#243b53',
                    800 => '#102a43',
                    900 => '#0b192c', // Azul Noturno CBN-GO
                    950 => '#060d17',
                ],
            ])
            ->widgets([
                Widgets\AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
