<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Componente Alpine WebAuthn para assinatura com passkey --}}
        <div
            wire:ignore
            x-ignore
            ax-load
            ax-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('passkey-sign') }}"
            x-data="passkeySign()"
        >
            <div x-show="!supported" x-cloak class="p-4 rounded-xl bg-danger-50 dark:bg-danger-950/50 border border-danger-200 dark:border-danger-800 text-danger-700 dark:text-danger-300 flex items-start gap-3">
                <x-heroicon-o-exclamation-triangle class="w-6 h-6 shrink-0 mt-0.5" />
                <div>
                    <h4 class="font-semibold text-sm">Navegador ou dispositivo sem suporte a WebAuthn</h4>
                    <p class="text-sm mt-1">Seu navegador atual não suporta autenticação com passkey. Para assinar, utilize um navegador moderno em um dispositivo com suporte biométrico ou chave física.</p>
                </div>
            </div>
        </div>

        {{-- Alerta para usuário que tem a vez de assinar mas não possui passkey cadastrada --}}
        @if ($this->canSignReport() && ! $this->userHasPasskeys())
            <div class="p-4 rounded-xl bg-warning-50 dark:bg-warning-950/50 border border-warning-200 dark:border-warning-800 text-warning-800 dark:text-warning-200 flex items-start gap-3">
                <x-heroicon-m-key class="w-6 h-6 shrink-0 text-warning-600 dark:text-warning-400 mt-0.5" />
                <div class="flex-1">
                    <h4 class="font-semibold text-sm">Passkey necessária para assinatura</h4>
                    <p class="text-sm mt-1">
                        Você precisa cadastrar uma passkey antes de assinar este relatório.
                    </p>
                    <div class="mt-3">
                        <x-filament::button
                            tag="a"
                            href="/juban/passkeys"
                            color="warning"
                            size="sm"
                            icon="heroicon-m-arrow-top-right-on-square"
                        >
                            Cadastrar Passkey agora
                        </x-filament::button>
                    </div>
                </div>
            </div>
        @endif

        {{-- Cabeçalho do Relatório e Status --}}
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-6 shadow-sm">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <div class="flex items-center gap-3">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white">
                            Relatório Q{{ $record->quarter }}/{{ $record->year }} &bull; JUBAN
                        </h2>
                        <x-filament::badge :color="$record->status->getColor()">
                            {{ $record->status->getLabel() }}
                        </x-filament::badge>
                    </div>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Período de competência: <strong>{{ $record->period_from->format('d/m/Y') }}</strong> a <strong>{{ $record->period_until->format('d/m/Y') }}</strong>
                    </p>
                </div>

                @if ($record->pdf_sha256)
                    <div class="bg-gray-50 dark:bg-gray-800/60 border border-gray-200 dark:border-gray-700 rounded-lg p-3 max-w-md">
                        <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider block">Hash Criptográfico (SHA-256)</span>
                        <code class="text-xs text-gray-800 dark:text-gray-200 font-mono break-all select-all block mt-0.5">{{ $record->pdf_sha256 }}</code>
                    </div>
                @endif
            </div>
        </div>

        {{-- Cards de Resumo Financeiro --}}
        @php
            $summary = $record->snapshot['summary'] ?? [];
            $receitas = (float) ($summary['total_receitas'] ?? 0);
            $despesas = (float) ($summary['total_despesas'] ?? 0);
            $saldo = (float) ($summary['saldo'] ?? 0);
            $count = count($record->snapshot['transactions'] ?? []);
        @endphp
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-5 shadow-sm border-l-4 border-l-success-600">
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total de Receitas</span>
                <div class="text-2xl font-bold text-success-600 dark:text-success-400 mt-1">
                    R$ {{ number_format($receitas, 2, ',', '.') }}
                </div>
            </div>

            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-5 shadow-sm border-l-4 border-l-danger-600">
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total de Despesas</span>
                <div class="text-2xl font-bold text-danger-600 dark:text-danger-400 mt-1">
                    R$ {{ number_format($despesas, 2, ',', '.') }}
                </div>
            </div>

            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-5 shadow-sm border-l-4 {{ $saldo >= 0 ? 'border-l-primary-600' : 'border-l-danger-600' }}">
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Saldo Líquido</span>
                <div class="text-2xl font-bold {{ $saldo >= 0 ? 'text-primary-600 dark:text-primary-400' : 'text-danger-600 dark:text-danger-400' }} mt-1">
                    R$ {{ number_format($saldo, 2, ',', '.') }}
                </div>
            </div>

            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-5 shadow-sm border-l-4 border-l-gray-400">
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Lançamentos no Período</span>
                <div class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                    {{ $count }}
                </div>
            </div>
        </div>

        {{-- Linha do Tempo (Audit Timeline) --}}
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-6 shadow-sm">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white mb-6 flex items-center gap-2">
                <x-heroicon-m-clock class="w-5 h-5 text-primary-600 dark:text-primary-400" />
                Linha do Tempo de Homologação e Assinaturas
            </h3>

            <div class="relative border-l-2 border-gray-200 dark:border-gray-700 ml-4 space-y-8 pb-2">
                {{-- Etapa 1: Fechamento Contábil --}}
                <div class="relative pl-6">
                    <div class="absolute -left-[9px] top-1 w-4 h-4 rounded-full bg-success-600 border-2 border-white dark:border-gray-900"></div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-semibold text-gray-900 dark:text-white">Fechamento Contábil</span>
                            <x-filament::badge color="success" size="sm">Concluído</x-filament::badge>
                        </div>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                            Realizado em {{ $record->closed_at ? $record->closed_at->format('d/m/Y \à\s H:i') : 'N/A' }}
                            por <strong>{{ $record->closedBy?->name ?? 'Sistema' }}</strong>.
                        </p>
                    </div>
                </div>

                {{-- Etapa 2: Assinatura da Tesouraria --}}
                @php
                    $treasurySig = $record->treasurySignature;
                @endphp
                <div class="relative pl-6">
                    <div class="absolute -left-[9px] top-1 w-4 h-4 rounded-full {{ $treasurySig ? 'bg-success-600' : 'bg-warning-500' }} border-2 border-white dark:border-gray-900"></div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-semibold text-gray-900 dark:text-white">Assinatura da Tesouraria</span>
                            @if ($treasurySig)
                                <x-filament::badge color="success" size="sm">Assinado com passkey</x-filament::badge>
                            @else
                                <x-filament::badge color="warning" size="sm">Aguardando assinatura da tesouraria</x-filament::badge>
                            @endif
                        </div>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                            @if ($treasurySig)
                                Assinado em {{ $treasurySig->signed_at->format('d/m/Y \à\s H:i') }}
                                por <strong>{{ $treasurySig->user->name }}</strong> via <em>{{ $treasurySig->passkey->name }}</em>.
                            @else
                                Pendente de homologação e assinatura biométrica do tesoureiro da JUBAN.
                            @endif
                        </p>
                    </div>
                </div>

                {{-- Etapa 3: Assinatura da Presidência --}}
                @php
                    $presidencySig = $record->presidencySignature;
                @endphp
                <div class="relative pl-6">
                    <div class="absolute -left-[9px] top-1 w-4 h-4 rounded-full {{ $presidencySig ? 'bg-success-600' : ($treasurySig ? 'bg-warning-500' : 'bg-gray-300 dark:bg-gray-600') }} border-2 border-white dark:border-gray-900"></div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-semibold text-gray-900 dark:text-white">Assinatura da Presidência</span>
                            @if ($presidencySig)
                                <x-filament::badge color="success" size="sm">Assinado com passkey</x-filament::badge>
                            @elseif ($treasurySig)
                                <x-filament::badge color="info" size="sm">Aguardando assinatura da presidência</x-filament::badge>
                            @else
                                <x-filament::badge color="gray" size="sm">Aguardando etapa anterior</x-filament::badge>
                            @endif
                        </div>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                            @if ($presidencySig)
                                Assinado em {{ $presidencySig->signed_at->format('d/m/Y \à\s H:i') }}
                                por <strong>{{ $presidencySig->user->name }}</strong> via <em>{{ $presidencySig->passkey->name }}</em>.
                            @elseif ($treasurySig)
                                Aguardando conferência e assinatura biométrica do presidente da JUBAN.
                            @else
                                Esta etapa será desbloqueada após a assinatura do tesoureiro.
                            @endif
                        </p>
                    </div>
                </div>

                {{-- Reabertura (se aplicável) --}}
                @if ($record->status === \App\Enums\JubanReportStatus::REABERTO)
                    <div class="relative pl-6">
                        <div class="absolute -left-[9px] top-1 w-4 h-4 rounded-full bg-danger-600 border-2 border-white dark:border-gray-900"></div>
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-semibold text-danger-600 dark:text-danger-400">Trimestre Reaberto</span>
                                <x-filament::badge color="danger" size="sm">Reaberto</x-filament::badge>
                            </div>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                Reaberto em {{ $record->reopened_at ? $record->reopened_at->format('d/m/Y \à\s H:i') : 'N/A' }}
                                por <strong>{{ $record->reopenedBy?->name ?? 'SuperAdmin' }}</strong>.
                                @if ($record->reopen_reason)
                                    <br><em>Motivo: {{ $record->reopen_reason }}</em>
                                @endif
                            </p>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Seção de Comprovante de Assinaturas (quando houver ao menos 1 assinatura) --}}
        @if ($record->signatures()->exists())
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-6 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                        <x-heroicon-m-check-badge class="w-5 h-5 text-success-600 dark:text-success-400" />
                        Comprovante Oficial de Assinaturas Digitais
                    </h3>
                </div>

                <div class="space-y-4">
                    @foreach ($record->signatures as $sig)
                        <div class="border border-gray-200 dark:border-gray-800 rounded-lg p-4 bg-gray-50/50 dark:bg-gray-800/40">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-gray-200 dark:border-gray-700 pb-2 mb-3">
                                <span class="font-bold text-gray-900 dark:text-white text-sm">
                                    {{ $sig->role->getLabel() }}: {{ $sig->user->name }}
                                </span>
                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                    Assinado em {{ $sig->signed_at->format('d/m/Y \à\s H:i:s') }}
                                </span>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs text-gray-600 dark:text-gray-300">
                                <div><strong>Dispositivo/Passkey:</strong> {{ $sig->passkey->name }}</div>
                                <div><strong>IP de Auditoria:</strong> {{ $sig->ip ?? 'N/A' }}</div>
                                <div class="sm:col-span-2 font-mono break-all text-gray-500">
                                    <strong>Challenge SHA-256:</strong> {{ $sig->challenge }}
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
