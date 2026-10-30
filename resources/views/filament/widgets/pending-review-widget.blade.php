@php
    $items = $this->getPendingItems();
@endphp

<x-filament::section>
    <x-slot name="heading">
        <div class="flex items-center gap-x-2">
            <x-filament::icon
                icon="heroicon-o-clock"
                class="h-5 w-5 text-warning-500"
            />
            <span class="text-base font-semibold text-gray-900 dark:text-white">
                Aguardando Revisão
            </span>
            @if ($items->isNotEmpty())
                <span class="inline-flex items-center rounded-full bg-warning-50 px-2 py-0.5 text-xs font-medium text-warning-700 dark:bg-warning-950/50 dark:text-warning-300">
                    {{ $items->count() }}
                </span>
            @endif
        </div>
    </x-slot>

    <x-slot name="headerEnd">
        @if ($items->isNotEmpty())
            <a
                href="{{ \App\Filament\Resources\NewsResource::getUrl('index') }}"
                class="text-xs font-semibold text-primary-600 hover:text-primary-500 hover:underline dark:text-primary-400"
            >
                Revisar tudo &rarr;
            </a>
        @endif
    </x-slot>

    @if ($items->isEmpty())
        <div class="flex flex-col items-center justify-center py-8 text-center">
            <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-success-50 text-success-600 dark:bg-success-950/50 dark:text-success-400">
                <x-filament::icon icon="heroicon-o-check-circle" class="h-6 w-6" />
            </div>
            <h4 class="text-sm font-semibold text-gray-900 dark:text-white">
                Tudo em dia!
            </h4>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Nenhuma publicação aguardando revisão no momento.
            </p>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-gray-600 dark:text-gray-300">
                <thead class="border-b border-gray-200 text-xs font-semibold uppercase text-gray-500 dark:border-white/10 dark:text-gray-400">
                    <tr>
                        <th class="pb-3 pt-1">Tipo</th>
                        <th class="pb-3 pt-1">Título</th>
                        <th class="pb-3 pt-1">Autor / Igreja</th>
                        <th class="pb-3 pt-1 text-right">Enviado há</th>
                        <th class="pb-3 pt-1 text-right">Ação</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach ($items as $item)
                        <tr class="hover:bg-gray-50/50 dark:hover:bg-white/5">
                            <td class="py-3">
                                <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium {{ match ($item['type']) {
                                    'Notícia' => 'bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300',
                                    'Artigo' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300',
                                    default => 'bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300',
                                } }}">
                                    {{ $item['type'] }}
                                </span>
                            </td>
                            <td class="py-3 font-medium text-gray-900 dark:text-white">
                                <a href="{{ $item['url'] }}" class="hover:text-primary-600 hover:underline">
                                    {{ \Illuminate\Support\Str::limit($item['title'], 60) }}
                                </a>
                            </td>
                            <td class="py-3 text-xs text-gray-500 dark:text-gray-400">
                                {{ $item['author'] }}
                            </td>
                            <td class="py-3 text-right text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                {{ $item['created_at']?->diffForHumans() }}
                            </td>
                            <td class="py-3 text-right">
                                <a
                                    href="{{ $item['url'] }}"
                                    class="inline-flex items-center gap-x-1 text-xs font-medium text-primary-600 hover:text-primary-500 hover:underline dark:text-primary-400"
                                >
                                    Revisar
                                    <x-filament::icon icon="heroicon-m-chevron-right" class="h-3 w-3" />
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-filament::section>
