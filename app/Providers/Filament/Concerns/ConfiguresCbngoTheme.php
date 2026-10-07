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
            // Logo oficial CBN | GO (kit CBN_ESTADUAIS). Paleta amostrada dos arquivos *-CORES.png: vermelho #E81828 + tinta #201820.
            ->brandLogo(fn () => asset('images/logo-cbngo.png'))
            ->darkModeBrandLogo(fn () => asset('images/logo-cbngo-dark.png'))
            ->brandLogoHeight('2.75rem')
            ->colors([
                'primary' => [
                    50 => '#fdeced',
                    100 => '#fbdadc',
                    200 => '#f7b5ba',
                    300 => '#f38b93',
                    400 => '#ee5864',
                    500 => '#e81828',
                    600 => '#c51422',
                    700 => '#a2101c',
                    800 => '#7f0d16',
                    900 => '#5c0910',
                    950 => '#330508',
                ],
                'gray' => [
                    50 => '#f6f5f6',
                    100 => '#e8e7e8',
                    200 => '#d2d0d2',
                    300 => '#b0aeb0',
                    400 => '#8f8b8f',
                    500 => '#746f74',
                    600 => '#5e585e',
                    700 => '#484148',
                    800 => '#362f36',
                    900 => '#201820',
                    950 => '#1b141b',
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
