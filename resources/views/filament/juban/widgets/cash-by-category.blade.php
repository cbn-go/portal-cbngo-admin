@php
    use App\Filament\Juban\Resources\JubanTransactionResource;

    $summary = $this->getSummary();
@endphp

<x-filament-widgets::widget>
    @include('filament.strategy.styles')

    <x-filament::section>
        <x-slot name="heading">Por categoria</x-slot>
        <x-slot name="description">{{ $summary->periodLabel() }}</x-slot>

        @if ($summary->count === 0)
            <p class="st-card-label">Nenhum lançamento no período.</p>
        @else
            <div class="st-scroll">
                <table class="st-table">
                    <thead>
                        <tr>
                            <th>Categoria</th>
                            <th class="st-num">Lançamentos</th>
                            <th class="st-num">Valor</th>
                            <th class="st-num">%</th>
                        </tr>
                    </thead>
                    @foreach ($this->getTypes() as $type)
                        @php($rows = $summary->categoriesOf($type))
                        @continue($rows === [])
                        <tbody>
                            <tr>
                                <td class="st-strong" colspan="2">{{ $type->isReceita() ? 'Receitas' : 'Despesas' }}</td>
                                <td class="st-num st-strong">{{ JubanTransactionResource::formatBrl($summary->totalOf($type)) }}</td>
                                <td class="st-num st-strong">100,0%</td>
                            </tr>
                            @foreach ($rows as $row)
                                <tr>
                                    <td>{{ $row->name }}</td>
                                    <td class="st-num">{{ $row->count }}</td>
                                    <td class="st-num">{{ JubanTransactionResource::formatBrl($row->total) }}</td>
                                    <td class="st-num">{{ number_format($row->percentOfType, 1, ',', '.') }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                    @endforeach
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
