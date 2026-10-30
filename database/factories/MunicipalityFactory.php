<?php

namespace Database\Factories;

use App\Models\Municipality;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Municipality>
 */
class MunicipalityFactory extends Factory
{
    protected $model = Municipality::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->city();

        return [
            'ibge_code' => (string) fake()->unique()->numerify('52#####'),
            'name' => $name,
            'slug' => Str::slug($name),
            'normalized_name' => Municipality::normalizeName($name),
            'immediate_region_code' => (string) fake()->numerify('5200##'),
            'immediate_region_name' => 'Goiânia',
            'intermediate_region_code' => (string) fake()->numerify('52##'),
            'intermediate_region_name' => 'Goiânia',
            'micro_region_name' => 'Goiânia',
            'meso_region_name' => 'Centro Goiano',
        ];
    }
}
