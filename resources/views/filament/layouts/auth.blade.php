@php
    $livewire ??= null;
@endphp

<x-filament-panels::layout.base :livewire="$livewire">
    @push('styles')
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400..900;1,400..900&family=Plus+Jakarta+Sans:ital,wght@0,300..800;1,300..800&display=swap" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>
            .font-brand-title {
                font-family: 'Baker Signet', 'Playfair Display', Georgia, serif;
            }
            .font-brand-body {
                font-family: 'Humanist 521', 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            }
        </style>
    @endpush

    <script>
        document.documentElement.classList.remove('dark');
    </script>

    <div class="font-brand-body min-h-screen bg-white text-gray-900 antialiased">
        {{ $slot }}
    </div>
</x-filament-panels::layout.base>
