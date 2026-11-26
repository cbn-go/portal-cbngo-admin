<?php

namespace Database\Factories;

use App\Enums\FiscalCouncilSeat;
use App\Models\FiscalCouncilMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FiscalCouncilMember>
 */
class FiscalCouncilMemberFactory extends Factory
{
    protected $model = FiscalCouncilMember::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'seat' => fake()->randomElement(FiscalCouncilSeat::cases()),
            'title' => fake()->randomElement(['Pr.', 'Dc.', 'Ir.', 'Sr.', 'Sra.']),
            'qualification' => fake()->randomElement(['Contador', 'Técnico em contabilidade', 'Administrador', 'Membro da Igreja']),
            'term_starts_on' => '2026-01-01',
            'term_ends_on' => '2028-12-31',
            'active' => true,
        ];
    }

    public function relator(): static
    {
        return $this->state(fn () => [
            'seat' => FiscalCouncilSeat::RELATOR,
            'title' => 'Pr.',
            'qualification' => 'Relator do Conselho Fiscal',
        ]);
    }

    public function vogal1(): static
    {
        return $this->state(fn () => [
            'seat' => FiscalCouncilSeat::VOGAL_1,
            'title' => 'Dc.',
            'qualification' => '1º Vogal',
        ]);
    }

    public function vogal2(): static
    {
        return $this->state(fn () => [
            'seat' => FiscalCouncilSeat::VOGAL_2,
            'title' => 'Sra.',
            'qualification' => '2ª Vogal',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'active' => false,
        ]);
    }
}
