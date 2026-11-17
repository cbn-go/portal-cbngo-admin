@php
    use App\Enums\JubanTransactionType;
    use App\Filament\Juban\Resources\JubanTransactionResource;

    $summary = $this->getSummary();
    $title = $summary->periodTitle();
    $chart = $this->getMonthlyChart($summary);
    $despesas = $summary->categoriesOf(JubanTransactionType::DESPESA);
    $receitas = $summary->categoriesOf(JubanTransactionType::RECEITA);
@endphp

<x-filament-widgets::widget>
    @include('filament.juban.styles')

    <div class="jb-row">
        <section class="jb-panel is-wide" aria-labelledby="jb-gastos">
            <div class="jb-panel-head">
                <div>
                    <h2 id="jb-gastos" class="jb-panel-title">Gastos da JUBAN GO {{ $title }}</h2>
                    <p class="jb-panel-desc">Por mês · {{ $chart['unit'] }}</p>
                </div>
                <a href="{{ $this->getTransactionsUrl($summary) }}" class="jb-link">Ver lançamentos</a>
            </div>

            <div class="jb-bars" role="img" aria-label="Gastos por mês {{ $title }}">
                @foreach ($chart['bars'] as $bar)
                    <div class="jb-bar">
                        <span class="jb-bar-value">{{ $bar['value'] }}</span>
                        <div class="jb-bar-fill" style="height: {{ $bar['height'] }}px"></div>
                        <span class="jb-bar-label">{{ $bar['label'] }}</span>
                    </div>
                @endforeach
            </div>

            <h3 class="jb-subtitle">Por categoria</h3>
            @forelse ($despesas as $row)
                <div class="jb-cat">
                    <div class="jb-cat-line">
                        <span>{{ $row->name }}</span>
                        <span class="jb-cat-value">{{ JubanTransactionResource::formatBrl($row->total) }} · {{ $this->formatPercent($row->percentOfType) }}</span>
                    </div>
                    <div class="jb-cat-track"><div class="jb-cat-fill" style="width: {{ $row->percentOfType }}%"></div></div>
                </div>
            @empty
                <p class="jb-empty">Nenhum gasto no período.</p>
            @endforelse
        </section>

        <section class="jb-panel" aria-labelledby="jb-receita">
            <div class="jb-panel-head">
                <div>
                    <h2 id="jb-receita" class="jb-panel-title">Receita por categoria</h2>
                    <p class="jb-panel-desc">{{ $summary->periodLabel() }}</p>
                </div>
            </div>

            @forelse ($receitas as $row)
                <div class="jb-cat">
                    <div class="jb-cat-line">
                        <span>{{ $row->name }}</span>
                        <span class="jb-cat-value">{{ JubanTransactionResource::formatBrl($row->total) }} · {{ $this->formatPercent($row->percentOfType) }}</span>
                    </div>
                    <div class="jb-cat-track"><div class="jb-cat-fill is-receita" style="width: {{ $row->percentOfType }}%"></div></div>
                </div>
            @empty
                <p class="jb-empty">Nenhuma receita no período.</p>
            @endforelse
        </section>
    </div>
</x-filament-widgets::widget>
