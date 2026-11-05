<x-filament-panels::page>
    <x-filament-panels::form wire:submit="save">
        {{ $this->form }}

        @if ($this->church !== null)
            <div class="mt-6 flex justify-end">
                <x-filament-panels::form.actions
                    :actions="$this->getFormActions()"
                />
            </div>
        @endif
    </x-filament-panels::form>
</x-filament-panels::page>
