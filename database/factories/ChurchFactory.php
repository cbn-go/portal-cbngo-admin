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
            'latitude' => (string) fake()->latitude(-19.5, -12.4),
            'longitude' => (string) fake()->longitude(-53.2, -45.9),
            'phone' => null,
            'cellphone' => null,
            'email' => fake()->optional()->safeEmail(),
            'social_links' => null,
            'service_times' => [
                ['day' => 'Domingo', 'time' => '19h00', 'label' => 'Culto de Celebração'],
                ['day' => 'Quarta-feira', 'time' => '20h00', 'label' => 'Reunião de Oração'],
            ],
            'logo' => null,
            'is_active' => true,
        ];
    }
}
