<?php

namespace Tests\Feature\Services\Strategy;

use App\Enums\StrategyIndicator;
use App\Services\Strategy\StateIndicatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Filament\Strategy\CreatesStrategyData;
use Tests\TestCase;

class StateIndicatorServiceTest extends TestCase
{
    use CreatesStrategyData;
    use RefreshDatabase;

    public function test_absolute_indicators_are_summed(): void
    {
        $this->municipality([], ['population_estimate' => 1000]);
        $this->municipality([], ['population_estimate' => 3000]);

        $this->assertSame(4000.0, (new StateIndicatorService)->stateValue(StrategyIndicator::POPULATION_ESTIMATE));
    }

    public function test_percentages_are_weighted_by_the_base_population(): void
    {
        // 10% de 1.000 e 50% de 9.000: a média simples seria 30%, a ponderada é 46%.
        $this->municipality([], ['religion_evangelical_2022' => 100, 'population_10_plus_2022' => 1000, 'religion_evangelical_pct_2022' => 10]);
        $this->municipality([], ['religion_evangelical_2022' => 4500, 'population_10_plus_2022' => 9000, 'religion_evangelical_pct_2022' => 50]);

        $this->assertSame(46.0, (new StateIndicatorService)->stateValue(StrategyIndicator::RELIGION_EVANGELICAL_PCT_2022));
    }

    public function test_growth_density_and_pib_use_ratios_of_sums(): void
    {
        $this->municipality([], ['population_census_2022' => 1000, 'population_estimate' => 1100, 'territorial_area' => 10, 'pib_per_capita' => 20000]);
        $this->municipality([], ['population_census_2022' => 3000, 'population_estimate' => 3300, 'territorial_area' => 30, 'pib_per_capita' => 40000]);

        $service = new StateIndicatorService;

        $this->assertSame(10.0, $service->stateValue(StrategyIndicator::POPULATION_GROWTH));
        $this->assertSame(100.0, $service->stateValue(StrategyIndicator::DEMOGRAPHIC_DENSITY));
        // Ponderado pela população estimada: (1100 × 20.000 + 3300 × 40.000) ÷ 4400.
        $this->assertSame(35000.0, $service->stateValue(StrategyIndicator::PIB_PER_CAPITA));
    }

    public function test_variation_is_the_difference_between_census_percentages(): void
    {
        $this->municipality([], [
            'religion_evangelical_2022' => 300, 'population_10_plus_2022' => 1000,
            'religion_evangelical_2010' => 200, 'population_2010' => 1000,
        ]);

        $this->assertSame(10.0, (new StateIndicatorService)->stateValue(StrategyIndicator::RELIGION_EVANGELICAL_VARIATION));
    }

    public function test_missing_indicators_return_null_instead_of_zero(): void
    {
        $this->municipality();

        $service = new StateIndicatorService;

        $this->assertNull($service->stateValue(StrategyIndicator::POPULATION_ESTIMATE));
        $this->assertNull($service->stateValue(StrategyIndicator::RELIGION_EVANGELICAL_PCT_2022));
        $this->assertNull($service->lastSyncedAt());
        $this->assertFalse($service->hasAnyData());
    }

    public function test_latest_period_wins_and_sync_dates_are_tracked(): void
    {
        $municipality = $this->municipality();
        $this->indicator($municipality, StrategyIndicator::POPULATION_ESTIMATE, 1000, '2024')
            ->forceFill(['fetched_at' => Carbon::parse('2026-01-10 08:00')])->save();
        $this->indicator($municipality, StrategyIndicator::POPULATION_ESTIMATE, 1200, '2025')
            ->forceFill(['fetched_at' => Carbon::parse('2026-10-01 09:30')])->save();

        $service = new StateIndicatorService;

        $this->assertSame('1200.00', (string) $service->valueFor($municipality, StrategyIndicator::POPULATION_ESTIMATE)?->value);
        $this->assertSame(['2025', '2024'], $service->periodsFor(StrategyIndicator::POPULATION_ESTIMATE));
        $this->assertSame('2026-10-01 09:30', $service->lastSyncedAt(StrategyIndicator::POPULATION_ESTIMATE)?->format('Y-m-d H:i'));
    }
}
