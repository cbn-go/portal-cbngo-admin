<?php

namespace Database\Factories;

use App\Enums\JubanPaymentMethod;
use App\Enums\JubanTransactionType;
use App\Models\JubanCategory;
use App\Models\JubanTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JubanTransaction>
 */
class JubanTransactionFactory extends Factory
{
    protected $model = JubanTransaction::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => fake()->randomElement(JubanTransactionType::cases()),
            'amount' => fake()->randomFloat(2, 10, 5000),
            'occurred_on' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'category_id' => JubanCategory::factory(),
            'description' => fake()->sentence(4),
            'payment_method' => fake()->randomElement(JubanPaymentMethod::cases()),
            'document_ref' => fake()->optional()->numerify('DOC-#####'),
            'notes' => fake()->optional()->paragraph(),
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function receita(): static
    {
        return $this->state(fn (): array => [
            'type' => JubanTransactionType::RECEITA,
            'category_id' => JubanCategory::factory()->receita(),
        ]);
    }

    public function despesa(): static
    {
        return $this->state(fn (): array => [
            'type' => JubanTransactionType::DESPESA,
            'category_id' => JubanCategory::factory()->despesa(),
        ]);
    }
}
