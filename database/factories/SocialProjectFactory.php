<?php

namespace Database\Factories;

use App\Enums\ProjectCategory;
use App\Enums\PublishStatus;
use App\Models\Church;
use App\Models\SocialProject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SocialProject>
 */
class SocialProjectFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'church_id' => Church::factory(),
            'user_id' => null,
            'title' => fake()->sentence(4),
            'slug' => fake()->unique()->slug(),
            'category' => fake()->randomElement(ProjectCategory::cases()),
            'summary' => fake()->sentence(12),
            'description' => '<p>'.fake()->paragraph().'</p>',
            'cover_image' => null,
            'gallery' => null,
            'target_audience' => 'Crianças e adolescentes do bairro',
            'beneficiaries_count' => fake()->numberBetween(10, 300),
            'city' => 'Goiânia',
            'started_at' => now()->subMonths(6)->toDateString(),
            'ended_at' => null,
            'contact_name' => fake()->name(),
            'contact_phone' => '(62) 99999-0000',
            'contact_email' => fake()->safeEmail(),
            'how_to_help' => 'Voluntários aos sábados e doação de alimentos.',
            'status' => PublishStatus::DRAFT,
            'published_at' => null,
            'is_featured' => false,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => PublishStatus::PUBLISHED,
            'published_at' => now()->subDay(),
        ]);
    }

    public function pendingReview(): static
    {
        return $this->state(fn (): array => ['status' => PublishStatus::PENDING_REVIEW]);
    }

    public function ended(): static
    {
        return $this->state(fn (): array => [
            'started_at' => now()->subYear()->toDateString(),
            'ended_at' => now()->subMonth()->toDateString(),
        ]);
    }

    public function featured(): static
    {
        return $this->state(fn (): array => ['is_featured' => true]);
    }
}
