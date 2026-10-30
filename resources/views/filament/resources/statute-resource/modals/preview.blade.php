<div class="space-y-6 text-gray-800 dark:text-gray-100 max-h-[75vh] overflow-y-auto px-4 py-2">
    @php
        $structure = $record->structure;
    @endphp

    <div class="text-center pb-6 border-b border-gray-200 dark:border-gray-700">
        <h2 class="text-xl font-bold tracking-tight text-gray-900 dark:text-white uppercase">{{ $structure->title }}</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
            Versão: <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $record->version_label }}</span> |
            Aprovado em: {{ $record->approved_at?->format('d/m/Y') }} ({{ $record->approval_body }})
        </p>
    </div>

    @foreach($structure->chapters as $chapter)
        <div class="mt-8 space-y-4">
            <div class="text-center">
                <h3 class="text-base font-bold text-primary-600 dark:text-primary-400 uppercase tracking-wider">{{ $chapter->num }}</h3>
                @if($chapter->heading)
                    <h4 class="text-lg font-semibold text-gray-900 dark:text-white mt-0.5">{{ $chapter->heading }}</h4>
                @endif
            </div>

            {{-- Artigos diretos do capítulo --}}
            @foreach($chapter->articles as $article)
                @include('filament.resources.statute-resource.partials.article-render', ['article' => $article])
            @endforeach

            {{-- Seções do capítulo --}}
            @foreach($chapter->sections as $section)
                <div class="mt-6 ml-2 space-y-4">
                    <div class="text-center">
                        <h4 class="text-sm font-bold text-primary-500 uppercase tracking-wide">{{ $section->num }}</h4>
                        @if($section->heading)
                            <h5 class="text-base font-medium text-gray-800 dark:text-gray-200">{{ $section->heading }}</h5>
                        @endif
                    </div>

                    @foreach($section->articles as $article)
                        @include('filament.resources.statute-resource.partials.article-render', ['article' => $article])
                    @endforeach
                </div>
            @endforeach
        </div>
    @endforeach

    @if($structure->conclusions)
        <div class="mt-10 pt-6 border-t border-gray-200 dark:border-gray-700 text-center italic text-sm text-gray-600 dark:text-gray-400">
            <p>{{ $structure->conclusions }}</p>
        </div>
    @endif
</div>
