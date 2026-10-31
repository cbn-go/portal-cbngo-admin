@php
    use App\Filament\Resources\MunicipalityResource;
    use Filament\Support\Facades\FilamentAsset;
@endphp

<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Mapa de Goiás por município</x-slot>
        <x-slot name="description">Passe o mouse para ver os números; clique para abrir o município.</x-slot>

        <style>
            .st-map-grid { display: grid; grid-template-columns: minmax(0, 1fr) 15rem; gap: 1.25rem; align-items: start; }
            /* isolation: as camadas do Leaflet (z-index 400+) não passam por cima do menu lateral do Filament. */
            .st-map { height: 30rem; width: 100%; border-radius: 0.5rem; background: rgb(var(--gray-50)); position: relative; z-index: 0; isolation: isolate; }
            .st-map .leaflet-container { font-family: inherit; }
            .st-legend { display: flex; flex-direction: column; gap: 0.75rem; font-size: 0.8125rem; color: rgb(var(--gray-700)); }
            .st-legend-title { font-size: 0.75rem; font-weight: 600; color: rgb(var(--gray-950)); }
            .st-legend-item { display: flex; align-items: center; gap: 0.5rem; }
            .st-legend-swatch { width: 1rem; height: 1rem; border-radius: 0.25rem; flex: none; }
            .st-legend-count { margin-left: auto; color: rgb(var(--gray-500)); font-variant-numeric: tabular-nums; }
            .st-legend-marker { width: 0.75rem; height: 0.75rem; border-radius: 9999px; border: 2px solid #0b0b0b; background: #fff; flex: none; margin: 0 0.125rem; }
            .st-legend-note { font-size: 0.75rem; color: rgb(var(--gray-500)); }
            @media (max-width: 767px) {
                .st-map-grid { grid-template-columns: minmax(0, 1fr); }
                .st-map { height: 22rem; }
            }
        </style>

        @if (! $available)
            <div class="flex items-start gap-2 rounded-lg p-4 text-sm" style="background: rgb(var(--warning-50)); color: rgb(var(--warning-700));">
                <x-heroicon-m-exclamation-triangle class="h-5 w-5 flex-none" />
                <span>Malha municipal ainda não sincronizada. Rode <code>php artisan strategy:sync-map</code>.</span>
            </div>
        @else
            <div
                x-ignore
                ax-load
                ax-load-src="{{ FilamentAsset::getAlpineComponentSrc('strategy-map') }}"
                x-data="strategyMap(@js($config))"
                class="st-map-grid"
                wire:ignore
            >
                <div>
                    <div x-ref="map" class="st-map" role="img" aria-label="Mapa coroplético dos municípios de Goiás"></div>
                    <p x-show="loading" class="st-legend-note mt-2">Carregando o mapa…</p>
                    <p x-show="error" x-text="error" class="mt-2 text-sm" style="color: rgb(var(--danger-600));"></p>
                </div>

                <div class="st-legend">
                    <label class="st-legend" style="gap: 0.4rem;">
                        <span class="st-legend-title">Indicador</span>
                        <x-filament::input.wrapper>
                            <x-filament::input.select x-model="layer">
                                @foreach ($config['layers'] as $key => $layer)
                                    <option value="{{ $key }}">{{ $layer['label'] }}</option>
                                @endforeach
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </label>

                    <p class="st-legend-note" x-text="current().description"></p>

                    <div class="st-legend" style="gap: 0.4rem;">
                        <template x-for="item in current().classes" :key="item.color + item.label">
                            <div class="st-legend-item">
                                <span class="st-legend-swatch" :style="`background: ${item.color}`"></span>
                                <span x-text="item.label"></span>
                                <span class="st-legend-count" x-text="countFor(item.color)"></span>
                            </div>
                        </template>
                        <template x-for="item in current().special" :key="item.key">
                            <div class="st-legend-item">
                                <span class="st-legend-swatch" :style="`background: ${item.color}`"></span>
                                <span x-text="item.label"></span>
                                <span class="st-legend-count" x-text="countFor(item.color)"></span>
                            </div>
                        </template>
                        <div class="st-legend-item">
                            <span class="st-legend-marker"></span>
                            <span>Igreja com localização</span>
                        </div>
                    </div>

                    <p class="st-legend-note">
                        Os números ao lado são a quantidade de municípios.
                        @if ($churchesWithoutCoordinates > 0)
                            {{ $churchesWithoutCoordinates === 1 ? '1 igreja ativa não tem' : "{$churchesWithoutCoordinates} igrejas ativas não têm" }} latitude e longitude e entra{{ $churchesWithoutCoordinates === 1 ? '' : 'm' }} só pela contagem do município.
                        @endif
                        A lista completa está em <a href="{{ MunicipalityResource::getUrl('index') }}" class="st-link">Municípios</a>.
                    </p>
                </div>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
