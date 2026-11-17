<?php

namespace App\Filament\Juban\Widgets;

use App\Enums\JubanTransactionType;
use App\Services\Juban\CashSummary;
use App\Services\Juban\CashSummaryResult;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

class CashByCategoryWidget extends Widget
{
    use InteractsWithPageFilters;

    protected static bool $isLazy = false;

    protected static string $view = 'filament.juban.widgets.cash-by-category';

    protected int|string|array $columnSpan = 'full';

    public function getSummary(): CashSummaryResult
    {
        [$from, $until] = CashSummary::periodFromFilters($this->filters);

        return app(CashSummary::class)->forPeriod($from, $until);
    }

    /**
     * @return list<JubanTransactionType>
     */
    public function getTypes(): array
    {
        return [JubanTransactionType::RECEITA, JubanTransactionType::DESPESA];
    }
}
