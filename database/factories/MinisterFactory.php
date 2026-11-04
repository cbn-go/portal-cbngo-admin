<?php

namespace Database\Factories;

use App\Models\Minister;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Minister>
 */
class MinisterFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'registration_number' => (string) fake()->unique()->numberBetween(1000, 9999),
            'function' => 'Pastor',
            'name' => fake()->name('male'),
            'status' => 'ATIVO(A)',
            'sectional' => 'ORMIBAN GO',
            'church_name' => 'Igreja Batista '.fake()->city(),
            'address' => fake()->streetAddress(),
            'zip_code' => '74000-000',
            'phone' => '(62) 3000-0000',
            'email' => fake()->optional()->safeEmail(),
            'cpf' => str_pad((string) fake()->unique()->numerify('###########'), 11, '0', STR_PAD_LEFT),
            'birth_date' => fake()->date('Y-m-d', '-30 years'),
            'nationality' => 'Brasileira',
            'place_of_birth' => fake()->city().' GO',
            'education_level' => 'Superior Completo',
            'marital_status' => 'Casado(a)',
            'gender' => 'Masculino',
            'profession' => 'Pastor',
            'identity_document' => (string) fake()->numberBetween(100000, 999999),
            'identity_issuer' => 'SSP/GO',
            'passport' => null,
            'identity_issued_at' => null,
            'other_phones' => null,
            'occupational_roles' => null,
            'registered_at' => now()->subYears(5),
            'source_updated_at' => now(),
            'church_id' => null,
            'user_id' => null,
        ];
    }
}
