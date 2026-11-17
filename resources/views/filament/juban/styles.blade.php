{{-- O painel usa o CSS pré-compilado do Filament; estas regras seguem o design da tela da tesouraria. --}}
<style>
    .jb-head { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 16px; }
    .jb-head-date { margin: 0 0 6px; font-size: 14px; font-weight: 500; color: #5E585E; }
    .jb-head-title { margin: 0; font-family: 'Playfair Display', Georgia, serif; font-size: 34px; font-weight: 800; letter-spacing: -0.01em; line-height: 1.2; color: #201820; }
    .jb-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }
    .jb-btn { display: inline-flex; align-items: center; gap: 8px; min-height: 44px; padding: 0 16px; border-radius: 10px; border: 1px solid #D2D0D2; background: #FFFFFF; color: #201820; font-size: 14px; font-weight: 600; text-decoration: none; white-space: nowrap; }
    .jb-btn:hover { background: #F6F5F6; }
    .jb-btn-primary { border-color: #E81828; background: #E81828; color: #FFFFFF; font-weight: 700; }
    .jb-btn-primary:hover { background: #C51422; }
    .jb-btn svg { width: 16px; height: 16px; flex: none; }

    .jb-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 20px; }
    .jb-card, .jb-panel { padding: 24px; border: 1px solid #E8E7E8; border-radius: 14px; background: #FFFFFF; box-sizing: border-box; min-width: 0; }
    .jb-card { display: flex; flex-direction: column; gap: 6px; }
    .jb-card-label { margin: 0; font-size: 14px; font-weight: 500; color: #484148; }
    .jb-card-value { margin: 4px 0 0; font-size: 32px; font-weight: 700; letter-spacing: -0.02em; line-height: 1.1; color: #201820; }
    .jb-card-value.is-negative { color: #A2101C; }
    .jb-card-desc { margin: 0; font-size: 13.5px; line-height: 1.45; color: #5E585E; }
    .jb-link { color: #A2101C; font-weight: 600; text-decoration: none; white-space: nowrap; }
    .jb-link:hover { color: #5C0910; text-decoration: underline; }

    .jb-row { display: flex; flex-wrap: wrap; gap: 20px; align-items: stretch; }
    .jb-row > .jb-panel { flex: 1 1 360px; }
    .jb-row > .jb-panel.is-wide { flex: 1.4 1 520px; }
    .jb-panel-head { display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 18px; }
    .jb-panel-title { margin: 0; font-size: 18px; font-weight: 600; color: #201820; }
    .jb-panel-desc { margin: 4px 0 0; font-size: 14px; color: #5E585E; }
    .jb-subtitle { margin: 22px 0 4px; font-size: 16px; font-weight: 600; color: #201820; }
    .jb-empty { margin: 0; padding: 12px 0; font-size: 14px; color: #5E585E; }

    .jb-bars { display: flex; align-items: flex-end; gap: 10px; height: 206px; border-bottom: 1px solid #E8E7E8; overflow-x: auto; }
    .jb-bar { flex: 1 1 0; min-width: 28px; display: flex; flex-direction: column; align-items: center; justify-content: flex-end; gap: 8px; }
    .jb-bar-value { font-size: 12.5px; font-weight: 600; color: #484148; white-space: nowrap; }
    .jb-bar-fill { width: 70%; max-width: 56px; border-radius: 6px 6px 2px 2px; background: #9F1D12; }
    .jb-bar-label { font-size: 13px; color: #5E585E; white-space: nowrap; }

    .jb-cat { padding: 10px 0; }
    .jb-cat-line { display: flex; justify-content: space-between; gap: 10px; margin-bottom: 6px; font-size: 14.5px; color: #201820; }
    .jb-cat-value { font-weight: 600; white-space: nowrap; }
    .jb-cat-track { height: 8px; border-radius: 999px; background: #EFEDEA; overflow: hidden; }
    .jb-cat-fill { height: 100%; border-radius: 999px; background: #201820; }
    .jb-cat-fill.is-receita { background: #15803D; }

    @media (max-width: 640px) {
        .jb-head-title { font-size: 28px; }
        .jb-card-value { font-size: 26px; }
    }
</style>
