<?php

namespace Database\Factories;

use App\Enums\JubanCategoryKind;
use App\Models\JubanCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<JubanCategory>
 */
class JubanCategoryFactory extends Factory
{
    protected $model = JubanCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => ucfirst($name),
            'code' => Str::slug($name).'-'.fake()->unique()->numberBetween(100, 999),
            'kind' => fake()->randomElement(JubanCategoryKind::cases()),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }

    public function receita(): static
    {
        return $this->state(fn (): array => ['kind' => JubanCategoryKind::RECEITA]);
    }

    public function despesa(): static
    {
        return $this->state(fn (): array => ['kind' => JubanCategoryKind::DESPESA]);
    }

    public function ambos(): static
    {
        return $this->state(fn (): array => ['kind' => JubanCategoryKind::AMBOS]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
