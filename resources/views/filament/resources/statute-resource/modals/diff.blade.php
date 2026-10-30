<div class="space-y-6 max-h-[75vh] overflow-y-auto px-4 py-2">
    {{-- Header de Estatísticas --}}
    <div class="p-4 rounded-xl bg-gray-50 dark:bg-gray-800/60 border border-gray-200 dark:border-gray-700">
        <div class="flex items-center justify-between mb-3">
            <div>
                <span class="text-xs uppercase font-bold text-gray-500 tracking-wider">Comparação de Versões</span>
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                    {{ $oldVersionLabel }} <span class="text-gray-400 mx-1">→</span> {{ $newVersionLabel }}
                </h3>
            </div>
            @if(!$diff->hasDifferences())
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                    Idênticas
                </span>
            @endif
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-center">
            <div class="p-2 rounded-lg bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800">
                <span class="text-xs text-emerald-700 dark:text-emerald-300 font-medium block">Adicionados</span>
                <span class="text-lg font-bold text-emerald-800 dark:text-emerald-200">{{ $diff->addedCount }}</span>
            </div>
            <div class="p-2 rounded-lg bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800">
                <span class="text-xs text-amber-700 dark:text-amber-300 font-medium block">Alterados</span>
                <span class="text-lg font-bold text-amber-800 dark:text-amber-200">{{ $diff->modifiedCount }}</span>
            </div>
            <div class="p-2 rounded-lg bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800">
                <span class="text-xs text-rose-700 dark:text-rose-300 font-medium block">Removidos</span>
                <span class="text-lg font-bold text-rose-800 dark:text-rose-200">{{ $diff->removedCount }}</span>
            </div>
            <div class="p-2 rounded-lg bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                <span class="text-xs text-gray-600 dark:text-gray-400 font-medium block">Inalterados</span>
                <span class="text-lg font-bold text-gray-700 dark:text-gray-300">{{ $diff->unchangedCount }}</span>
            </div>
        </div>
    </div>

    {{-- Lista de Diferenças --}}
    @if(!$diff->hasDifferences())
        <div class="text-center py-8 text-gray-500 dark:text-gray-400">
            <p class="text-sm">Nenhuma diferença textual encontrada entre os artigos das duas versões.</p>
        </div>
    @else
        <div class="space-y-4">
            @foreach($diff->articles as $item)
                @if($item->type === 'unchanged')
                    @continue
                @endif

                @if($item->type === 'added')
                    <div class="p-4 rounded-lg bg-emerald-50/50 dark:bg-emerald-950/20 border-l-4 border-emerald-500 border-t border-r border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between mb-2">
                            <span class="font-bold text-emerald-800 dark:text-emerald-300 text-sm">{{ $item->num }}</span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300">
                                Novo Artigo
                            </span>
                        </div>
                        <pre class="font-sans text-xs text-gray-800 dark:text-gray-200 whitespace-pre-wrap leading-relaxed">{{ $item->newText }}</pre>
                    </div>
                @elseif($item->type === 'removed')
                    <div class="p-4 rounded-lg bg-rose-50/50 dark:bg-rose-950/20 border-l-4 border-rose-500 border-t border-r border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between mb-2">
                            <span class="font-bold text-rose-800 dark:text-rose-300 text-sm">{{ $item->num }}</span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-rose-100 text-rose-800 dark:bg-rose-900/60 dark:text-rose-300">
                                Revogado / Removido
                            </span>
                        </div>
                        <pre class="font-sans text-xs text-gray-600 dark:text-gray-400 line-through whitespace-pre-wrap leading-relaxed">{{ $item->oldText }}</pre>
                    </div>
                @elseif($item->type === 'modified')
                    <div class="p-4 rounded-lg bg-amber-50/40 dark:bg-amber-950/20 border-l-4 border-amber-500 border-t border-r border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between mb-2">
                            <span class="font-bold text-amber-800 dark:text-amber-300 text-sm">{{ $item->num }}</span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-300">
                                Modificado
                            </span>
                        </div>

                        @if(!empty($item->details))
                            <ul class="text-xs text-amber-700 dark:text-amber-400 mb-3 list-disc list-inside">
                                @foreach($item->details as $detail)
                                    <li>{{ $detail }}</li>
                                @endforeach
                            </ul>
                        @endif

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
                            <div class="p-3 rounded bg-white dark:bg-gray-900 border border-rose-200 dark:border-rose-900/50">
                                <div class="text-[10px] font-bold uppercase text-rose-600 dark:text-rose-400 mb-1">Versão Anterior</div>
                                <pre class="font-sans text-gray-700 dark:text-gray-300 whitespace-pre-wrap leading-relaxed">{{ $item->oldText }}</pre>
                            </div>
                            <div class="p-3 rounded bg-white dark:bg-gray-900 border border-emerald-200 dark:border-emerald-900/50">
                                <div class="text-[10px] font-bold uppercase text-emerald-600 dark:text-emerald-400 mb-1">Nova Versão</div>
                                <pre class="font-sans text-gray-700 dark:text-gray-300 whitespace-pre-wrap leading-relaxed">{{ $item->newText }}</pre>
                            </div>
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
    @endif
</div>
