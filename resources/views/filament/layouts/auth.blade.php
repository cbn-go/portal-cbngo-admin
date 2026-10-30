@php
    $livewire ??= null;
@endphp

<x-filament-panels::layout.base :livewire="$livewire">
    @push('styles')
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400..900;1,400..900&family=Plus+Jakarta+Sans:ital,wght@0,300..800;1,300..800&display=swap" rel="stylesheet">
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
        <style>
            .font-brand-title {
                font-family: 'Baker Signet', 'Playfair Display', Georgia, serif;
            }
            .font-brand-body {
                font-family: 'Humanist 521', 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            }

            /* Garantir contraste estrito na coluna branca do formulário */
            .auth-form-column label,
            .auth-form-column .fi-fo-field-wrp-label,
            .auth-form-column .fi-fo-field-wrp-label span,
            .auth-form-column .fi-checkbox-label,
            .auth-form-column header h1 {
                color: #111827 !important;
            }

            .auth-form-column header p,
            .auth-form-column .fi-fo-field-wrp-helper-text {
                color: #4b5563 !important;
            }

            .auth-form-column .fi-input-wrp {
                background-color: #ffffff !important;
                border-color: #d1d5db !important;
                box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05) !important;
            }

            .auth-form-column .fi-input-wrp input {
                color: #111827 !important;
                background-color: transparent !important;
            }

            .auth-form-column .fi-input-wrp input::placeholder {
                color: #9ca3af !important;
            }

            .auth-form-column button[type="submit"] span,
            .auth-form-column .fi-btn-label {
                color: #ffffff !important;
            }
        </style>
    @endpush

    <script>
        document.documentElement.classList.remove('dark');
        const observer = new MutationObserver(() => {
            if (document.documentElement.classList.contains('dark')) {
                document.documentElement.classList.remove('dark');
            }
        });
        observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
    </script>

    <div class="font-brand-body min-h-screen bg-white text-gray-900 antialiased">
        {{ $slot }}
    </div>
</x-filament-panels::layout.base>
