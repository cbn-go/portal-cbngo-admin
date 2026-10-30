<x-filament-panels::page
    @class([
        'fi-resource-list-records-page',
        'fi-resource-' . str_replace('/', '-', $this->getResource()::getSlug()),
    ])
>
    <div class="flex flex-col gap-y-6">
        @php
            $legacyCount = \App\Models\Banner::query()->where('is_square', false)->count();
        @endphp

        @if ($legacyCount > 0)
            <div class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-amber-900 shadow-sm dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300">
                <div class="flex items-center gap-3">
                    <x-filament::icon icon="heroicon-m-exclamation-triangle" class="h-5 w-5 text-amber-600 dark:text-amber-400 shrink-0" />
                    <div class="text-sm">
                        <strong>{{ $legacyCount }} {{ $legacyCount === 1 ? 'aviso precisa' : 'avisos precisam' }} de foto quadrada 1:1.</strong>
                        Artes antigas no formato 16:9 foram desativadas e não aparecem no carrossel da home até que você edite o aviso e envie uma foto 1:1.
                    </div>
                </div>
            </div>
        @endif

        {{ $this->table }}
    </div>
</x-filament-panels::page>
