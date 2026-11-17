<?php

namespace App\Services\Juban;

use App\Enums\JubanTransactionType;
use Illuminate\Support\Carbon;

final readonly class CashSummaryResult
{
    /**
     * @param  list<CashSummaryCategory>  $categories
     * @param  list<array{month: Carbon, total: float}>  $monthlyDespesas
     */
    public function __construct(
        public Carbon $from,
        public Carbon $until,
        public float $receitas,
        public float $despesas,
        public float $saldo,
        public int $count,
        public array $categories,
        public array $monthlyDespesas = [],
    ) {}

    public function countOf(JubanTransactionType $type): int
    {
        return array_sum(array_map(
            fn (CashSummaryCategory $category): int => $category->count,
            $this->categoriesOf($type),
        ));
    }

    /**
     * Complemento dos títulos: "em 2026", "em novembro de 2026" ou "de 01/10/2026 a 15/11/2026".
     */
    public function periodTitle(): string
    {
        if ($this->from->isSameDay($this->from->copy()->startOfYear()) && $this->until->isSameDay($this->from->copy()->endOfYear())) {
            return 'em '.$this->from->year;
        }

        if ($this->from->isSameDay($this->from->copy()->startOfMonth()) && $this->until->isSameDay($this->from->copy()->endOfMonth())) {
            return 'em '.$this->from->copy()->locale('pt_BR')->translatedFormat('F \d\e Y');
        }

        return 'de '.$this->periodLabel();
    }

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
