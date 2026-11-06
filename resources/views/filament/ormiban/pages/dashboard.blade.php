@php
    $summary = $this->summary();
    $church = $this->church();
    $shortcuts = $this->shortcuts();
@endphp

<x-filament-panels::page>
    {{-- O painel usa o CSS pré-compilado do Filament; estas regras cobrem o layout desta tela. --}}
    <style>
        .oh-facts { display: grid; grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr)); gap: 1rem 1.5rem; margin: 0; }
        .oh-fact dt { font-size: 0.75rem; font-weight: 500; color: rgb(var(--gray-500)); }
        .oh-fact dd { margin: 0.25rem 0 0; font-size: 0.95rem; font-weight: 600; color: rgb(var(--gray-950)); }
        .dark .oh-fact dd { color: #fff; }
        .oh-name { margin: 0 0 1rem; font-size: 1.125rem; font-weight: 700; color: rgb(var(--gray-950)); }
        .dark .oh-name { color: #fff; }
        .oh-invite { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem; padding: 1rem; border-radius: 0.5rem; border: 1px solid rgb(var(--primary-200)); background: rgb(var(--primary-50)); color: rgb(var(--primary-900)); font-size: 0.875rem; line-height: 1.5; }
        .dark .oh-invite { border-color: rgb(var(--primary-800)); background: rgba(var(--primary-950), 0.6); color: rgb(var(--primary-100)); }
        .oh-invite p { margin: 0; }
        .oh-btn { display: inline-flex; align-items: center; justify-content: center; min-height: 44px; padding: 0 1rem; border-radius: 0.5rem; background: #E81828; color: #fff; font-weight: 600; text-decoration: none; white-space: nowrap; }
        .oh-btn:hover { background: #c51422; }
        .oh-info { padding: 1rem; border-radius: 0.5rem; border: 1px solid rgb(var(--gray-200)); background: rgb(var(--gray-50)); color: rgb(var(--gray-700)); font-size: 0.875rem; line-height: 1.5; }
        .dark .oh-info { border-color: rgb(var(--gray-700)); background: rgb(var(--gray-800)); color: rgb(var(--gray-200)); }
        .oh-shortcuts { display: grid; grid-template-columns: repeat(auto-fit, minmax(13rem, 1fr)); gap: 1rem; }
        .oh-shortcut { display: flex; align-items: flex-start; gap: 0.75rem; min-height: 44px; padding: 1rem; border-radius: 0.75rem; border: 1px solid rgb(var(--gray-200)); background: #fff; text-decoration: none; transition: border-color 0.15s ease; }
        .oh-shortcut:hover, .oh-shortcut:focus-visible { border-color: rgb(var(--primary-500)); }
        .dark .oh-shortcut { border-color: rgb(var(--gray-700)); background: rgb(var(--gray-900)); }
        .oh-shortcut svg { flex-shrink: 0; width: 1.5rem; height: 1.5rem; color: rgb(var(--primary-600)); }
        .oh-shortcut-label { display: block; font-size: 0.95rem; font-weight: 600; color: rgb(var(--gray-950)); }
        .dark .oh-shortcut-label { color: #fff; }
        .oh-shortcut-desc { display: block; margin-top: 0.125rem; font-size: 0.8rem; color: rgb(var(--gray-500)); }
        .oh-stack { display: flex; flex-direction: column; gap: 1.5rem; }
    </style>

    <div class="oh-stack">
        @if ($this->isSuperAdminWithoutMinister())
            <div class="oh-info" data-testid="super-admin-notice">
                <strong>Esta é a tela inicial do ministro.</strong>
                Ela mostra a ficha ministerial de quem entra no Ormiban; seu usuário de super administrador não tem ficha vinculada, por isso não há resumo pessoal aqui.
            </div>
        @elseif ($summary === null)
            @include('filament.ormiban.partials.no-minister-notice')
        @else
            <x-filament::section heading="Minha ficha no rol" description="Dados oficiais registrados na convenção. Para alterações, procure a secretaria.">
                <p class="oh-name">{{ $this->minister()?->name }}</p>

                <dl class="oh-facts">
                    <div class="oh-fact">
                        <dt>Função / Título</dt>
                        <dd>{{ $summary['function'] }}</dd>
                    </div>
                    <div class="oh-fact">
                        <dt>Matrícula Oficial</dt>
                        <dd>{{ $summary['registration_number'] }}</dd>
                    </div>
                    <div class="oh-fact">
                        <dt>Situação Cadastral</dt>
                        <dd>
                            <x-filament::badge :color="$summary['is_active'] ? 'success' : 'gray'" style="display: inline-flex;">
                                {{ $summary['status'] }}
                            </x-filament::badge>
                        </dd>
                    </div>
                    <div class="oh-fact">
                        <dt>{{ $church === null ? 'Igreja informada no rol' : 'Igreja' }}</dt>
                        <dd>{{ $summary['church'] }}</dd>
                    </div>
                    <div class="oh-fact">
                        <dt>Tempo de Ministério</dt>
                        <dd>{{ $summary['ministry_time'] }}</dd>
                    </div>
                </dl>
            </x-filament::section>

            @if ($church === null)
                <div class="oh-invite" data-testid="church-invite">
                    @if ($this->canClaimChurch())
                        <p>
                            <strong>Sua ficha ainda não tem igreja vinculada.</strong>
                            Em Minha Igreja você escolhe uma igreja já cadastrada ou cadastra a sua.
                        </p>
                        <a href="{{ \App\Filament\Ormiban\Pages\EditMyChurch::getUrl() }}" class="oh-btn">
                            Vincular ou cadastrar igreja
                        </a>
                    @else
                        <p>
                            <strong>Sua ficha ainda não tem igreja vinculada.</strong>
                            Veja em Minha Igreja como fazer a associação.
                        </p>
                        <a href="{{ \App\Filament\Ormiban\Pages\EditMyChurch::getUrl() }}" class="oh-btn">
                            Abrir Minha Igreja
                        </a>
                    @endif
                </div>
            @endif
        @endif

        @if (count($shortcuts) > 0)
            <section aria-labelledby="oh-atalhos" class="oh-stack" style="gap: 0.75rem;">
                <h2 id="oh-atalhos" class="oh-name" style="margin: 0; font-size: 1rem;">Atalhos</h2>
                <div class="oh-shortcuts">
                    @foreach ($shortcuts as $shortcut)
                        <a href="{{ $shortcut['url'] }}" class="oh-shortcut">
                            <x-filament::icon :icon="$shortcut['icon']" />
                            <span>
                                <span class="oh-shortcut-label">{{ $shortcut['label'] }}</span>
                                <span class="oh-shortcut-desc">{{ $shortcut['description'] }}</span>
                            </span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-filament-panels::page>
