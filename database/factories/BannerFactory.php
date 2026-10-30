<?php

namespace Database\Factories;

use App\Models\Banner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Banner>
 */
class BannerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'title' => fake()->sentence(4),
            'tag' => 'Assembleia',
            'subtitle' => 'Quatro dias de louvor, palavra e comunhão para toda a convenção.',
            'meta' => '14–15 mar · Anápolis',
            'image' => 'banners/'.fake()->uuid().'.jpg',
            'alt_text' => 'Encontro de Missões Nacionais 2026, de 14 a 16 de novembro, em Goiânia. Inscrições abertas.',
            'link_url' => '/eventos',
            'link_label' => 'Saiba mais',
            'opens_in_new_tab' => false,
            'starts_at' => null,
            'ends_at' => null,
            'is_active' => true,
            'is_square' => true,
        ];
    }

    public function externalLink(): static
    {
        return $this->state(fn (): array => [
            'link_url' => 'https://www.convencaobatistanacional.com.br',
            'opens_in_new_tab' => true,
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (): array => [
            'starts_at' => now()->addDays(5),
            'ends_at' => now()->addDays(20),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'starts_at' => now()->subDays(30),
            'ends_at' => now()->subDay(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
