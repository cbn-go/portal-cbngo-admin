<div class="min-h-screen flex flex-col md:flex-row bg-white">
    {{-- Coluna Institucional Esquerda (Desktop) --}}
    <aside class="relative flex flex-col justify-between bg-[#201820] text-white border-t-[6px] border-[#E81828] md:w-5/12 lg:w-5/12 xl:w-[480px] p-6 sm:p-10 shrink-0">
        <div>
            <div class="flex items-center gap-4">
                <img
                    src="{{ asset('images/logo-cbngo-dark.png') }}"
                    alt="Logo CBN Goiás"
                    class="h-10 sm:h-12 w-auto object-contain"
                />
            </div>

            <div class="mt-4 sm:mt-6">
                <span class="inline-block text-xs font-semibold uppercase tracking-wider text-[#ee5864]">
                    Ormiban GO &bull; Ordem dos Ministros
                </span>
                <h2 class="font-brand-title mt-2 text-2xl sm:text-3xl font-bold leading-tight text-white">
                    Acesso exclusivo para Ministros e Missionários.
                </h2>
                <p class="mt-3 text-sm text-gray-300 leading-relaxed">
                    Consulte seu histórico ministerial, dados cadastrais e mantenha sua ficha de membro atualizada junto à convenção.
                </p>
            </div>

            <div class="hidden md:block mt-8 space-y-4 border-t border-white/10 pt-6">
                <div class="rounded-lg bg-white/5 p-4 border border-white/10">
                    <h3 class="font-semibold text-sm text-white flex items-center gap-2">
                        <svg class="h-4 w-4 text-[#ee5864]" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                        </svg>
                        Primeiro Acesso com CPF
                    </h3>
                    <p class="text-xs text-gray-300 mt-1">
                        Se este for seu primeiro acesso ao novo portal, informe seu CPF e deixe o campo senha em branco para definir sua senha de acesso.
                    </p>
                </div>
            </div>
        </div>

        <div class="mt-8 text-xs text-gray-400">
            &copy; {{ date('Y') }} Convenção Batista Nacional de Goiás
        </div>
    </aside>

    {{-- Coluna Direita (Formulário de Login) --}}
    <main class="auth-form-column flex-1 flex flex-col justify-between p-6 sm:p-10 bg-white">
        <div class="flex justify-end mb-4">
            <a
                href="{{ url('/') }}"
                class="inline-flex items-center gap-1.5 text-xs text-gray-500 hover:text-gray-900 transition-colors py-1 px-2 rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-[#E81828]"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                </svg>
                <span>Voltar ao site</span>
            </a>
        </div>

        <div class="w-full max-w-[420px] mx-auto my-auto py-2">
            <header class="mb-6">
                <h1 class="font-brand-title text-2xl sm:text-3xl font-bold tracking-tight text-gray-900">
                    Acesso Ormiban
                </h1>
                <p class="mt-2 text-sm text-gray-600">
                    Informe seu CPF e senha de ministro cadastrado.
                </p>
            </header>

            {{-- Alertas de Erro --}}
            @php
                $cpfError = $errors->first('data.cpf');
                $isAccountInactive = ($cpfError === 'Sua conta está desativada. Entre em contato com a administração.');
                $showInvalidAlert = $hasAuthFailed || ($errors->has('data.cpf') && ! $isAccountInactive);
            @endphp

            @if ($showInvalidAlert)
                <div
                    role="alert"
                    aria-live="assertive"
                    class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-red-900 shadow-sm"
                >
                    <div class="flex items-start gap-3">
                        <svg class="h-5 w-5 flex-shrink-0 text-[#E81828] mt-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
                        </svg>
                        <div class="text-sm">
                            <p class="font-semibold text-[#c51422]">
                                {{ $cpfError ?: 'Não foi possível entrar. Confira seu CPF e senha.' }}
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
                        <svg class="h-5 w-5 flex-shrink-0 text-amber-600 mt-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
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

            {{-- Formulário --}}
            <x-filament-panels::form id="form" wire:submit="authenticate">
                {{ $this->form }}

                <div class="flex items-center justify-between text-sm mt-3 mb-1">
                    <label for="data.remember" class="inline-flex items-center gap-2 cursor-pointer select-none">
                        <input
                            id="data.remember"
                            type="checkbox"
                            wire:model="data.remember"
                            tabindex="3"
                            class="h-4 w-4 rounded border-2 border-gray-300 text-[#E81828] focus:ring-[#E81828] focus:ring-offset-0 cursor-pointer accent-[#E81828]"
                        />
                        <span class="text-sm font-medium text-gray-700">Manter conectado</span>
                    </label>

                    @if (filament()->hasPasswordReset())
                        <a
                            href="{{ filament()->getRequestPasswordResetUrl() }}"
                            tabindex="4"
                            class="text-sm font-medium text-[#E81828] hover:text-[#c51422] hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-[#E81828] rounded py-1"
                        >
                            Esqueci minha senha
                        </a>
                    @endif
                </div>

                <div class="mt-4">
                    <x-filament-panels::form.actions
                        :actions="$this->getCachedFormActions()"
                        :full-width="$this->hasFullWidthFormActions()"
                    />
                </div>
            </x-filament-panels::form>

            <div class="mt-5 rounded-lg bg-gray-50 p-3 text-xs text-gray-500 border border-gray-200">
                <div class="flex items-center gap-2">
                    <svg class="h-4 w-4 flex-shrink-0 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                    </svg>
                    <span>Após 5 tentativas sem sucesso, o acesso fica bloqueado por alguns minutos.</span>
                </div>
            </div>

            <div class="mt-6 text-center text-sm text-gray-600">
                Dúvidas sobre seu cadastro no rol? Contate a secretaria:
                <a
                    href="mailto:secretaria@cbngo.org.br"
                    class="font-semibold text-[#E81828] hover:text-[#c51422] underline focus:outline-none focus-visible:ring-2 focus-visible:ring-[#E81828] rounded"
                >
                    secretaria@cbngo.org.br
                </a>
            </div>
        </div>

        <div class="md:hidden mt-8 pt-4 border-t border-gray-200 text-center text-xs text-gray-500">
            Acesso exclusivo para membros da Ormiban GO.
        </div>
    </main>
</div>
