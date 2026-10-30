@php
    $siteUrl = config('portal.site_url', 'https://cbngo.org.br');
@endphp

<a
    href="{{ $siteUrl }}"
    target="_blank"
    rel="noopener noreferrer"
    class="fi-topbar-item-button group flex items-center gap-x-2 rounded-lg px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-white/5"
    title="Abrir o site público da CBN Goiás em nova aba"
>
    <x-filament::icon
        icon="heroicon-o-arrow-top-right-on-square"
        class="h-5 w-5 text-gray-500 group-hover:text-primary-600 dark:text-gray-400 dark:group-hover:text-primary-400"
    />
    <span class="hidden sm:inline">Ver site</span>
</a>
