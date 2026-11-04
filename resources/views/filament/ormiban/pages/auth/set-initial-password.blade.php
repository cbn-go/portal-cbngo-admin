<div class="min-h-screen flex flex-col md:flex-row bg-white">
    {{-- Coluna Institucional Esquerda --}}
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
                    Ormiban GO &bull; Primeiro Acesso
                </span>
                <h2 class="font-brand-title mt-2 text-2xl sm:text-3xl font-bold leading-tight text-white">
                    Defina sua senha pessoal de acesso.
                </h2>
                <p class="mt-3 text-sm text-gray-300 leading-relaxed">
                    Identificamos que este é o seu primeiro acesso ao painel da Ormiban GO. Para proteger suas informações, cadastre uma nova senha individual.
                </p>
            </div>

            <div class="hidden md:block mt-8 space-y-3 border-t border-white/10 pt-6 text-xs text-gray-300">
                <p class="font-semibold text-white">Recomendações de segurança:</p>
                <ul class="list-disc list-inside space-y-1">
                    <li>Utilize pelo menos 8 caracteres</li>
                    <li>Combine letras, números e símbolos</li>
                    <li>Não compartilhe sua senha com terceiros</li>
                </ul>
            </div>
        </div>

        <div class="mt-8 text-xs text-gray-400">
            &copy; {{ date('Y') }} Convenção Batista Nacional de Goiás
        </div>
    </aside>

    {{-- Coluna Direita (Formulário) --}}
    <main class="auth-form-column flex-1 flex flex-col justify-between p-6 sm:p-10 bg-white">
        <div class="w-full max-w-[420px] mx-auto my-auto py-2">
            <header class="mb-6">
                <h1 class="font-brand-title text-2xl sm:text-3xl font-bold tracking-tight text-gray-900">
                    Criar Senha de Acesso
                </h1>
                <p class="mt-2 text-sm text-gray-600">
                    Defina sua nova senha para prosseguir ao painel.
                </p>
            </header>

            <x-filament-panels::form id="form" wire:submit="save">
                {{ $this->form }}

                <div class="mt-6">
                    <x-filament-panels::form.actions
                        :actions="$this->getFormActions()"
                        :full-width="true"
                    />
                </div>
            </x-filament-panels::form>
        </div>
    </main>
</div>
