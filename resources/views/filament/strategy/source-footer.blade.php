<div class="mt-6 flex flex-wrap items-center gap-x-2 gap-y-1 border-t border-gray-200 pt-4 text-xs text-gray-500">
    <span>Fonte: IBGE (Censo 2022, Censo 2010, Estimativas da População, PIB dos Municípios).</span>

    @if ($lastSyncedAt)
        <span>Última sincronização: {{ $lastSyncedAt->timezone(config('app.timezone'))->format('d/m/Y H:i') }}.</span>
    @else
        <span class="inline-flex items-center gap-1 rounded-md bg-warning-50 px-2 py-0.5 font-medium text-warning-700 ring-1 ring-inset ring-warning-600/20">
            <x-heroicon-m-exclamation-triangle class="h-4 w-4" />
            Indicadores ainda não sincronizados. Rode <code>php artisan strategy:sync-indicators</code>.
        </span>
    @endif
</div>
