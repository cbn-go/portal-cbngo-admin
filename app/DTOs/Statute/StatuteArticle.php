<?php

namespace App\DTOs\Statute;

class StatuteArticle
{
    /**
     * @param  array<int, StatuteElement>  $elements
     */
    public function __construct(
        public readonly string $eId,
        public readonly string $num,
        public readonly ?string $heading = null,
        public readonly ?string $introText = null,
        public readonly array $elements = []
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
            'introText' => $this->introText,
            'elements' => array_map(fn (StatuteElement $el) => $el->toArray(), $this->elements),
        ];
    }
}
