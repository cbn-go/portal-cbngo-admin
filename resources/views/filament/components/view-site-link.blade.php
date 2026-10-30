@php
    $siteUrl = config('portal.site_url', 'https://cbngo.org.br');
@endphp

<a
    href="{{ $siteUrl }}"
    target="_blank"
    rel="noopener noreferrer"
    style="display: inline-flex; align-items: center; gap: 6px; min-height: 44px; padding: 0 12px; font-size: 14px; font-weight: 600; text-decoration: none; color: #484148; border-radius: 8px; transition: color 0.15s ease;"
    onmouseover="this.style.color='#A2101C'"
    onmouseout="this.style.color='#484148'"
    title="Abrir o site público da CBN Goiás em nova aba"
>
    <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M14 4h6v6"></path>
        <path d="M20 4l-9 9"></path>
        <path d="M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"></path>
    </svg>
    <span>Ver site</span>
</a>
