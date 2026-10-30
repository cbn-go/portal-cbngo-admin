<div class="my-3 p-3 rounded-lg bg-gray-50 dark:bg-gray-800/50 border border-gray-100 dark:border-gray-800" id="{{ $article->eId }}">
    <p class="leading-relaxed text-sm">
        <strong class="font-bold text-gray-900 dark:text-white mr-1.5">{{ $article->num }}</strong>
        @if($article->heading)
            <span class="font-semibold italic text-gray-700 dark:text-gray-300 block mb-1">{{ $article->heading }}</span>
        @endif
        <span class="text-gray-800 dark:text-gray-200">{{ $article->introText }}</span>
    </p>

    @if(!empty($article->elements))
        <div class="mt-2 space-y-1.5 pl-3 border-l-2 border-gray-200 dark:border-gray-700">
            @foreach($article->elements as $element)
                <div class="text-sm leading-relaxed" id="{{ $element->eId }}">
                    @if($element->num)
                        <strong class="font-medium text-gray-900 dark:text-gray-100 mr-1">{{ $element->num }}</strong>
                    @endif
                    <span class="text-gray-700 dark:text-gray-300">{{ $element->content ?? $element->introText }}</span>

                    @if(!empty($element->subElements))
                        <div class="mt-1 space-y-1 pl-3 border-l-2 border-gray-100 dark:border-gray-800">
                            @foreach($element->subElements as $sub)
                                <div class="text-xs leading-relaxed" id="{{ $sub->eId }}">
                                    @if($sub->num)
                                        <strong class="font-medium text-gray-800 dark:text-gray-200 mr-1">{{ $sub->num }}</strong>
                                    @endif
                                    <span class="text-gray-600 dark:text-gray-400">{{ $sub->content ?? $sub->introText }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
