@php
    $stats = $this->getStatsData();
    $chartData = $this->getContributionsChartData();
    $pendingChurches = $this->getPendingChurches();
    $pendingItems = $this->getPendingReviewItems();
    $portal = $this->getPortalSummary();
@endphp

<x-filament-panels::page class="cbn-mockup-dashboard">

    <div style="display: flex; flex-direction: column; gap: 24px; width: 100%; box-sizing: border-box;">

        {{-- Top Header: Greeting, Date & Quick Action Buttons --}}
        <div style="display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 16px;">
            <div style="display: flex; flex-direction: column; gap: 6px;">
                <p style="margin: 0; font-size: 14px; color: #5E585E; font-weight: 500;">
                    {{ $this->getSubheading() }}
                </p>
                <h1 style="margin: 0; font-family: 'Playfair Display', Georgia, serif; font-size: 34px; font-weight: 800; letter-spacing: -0.01em; color: #201820; line-height: 1.2;">
                    {{ $this->getTitle() }}
                </h1>
            </div>
            <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 10px;">
                <a
                    href="{{ \App\Filament\Resources\NewsResource::getUrl('create') }}"
                    style="display: inline-flex; align-items: center; gap: 8px; min-height: 44px; padding: 0 16px; border: 1px solid #D2D0D2; border-radius: 10px; background: #FFFFFF; color: #201820; font-size: 14px; font-weight: 600; text-decoration: none; cursor: pointer;"
                >
                    <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 5v14"></path>
                        <path d="M5 12h14"></path>
                    </svg>
                    Nova notícia
                </a>
                <a
                    href="#"
                    style="display: inline-flex; align-items: center; gap: 8px; min-height: 44px; padding: 0 16px; border-radius: 10px; background: #E81828; color: #FFFFFF; font-size: 14px; font-weight: 700; text-decoration: none; cursor: pointer; border: none;"
                >
                    <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 5v14"></path>
                        <path d="M5 12h14"></path>
                    </svg>
                    Registrar contribuição
                </a>
            </div>
        </div>

        {{-- Indicadores (4 Cards em Grid) --}}
        <section aria-label="Indicadores" class="cbn-indicators-grid">
            <div style="padding: 20px; border: 1px solid #E8E7E8; border-radius: 14px; background: #FFFFFF; display: flex; flex-direction: column; gap: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
                <p style="margin: 0; font-size: 13px; font-weight: 600; color: #5E585E;">Igrejas ativas</p>
                <p style="margin: 0; font-size: 32px; font-weight: 700; letter-spacing: -0.02em; color: #201820; line-height: 1.1;">
                    {{ $stats['active_churches'] }}
                </p>
                <p style="margin: 0; font-size: 13px; color: #5E585E;">{{ $stats['cities_subtitle'] }}</p>
            </div>

            <div style="padding: 20px; border: 1px solid #E8E7E8; border-radius: 14px; background: #FFFFFF; display: flex; flex-direction: column; gap: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
                <p style="margin: 0; font-size: 13px; font-weight: 600; color: #5E585E;">Pastores no rol</p>
                <p style="margin: 0; font-size: 32px; font-weight: 700; letter-spacing: -0.02em; color: #201820; line-height: 1.1;">
                    {{ $stats['pastors_count'] }}
                </p>
                <p style="margin: 0; font-size: 13px; color: #5E585E;">{{ $stats['pastors_subtitle'] }}</p>
            </div>

            <div style="padding: 20px; border: 1px solid #E8E7E8; border-radius: 14px; background: #FFFFFF; display: flex; flex-direction: column; gap: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
                <p style="margin: 0; font-size: 13px; font-weight: 600; color: #5E585E;">Contribuições em outubro</p>
                <p style="margin: 0; font-size: 32px; font-weight: 700; letter-spacing: -0.02em; color: #201820; line-height: 1.1;">
                    {{ $stats['contributions_month'] }}
                </p>
                <p style="margin: 0; font-size: 13px; color: #5E585E;">{{ $stats['contributions_subtitle'] }}</p>
            </div>

            <div style="padding: 20px; border: 1px solid #E8E7E8; border-radius: 14px; background: #FFFFFF; display: flex; flex-direction: column; gap: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
                <p style="margin: 0; font-size: 13px; font-weight: 600; color: #5E585E;">Aguardando revisão</p>
                <p style="margin: 0; font-size: 32px; font-weight: 700; letter-spacing: -0.02em; color: #201820; line-height: 1.1;">
                    {{ $stats['total_pending'] }}
                </p>
                <p style="margin: 0; font-size: 13px; color: #5E585E;">{{ $stats['pending_subtitle'] }}</p>
            </div>
        </section>

        {{-- Grid Principal de 2 Colunas: Esquerda (2fr) e Direita (1fr) --}}
        <div class="cbn-dashboard-grid">

            {{-- Coluna Esquerda: Contribuições (Gráfico) + Igrejas sem contribuição (Tabela) --}}
            <div style="display: flex; flex-direction: column; gap: 16px; min-width: 0;">

                {{-- Card 1: Contribuições das igrejas (Gráfico de Barras) --}}
                <section
                    aria-labelledby="titulo-contrib"
                    x-data="{ hovered: -1 }"
                    style="padding: 24px; border: 1px solid #E8E7E8; border-radius: 14px; background: #FFFFFF; display: flex; flex-direction: column; gap: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);"
                >
                    <div style="display: flex; flex-wrap: wrap; align-items: baseline; justify-content: space-between; gap: 8px;">
                        <div style="display: flex; flex-direction: column; gap: 4px;">
                            <h2 id="titulo-contrib" style="margin: 0; font-size: 17px; font-weight: 700; color: #201820;">
                                Contribuições das igrejas
                            </h2>
                            <p style="margin: 0; font-size: 13px; color: #5E585E;">
                                Total recebido por mês, maio a outubro de 2026
                            </p>
                        </div>
                        <a href="#" style="font-size: 14px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; min-height: 44px; color: #A2101C;">
                            Ver relatório
                        </a>
                    </div>

                    <div style="position: relative; display: flex; flex-direction: column; gap: 8px;">
                        <div style="position: relative; height: 220px; display: grid; grid-template-columns: 56px minmax(0, 1fr);">
                            {{-- Escala Y --}}
                            <div style="position: relative; font-size: 12px; color: #5E585E;">
                                <span style="position: absolute; top: -8px; right: 10px;">50 mil</span>
                                <span style="position: absolute; top: 102px; right: 10px;">25 mil</span>
                                <span style="position: absolute; bottom: -8px; right: 10px;">0</span>
                            </div>

                            {{-- Área das Barras --}}
                            <div style="position: relative; border-bottom: 1px solid #B0AEB0;">
                                <div style="position: absolute; left: 0; right: 0; top: 0; border-top: 1px dashed #E8E7E8;"></div>
                                <div style="position: absolute; left: 0; right: 0; top: 110px; border-top: 1px dashed #E8E7E8;"></div>
                                <div style="position: absolute; inset: 0; display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 2px; align-items: end; padding: 0 8px;">
                                    @foreach ($chartData['bars'] as $index => $bar)
                                        @php
                                            $barHeightPx = round(($bar['raw_value'] / 50000) * 220);
                                            $isLast = $bar['is_current'];
                                        @endphp
                                        <div
                                            @mouseenter="hovered = {{ $index }}"
                                            @mouseleave="hovered = -1"
                                            @focus="hovered = {{ $index }}"
                                            @blur="hovered = -1"
                                            tabindex="0"
                                            aria-label="{{ $bar['month'] }}: {{ $bar['value'] }}, {{ $bar['count'] }}"
                                            style="position: relative; height: 100%; padding: 0; border: 0; background: transparent; cursor: pointer; display: flex; align-items: flex-end; justify-content: center; outline: none;"
                                        >
                                            <span
                                                :style="hovered === {{ $index }} || {{ $isLast ? 'true' : 'false' }} ? 'background: #E81828' : 'background: #B0AEB0'"
                                                style="display: block; width: min(56px, 70%); border-radius: 4px 4px 0 0; height: {{ $barHeightPx }}px; transition: background 0.15s ease;"
                                            ></span>

                                            <div
                                                x-show="hovered === {{ $index }}"
                                                x-cloak
                                                style="position: absolute; left: 50%; bottom: {{ $barHeightPx + 8 }}px; transform: translateX(-50%); padding: 8px 10px; border-radius: 8px; background: #201820; color: #FFFFFF; font-size: 12px; line-height: 1.4; white-space: nowrap; text-align: left; box-shadow: 0 4px 12px rgba(32,24,32,0.18); z-index: 10; pointer-events: none;"
                                            >
                                                <span style="display: block; color: #D2D0D2;">{{ $bar['month'] }} de 2026</span>
                                                <strong style="display: block; font-size: 14px; color: #FFFFFF;">{{ $bar['value'] }}</strong>
                                                <span style="display: block; color: #D2D0D2;">{{ $bar['count'] }}</span>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        {{-- Eixo X (Meses) --}}
                        <div style="display: grid; grid-template-columns: 56px minmax(0, 1fr);">
                            <span></span>
                            <div style="display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); padding: 0 8px; font-size: 12px; color: #5E585E; text-align: center;">
                                @foreach ($chartData['bars'] as $bar)
                                    <span style="{{ $bar['is_current'] ? 'font-weight: 700; color: #201820;' : '' }}">
                                        {{ $bar['short'] }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </section>

                {{-- Card 2: Igrejas sem contribuição em outubro (Tabela) --}}
                <section
                    aria-labelledby="titulo-pendentes"
                    style="padding: 24px; border: 1px solid #E8E7E8; border-radius: 14px; background: #FFFFFF; display: flex; flex-direction: column; gap: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);"
                >
                    <div style="display: flex; flex-wrap: wrap; align-items: baseline; justify-content: space-between; gap: 8px;">
                        <h2 id="titulo-pendentes" style="margin: 0; font-size: 17px; font-weight: 700; color: #201820;">
                            Igrejas sem contribuição em outubro
                        </h2>
                        <a href="#" style="font-size: 14px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; min-height: 44px; color: #A2101C;">
                            Ver todas as contribuições
                        </a>
                    </div>

                    <div style="overflow-x: auto;">
                        <table style="width: 100%; min-width: 520px; border-collapse: collapse; font-size: 14px; text-align: left;">
                            <thead>
                                <tr style="color: #5E585E; font-size: 12px; text-transform: uppercase; letter-spacing: 0.04em;">
                                    <th scope="col" style="padding: 10px 12px 10px 0; font-weight: 700; border-bottom: 1px solid #E8E7E8;">Igreja</th>
                                    <th scope="col" style="padding: 10px 12px; font-weight: 700; border-bottom: 1px solid #E8E7E8;">Cidade</th>
                                    <th scope="col" style="padding: 10px 12px; font-weight: 700; border-bottom: 1px solid #E8E7E8;">Última contribuição</th>
                                    <th scope="col" style="padding: 10px 0 10px 12px; font-weight: 700; border-bottom: 1px solid #E8E7E8;">Situação</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($pendingChurches as $church)
                                    <tr>
                                        <td style="padding: 14px 12px 14px 0; border-bottom: 1px solid #F6F5F6; font-weight: 600; color: #201820;">
                                            {{ $church['name'] }}
                                        </td>
                                        <td style="padding: 14px 12px; border-bottom: 1px solid #F6F5F6; color: #484148;">
                                            {{ $church['city'] }}
                                        </td>
                                        <td style="padding: 14px 12px; border-bottom: 1px solid #F6F5F6; color: #484148;">
                                            {{ $church['last_contribution'] }}
                                        </td>
                                        <td style="padding: 14px 0 14px 12px; border-bottom: 1px solid #F6F5F6;">
                                            @if ($church['status_type'] === 'warning')
                                                <span style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 999px; background: #FEF3C7; color: #92400E; font-size: 13px; font-weight: 700;">
                                                    <svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                                        <circle cx="12" cy="12" r="9"></circle>
                                                        <path d="M12 7v5l3 2"></path>
                                                    </svg>
                                                    {{ $church['status'] }}
                                                </span>
                                            @else
                                                <span style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 999px; background: #FDECED; color: #A2101C; font-size: 13px; font-weight: 700;">
                                                    <svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M12 3l9 16H3l9-16z"></path>
                                                        <path d="M12 10v4"></path>
                                                        <path d="M12 17v.5"></path>
                                                    </svg>
                                                    {{ $church['status'] }}
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            {{-- Coluna Direita: Aguardando revisão (Lista) + No ar no portal (Resumo 2x2) --}}
            <div style="display: flex; flex-direction: column; gap: 16px; min-width: 0;">

                {{-- Card 3: Aguardando revisão (Lista Vertical) --}}
                <section
                    aria-labelledby="titulo-revisao"
                    style="padding: 24px; border: 1px solid #E8E7E8; border-radius: 14px; background: #FFFFFF; display: flex; flex-direction: column; gap: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);"
                >
                    <div style="display: flex; align-items: baseline; justify-content: space-between; gap: 8px;">
                        <h2 id="titulo-revisao" style="margin: 0; font-size: 17px; font-weight: 700; color: #201820;">
                            Aguardando revisão
                        </h2>
                        <span style="font-size: 13px; color: #5E585E;">
                            {{ $pendingItems->count() }} {{ $pendingItems->count() === 1 ? 'item' : 'itens' }}
                        </span>
                    </div>

                    @if ($pendingItems->isEmpty())
                        <div style="padding: 24px 0; text-align: center; color: #5E585E; font-size: 14px;">
                            <p style="margin: 0; font-weight: 600; color: #201820;">Tudo em dia!</p>
                            <p style="margin: 4px 0 0; font-size: 13px;">Nenhuma publicação aguardando revisão no momento.</p>
                        </div>
                    @else
                        <ul style="list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column;">
                            @foreach ($pendingItems as $item)
                                <li style="padding: 14px 0; border-top: 1px solid #E8E7E8; display: flex; flex-direction: column; gap: 6px;">
                                    <span style="font-size: 12px; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase; color: #5E585E;">
                                        {{ $item['category_label'] }}
                                    </span>
                                    <a
                                        href="{{ $item['url'] }}"
                                        style="font-size: 15px; font-weight: 600; line-height: 1.4; color: #201820; text-decoration: none;"
                                        onmouseover="this.style.color='#A2101C'"
                                        onmouseout="this.style.color='#201820'"
                                    >
                                        {{ $item['title'] }}
                                    </a>
                                    <span style="font-size: 13px; color: #5E585E;">
                                        {{ $item['author'] }} · enviado {{ $item['time_ago'] }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <a
                        href="{{ \App\Filament\Resources\NewsResource::getUrl('index') }}"
                        style="align-self: flex-start; font-size: 14px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; min-height: 44px; color: #A2101C;"
                    >
                        Revisar tudo
                    </a>
                </section>

                {{-- Card 4: No ar no portal (Resumo 2x2) --}}
                <section
                    aria-labelledby="titulo-portal"
                    style="padding: 24px; border: 1px solid #E8E7E8; border-radius: 14px; background: #FFFFFF; display: flex; flex-direction: column; gap: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);"
                >
                    <div style="display: flex; align-items: baseline; justify-content: space-between; gap: 8px;">
                        <h2 id="titulo-portal" style="margin: 0; font-size: 17px; font-weight: 700; color: #201820;">
                            No ar no portal
                        </h2>
                        <a
                            href="{{ $portal['site_url'] }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            style="font-size: 13px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; color: #A2101C;"
                        >
                            Ver site
                            <svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M14 4h6v6"></path>
                                <path d="M20 4l-9 9"></path>
                                <path d="M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"></path>
                            </svg>
                        </a>
                    </div>

                    <dl style="margin: 0; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px;">
                        <div style="display: flex; flex-direction: column; gap: 2px;">
                            <dt style="font-size: 13px; color: #5E585E;">Banners vigentes</dt>
                            <dd style="margin: 0; font-size: 24px; font-weight: 700; color: #201820;">{{ $portal['banners_count'] }}</dd>
                        </div>
                        <div style="display: flex; flex-direction: column; gap: 2px;">
                            <dt style="font-size: 13px; color: #5E585E;">Avisos ativos</dt>
                            <dd style="margin: 0; font-size: 24px; font-weight: 700; color: #201820;">{{ $portal['notices_count'] }}</dd>
                        </div>
                        <div style="display: flex; flex-direction: column; gap: 2px;">
                            <dt style="font-size: 13px; color: #5E585E;">Notícias publicadas</dt>
                            <dd style="margin: 0; font-size: 24px; font-weight: 700; color: #201820;">{{ $portal['news_count'] }}</dd>
                        </div>
                        <div style="display: flex; flex-direction: column; gap: 2px;">
                            <dt style="font-size: 13px; color: #5E585E;">Artigos publicados</dt>
                            <dd style="margin: 0; font-size: 24px; font-weight: 700; color: #201820;">{{ $portal['articles_count'] }}</dd>
                        </div>
                    </dl>

                    <p style="margin: 0; padding-top: 14px; border-top: 1px solid #E8E7E8; font-size: 13px; line-height: 1.5; color: #484148;">
                        {{ $portal['scheduled_notice'] }}
                    </p>
                </section>

            </div>

        </div>

    </div>

</x-filament-panels::page>
