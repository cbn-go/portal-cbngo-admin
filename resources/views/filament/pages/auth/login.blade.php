<div class="min-h-screen flex flex-col md:flex-row bg-white">
    {{-- Coluna Institucional Esquerda (Desktop) / Cabeçalho Superior Compacto (Mobile) --}}
    <aside class="relative flex flex-col justify-between bg-[#201820] text-white border-t-[6px] border-[#E81828] md:w-5/12 lg:w-5/12 xl:w-[480px] p-6 sm:p-10 shrink-0">
        <div>
            {{-- Logo e Subtítulo --}}
            <div class="flex items-center gap-4">
                <img
                    src="{{ asset('images/logo-cbngo-dark.png') }}"
                    alt="Logo CBN Goiás"
                    class="h-10 sm:h-12 w-auto object-contain"
                />
            </div>

            <div class="mt-4 sm:mt-6">
                <span class="inline-block text-xs font-semibold uppercase tracking-wider text-[#ee5864]">
                    Sistema de gestão da convenção
                </span>
                <h2 class="font-brand-title mt-2 text-2xl sm:text-3xl font-bold leading-tight text-white">
                    A administração da CBN Goiás em um só lugar.
                </h2>
            </div>

            {{-- Lista dos Módulos: Somente no Desktop (oculto no mobile <= 768px) --}}
            <div class="hidden md:block mt-8 space-y-5">
                <div class="border-t border-white/10 pt-6">
                    <ul class="space-y-4" role="list">
                        {{-- Módulo 1: Secretaria --}}
                        <li class="flex items-start gap-3">
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white/10 text-[#ee5864]" aria-hidden="true">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-semibold text-sm text-white">Secretaria</h3>
                                <p class="text-xs text-gray-400 mt-0.5">Rol de igrejas, pastores e credenciamento oficial.</p>
                            </div>
                        </li>

                        {{-- Módulo 2: Financeiro --}}
                        <li class="flex items-start gap-3">
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white/10 text-[#ee5864]" aria-hidden="true">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6H2.25m0 0v10.5m0-10.5h19.5m0 0v10.5m-19.5 0h19.5m-19.5 0v.75c0 .754.726 1.294 1.453 1.096A60.103 60.103 0 0021.75 18M9 12a2.25 2.25 0 114.5 0 2.25 2.25 0 01-4.5 0z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-semibold text-sm text-white">Financeiro</h3>
                                <p class="text-xs text-gray-400 mt-0.5">Contribuições, relatórios e controle de tesouraria.</p>
                            </div>
                        </li>

                        {{-- Módulo 3: Dados e relatórios --}}
                        <li class="flex items-start gap-3">
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white/10 text-[#ee5864]" aria-hidden="true">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-semibold text-sm text-white">Dados e relatórios</h3>
                                <p class="text-xs text-gray-400 mt-0.5">Indicadores estatísticos e panoramas convencionais.</p>
                            </div>
                        </li>

                        {{-- Módulo 4: Portal e comunicação --}}
                        <li class="flex items-start gap-3">
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white/10 text-[#ee5864]" aria-hidden="true">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-semibold text-sm text-white">Portal e comunicação</h3>
                                <p class="text-xs text-gray-400 mt-0.5">Notícias, artigos, projetos sociais e avisos.</p>
                            </div>
                        </li>
                    </ul>

                    <p class="mt-6 text-xs text-gray-400 italic">
                        Cada pessoa vê apenas os módulos liberados para a sua função.
                    </p>
                </div>
            </div>
        </div>

        {{-- Rodapé Coluna Esquerda: Desktop apenas --}}
        <div class="hidden md:block mt-8 pt-4 border-t border-white/10 text-xs text-gray-400">
            Ambiente restrito a pessoas autorizadas pela CBN-GO.
        </div>
    </aside>

    {{-- Coluna Direita (Formulário) - Fundo Branco Puro conforme Mockup --}}
    <main class="auth-form-column flex-1 flex flex-col justify-between bg-white text-gray-900 p-6 sm:p-10 lg:p-12 overflow-y-auto">
        {{-- Link Voltar ao site (Desktop: topo direito; Mobile: também acessível no topo) --}}
        <div class="flex justify-end mb-4 sm:mb-6">
            <a
                href="{{ config('portal.site_url', 'https://cbngo.org.br') }}"
                class="inline-flex items-center gap-2 text-sm font-medium text-gray-600 hover:text-[#E81828] transition-colors py-2 px-3 rounded-lg hover:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#E81828]"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                </svg>
                <span>Voltar ao site</span>
            </a>
        </div>

        {{-- Container Centralizado do Formulário (~400px de largura) --}}
        <div class="w-full max-w-[420px] mx-auto my-auto py-2">
            <header class="mb-6">
                <h1 class="font-brand-title text-2xl sm:text-3xl font-bold tracking-tight text-gray-900">
                    Entrar no sistema
                </h1>
                <p class="mt-2 text-sm text-gray-600">
                    Use o e-mail e a senha cadastrados pela secretaria da CBN-GO.
                </p>
            </header>

            {{-- Alertas de Erro (Credenciais Inválidas / Conta Desativada) --}}
            @php
                $emailError = $errors->first('data.email');
                $isAccountInactive = ($emailError === 'Sua conta está desativada. Entre em contato com a administração.');
                $showInvalidCredentialsAlert = $hasAuthFailed || ($errors->has('data.email') && ! $isAccountInactive);
            @endphp

            @if ($showInvalidCredentialsAlert)
                <div
                    role="alert"
                    aria-live="assertive"
                    class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-red-900 shadow-sm"
                >
                    <div class="flex items-start gap-3">
                        <svg class="h-5 w-5 flex-shrink-0 text-[#E81828] mt-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
                        </svg>
                        <div class="text-sm">
                            <p class="font-semibold text-[#c51422]">
                                Não foi possível entrar. Confira o e-mail e a senha.
                            </p>
                            @if ($attemptsRemaining !== null && $attemptsRemaining > 0)
                                <p class="mt-1 text-xs text-red-700">
                                    Você tem {{ $attemptsRemaining }} {{ $attemptsRemaining === 1 ? 'tentativa restante' : 'tentativas restantes' }} antes do bloqueio temporário.
                                </p>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            @if ($isAccountInactive)
                <div
                    role="alert"
                    aria-live="assertive"
                    class="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-amber-900 shadow-sm"
                >
                    <div class="flex items-start gap-3">
                        <svg class="h-5 w-5 flex-shrink-0 text-amber-600 mt-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                        </svg>
                        <div class="text-sm">
                            <p class="font-semibold text-amber-800">
                                Sua conta está desativada. Entre em contato com a administração.
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Formulário de Autenticação --}}
            <x-filament-panels::form id="form" wire:submit="authenticate">
                {{ $this->form }}

                @if (filament()->hasPasswordReset())
                    <div class="-mt-3 mb-2 flex justify-end">
                        <a
                            href="{{ filament()->getRequestPasswordResetUrl() }}"
                            tabindex="4"
                            class="text-sm font-medium text-[#E81828] hover:text-[#c51422] hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-[#E81828] rounded py-1"
                        >
                            Esqueci minha senha
                        </a>
                    </div>
                @endif

                <div class="mt-2">
                    <x-filament-panels::form.actions
                        :actions="$this->getCachedFormActions()"
                        :full-width="$this->hasFullWidthFormActions()"
                    />
                </div>
            </x-filament-panels::form>

            {{-- Aviso de Limite de Tentativas --}}
            <div class="mt-5 rounded-lg bg-gray-50 p-3 text-xs text-gray-500 border border-gray-200">
                <div class="flex items-center gap-2">
                    <svg class="h-4 w-4 flex-shrink-0 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                    </svg>
                    <span>Após 5 tentativas sem sucesso, o acesso fica bloqueado por alguns minutos.</span>
                </div>
            </div>

            {{-- Rodapé do Formulário: Solicitação de Cadastro à Secretaria --}}
            <div class="mt-6 text-center text-sm text-gray-600">
                Ainda não tem acesso? Peça o cadastro à secretaria pelo e-mail
                <a
                    href="mailto:{{ config('portal.contact_email', 'contato@cbngo.org.br') }}"
                    class="font-semibold text-[#E81828] hover:text-[#c51422] underline focus:outline-none focus-visible:ring-2 focus-visible:ring-[#E81828] rounded"
                >
                    {{ config('portal.contact_email', 'contato@cbngo.org.br') }}
                </a>
            </div>
        </div>

        {{-- Rodapé Mobile (<= 768px): Ambiente restrito --}}
        <div class="md:hidden mt-8 pt-4 border-t border-gray-200 text-center text-xs text-gray-500">
            Ambiente restrito a pessoas autorizadas pela CBN-GO.
        </div>
    </main>
</div>
