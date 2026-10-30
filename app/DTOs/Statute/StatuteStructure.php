<?php

namespace App\DTOs\Statute;

class StatuteStructure
{
    /**
     * @param  array<int, StatuteChapter>  $chapters
     */
    public function __construct(
        public readonly string $title,
        public readonly ?string $frbrWorkUri = null,
        public readonly ?string $frbrExpressionUri = null,
        public readonly ?string $frbrManifestationUri = null,
        public readonly ?string $conclusions = null,
        public readonly array $chapters = []
    ) {}

    /**
     * @return array<int, StatuteArticle>
     */
    public function allArticles(): array
    {
        $articles = [];
        foreach ($this->chapters as $chapter) {
            foreach ($chapter->articles as $art) {
                $articles[] = $art;
            }
            foreach ($chapter->sections as $sec) {
                foreach ($sec->articles as $art) {
                    $articles[] = $art;
                }
            }
        }

        return $articles;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'frbrWorkUri' => $this->frbrWorkUri,
            'frbrExpressionUri' => $this->frbrExpressionUri,
            'frbrManifestationUri' => $this->frbrManifestationUri,
            'conclusions' => $this->conclusions,
            'chapters' => array_map(fn (StatuteChapter $chp) => $chp->toArray(), $this->chapters),
        ];
    }
}
