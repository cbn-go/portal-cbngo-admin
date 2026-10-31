<x-filament-panels::page>
    @include('filament.strategy.styles')

    @foreach ($this->indicatorGroups() as $group => $rows)
        <x-filament::section collapsible>
            <x-slot name="heading">{{ $group }}</x-slot>

            <div class="st-scroll">
                <table class="st-table">
                    <thead>
                        <tr>
                            <th>Indicador</th>
                            <th>Fonte</th>
                            <th>Tabela</th>
                            <th>Ano</th>
                            <th>Última sincronização</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $item)
                            @php($indicator = $item['indicator'])
                            <tr>
                                <td>
                                    <div class="st-strong">{{ $indicator->label() }} <span class="st-muted">({{ $indicator->unit() }})</span></div>
                                    <div class="st-muted">{{ $indicator->description() }}</div>
                                    @if ($indicator->methodologicalNote())
                                        <div class="st-note">{{ $indicator->methodologicalNote() }}</div>
                                    @endif
                                </td>
                                <td class="st-muted">{{ $indicator->source() }}</td>
                                <td class="st-nowrap">{{ $item['table'] }}</td>
                                <td>{{ $item['periods'] ? implode(', ', $item['periods']) : '—' }}</td>
                                <td class="st-nowrap">
                                    @if ($item['lastSyncedAt'])
                                        {{ $item['lastSyncedAt']->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                                    @else
                                        <x-filament::badge color="warning">Nunca sincronizado</x-filament::badge>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endforeach

    <x-filament::section>
        <x-slot name="heading">Índice de prioridade</x-slot>
        <x-slot name="description">Nota de 0 a 100 que combina os fatores abaixo. Quando falta um dado do IBGE, o peso dele é redistribuído entre os fatores disponíveis.</x-slot>

        <table class="st-table">
            <thead>
                <tr>
                    <th>Fator</th>
                    <th class="st-num">Peso</th>
                    <th>Fonte</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($this->priorityFactors() as $factor)
                    <tr>
                        <td>
                            <div class="st-strong">{{ $factor['label'] }}</div>
                            <div class="st-muted">{{ $factor['description'] }}</div>
                        </td>
                        <td class="st-num">{{ number_format($factor['weight'] * 100, 0) }}%</td>
                        <td class="st-muted">{{ $factor['source'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <p class="mt-3 text-xs text-gray-500">Faixas: alta a partir de 70, média de 40 a 69 e baixa abaixo de 40. A ausência de igreja vale 100 sem nenhuma igreja ativa e cai para 100 ÷ (1 + n) com n igrejas.</p>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Limitações</x-slot>

        <ul class="list-disc space-y-1 pl-5 text-sm text-gray-700">
            @foreach (\App\Filament\Pages\Strategy\Methodology::LIMITATIONS as $limitation)
                <li>{{ $limitation }}</li>
            @endforeach
        </ul>
    </x-filament::section>
</x-filament-panels::page>
