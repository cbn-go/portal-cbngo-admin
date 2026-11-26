<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Cabeçalho do Relatório e Status --}}
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-6 shadow-sm">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <div class="flex items-center gap-3">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white">
                            Relatório Q{{ $record->quarter }}/{{ $record->year }}{{ $record->version > 1 ? " (v{$record->version})" : '' }} &bull; JUBAN
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

        {{-- Cards de Resumo Financeiro do Snapshot --}}
        @php
            $summary = $record->snapshot['summary'] ?? [];
            $receitas = (float) ($summary['total_receitas'] ?? $summary['receitas'] ?? 0);
            $despesas = (float) ($summary['total_despesas'] ?? $summary['despesas'] ?? 0);
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
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Lançamentos</span>
                <div class="text-2xl font-bold text-gray-800 dark:text-gray-100 mt-1">
                    {{ $count }} registros
                </div>
            </div>
        </div>

        {{-- Seção de Pedidos de Esclarecimento --}}
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-6 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                    <x-heroicon-m-chat-bubble-left-right class="w-5 h-5 text-warning-600 dark:text-warning-400" />
                    Pedidos de Esclarecimento à Tesouraria
                </h3>
                <div class="flex items-center gap-2">
                    <x-filament::badge color="{{ $record->hasOpenInquiries() ? 'warning' : 'gray' }}">
                        {{ $record->inquiries->count() }} pedido(s)
                    </x-filament::badge>
                </div>
            </div>

            @if ($record->inquiries()->exists())
                <div class="space-y-4">
                    @foreach ($record->inquiries()->with(['askedBy', 'answeredBy', 'closedWithoutAnswerBy', 'transaction'])->latest()->get() as $inquiry)
                        <div class="border border-gray-200 dark:border-gray-800 rounded-lg p-4 bg-gray-50/50 dark:bg-gray-800/40 space-y-3">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-gray-200 dark:border-gray-700 pb-2">
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-gray-900 dark:text-white text-sm">
                                        {{ $inquiry->askedBy->name }}
                                    </span>
                                    <x-filament::badge :color="$inquiry->status->getColor()">
                                        {{ $inquiry->status->getLabel() }}
                                    </x-filament::badge>
                                </div>
                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                    Enviado em {{ $inquiry->asked_at->format('d/m/Y \à\s H:i') }}
                                </span>
                            </div>

                            @if ($inquiry->transaction)
                                <div class="p-2.5 rounded bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-xs text-gray-700 dark:text-gray-300">
                                    <strong>Lançamento associado:</strong> {{ $inquiry->transaction->occurred_on->format('d/m/Y') }} &bull; {{ $inquiry->transaction->description }} &bull; R$ {{ number_format((float) $inquiry->transaction->amount, 2, ',', '.') }}
                                </div>
                            @endif

                            <div class="text-sm text-gray-800 dark:text-gray-200 whitespace-pre-wrap">
                                <strong>Pergunta:</strong> {{ $inquiry->question }}
                            </div>

                            @if ($inquiry->isAnswered())
                                <div class="mt-3 p-3 rounded-lg bg-success-50 dark:bg-success-950/40 border border-success-200 dark:border-success-800 text-sm space-y-2">
                                    <div class="flex items-center justify-between text-xs text-success-800 dark:text-success-300 border-b border-success-200 dark:border-success-800 pb-1">
                                        <span><strong>Resposta da Tesouraria ({{ $inquiry->answeredBy?->name }}):</strong></span>
                                        <span>{{ $inquiry->answered_at?->format('d/m/Y \à\s H:i') }}</span>
                                    </div>
                                    <div class="text-gray-900 dark:text-white whitespace-pre-wrap">
                                        {{ $inquiry->answer }}
                                    </div>
                                    @if ($inquiry->attachment_path)
                                        <div class="pt-2">
                                            <x-filament::button
                                                tag="a"
                                                href="{{ route('juban.inquiries.download-attachment', $inquiry) }}"
                                                target="_blank"
                                                color="gray"
                                                size="xs"
                                                icon="heroicon-m-paper-clip"
                                            >
                                                Baixar Comprovante Anexo
                                            </x-filament::button>
                                        </div>
                                    @endif
                                </div>
                            @elseif ($inquiry->isClosedWithoutAnswer())
                                <div class="mt-3 p-3 rounded-lg bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-xs space-y-1">
                                    <div class="font-semibold text-gray-700 dark:text-gray-300">
                                        Encerrado sem resposta por {{ $inquiry->closedWithoutAnswerBy?->name }} em {{ $inquiry->closed_without_answer_at?->format('d/m/Y \à\s H:i') }}
                                    </div>
                                    @if ($inquiry->closed_without_answer_reason)
                                        <div class="text-gray-600 dark:text-gray-400 italic">
                                            Justificativa: {{ $inquiry->closed_without_answer_reason }}
                                        </div>
                                    @endif
                                </div>
                            @elseif ($inquiry->isOpen() && $this->canCloseWithoutAnswer($inquiry))
                                <div class="pt-2 flex justify-end">
                                    {{ ($this->closeWithoutAnswerAction)(['inquiry' => $inquiry->id]) }}
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-6 text-sm text-gray-500 dark:text-gray-400">
                    Nenhum pedido de esclarecimento registrado para este relatório.
                </div>
            @endif
        </div>

        {{-- Assinaturas Eletrônicas da Diretoria da JUBAN --}}
        @if ($record->signatures()->exists())
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-6 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                        <x-heroicon-m-check-badge class="w-5 h-5 text-success-600 dark:text-success-400" />
                        Assinaturas da Diretoria da JUBAN
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
