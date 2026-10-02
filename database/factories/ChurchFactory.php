<?php

namespace Database\Factories;

use App\Models\Church;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Church>
 */
class ChurchFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'cnpj' => null,
            'registration_number' => null,
            'pastor_name' => fake()->name(),
            'city' => fake()->city(),
            'state' => 'GO',
            'neighborhood' => null,
            'zip_code' => null,
            'address' => fake()->streetAddress(),
            'number' => null,
            'complement' => null,
            'phone' => null,
            'cellphone' => null,
            'email' => fake()->optional()->safeEmail(),
            'social_links' => null,
            'logo' => null,
            'is_active' => true,
        ];
    }
}
