@php
    $livewire ??= null;
@endphp

<x-filament-panels::layout.base :livewire="$livewire">
    @push('styles')
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400..900;1,400..900&family=Plus+Jakarta+Sans:ital,wght@0,300..800;1,300..800&display=swap" rel="stylesheet">
        <style>
            .font-brand-title {
                font-family: 'Baker Signet', 'Playfair Display', Georgia, serif;
            }
            .font-brand-body {
                font-family: 'Humanist 521', 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            }
        </style>
    @endpush

    <div class="font-brand-body min-h-screen text-gray-900 antialiased dark:text-gray-100">
        {{ $slot }}
    </div>
</x-filament-panels::layout.base>
