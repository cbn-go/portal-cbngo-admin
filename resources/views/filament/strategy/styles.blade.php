{{-- O painel usa o CSS pré-compilado do Filament; estas regras cobrem o layout das telas de Estratégia. --}}
<style>
    .st-table { width: 100%; font-size: 0.875rem; border-collapse: collapse; }
    .st-table th { padding: 0.5rem 1rem 0.5rem 0; text-align: left; font-size: 0.75rem; font-weight: 500; color: rgb(var(--gray-500)); border-bottom: 1px solid rgb(var(--gray-200)); }
    .st-table td { padding: 0.6rem 1rem 0.6rem 0; vertical-align: top; color: rgb(var(--gray-700)); border-bottom: 1px solid rgb(var(--gray-100)); }
    .st-table th:last-child, .st-table td:last-child { padding-right: 0; }
    .st-table .st-num { text-align: right; white-space: nowrap; }
    .st-table .st-nowrap { white-space: nowrap; }
    .st-table .st-strong { font-weight: 600; color: rgb(var(--gray-950)); }
    .st-table .st-muted { font-size: 0.75rem; color: rgb(var(--gray-500)); }
    .st-scroll { overflow-x: auto; }
    .st-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr)); gap: 1rem; }
    .st-card { border: 1px solid rgb(var(--gray-100)); background: rgb(var(--gray-50)); border-radius: 0.5rem; padding: 1rem; }
    .st-card-label { font-size: 0.75rem; font-weight: 500; color: rgb(var(--gray-500)); }
    .st-card-value { margin-top: 0.25rem; font-size: 1.25rem; font-weight: 700; color: rgb(var(--gray-950)); }
    .st-link { font-weight: 500; color: rgb(var(--primary-600)); }
    .st-link:hover { text-decoration: underline; }
    .st-list > li { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 0.6rem 0; font-size: 0.875rem; border-bottom: 1px solid rgb(var(--gray-100)); }
    .st-list > li:last-child { border-bottom: 0; }
    .st-note { margin-top: 0.25rem; font-size: 0.75rem; color: rgb(var(--warning-700)); }
</style>
