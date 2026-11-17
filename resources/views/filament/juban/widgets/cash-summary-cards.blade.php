@php
    use App\Enums\JubanTransactionType;
    use App\Filament\Juban\Resources\JubanTransactionResource;

    $summary = $this->getSummary();
    $title = $summary->periodTitle();
@endphp

<x-filament-widgets::widget>
    @include('filament.juban.styles')

    <section aria-label="Resumo de caixa" class="jb-cards">
        <div class="jb-card">
            <p class="jb-card-label">Gastos {{ $title }}</p>
            <p class="jb-card-value">{{ JubanTransactionResource::formatBrl($summary->despesas) }}</p>
            <p class="jb-card-desc">{{ static::countLabel($summary->countOf(JubanTransactionType::DESPESA)) }}</p>
        </div>

        <div class="jb-card">
            <p class="jb-card-label">Receita {{ $title }}</p>
            <p class="jb-card-value">{{ JubanTransactionResource::formatBrl($summary->receitas) }}</p>
            <p class="jb-card-desc">{{ static::countLabel($summary->countOf(JubanTransactionType::RECEITA)) }}</p>
        </div>

        <div class="jb-card">
            <p class="jb-card-label">Saldo {{ $title }}</p>
            <p @class(['jb-card-value', 'is-negative' => $summary->saldo < 0])>
                {{ ($summary->saldo < 0 ? '- ' : '').JubanTransactionResource::formatBrl(abs($summary->saldo)) }}
            </p>
            <p class="jb-card-desc">
                Receita menos gastos ·
                <a href="{{ $this->getTransactionsUrl($summary) }}" class="jb-link">{{ static::countLabel($summary->count) }}</a>
            </p>
        </div>
    </section>
</x-filament-widgets::widget>
