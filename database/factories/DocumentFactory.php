<?php

namespace Database\Factories;

use App\Enums\DocumentCategory;
use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'slug' => fake()->unique()->slug(),
            'description' => fake()->sentence(),
            'category' => fake()->randomElement(DocumentCategory::cases()),
            'file' => 'documents/files/'.fake()->uuid().'.pdf',
            'external_url' => null,
            'is_active' => true,
        ];
    }

    public function externalLink(): static
    {
        return $this->state(fn (): array => [
            'file' => null,
            'external_url' => fake()->url(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
