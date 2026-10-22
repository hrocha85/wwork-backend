<style>
    .fi-body {
        background-color: #D1DFD2;
        color: #1F2937;
    }

    html.dark .fi-body {
        background-color: #1F2937;
        color: #D1DFD2;
    }

    html.dark .fi-body :is(h1, h2, h3, label, .fi-logo) {
        color: #D1DFD2;
    }

    .fi-simple-main,
    .fi-topbar,
    .fi-section,
    .fi-ta-ctn,
    .fi-modal-window,
    .fi-dropdown-panel,
    .fi-sidebar {
        background-color: #FFFFFF;
        color: #1F2937;
    }

    html.dark .fi-simple-main,
    html.dark .fi-topbar,
    html.dark .fi-section,
    html.dark .fi-ta-ctn,
    html.dark .fi-modal-window,
    html.dark .fi-dropdown-panel,
    html.dark .fi-sidebar {
        background-color: #2C3848;
        color: #D1DFD2;
    }

    .wwork-sign-in {
        background-color: #FB7E00;
        color: #ffffff;
    }

    .wwork-sign-in:hover {
        background-color: #FB7E00;
        color: #ffffff;
    }

    .fi-color-warning {
        --text: #1F2937;
        --hover-text: #1F2937;
        --dark-text: #1F2937;
        --dark-hover-text: #1F2937;
    }

    .fi-body,
    .fi-sidebar,
    .fi-topbar,
    .fi-main {
        font-family: Inter, ui-sans-serif, system-ui, sans-serif;
    }

    .fi-sidebar-group-label {
        color: #79B4B0;
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .fi-sidebar-item-icon {
        color: #79B4B0;
    }

    .fi-sidebar-item.fi-active > .fi-sidebar-item-btn {
        background: color-mix(in srgb, #79B4B0 18%, #FFFFFF);
        box-shadow: inset 3px 0 0 #79B4B0;
        color: #1F2937;
    }

    html.dark .fi-sidebar-item.fi-active > .fi-sidebar-item-btn {
        background: color-mix(in srgb, #79B4B0 24%, #2C3848);
        color: #D1DFD2;
    }

    .fi-section,
    .fi-ta-ctn,
    .fi-wi-stats-overview-stat,
    .fi-wi-chart {
        border-radius: 16px;
        box-shadow: 0 10px 28px rgba(31, 41, 55, 0.06);
    }

    .fi-header-subheading {
        max-width: 44rem;
        color: color-mix(in srgb, #1F2937 70%, #79B4B0);
    }

    html.dark .fi-header-subheading {
        color: #D1DFD2;
    }

    .wwork-sign-in {
        border-radius: 999px;
        font-weight: 600;
    }

    .fi-fo-field-label-content {
        color: #79B4B0;
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .fi-fo-field .fi-sc-text {
        color: color-mix(in srgb, #1F2937 68%, #79B4B0);
        font-size: 0.84rem;
        line-height: 1.45;
    }

    html.dark .fi-fo-field .fi-sc-text {
        color: #D1DFD2;
    }

    .fi-input-wrp {
        border-radius: 12px;
        min-height: 3rem;
    }

    .fi-input {
        min-height: 2.75rem;
    }

    .fi-input-wrp:focus-within {
        box-shadow: 0 0 0 3px color-mix(in srgb, #79B4B0 35%, transparent);
    }

    .fi-modal-window {
        border-radius: 24px;
        box-shadow: 0 18px 40px -24px #1F2937;
    }

    .fi-modal-heading {
        font-weight: 700;
        letter-spacing: -0.02em;
    }

    .fi-modal-description {
        color: color-mix(in srgb, #1F2937 72%, #79B4B0);
        line-height: 1.5;
    }

    html.dark .fi-modal-description {
        color: #D1DFD2;
    }

    .fi-modal-close-btn {
        border-radius: 999px;
        background: #D1DFD2;
    }

    html.dark .fi-modal-close-btn {
        background: #1F2937;
    }

    .fi-modal-footer .fi-btn,
    .fi-sc-actions .fi-btn,
    .fi-simple-page .fi-btn,
    .fi-page-header-main-ctn .fi-btn,
    .wwork-form-page .fi-btn {
        border-radius: 999px;
        font-weight: 600;
        min-height: 2.75rem;
    }

    .wwork-form-page .fi-page-header-main-ctn,
    .wwork-form-page .fi-page-content {
        max-width: 40rem;
    }

    .wwork-form-page .fi-page-content {
        background: #FFFFFF;
        border-radius: 24px;
        box-shadow: 0 12px 28px -18px #1F2937;
        padding: 1.5rem;
    }

    html.dark .wwork-form-page .fi-page-content {
        background: #2C3848;
    }

    /* ─── Dashboard: Layout & Cards Modernos ─── */

    /* Cards de métricas: altura uniforme, borda sutil de marca, elevação suave */
    .fi-wi-stats-overview-stat {
        min-height: 135px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        border: 1px solid rgba(121, 180, 176, 0.22);
        transition: transform 0.18s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.18s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .fi-wi-stats-overview-stat:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 28px rgba(31, 41, 55, 0.08);
    }

    html.dark .fi-wi-stats-overview-stat {
        border: 1px solid rgba(121, 180, 176, 0.16);
    }

    /* Ícone de destaque dentro do card: badge arredondado com tom teal suave */
    .fi-wi-stats-overview-stat-icon {
        background-color: rgba(121, 180, 176, 0.12) !important;
        color: #3B7A76 !important;
        border-radius: 12px !important;
        padding: 0.5rem !important;
    }

    html.dark .fi-wi-stats-overview-stat-icon {
        background-color: rgba(121, 180, 176, 0.22) !important;
        color: #79B4B0 !important;
    }

    /* Valor principal do stat: tipografia limpa, peso semibold/bold, tamanho controlado */
    .fi-wi-stats-overview-stat-value {
        font-size: 1.65rem !important;
        font-weight: 700;
        line-height: 1.25;
        letter-spacing: -0.02em;
        word-break: break-word;
        overflow-wrap: anywhere;
        color: #111827;
    }

    html.dark .fi-wi-stats-overview-stat-value {
        color: #F9FAFB;
    }

    /* Label superior do card */
    .fi-wi-stats-overview-stat-label {
        font-size: 0.775rem;
        font-weight: 600;
        color: #6B7280;
        letter-spacing: 0.025em;
        text-transform: capitalize;
    }

    html.dark .fi-wi-stats-overview-stat-label {
        color: #9CA3AF;
    }

    /* Descrição do card: quebra de linha limpa, tamanho legível */
    .fi-wi-stats-overview-stat-description {
        font-size: 0.775rem;
        line-height: 1.45;
        word-break: break-word;
        overflow-wrap: anywhere;
        max-width: none;
        margin-top: 0.4rem;
        color: color-mix(in srgb, #1F2937 68%, #79B4B0);
    }

    html.dark .fi-wi-stats-overview-stat-description {
        color: #D1DFD2;
    }

    /* Alinha os cards de stats para que todos da mesma linha tenham a mesma altura */
    .fi-wi-stats-overview-stats-ctn {
        align-items: stretch;
    }

    /* Gráficos: proporção elegante e canvas contido */
    .fi-wi-chart {
        min-height: 310px;
        border: 1px solid rgba(121, 180, 176, 0.22);
    }

    html.dark .fi-wi-chart {
        border: 1px solid rgba(121, 180, 176, 0.16);
    }

    .fi-wi-chart canvas {
        max-height: 235px !important;
    }

    /* Widget de tabela no dashboard */
    .fi-wi-tbl {
        border-radius: 16px;
        border: 1px solid rgba(121, 180, 176, 0.22);
        box-shadow: 0 10px 28px rgba(31, 41, 55, 0.06);
        overflow: hidden;
    }

    html.dark .fi-wi-tbl {
        border: 1px solid rgba(121, 180, 176, 0.16);
    }

    .fi-wi-tbl .fi-ta-ctn {
        box-shadow: none;
        border-radius: 0;
    }

    .fi-wi-tbl .fi-header-heading {
        font-size: 1rem;
        font-weight: 700;
        color: #1F2937;
    }

    html.dark .fi-wi-tbl .fi-header-heading {
        color: #D1DFD2;
    }

    /* Espaçamento e layout do formulário de filtros */
    .fi-page-header-content-end {
        padding-top: 0.25rem;
    }
</style>
