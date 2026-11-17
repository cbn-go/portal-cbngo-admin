<?php

namespace App\Services\Juban;

use App\Enums\JubanTransactionType;
use App\Models\JubanCategory;
use App\Models\JubanTransaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Resumo de caixa da tesouraria JUBAN: totais, saldo e quebra por categoria
 * num período (datas de ocorrência inclusivas). Lançamentos excluídos ficam de fora.
 */
class CashSummary
{
    public function forPeriod(Carbon $from, Carbon $until): CashSummaryResult
    {
        $from = $from->copy()->startOfDay();
        $until = $until->copy()->startOfDay();

        $rows = $this->periodQuery($from, $until)
            ->selectRaw('type, category_id, COUNT(*) as aggregate_count, SUM(amount) as aggregate_total')
            ->groupBy('type', 'category_id')
            ->toBase()
            ->get();

        /** @var array<string, float> $totals */
        $totals = [
            JubanTransactionType::RECEITA->value => 0.0,
            JubanTransactionType::DESPESA->value => 0.0,
        ];
        $count = 0;

        foreach ($rows as $row) {
            $totals[(string) $row->type] += (float) $row->aggregate_total;
            $count += (int) $row->aggregate_count;
        }

        $categoryNames = JubanCategory::query()
            ->whereIn('id', $rows->pluck('category_id')->unique()->all())
            ->pluck('name', 'id');

        $categories = $rows
            ->map(function (object $row) use ($totals, $categoryNames): CashSummaryCategory {
                $type = JubanTransactionType::from((string) $row->type);
                $total = round((float) $row->aggregate_total, 2);
                $typeTotal = $totals[$type->value];

                return new CashSummaryCategory(
                    categoryId: (int) $row->category_id,
                    name: (string) ($categoryNames[$row->category_id] ?? '—'),
                    type: $type,
                    count: (int) $row->aggregate_count,
                    total: $total,
                    percentOfType: $typeTotal > 0 ? round($total / $typeTotal * 100, 1) : 0.0,
                );
            })
            ->sort(function (CashSummaryCategory $a, CashSummaryCategory $b): int {
                return [$a->type === JubanTransactionType::DESPESA, -$a->total, $a->name]
                    <=> [$b->type === JubanTransactionType::DESPESA, -$b->total, $b->name];
            })
            ->values()
            ->all();

        $receitas = round($totals[JubanTransactionType::RECEITA->value], 2);
        $despesas = round($totals[JubanTransactionType::DESPESA->value], 2);

        return new CashSummaryResult(
            from: $from,
            until: $until,
            receitas: $receitas,
            despesas: $despesas,
            saldo: round($receitas - $despesas, 2),
            count: $count,
            categories: $categories,
        );
    }

    /**
     * Período a partir dos filtros da página (datas `from`/`until`); mês atual por padrão.
     *
     * @param  array<string, mixed>|null  $filters
     * @return array{Carbon, Carbon}
     */
    public static function periodFromFilters(?array $filters): array
    {
        $from = filled($filters['from'] ?? null) ? Carbon::parse((string) $filters['from']) : now()->startOfMonth();
        $until = filled($filters['until'] ?? null) ? Carbon::parse((string) $filters['until']) : now()->endOfMonth();

        if ($until->lt($from)) {
            [$from, $until] = [$until, $from];
        }

        return [$from->startOfDay(), $until->startOfDay()];
    }

    /**
     * @return Builder<JubanTransaction>
     */
    private function periodQuery(Carbon $from, Carbon $until): Builder
    {
        return JubanTransaction::query()
            ->whereDate('occurred_on', '>=', $from->toDateString())
            ->whereDate('occurred_on', '<=', $until->toDateString());
    }
}
