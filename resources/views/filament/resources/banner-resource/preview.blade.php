@php
    $formData = $this->data ?? [];
    $record = method_exists($this, 'getRecord') ? $this->getRecord() : null;

    $tag = (!empty($formData['tag'])) ? $formData['tag'] : ($record->tag ?? 'Avisos da Convenção');
    $title = (!empty($formData['title'])) ? $formData['title'] : ($record->title ?? 'Título do aviso em destaque');
    $subtitle = (!empty($formData['subtitle'])) ? $formData['subtitle'] : ($record->subtitle ?? 'Subtítulo explicativo com detalhes do evento, chamada para ação ou resumo da mensagem institucional.');
    $linkLabel = (!empty($formData['link_label'])) ? $formData['link_label'] : ($record->link_label ?? \App\Models\Banner::DEFAULT_LINK_LABEL);
    $meta = (!empty($formData['meta'])) ? $formData['meta'] : ($record->meta ?? '14–15 mar · Anápolis');
    $opensInNewTab = (bool) (!empty($formData['opens_in_new_tab']) ? $formData['opens_in_new_tab'] : ($record->opens_in_new_tab ?? false));

    $imageState = $formData['image'] ?? ($record->image ?? null);
    $imageUrl = null;

    if ($imageState) {
        $file = is_array($imageState) ? (array_values($imageState)[0] ?? null) : $imageState;

        if ($file instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
            try {
                $imageUrl = $file->temporaryUrl();
            } catch (\Throwable $e) {
                $imageUrl = null;
            }
        } elseif (is_string($file) && filled($file)) {
            $imageUrl = \Illuminate\Support\Facades\Storage::disk(\App\Support\MediaVariants::diskName())->url($file);
        }
    }
@endphp

<div class="relative overflow-hidden rounded-2xl border border-neutral-200 bg-[#FAF6F0] p-6 lg:p-8 dark:border-neutral-800 dark:bg-neutral-900/60">
    <div class="mb-3 flex items-center justify-between border-b border-neutral-200/60 pb-3 text-xs text-neutral-500 dark:border-neutral-800">
        <span class="inline-flex items-center gap-1.5 font-semibold text-neutral-600 dark:text-neutral-300">
            <span class="h-2 w-2 rounded-full bg-[#E00209]"></span>
            Simulação da nova Hero (Site Público)
        </span>
        <span class="text-[11px] uppercase tracking-wider text-neutral-400">Proporção 1:1</span>
    </div>

    <div class="grid items-center gap-6 lg:grid-cols-12 lg:gap-8">
        {{-- Texto à esquerda --}}
        <div class="min-w-0 lg:col-span-7">
            <div class="mb-3 inline-flex items-center gap-1.5 rounded-full bg-white/80 px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-wider text-[#D42D12] shadow-sm ring-1 ring-black/5 dark:bg-neutral-800 dark:text-[#F36529]">
                <span>{{ $tag }}</span>
            </div>

            <h2 class="text-2xl font-bold tracking-tight text-neutral-900 sm:text-3xl dark:text-white" style="font-family: Georgia, serif;">
                {{ $title }}
            </h2>

            <p class="mt-2.5 text-sm leading-relaxed text-neutral-700 sm:text-base dark:text-neutral-300">
                {{ $subtitle }}
            </p>

            <div class="mt-5 flex flex-wrap items-center gap-3">
                <span class="inline-flex items-center gap-2 rounded-lg bg-[#D42D12] px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-[#1E120D]">
                    <span>{{ $linkLabel }}</span>
                    @if ($opensInNewTab)
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/>
                        </svg>
                    @else
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/>
                        </svg>
                    @endif
                </span>

                @if (filled($meta))
                    <span class="text-xs font-medium text-neutral-500 dark:text-neutral-400">
                        {{ $meta }}
                    </span>
                @endif
            </div>
        </div>

        {{-- Foto quadrada 1:1 à direita --}}
        <div class="min-w-0 lg:col-span-5">
            <div class="relative mx-auto aspect-square w-full max-w-[320px] overflow-hidden rounded-2xl bg-neutral-200 shadow-lg ring-1 ring-black/10 dark:bg-neutral-800">
                @if ($imageUrl)
                    <img
                        src="{{ $imageUrl }}"
                        alt="{{ (string) ($formData['alt_text'] ?? $record?->alt_text ?? '') }}"
                        class="h-full w-full object-cover"
                    />
                @else
                    <div class="flex h-full w-full flex-col items-center justify-center p-6 text-center text-neutral-400">
                        <svg class="h-12 w-12 text-neutral-300 dark:text-neutral-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                        </svg>
                        <span class="mt-2 text-xs font-medium">Envie uma foto quadrada (1:1)</span>
                    </div>
                @endif

                <span class="absolute left-3 top-3 inline-flex items-center gap-1 rounded-full bg-white/95 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-[#D42D12] shadow">
                    {{ $tag }}
                </span>

                <span class="absolute bottom-3 right-3 rounded-full bg-black/70 px-2.5 py-0.5 text-[11px] font-semibold text-white tabular-nums">
                    1 / 4
                </span>
            </div>
        </div>
    </div>
</div>
