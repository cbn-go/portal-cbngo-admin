<x-filament-panels::page>
    {{ $this->filtersForm }}

    <x-filament::grid
        :default="1"
        class="fi-wi gap-6"
    >
        @foreach ($this->getIndicatorWidgets() as $widget)
            @livewire(
                $widget,
                ['filters' => $this->filters],
                key($widget.'-'.md5(json_encode($this->filters))),
            )
        @endforeach
    </x-filament::grid>
</x-filament-panels::page>
