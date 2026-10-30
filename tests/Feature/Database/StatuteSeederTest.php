<?php

namespace Tests\Feature\Database;

use App\Enums\PublishStatus;
use App\Models\Statute;
use Database\Seeders\StatuteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatuteSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_published_statute_version(): void
    {
        $this->seed(StatuteSeeder::class);

        $statute = Statute::query()->where('version_label', '2025-10-18')->first();

        $this->assertNotNull($statute);
        $this->assertSame('Estatuto da Convenção Batista Nacional do Estado de Goiás', $statute->title);
        $this->assertSame(PublishStatus::PUBLISHED, $statute->status);
        $this->assertSame('2025-10-18', $statute->approved_at->format('Y-m-d'));
        $this->assertSame('Anápolis, GO', $statute->approved_place);
        $this->assertSame('Assembleia Geral', $statute->approval_body);
        $this->assertStringContainsString('Cartório de Pessoas Jurídicas', $statute->registry_info);
        $this->assertSame('2025-10-18', $statute->effective_at->format('Y-m-d'));
        $this->assertSame('statutes/04-estatuto-cbngo-atualizado.pdf', $statute->original_pdf);

        // Verifica a contagem da árvore do AKN
        $structure = $statute->structure;
        $this->assertCount(6, $structure->chapters);
        $this->assertSame(21, count($structure->allArticles()));
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(StatuteSeeder::class);
        $this->seed(StatuteSeeder::class);

        $this->assertSame(1, Statute::query()->count());
    }
}
