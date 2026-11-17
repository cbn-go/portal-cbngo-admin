<?php

namespace App\Services\Juban;

use App\Enums\JubanTransactionType;
use Illuminate\Support\Carbon;

final readonly class CashSummaryResult
{
    /**
     * @param  list<CashSummaryCategory>  $categories
     */
    public function __construct(
        public Carbon $from,
        public Carbon $until,
        public float $receitas,
        public float $despesas,
        public float $saldo,
        public int $count,
        public array $categories,
    ) {}

    /**
     * @return list<CashSummaryCategory>
     */
    public function categoriesOf(JubanTransactionType $type): array
    {
        return array_values(array_filter(
            $this->categories,
            fn (CashSummaryCategory $category): bool => $category->type === $type,
        ));
    }

    public function totalOf(JubanTransactionType $type): float
    {
        return $type->isReceita() ? $this->receitas : $this->despesas;
    }

    public function periodLabel(): string
    {
        return $this->from->format('d/m/Y').' a '.$this->until->format('d/m/Y');
    }
}
