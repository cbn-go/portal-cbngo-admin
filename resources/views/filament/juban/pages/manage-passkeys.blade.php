<x-filament-panels::page>
    <div x-data="passkeyRegister()" class="space-y-6">
        {{-- Aviso se navegador não suportar WebAuthn --}}
        <div x-show="!supported" x-cloak class="p-4 rounded-xl bg-danger-50 dark:bg-danger-950/50 border border-danger-200 dark:border-danger-800 text-danger-700 dark:text-danger-300 flex items-start gap-3">
            <x-heroicon-o-exclamation-triangle class="w-6 h-6 shrink-0 mt-0.5" />
            <div>
                <h4 class="font-semibold text-sm">Navegador ou dispositivo incompatível</h4>
                <p class="text-sm mt-1">Seu navegador atual não possui suporte à API WebAuthn/Passkeys. Para assinar relatórios, utilize um navegador moderno como Chrome, Safari ou Edge em um dispositivo com suporte a biometria ou chave física.</p>
            </div>
        </div>

        {{-- Card de Instruções e Ação de Cadastro --}}
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-6 shadow-sm">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                        <x-heroicon-m-key class="w-5 h-5 text-primary-600 dark:text-primary-400" />
                        Autenticação Forte para Assinatura de Relatórios
                    </h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1 max-w-2xl">
                        Cadastre a biometria do seu dispositivo (Windows Hello, Touch ID, Face ID ou chave física de segurança).
                        As passkeys são utilizadas pelo tesoureiro e pelo presidente da JUBAN para assinar de forma não-repudiável os relatórios financeiros trimestrais.
                    </p>
                </div>

                <div>
                    <x-filament::button
                        x-on:click="$dispatch('open-modal', { id: 'register-passkey-modal' })"
                        icon="heroicon-m-plus"
                        color="primary"
                    >
                        Cadastrar Passkey
                    </x-filament::button>
                </div>
            </div>
        </div>

        {{-- Listagem de Passkeys Cadastradas --}}
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl overflow-hidden shadow-sm">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-950/50 flex items-center justify-between">
                <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">
                    Dispositivos Cadastrados ({{ $this->passkeys->count() }})
                </h4>
            </div>

            @if ($this->passkeys->isEmpty())
                <div class="p-12 text-center">
                    <div class="w-12 h-12 rounded-full bg-primary-50 dark:bg-primary-950/50 text-primary-600 dark:text-primary-400 mx-auto flex items-center justify-center mb-3">
                        <x-heroicon-o-finger-print class="w-6 h-6" />
                    </div>
                    <h5 class="text-base font-medium text-gray-900 dark:text-white">Nenhuma passkey cadastrada</h5>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 max-w-md mx-auto">
                        Você ainda não possui nenhuma credencial cadastrada neste dispositivo ou conta. Clique no botão acima para adicionar a biometria ou chave do seu aparelho.
                    </p>
                </div>
            @else
                <div class="divide-y divide-gray-200 dark:divide-gray-800">
                    @foreach ($this->passkeys as $passkey)
                        <div class="p-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                            <div class="flex items-start gap-4">
                                <div class="w-10 h-10 rounded-lg bg-gray-100 dark:bg-gray-800 flex items-center justify-center text-gray-600 dark:text-gray-300 shrink-0">
                                    <x-heroicon-o-device-phone-mobile class="w-5 h-5" />
                                </div>
                                <div>
                                    <h5 class="text-base font-semibold text-gray-900 dark:text-white">
                                        {{ $passkey->name }}
                                    </h5>
                                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        <span>Cadastrada em: {{ $passkey->created_at->format('d/m/Y \à\s H:i') }}</span>
                                        <span>•</span>
                                        <span>Último uso: {{ $passkey->last_used_at ? $passkey->last_used_at->format('d/m/Y \à\s H:i') : 'Nunca utilizada' }}</span>
                                        @if ($passkey->backup_eligible)
                                            <span>•</span>
                                            <span class="inline-flex items-center gap-1 text-primary-600 dark:text-primary-400 font-medium">
                                                <x-heroicon-m-cloud class="w-3.5 h-3.5" /> Sincronizada
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 self-end sm:self-center">
                                <x-filament::button
                                    size="sm"
                                    color="gray"
                                    icon="heroicon-m-pencil-square"
                                    x-on:click="$dispatch('open-modal', { id: 'rename-passkey-modal-{{ $passkey->id }}' })"
                                >
                                    Renomear
                                </x-filament::button>

                                <x-filament::button
                                    size="sm"
                                    color="danger"
                                    icon="heroicon-m-trash"
                                    wire:click="deletePasskey({{ $passkey->id }})"
                                    wire:confirm="Tem certeza que deseja remover esta passkey? Você não poderá mais utilizá-la para assinar relatórios."
                                >
                                    Excluir
                                </x-filament::button>
                            </div>
                        </div>

                        {{-- Modal de Renomear --}}
                        <x-filament::modal id="rename-passkey-modal-{{ $passkey->id }}" width="md">
                            <x-slot name="heading">Renomear Passkey</x-slot>
                            <x-slot name="description">Altere o rótulo identificador do seu dispositivo.</x-slot>

                            <div x-data="{ newName: '{{ $passkey->name }}' }" class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Novo nome</label>
                                    <input
                                        type="text"
                                        x-model="newName"
                                        class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm"
                                    />
                                </div>

                                <div class="flex justify-end gap-2 pt-2">
                                    <x-filament::button color="gray" x-on:click="$dispatch('close-modal', { id: 'rename-passkey-modal-{{ $passkey->id }}' })">
                                        Cancelar
                                    </x-filament::button>
                                    <x-filament::button
                                        color="primary"
                                        x-on:click="$wire.renamePasskey({{ $passkey->id }}, newName); $dispatch('close-modal', { id: 'rename-passkey-modal-{{ $passkey->id }}' })"
                                    >
                                        Salvar
                                    </x-filament::button>
                                </div>
                            </div>
                        </x-filament::modal>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Modal de Início de Registro com Confirmação de Senha --}}
        <x-filament::modal id="register-passkey-modal" width="md">
            <x-slot name="heading">Cadastrar Nova Passkey</x-slot>
            <x-slot name="description">
                Confirme sua senha atual para autorizar o cadastro de um novo autenticador biométrico ou chave de hardware.
            </x-slot>

            <div x-data="{ regName: 'Meu Dispositivo', regPassword: '' }" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nome identificador</label>
                    <input
                        type="text"
                        x-model="regName"
                        placeholder="Ex: Notebook Dell - Windows Hello"
                        class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Sua senha atual</label>
                    <input
                        type="password"
                        x-model="regPassword"
                        placeholder="Digite sua senha de acesso"
                        class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm"
                    />
                    @error('password')
                        <p class="text-xs text-danger-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <x-filament::button color="gray" x-on:click="$dispatch('close-modal', { id: 'register-passkey-modal' })">
                        Cancelar
                    </x-filament::button>
                    <x-filament::button
                        color="primary"
                        x-bind:disabled="!regPassword"
                        x-on:click="$wire.startRegistration(regName, regPassword); $dispatch('close-modal', { id: 'register-passkey-modal' })"
                    >
                        Continuar para Biometria
                    </x-filament::button>
                </div>
            </div>
        </x-filament::modal>
    </div>
</x-filament-panels::page>
