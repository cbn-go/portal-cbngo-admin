<?php

namespace Database\Factories;

use App\Enums\StrategyIndicator;
use App\Models\Municipality;
use App\Models\MunicipalityIndicator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MunicipalityIndicator>
 */
class MunicipalityIndicatorFactory extends Factory
{
    protected $model = MunicipalityIndicator::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'municipality_id' => Municipality::factory(),
            'indicator' => StrategyIndicator::POPULATION_CENSUS_2022,
            'period' => '2022',
            'value' => fake()->randomFloat(2, 1000, 500000),
            'source' => 'IBGE/SIDRA 9514',
            'fetched_at' => now(),
        ];
    }
}
