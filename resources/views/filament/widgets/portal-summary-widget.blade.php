@php
    $data = $this->getData();
@endphp

<x-filament::section>
    <x-slot name="heading">
        <div class="flex items-center gap-x-2">
            <x-filament::icon
                icon="heroicon-o-globe-alt"
                class="h-5 w-5 text-primary-500"
            />
            <span class="text-base font-semibold text-gray-900 dark:text-white">
                No Ar no Portal CBN-GO
            </span>
        </div>
    </x-slot>

    <x-slot name="headerEnd">
        <a
            href="{{ $data['site_url'] }}"
            target="_blank"
            rel="noopener noreferrer"
            class="inline-flex items-center gap-x-1 text-xs font-semibold text-primary-600 hover:text-primary-500 hover:underline dark:text-primary-400"
        >
            Abrir site público
            <x-filament::icon icon="heroicon-m-arrow-top-right-on-square" class="h-3.5 w-3.5" />
        </a>
    </x-slot>

    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div class="rounded-lg border border-gray-100 bg-gray-50/50 p-4 dark:border-white/5 dark:bg-white/5">
            <div class="flex items-center gap-x-2 text-xs font-medium text-gray-500 dark:text-gray-400">
                <x-filament::icon icon="heroicon-o-photo" class="h-4 w-4 text-primary-500" />
                <span>Banners no ar</span>
            </div>
            <div class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">
                {{ $data['visible_banners_count'] }}
            </div>
        </div>

        <div class="rounded-lg border border-gray-100 bg-gray-50/50 p-4 dark:border-white/5 dark:bg-white/5">
            <div class="flex items-center gap-x-2 text-xs font-medium text-gray-500 dark:text-gray-400">
                <x-filament::icon icon="heroicon-o-megaphone" class="h-4 w-4 text-amber-500" />
                <span>Avisos ativos</span>
            </div>
            <div class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">
                {{ $data['active_notices_count'] }}
            </div>
        </div>

        <div class="rounded-lg border border-gray-100 bg-gray-50/50 p-4 dark:border-white/5 dark:bg-white/5">
            <div class="flex items-center gap-x-2 text-xs font-medium text-gray-500 dark:text-gray-400">
                <x-filament::icon icon="heroicon-o-newspaper" class="h-4 w-4 text-blue-500" />
                <span>Notícias no ar</span>
            </div>
            <div class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">
                {{ $data['published_news_count'] }}
            </div>
        </div>

        <div class="rounded-lg border border-gray-100 bg-gray-50/50 p-4 dark:border-white/5 dark:bg-white/5">
            <div class="flex items-center gap-x-2 text-xs font-medium text-gray-500 dark:text-gray-400">
                <x-filament::icon icon="heroicon-o-document-text" class="h-4 w-4 text-emerald-500" />
                <span>Artigos no ar</span>
            </div>
            <div class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">
                {{ $data['published_articles_count'] }}
            </div>
        </div>
    </div>

    @if ($data['next_scheduled_banner'])
        <div class="mt-4 flex items-center justify-between rounded-lg border border-dashed border-gray-200 bg-gray-50/30 px-4 py-2.5 text-xs text-gray-600 dark:border-white/10 dark:bg-white/5 dark:text-gray-300">
            <div class="flex items-center gap-x-2">
                <x-filament::icon icon="heroicon-m-calendar" class="h-4 w-4 text-gray-400" />
                <span>
                    <strong>Próximo banner agendado:</strong> {{ $data['next_scheduled_banner']->title }}
                </span>
            </div>
            <span class="text-gray-500 dark:text-gray-400">
                Entrada: {{ $data['next_scheduled_banner']->starts_at?->translatedFormat('d/m/Y \à\s H:i') }}
            </span>
        </div>
    @endif
</x-filament::section>
