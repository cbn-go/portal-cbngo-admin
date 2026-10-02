<?php

namespace Database\Factories;

use App\Models\AuthorProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuthorProfile>
 */
class AuthorProfileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'pastoral_title' => 'Pr.',
            'bio' => fake()->optional()->paragraph(),
            'avatar' => null,
            'social_links' => null,
        ];
    }
}
