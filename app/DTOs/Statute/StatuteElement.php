<?php

namespace App\DTOs\Statute;

class StatuteElement
{
    /**
     * @param  array<int, StatuteElement>  $subElements
     */
    public function __construct(
        public readonly string $eId,
        public readonly string $type,
        public readonly ?string $num = null,
        public readonly ?string $introText = null,
        public readonly ?string $content = null,
        public readonly array $subElements = []
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'eId' => $this->eId,
            'type' => $this->type,
            'num' => $this->num,
            'introText' => $this->introText,
            'content' => $this->content,
            'subElements' => array_map(fn (StatuteElement $el) => $el->toArray(), $this->subElements),
        ];
    }
}
