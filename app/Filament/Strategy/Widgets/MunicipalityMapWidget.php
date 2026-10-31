<?php

namespace App\Filament\Strategy\Widgets;

use App\Services\Strategy\MunicipalityMap;
use Filament\Support\Facades\FilamentAsset;
use Filament\Widgets\Widget;

/**
 * Mapa coroplético de Goiás na Visão de Goiás. A troca de camada acontece só
 * no navegador; o widget não faz requisições Livewire depois de renderizado.
 */
class MunicipalityMapWidget extends Widget
{
    protected static bool $isLazy = false;

    protected static string $view = 'filament.strategy.widgets.municipality-map';

    protected int|string|array $columnSpan = 'full';

    public const INITIAL_LAYER = 'inhabitants_per_church';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $map = app(MunicipalityMap::class);

        if (! $map->exists()) {
            return ['available' => false];
        }

        $municipalities = $map->municipalities();

        return [
            'available' => true,
            'config' => [
                'geojsonUrl' => route('filament.admin.strategy.map-geojson'),
                'leafletJs' => FilamentAsset::getScriptSrc('leaflet'),
                'leafletCss' => FilamentAsset::getStyleHref('leaflet'),
                'municipalities' => $municipalities,
                'churches' => $map->churches(),
                'layers' => $map->layers($municipalities),
                'initialLayer' => self::INITIAL_LAYER,
            ],
            'churchesWithoutCoordinates' => $map->churchesWithoutCoordinates(),
        ];
    }
}
