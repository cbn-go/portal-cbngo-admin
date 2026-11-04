<?php

namespace App\Providers\Filament;

use App\Filament\Ormiban\Pages\Auth\OrmibanLogin;
use App\Filament\Ormiban\Pages\Auth\SetInitialPassword;
use App\Http\Middleware\EnsurePasswordIsSet;
use App\Providers\Filament\Concerns\ConfiguresCbngoTheme;
use Filament\Http\Middleware\Authenticate;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;

class OrmibanPanelProvider extends PanelProvider
{
    use ConfiguresCbngoTheme;

    public function panel(Panel $panel): Panel
    {
        $panel = $panel
            ->id('ormiban')
            ->path('ormiban')
            ->brandName('CBN Goiás - Ormiban')
            ->pages([
                Pages\Dashboard::class,
                SetInitialPassword::class,
            ]);

        $panel = $this->applyCbngoConfiguration($panel);

        return $panel
            ->login(OrmibanLogin::class)
            ->authMiddleware([
                Authenticate::class,
                EnsurePasswordIsSet::class,
            ]);
    }
}
