<?php

namespace Database\Factories;

use App\Enums\NoticePriority;
use App\Models\Notice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notice>
 */
class NoticeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(),
            'slug' => fake()->unique()->slug(),
            'content' => fake()->paragraph(),
            'action_url' => null,
            'action_label' => null,
            'priority' => NoticePriority::NORMAL,
            'starts_at' => null,
            'expires_at' => null,
            'is_active' => true,
        ];
    }
}
