<?php

namespace App\DTOs\Statute;

class StatuteDiffResult
{
    /**
     * @param  array<int, ArticleDiffItem>  $articles
     */
    public function __construct(
        public readonly array $articles,
        public readonly int $addedCount = 0,
        public readonly int $modifiedCount = 0,
        public readonly int $removedCount = 0,
        public readonly int $unchangedCount = 0
    ) {}

    public function hasDifferences(): bool
    {
        return ($this->addedCount + $this->modifiedCount + $this->removedCount) > 0;
    }
}
