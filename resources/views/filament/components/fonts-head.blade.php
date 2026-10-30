<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700..800;1,700..800&family=Plus+Jakarta+Sans:ital,wght@0,400..700;1,400..700&display=swap" rel="stylesheet">

@if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
    @vite(['resources/css/app.css', 'resources/js/app.js'])
@endif

<style>
    body, .fi-body, .fi-main {
        background-color: #F6F5F6 !important;
        font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif !important;
        color: #201820;
    }

    .fi-topbar {
        background-color: #FFFFFF !important;
        border-bottom: 1px solid #E8E7E8 !important;
    }

    .fi-sidebar {
        background-color: #FFFFFF !important;
        border-right: 1px solid #E8E7E8 !important;
    }

    /* Esconder o cabeçalho padrão duplicado do Filament no Dashboard */
    .cbn-mockup-dashboard header.fi-header {
        display: none !important;
    }

    /* Ajustar espaçamento superior do container do Filament no Dashboard */
    .cbn-mockup-dashboard > section {
        padding-top: 0 !important;
        padding-bottom: 2rem !important;
    }

    .cbn-mockup-dashboard {
        font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
        color: #201820;
    }

    .font-serif-cbn {
        font-family: 'Playfair Display', Georgia, serif !important;
    }

    .cbn-dashboard-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 16px;
        align-items: start;
    }

    @media (min-width: 1024px) {
        .cbn-dashboard-grid {
            grid-template-columns: 2fr 1fr !important;
        }
    }

    .cbn-indicators-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
        gap: 16px;
    }

    a.cbn-link-red {
        color: #A2101C !important;
        text-decoration: none;
    }
    a.cbn-link-red:hover {
        color: #5C0910 !important;
    }
</style>
