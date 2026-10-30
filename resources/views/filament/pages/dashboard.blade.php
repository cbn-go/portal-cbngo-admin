@php
    $stats = $this->getStatsData();
    $chartData = $this->getContributionsChartData();
    $pendingChurches = $this->getPendingChurches();
    $pendingItems = $this->getPendingReviewItems();
    $portal = $this->getPortalSummary();
@endphp

<x-filament-panels::page class="fi-dashboard-page space-y-6">

    {{-- Top Greeting & Quick Actions --}}
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="flex flex-col gap-1.5">
            <p class="m-0 text-sm text-[#5E585E] dark:text-gray-400 font-medium">
                {{ $this->getSubheading() }}
            </p>
            <h1 class="m-0 font-serif-cbn text-[30px] sm:text-[34px] font-extrabold tracking-[-0.01em] text-[#201820] dark:text-white leading-tight">
                {{ $this->getTitle() }}
            </h1>
        </div>
        <div class="flex flex-wrap items-center gap-2.5">
            <a
                href="{{ \App\Filament\Resources\NewsResource::getUrl('create') }}"
                class="inline-flex items-center gap-2 min-h-[44px] px-4 py-2 border border-[#D2D0D2] dark:border-white/20 rounded-[10px] bg-white dark:bg-gray-800 text-[#201820] dark:text-white text-sm font-semibold hover:bg-gray-50 dark:hover:bg-gray-700 transition"
            >
                <svg aria-hidden="true" class="w-4 h-4 text-current" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 5v14"></path>
                    <path d="M5 12h14"></path>
                </svg>
                Nova notícia
            </a>
            <a
                href="#"
                class="inline-flex items-center gap-2 min-h-[44px] px-4 py-2 rounded-[10px] bg-[#E81828] hover:bg-[#C51422] text-white text-sm font-bold shadow-sm transition"
            >
                <svg aria-hidden="true" class="w-4 h-4 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 5v14"></path>
                    <path d="M5 12h14"></path>
                </svg>
                Registrar contribuição
            </a>
        </div>
    </div>

    {{-- Indicadores (4 Cards em Grade) --}}
    <section aria-label="Indicadores" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="p-5 border border-[#E8E7E8] dark:border-white/10 rounded-[14px] bg-white dark:bg-gray-900 flex flex-col gap-1.5 shadow-sm">
            <p class="m-0 text-[13px] font-semibold text-[#5E585E] dark:text-gray-400">Igrejas ativas</p>
            <p class="m-0 text-[32px] font-bold tracking-[-0.02em] text-[#201820] dark:text-white leading-none">
                {{ $stats['active_churches'] }}
            </p>
            <p class="m-0 text-[13px] text-[#5E585E] dark:text-gray-400">em {{ $stats['cities_count'] }} cidades de Goiás</p>
        </div>

        <div class="p-5 border border-[#E8E7E8] dark:border-white/10 rounded-[14px] bg-white dark:bg-gray-900 flex flex-col gap-1.5 shadow-sm">
            <p class="m-0 text-[13px] font-semibold text-[#5E585E] dark:text-gray-400">Pastores no rol</p>
            <p class="m-0 text-[32px] font-bold tracking-[-0.02em] text-[#201820] dark:text-white leading-none">
                {{ $stats['pastors_count'] }}
            </p>
            <p class="m-0 text-[13px] text-[#5E585E] dark:text-gray-400">{{ $stats['pastors_subtitle'] }}</p>
        </div>

        <div class="p-5 border border-[#E8E7E8] dark:border-white/10 rounded-[14px] bg-white dark:bg-gray-900 flex flex-col gap-1.5 shadow-sm">
            <p class="m-0 text-[13px] font-semibold text-[#5E585E] dark:text-gray-400">Contribuições em outubro</p>
            <p class="m-0 text-[32px] font-bold tracking-[-0.02em] text-[#201820] dark:text-white leading-none">
                {{ $stats['contributions_month'] }}
            </p>
            <p class="m-0 text-[13px] text-[#5E585E] dark:text-gray-400">{{ $stats['contributions_subtitle'] }}</p>
        </div>

        <div class="p-5 border border-[#E8E7E8] dark:border-white/10 rounded-[14px] bg-white dark:bg-gray-900 flex flex-col gap-1.5 shadow-sm">
            <p class="m-0 text-[13px] font-semibold text-[#5E585E] dark:text-gray-400">Aguardando revisão</p>
            <p class="m-0 text-[32px] font-bold tracking-[-0.02em] text-[#201820] dark:text-white leading-none">
                {{ $stats['total_pending'] }}
            </p>
            <p class="m-0 text-[13px] text-[#5E585E] dark:text-gray-400">{{ $stats['pending_subtitle'] }}</p>
        </div>
    </section>

    {{-- Layout em 2 Colunas: Esquerda (2fr) e Direita (1fr) --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 items-start">

        {{-- Coluna Esquerda (Span 2) --}}
        <div class="lg:col-span-2 space-y-4 min-w-0">

            {{-- Card 1: Contribuições das igrejas (Gráfico de Barras) --}}
            <section
                aria-labelledby="titulo-contrib"
                x-data="{ hovered: -1 }"
                class="p-6 border border-[#E8E7E8] dark:border-white/10 rounded-[14px] bg-white dark:bg-gray-900 flex flex-col gap-5 shadow-sm"
            >
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <div class="flex flex-col gap-1">
                        <h2 id="titulo-contrib" class="m-0 text-[17px] font-bold text-[#201820] dark:text-white">
                            Contribuições das igrejas
                        </h2>
                        <p class="m-0 text-[13px] text-[#5E585E] dark:text-gray-400">
                            Total recebido por mês, maio a outubro de 2026
                        </p>
                    </div>
                    <a
                        href="#"
                        class="text-sm font-semibold text-[#A2101C] hover:text-[#5C0910] dark:text-primary-400 inline-flex items-center min-h-[44px]"
                    >
                        Ver relatório
                    </a>
                </div>

                {{-- Gráfico Interativo com Alpine.js --}}
                <div class="relative flex flex-col gap-2 pt-2">
                    <div class="relative h-[220px] grid grid-cols-[56px_minmax(0,1fr)]">
                        {{-- Escala Y --}}
                        <div class="relative text-xs text-[#5E585E] dark:text-gray-400">
                            <span class="absolute -top-2 right-2.5">50 mil</span>
                            <span class="absolute top-[102px] right-2.5">25 mil</span>
                            <span class="absolute -bottom-2 right-2.5">0</span>
                        </div>

                        {{-- Área das Barras --}}
                        <div class="relative border-b border-[#B0AEB0] dark:border-white/20">
                            {{-- Linhas Guia Tracejadas --}}
                            <div class="absolute inset-x-0 top-0 border-t border-dashed border-[#E8E7E8] dark:border-white/10"></div>
                            <div class="absolute inset-x-0 top-[110px] border-t border-dashed border-[#E8E7E8] dark:border-white/10"></div>

                            {{-- 6 Barras Mensais --}}
                            <div class="absolute inset-0 grid grid-cols-6 gap-1 sm:gap-2 items-end px-2">
                                @foreach ($chartData['bars'] as $index => $bar)
                                    <div
                                        class="relative h-full flex items-end justify-center group cursor-pointer"
                                        @mouseenter="hovered = {{ $index }}"
                                        @mouseleave="hovered = -1"
                                        @focus="hovered = {{ $index }}"
                                        @blur="hovered = -1"
                                        tabindex="0"
                                        aria-label="{{ $bar['month'] }}: {{ $bar['value'] }}, {{ $bar['count'] }}"
                                    >
                                        {{-- Barra --}}
                                        <div
                                            class="w-[70%] max-w-[56px] rounded-t transition-all duration-200"
                                            :class="hovered === {{ $index }} || {{ $bar['is_current'] ? 'true' : 'false' }} ? 'bg-[#E81828]' : 'bg-[#B0AEB0] dark:bg-gray-600'"
                                            style="height: {{ $bar['height'] }}%;"
                                        ></div>

                                        {{-- Tooltip Flutuante no Hover --}}
                                        <div
                                            x-show="hovered === {{ $index }}"
                                            x-transition:enter="transition ease-out duration-150"
                                            x-transition:enter-start="opacity-0 translate-y-1"
                                            x-transition:enter-end="opacity-100 translate-y-0"
                                            class="absolute left-1/2 -translate-x-1/2 p-2.5 rounded-lg bg-[#201820] text-white text-xs whitespace-nowrap shadow-xl z-20 pointer-events-none"
                                            style="bottom: calc({{ $bar['height'] }}% + 8px);"
                                        >
                                            <span class="block text-[#D2D0D2]">{{ $bar['month'] }} de 2026</span>
                                            <strong class="block text-sm font-bold text-white">{{ $bar['value'] }}</strong>
                                            <span class="block text-[#D2D0D2]">{{ $bar['count'] }}</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    {{-- Eixo X (Meses) --}}
                    <div class="grid grid-cols-[56px_minmax(0,1fr)]">
                        <span></span>
                        <div class="grid grid-cols-6 px-2 text-xs text-[#5E585E] dark:text-gray-400 text-center font-medium">
                            @foreach ($chartData['bars'] as $bar)
                                <span class="{{ $bar['is_current'] ? 'font-bold text-[#201820] dark:text-white' : '' }}">
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
                class="p-6 border border-[#E8E7E8] dark:border-white/10 rounded-[14px] bg-white dark:bg-gray-900 flex flex-col gap-4 shadow-sm"
            >
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <h2 id="titulo-pendentes" class="m-0 text-[17px] font-bold text-[#201820] dark:text-white">
                        Igrejas sem contribuição em outubro
                    </h2>
                    <a
                        href="#"
                        class="text-sm font-semibold text-[#A2101C] hover:text-[#5C0910] dark:text-primary-400 inline-flex items-center min-h-[44px]"
                    >
                        Ver todas as contribuições
                    </a>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[500px] border-collapse text-left text-sm">
                        <thead>
                            <tr class="text-[#5E585E] dark:text-gray-400 text-xs font-bold uppercase tracking-wider border-b border-[#E8E7E8] dark:border-white/10">
                                <th scope="col" class="py-2.5 pr-3">Igreja</th>
                                <th scope="col" class="py-2.5 px-3">Cidade</th>
                                <th scope="col" class="py-2.5 px-3">Última contribuição</th>
                                <th scope="col" class="py-2.5 pl-3">Situação</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @foreach ($pendingChurches as $church)
                                <tr class="hover:bg-gray-50/50 dark:hover:bg-white/5 transition">
                                    <td class="py-3.5 pr-3 font-semibold text-[#201820] dark:text-white">
                                        {{ $church['name'] }}
                                    </td>
                                    <td class="py-3.5 px-3 text-[#484148] dark:text-gray-300">
                                        {{ $church['city'] }}
                                    </td>
                                    <td class="py-3.5 px-3 text-[#484148] dark:text-gray-300">
                                        {{ $church['last_contribution'] }}
                                    </td>
                                    <td class="py-3.5 pl-3">
                                        @if ($church['status_type'] === 'warning')
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-[#FEF3C7] text-[#92400E]">
                                                <svg aria-hidden="true" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                                    <circle cx="12" cy="12" r="9"></circle>
                                                    <path d="M12 7v5l3 2"></path>
                                                </svg>
                                                {{ $church['status'] }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-[#FDECED] text-[#A2101C]">
                                                <svg aria-hidden="true" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
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

        {{-- Coluna Direita (Span 1) --}}
        <div class="lg:col-span-1 space-y-4 min-w-0">

            {{-- Card 3: Aguardando revisão (Lista Vertical) --}}
            <section
                aria-labelledby="titulo-revisao"
                class="p-6 border border-[#E8E7E8] dark:border-white/10 rounded-[14px] bg-white dark:bg-gray-900 flex flex-col gap-4 shadow-sm"
            >
                <div class="flex items-baseline justify-between gap-2">
                    <h2 id="titulo-revisao" class="m-0 text-[17px] font-bold text-[#201820] dark:text-white">
                        Aguardando revisão
                    </h2>
                    <span class="text-[13px] text-[#5E585E] dark:text-gray-400 font-medium">
                        {{ $pendingItems->count() }} {{ $pendingItems->count() === 1 ? 'item' : 'itens' }}
                    </span>
                </div>

                @if ($pendingItems->isEmpty())
                    <div class="py-6 text-center text-sm text-[#5E585E] dark:text-gray-400">
                        <p class="m-0 font-medium text-[#201820] dark:text-white">Tudo em dia!</p>
                        <p class="m-0 text-xs mt-1">Nenhuma publicação aguardando revisão no momento.</p>
                    </div>
                @else
                    <ul class="list-none m-0 p-0 flex flex-col divide-y divide-[#E8E7E8] dark:divide-white/10">
                        @foreach ($pendingItems as $item)
                            <li class="py-3.5 first:pt-0 last:pb-0 flex flex-col gap-1.5">
                                <span class="text-[11px] font-bold tracking-wider uppercase text-[#5E585E] dark:text-gray-400">
                                    {{ $item['category_label'] }}
                                </span>
                                <a
                                    href="{{ $item['url'] }}"
                                    class="text-[15px] font-semibold leading-snug text-[#201820] dark:text-white hover:text-[#A2101C] transition line-clamp-2"
                                >
                                    {{ $item['title'] }}
                                </a>
                                <span class="text-[13px] text-[#5E585E] dark:text-gray-400">
                                    {{ $item['author'] }} · enviado {{ $item['time_ago'] }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <a
                    href="{{ \App\Filament\Resources\NewsResource::getUrl('index') }}"
                    class="self-start text-sm font-semibold text-[#A2101C] hover:text-[#5C0910] dark:text-primary-400 inline-flex items-center min-h-[44px]"
                >
                    Revisar tudo
                </a>
            </section>

            {{-- Card 4: No ar no portal (Resumo 2x2) --}}
            <section
                aria-labelledby="titulo-portal"
                class="p-6 border border-[#E8E7E8] dark:border-white/10 rounded-[14px] bg-white dark:bg-gray-900 flex flex-col gap-4 shadow-sm"
            >
                <div class="flex items-center justify-between">
                    <h2 id="titulo-portal" class="m-0 text-[17px] font-bold text-[#201820] dark:text-white">
                        No ar no portal
                    </h2>
                    <a
                        href="{{ $portal['site_url'] }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="text-xs font-semibold text-[#A2101C] hover:text-[#5C0910] dark:text-primary-400 inline-flex items-center gap-1"
                    >
                        Ver site
                        <svg aria-hidden="true" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 4h6v6"></path>
                            <path d="M20 4l-9 9"></path>
                            <path d="M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"></path>
                        </svg>
                    </a>
                </div>

                <dl class="m-0 grid grid-cols-2 gap-4">
                    <div class="flex flex-col gap-0.5">
                        <dt class="text-[13px] text-[#5E585E] dark:text-gray-400">Banners vigentes</dt>
                        <dd class="m-0 text-[24px] font-bold text-[#201820] dark:text-white">{{ $portal['banners_count'] }}</dd>
                    </div>
                    <div class="flex flex-col gap-0.5">
                        <dt class="text-[13px] text-[#5E585E] dark:text-gray-400">Avisos ativos</dt>
                        <dd class="m-0 text-[24px] font-bold text-[#201820] dark:text-white">{{ $portal['notices_count'] }}</dd>
                    </div>
                    <div class="flex flex-col gap-0.5">
                        <dt class="text-[13px] text-[#5E585E] dark:text-gray-400">Notícias publicadas</dt>
                        <dd class="m-0 text-[24px] font-bold text-[#201820] dark:text-white">{{ $portal['news_count'] }}</dd>
                    </div>
                    <div class="flex flex-col gap-0.5">
                        <dt class="text-[13px] text-[#5E585E] dark:text-gray-400">Artigos publicados</dt>
                        <dd class="m-0 text-[24px] font-bold text-[#201820] dark:text-white">{{ $portal['articles_count'] }}</dd>
                    </div>
                </dl>

                <p class="m-0 pt-3.5 border-t border-[#E8E7E8] dark:border-white/10 text-[13px] leading-relaxed text-[#484148] dark:text-gray-300">
                    {{ $portal['scheduled_notice'] }}
                </p>
            </section>

        </div>

    </div>

</x-filament-panels::page>
