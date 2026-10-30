<?php

namespace App\DTOs\Statute;

class StatuteSection
{
    /**
     * @param  array<int, StatuteArticle>  $articles
     */
    public function __construct(
        public readonly string $eId,
        public readonly string $num,
        public readonly ?string $heading = null,
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
            'articles' => array_map(fn (StatuteArticle $art) => $art->toArray(), $this->articles),
        ];
    }
}
