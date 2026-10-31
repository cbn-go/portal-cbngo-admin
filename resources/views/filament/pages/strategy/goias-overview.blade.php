@php
    use App\Enums\StrategyIndicator;

    $habitantes = StrategyIndicator::POPULATION_ESTIMATE;
    $regions = $this->regions();
@endphp

<x-filament-panels::page>
    @include('filament.strategy.styles')

    <x-filament::section>
        <x-slot name="heading">Cobertura por região imediata</x-slot>
        <x-slot name="description">Das regiões com menor cobertura para as de maior. Clique em uma região para ver os municípios dela.</x-slot>

        @if ($regions->isEmpty())
            <p class="text-sm text-gray-500">A base de municípios ainda não foi carregada. Rode <code>php artisan strategy:sync-municipalities</code>.</p>
        @else
            <div class="st-scroll">
                <table class="st-table">
                    <thead>
                        <tr>
                            <th>Região imediata</th>
                            <th class="st-num">Municípios</th>
                            <th class="st-num">Com igreja</th>
                            <th class="st-num">Sem igreja</th>
                            <th class="st-num">Cobertura</th>
                            <th class="st-num">Igrejas</th>
                            <th class="st-num">População</th>
                            <th class="st-num">Hab. por igreja</th>
                            <th class="st-num">Projetos sociais</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($regions as $region)
                            <tr>
                                <td><a href="{{ $this->regionUrl($region) }}" class="st-link">{{ $region->regionName }}</a></td>
                                <td class="st-num">{{ $region->totalMunicipalitiesCount }}</td>
                                <td class="st-num">{{ $region->municipalitiesWithChurchCount }}</td>
                                <td class="st-num">{{ $region->municipalitiesWithoutChurchCount }}</td>
                                <td class="st-num st-strong">{{ number_format($region->coveragePercentage, 1, ',', '.') }}%</td>
                                <td class="st-num">{{ $region->activeChurchesCount }}</td>
                                <td class="st-num">{{ $region->totalPopulation > 0 ? $habitantes->format($region->totalPopulation) : '—' }}</td>
                                <td class="st-num">{{ $habitantes->format($region->inhabitantsPerChurch) }}</td>
                                <td class="st-num">{{ $region->publishedSocialProjectsCount }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
