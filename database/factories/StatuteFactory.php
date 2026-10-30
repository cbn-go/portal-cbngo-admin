<?php

namespace Database\Factories;

use App\Enums\PublishStatus;
use App\Models\Statute;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Statute>
 */
class StatuteFactory extends Factory
{
    protected $model = Statute::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $canonicalXml = file_get_contents(database_path('seeders/data/estatuto-2025-10-18.akn.xml'));

        return [
            'title' => 'Estatuto da Convenção Batista Nacional do Estado de Goiás',
            'version_label' => fake()->unique()->numerify('20##.#'),
            'approved_at' => fake()->date(),
            'approved_place' => fake()->city().', GO',
            'approval_body' => 'Assembleia Geral',
            'minutes_reference' => 'Ata da Assembleia Geral',
            'registry_info' => 'Cartório de Pessoas Jurídicas',
            'effective_at' => fake()->date(),
            'status' => PublishStatus::DRAFT,
            'akn_xml' => $canonicalXml,
            'original_pdf' => 'statutes/04-estatuto-cbngo-atualizado.pdf',
            'published_by' => null,
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => PublishStatus::PUBLISHED,
            'published_by' => User::factory(),
            'published_at' => now(),
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => [
            'status' => PublishStatus::ARCHIVED,
            'published_at' => now()->subYear(),
        ]);
    }
}
