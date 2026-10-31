<?php

namespace App\Filament\Concerns;

use App\Services\Strategy\StateIndicatorService;
use Illuminate\Contracts\View\View;

/**
 * Rodapé das telas do Painel de Estratégia: fonte e data da última sincronização.
 */
trait HasStrategySourceFooter
{
    public function getFooter(): ?View
    {
        return view('filament.strategy.source-footer', [
            'lastSyncedAt' => app(StateIndicatorService::class)->lastSyncedAt(),
        ]);
    }
}
