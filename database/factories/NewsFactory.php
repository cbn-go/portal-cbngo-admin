<?php

namespace Database\Factories;

use App\Enums\PublishStatus;
use App\Models\Church;
use App\Models\News;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<News>
 */
class NewsFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'church_id' => Church::factory(),
            'user_id' => User::factory(),
            'title' => fake()->sentence(),
            'slug' => fake()->unique()->slug(),
            'excerpt' => fake()->optional()->paragraph(),
            'content' => fake()->paragraphs(3, true),
            'featured_image' => null,
            'gallery' => null,
            'event_date' => null,
            'is_official' => false,
            'status' => PublishStatus::DRAFT,
            'published_at' => null,
        ];
    }
}
