<?php

namespace App\Services\Juban;

use App\Enums\JubanTransactionType;

final readonly class CashSummaryCategory
{
    public function __construct(
        public int $categoryId,
        public string $name,
        public JubanTransactionType $type,
        public int $count,
        public float $total,
        public float $percentOfType,
    ) {}
}
