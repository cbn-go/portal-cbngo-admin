<?php

namespace Database\Factories;

use App\Enums\BoardGroup;
use App\Enums\ClergyTitle;
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
            'title' => ClergyTitle::PASTOR,
            'name' => fake()->name(),
            'role' => fake()->randomElement(['Presidente', '1º Secretário', 'Tesoureiro', 'Vogal']),
            'group' => fake()->randomElement(array_filter(BoardGroup::cases(), fn (BoardGroup $group): bool => $group !== BoardGroup::COORDENACAO_REGIONAL)),
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
