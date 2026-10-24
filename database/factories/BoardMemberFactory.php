<?php

namespace Database\Factories;

use App\Enums\BoardGroup;
use App\Models\BoardMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BoardMember>
 */
class BoardMemberFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Pr. '.fake()->name(),
            'role' => fake()->randomElement(['Presidente', '1º Secretário', 'Tesoureiro', 'Vogal']),
            'group' => fake()->randomElement(BoardGroup::cases()),
            'region' => null,
            'term_label' => 'Gestão 2025–2027',
            'photo' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
