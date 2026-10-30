<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700..800;1,700..800&family=Plus+Jakarta+Sans:ital,wght@0,400..700;1,400..700&display=swap" rel="stylesheet">

@if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
    @vite(['resources/css/app.css', 'resources/js/app.js'])
@endif

<style>
    /* Fundo da página e tipografia base */
    body, .fi-body, .fi-main {
        background-color: #F6F5F6 !important;
        font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif !important;
        color: #201820;
    }

    /* Topbar institucional CBN-GO */
    .fi-topbar {
        background-color: #FFFFFF !important;
        border-bottom: 1px solid #E8E7E8 !important;
        box-shadow: none !important;
        min-height: 64px !important;
    }

    .fi-topbar nav {
        min-height: 64px !important;
        padding-left: 20px !important;
        padding-right: 24px !important;
    }

    /* Barra de busca global do topo */
    .fi-global-search-field-input,
    .fi-global-search-field input[type="search"] {
        background-color: #F6F5F6 !important;
        border: 1px solid #D2D0D2 !important;
        border-radius: 10px !important;
        height: 44px !important;
        font-size: 14px !important;
        color: #201820 !important;
        box-shadow: none !important;
        padding-left: 42px !important;
    }

    .fi-global-search-field-input::placeholder,
    .fi-global-search-field input::placeholder {
        color: #746F74 !important;
    }

    .fi-global-search-field-icon {
        color: #746F74 !important;
    }

    /* Botão de notificações e avatar */
    .fi-topbar-database-notifications-btn .fi-badge {
        background-color: #C51422 !important;
        color: #FFFFFF !important;
        font-weight: 700 !important;
    }

    .fi-user-menu-trigger .fi-avatar {
        background-color: #201820 !important;
        color: #FFFFFF !important;
        font-weight: 700 !important;
        border-radius: 16px !important;
    }

    /* Sidebar institucional CBN-GO */
    .fi-sidebar {
        background-color: #FFFFFF !important;
        border-right: 1px solid #E8E7E8 !important;
    }

    /* Rótulos dos grupos da Sidebar (SECRETARIA, PORTAL, SISTEMA) */
    .fi-sidebar-group-label,
    .fi-sidebar-group-label span {
        color: #5E585E !important;
        font-size: 12px !important;
        font-weight: 700 !important;
        letter-spacing: 0.08em !important;
        text-transform: uppercase !important;
    }

    /* Texto dos itens inativos da Sidebar - Tom escuro (#362F36) e nítido */
    .fi-sidebar-item-label {
        color: #362F36 !important;
        font-size: 14px !important;
        font-weight: 600 !important;
        transition: color 0.15s ease;
    }

    .fi-sidebar-item-icon {
        color: #5E585E !important;
        transition: color 0.15s ease;
    }

    /* Hover nos itens da Sidebar */
    .fi-sidebar-item-button:hover {
        background-color: #F6F5F6 !important;
    }
    .fi-sidebar-item-button:hover .fi-sidebar-item-label {
        color: #201820 !important;
    }
    .fi-sidebar-item-button:hover .fi-sidebar-item-icon {
        color: #201820 !important;
    }

    /* Item ativo na Sidebar (Visão Geral) */
    .fi-sidebar-item.fi-active .fi-sidebar-item-button,
    .fi-sidebar-item-button.fi-active,
    .fi-sidebar-item-button:has([aria-current="page"]) {
        background-color: #FDECED !important;
        border-radius: 10px !important;
    }

    .fi-sidebar-item.fi-active .fi-sidebar-item-label,
    .fi-sidebar-item-button.fi-active .fi-sidebar-item-label {
        color: #A2101C !important;
        font-weight: 700 !important;
    }

    .fi-sidebar-item.fi-active .fi-sidebar-item-icon,
    .fi-sidebar-item-button.fi-active .fi-sidebar-item-icon {
        color: #A2101C !important;
    }

    /* Ocultar cabeçalho padrão redundante do Filament no Dashboard */
    .cbn-mockup-dashboard header.fi-header {
        display: none !important;
    }

    /* Ajustar espaçamento do container do Dashboard */
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
