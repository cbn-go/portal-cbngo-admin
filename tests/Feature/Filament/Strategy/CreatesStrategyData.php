<?php

namespace Tests\Feature\Filament\Strategy;

use App\Enums\StrategyIndicator;
use App\Models\Municipality;
use App\Models\MunicipalityIndicator;

trait CreatesStrategyData
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, float>  $indicators  valor por `StrategyIndicator::value`
     */
    protected function municipality(array $attributes = [], array $indicators = []): Municipality
    {
        $municipality = Municipality::factory()->create($attributes);

        foreach ($indicators as $key => $value) {
            $this->indicator($municipality, StrategyIndicator::from($key), $value);
        }

        return $municipality;
    }

    protected function indicator(Municipality $municipality, StrategyIndicator $indicator, float $value, ?string $period = null): MunicipalityIndicator
    {
        return MunicipalityIndicator::factory()->create([
            'municipality_id' => $municipality->id,
            'indicator' => $indicator,
            'period' => $period ?? match ($indicator) {
                StrategyIndicator::POPULATION_ESTIMATE,
                StrategyIndicator::POPULATION_GROWTH,
                StrategyIndicator::NON_EVANGELICAL_POPULATION_ESTIMATE => '2025',
                StrategyIndicator::PIB_PER_CAPITA => '2023',
                StrategyIndicator::POPULATION_2010,
                StrategyIndicator::RELIGION_EVANGELICAL_2010,
                StrategyIndicator::RELIGION_BAPTIST_2010,
                StrategyIndicator::RELIGION_EVANGELICAL_PCT_2010,
                StrategyIndicator::RELIGION_BAPTIST_PCT_2010 => '2010',
                default => '2022',
            },
            'value' => $value,
            'source' => $indicator->source(),
            'fetched_at' => now(),
        ]);
    }
}
