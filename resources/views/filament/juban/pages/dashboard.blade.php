<x-filament-panels::page class="cbn-mockup-dashboard fi-dashboard-page">
    @include('filament.juban.styles')

    <div class="jb-head">
        <div>
            <p class="jb-head-date">{{ $this->getSubheading() }}</p>
            <h1 class="jb-head-title">{{ $this->getTitle() }}</h1>
        </div>

        <div class="jb-actions">
            <a href="{{ $this->transactionsUrl() }}" class="jb-btn">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="6" width="18" height="14" rx="2"></rect><path d="M3 10h18M16 15h2"></path></svg>
                Ver lançamentos
            </a>

            @if ($this->canCreateTransaction())
                <a href="{{ $this->createTransactionUrl() }}" class="jb-btn jb-btn-primary">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14"></path><path d="M5 12h14"></path></svg>
                    Novo lançamento
                </a>
            @endif
        </div>
    </div>

    {{ $this->filtersForm }}

    <x-filament-widgets::widgets
        :columns="$this->getColumns()"
        :data="['filters' => $this->filters, ...$this->getWidgetData()]"
        :widgets="$this->getVisibleWidgets()"
    />
</x-filament-panels::page>
