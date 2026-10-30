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

    /* Topbar institucional CBN-GO (mockup #34): branca, busca larga à esquerda, ações à direita */
    .fi-topbar nav {
        height: 64px !important;
        padding: 0 24px !important;
        gap: 16px 24px !important;
        background-color: #FFFFFF !important;
        border-bottom: 1px solid #E8E7E8 !important;
        box-shadow: none !important;
        --tw-ring-shadow: 0 0 #0000 !important;
    }

    /* Cabeçalho da sidebar com o logo: mesma altura e borda da topbar, formando uma faixa contínua */
    .fi-sidebar-header {
        height: 64px !important;
        padding: 0 24px !important;
        background-color: #FFFFFF !important;
        border-bottom: 1px solid #E8E7E8 !important;
        box-shadow: none !important;
        --tw-ring-shadow: 0 0 #0000 !important;
    }

    .fi-sidebar-header .fi-logo {
        height: 40px !important;
    }

    /* O grupo de ações ocupa a largura toda; a busca cresce até 560px e empurra o resto para a direita */
    .fi-topbar nav > div.ms-auto {
        flex: 1 1 auto;
        min-width: 0;
        margin-inline-start: 0 !important;
        gap: 8px !important;
    }

    .fi-topbar .fi-global-search {
        flex: 1 1 320px;
        max-width: 560px;
        margin-inline-end: auto;
    }

    .fi-topbar .fi-global-search > div,
    .fi-topbar .fi-global-search-field {
        width: 100%;
    }

    .fi-topbar .fi-global-search-field .fi-input-wrp {
        height: 44px;
        align-items: center;
        background-color: #F6F5F6 !important;
        border-radius: 10px !important;
        box-shadow: none !important;
        --tw-ring-color: #D2D0D2 !important;
    }

    .fi-topbar .fi-global-search-field .fi-input-wrp-prefix {
        padding-inline-start: 14px !important;
        color: #746F74 !important;
    }

    .fi-topbar .fi-global-search-field input {
        font-size: 14px !important;
        color: #201820 !important;
    }

    .fi-topbar .fi-global-search-field input::placeholder {
        color: #746F74 !important;
    }

    /* Sino de notificações: alvo de 44px e contador vermelho */
    .fi-topbar .fi-topbar-database-notifications-btn {
        width: 44px !important;
        height: 44px !important;
        margin: 0 !important;
        border-radius: 10px;
        color: #484148 !important;
    }

    .fi-topbar .fi-topbar-database-notifications-btn .fi-badge,
    .fi-topbar .fi-topbar-database-notifications-btn .fi-icon-btn-badge-ctn .fi-badge {
        background-color: #C51422 !important;
        color: #FFFFFF !important;
        font-weight: 700 !important;
        --tw-ring-shadow: 0 0 #0000 !important;
    }

    /* Menu do usuário: iniciais, nome, perfil e seta */
    .cbn-user-menu-trigger {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        min-height: 44px;
        padding: 0 8px 0 4px;
        border-radius: 10px;
        color: #201820;
        text-align: start;
    }

    .cbn-user-menu-trigger:hover {
        background-color: #F6F5F6;
    }

    .cbn-user-menu-avatar {
        width: 32px;
        height: 32px;
        flex: none;
        border-radius: 16px;
        background-color: #201820;
        color: #FFFFFF;
        font-size: 13px;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .cbn-user-menu-text {
        display: flex;
        flex-direction: column;
        line-height: 1.25;
    }

    .cbn-user-menu-name {
        font-size: 14px;
        font-weight: 600;
    }

    .cbn-user-menu-role {
        font-size: 12px;
        color: #5E585E;
    }

    @media (max-width: 767px) {
        .fi-topbar nav {
            padding: 0 16px !important;
            gap: 8px !important;
        }

        .fi-topbar .fi-global-search {
            flex-basis: 0;
        }

        .cbn-user-menu-text,
        .cbn-view-site-label {
            display: none;
        }
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
        padding-top: 32px !important;
        padding-bottom: 48px !important;
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
