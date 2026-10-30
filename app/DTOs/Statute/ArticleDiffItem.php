<?php

namespace App\DTOs\Statute;

class ArticleDiffItem
{
    /**
     * @param  array<int, string>  $details
     */
    public function __construct(
        public readonly string $eId,
        public readonly string $num,
        public readonly string $type, // 'added', 'modified', 'removed', 'unchanged'
        public readonly ?string $oldText = null,
        public readonly ?string $newText = null,
        public readonly array $details = []
    ) {}

    public function isAdded(): bool
    {
        return $this->type === 'added';
    }

    public function isModified(): bool
    {
        return $this->type === 'modified';
    }

    public function isRemoved(): bool
    {
        return $this->type === 'removed';
    }

    public function isUnchanged(): bool
    {
        return $this->type === 'unchanged';
    }
}
