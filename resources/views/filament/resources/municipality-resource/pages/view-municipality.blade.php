@php
    use App\Enums\StrategyIndicator;

    $coverage = $this->coverage();
    $band = $this->priorityBand();
    $churches = $this->churches();
    $projects = $this->socialProjects();
    $habitantes = StrategyIndicator::POPULATION_ESTIMATE;
@endphp

<x-filament-panels::page>
    @include('filament.strategy.styles')

    <x-filament::section>
        <x-slot name="heading">Cobertura da CBN-GO</x-slot>

        <div class="st-grid">
            @foreach ([
                ['Igrejas ativas', (string) $coverage->activeChurchesCount],
                ['População', $habitantes->format($coverage->population)],
                ['Habitantes por igreja', $habitantes->format($coverage->inhabitantsPerChurch)],
                ['Não evangélicos por igreja', $habitantes->format($coverage->nonEvangelicalsPerChurch)],
                ['Projetos sociais publicados', (string) $coverage->publishedSocialProjectsCount],
            ] as [$label, $value])
                <div class="st-card">
                    <div class="st-card-label">{{ $label }}</div>
                    <div class="st-card-value">{{ $value }}</div>
                </div>
            @endforeach
        </div>

        <div class="mt-6 flex flex-wrap items-center gap-2">
            <span class="text-sm font-semibold text-gray-950">Índice de prioridade:</span>
            @if ($coverage->priorityIndex !== null)
                <x-filament::badge :color="$band?->getColor()">
                    {{ number_format($coverage->priorityIndex, 1, ',', '.') }} · {{ $band?->getLabel() }}
                </x-filament::badge>
            @else
                <x-filament::badge color="warning">Sem dados do IBGE</x-filament::badge>
            @endif
        </div>

        <table class="st-table mt-3">
            <thead>
                <tr>
                    <th>Fator</th>
                    <th class="st-num">Peso</th>
                    <th class="st-num">Nota (0 a 100)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($this->priorityFactors() as $factor)
                    <tr>
                        <td class="st-strong">{{ $factor['label'] }}</td>
                        <td class="st-num">{{ number_format($factor['weight'] * 100, 0) }}%</td>
                        <td class="st-num">{{ $factor['score'] !== null ? number_format($factor['score'], 1, ',', '.') : 'Sem dados' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-filament::section>

    @foreach ($this->indicatorGroups() as $group => $rows)
        <x-filament::section collapsible>
            <x-slot name="heading">{{ $group }}</x-slot>

            <div class="st-scroll">
                <table class="st-table">
                    <thead>
                        <tr>
                            <th>Indicador</th>
                            <th class="st-num">Valor</th>
                            <th>Ano</th>
                            <th class="st-num">Goiás</th>
                            <th class="st-num">Comparação</th>
                            <th>Fonte</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $item)
                            @php($indicator = $item['indicator'])
                            <tr>
                                <td class="st-strong">{{ $indicator->label() }}</td>
                                @if ($item['row'])
                                    <td class="st-num st-strong">{{ $indicator->format($item['row']->value) }}</td>
                                    <td>{{ $item['row']->period }}</td>
                                @else
                                    <td class="st-num" colspan="2">
                                        <x-filament::badge color="warning">Sem dados do IBGE</x-filament::badge>
                                    </td>
                                @endif
                                <td class="st-num">{{ $indicator->format($item['state']) }}</td>
                                <td class="st-num">{{ $item['comparison'] ?? '—' }}</td>
                                <td class="st-muted">{{ $item['row']?->source ?? $indicator->source() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endforeach

    <x-filament::section>
        <x-slot name="heading">Igrejas da CBN-GO em {{ $this->record->name }}</x-slot>

        @if ($churches->isEmpty())
            <p class="text-sm text-gray-500">Nenhuma igreja da CBN-GO cadastrada neste município.</p>
        @else
            <ul class="st-list">
                @foreach ($churches as $church)
                    <li>
                        <div>
                            <a href="{{ $this->churchUrl($church) }}" class="st-link">{{ $church->name }}</a>
                            <div class="text-xs text-gray-500">{{ $church->pastor_name ?: 'Pastor não informado' }}{{ $church->neighborhood ? ' · '.$church->neighborhood : '' }}</div>
                        </div>
                        <x-filament::badge :color="$church->is_active ? 'success' : 'gray'">
                            {{ $church->is_active ? 'Ativa' : 'Inativa' }}
                        </x-filament::badge>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Projetos sociais</x-slot>

        @if ($projects->isEmpty())
            <p class="text-sm text-gray-500">Nenhum projeto social cadastrado pelas igrejas deste município.</p>
        @else
            <ul class="st-list">
                @foreach ($projects as $project)
                    <li>
                        <div>
                            <a href="{{ $this->socialProjectUrl($project) }}" class="st-link">{{ $project->title }}</a>
                            <div class="text-xs text-gray-500">{{ $project->church?->name }}</div>
                        </div>
                        <x-filament::badge :color="$project->status->getColor()">{{ $project->status->getLabel() }}</x-filament::badge>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>
</x-filament-panels::page>
