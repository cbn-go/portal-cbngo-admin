<?php

namespace App\DTOs\Statute;

class StatuteChapter
{
    /**
     * @param  array<int, StatuteSection>  $sections
     * @param  array<int, StatuteArticle>  $articles
     */
    public function __construct(
        public readonly string $eId,
        public readonly string $num,
        public readonly ?string $heading = null,
        public readonly array $sections = [],
        public readonly array $articles = []
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'eId' => $this->eId,
            'num' => $this->num,
            'heading' => $this->heading,
            'sections' => array_map(fn (StatuteSection $sec) => $sec->toArray(), $this->sections),
            'articles' => array_map(fn (StatuteArticle $art) => $art->toArray(), $this->articles),
        ];
    }
}
