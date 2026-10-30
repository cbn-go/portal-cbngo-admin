<?php

namespace Tests\Feature\Models;

use App\DTOs\Statute\StatuteStructure;
use App\Enums\PublishStatus;
use App\Enums\UserRole;
use App\Exceptions\AknValidationException;
use App\Models\Statute;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatuteTest extends TestCase
{
    use RefreshDatabase;

    private string $validXml;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validXml = file_get_contents(database_path('seeders/data/estatuto-2025-10-18.akn.xml'));
    }

    public function test_can_create_valid_statute(): void
    {
        $statute = Statute::create([
            'title' => 'Estatuto da CBN-GO',
            'version_label' => '2025-10-18',
            'approved_at' => '2025-10-18',
            'approved_place' => 'Anápolis, GO',
            'approval_body' => 'Assembleia Geral',
            'minutes_reference' => 'Ata da AGE',
            'registry_info' => 'Cartório de Pessoas Jurídicas',
            'effective_at' => '2025-10-18',
            'status' => PublishStatus::PUBLISHED,
            'akn_xml' => $this->validXml,
            'original_pdf' => 'statutes/04-estatuto-cbngo-atualizado.pdf',
        ]);

        $this->assertDatabaseHas('statutes', [
            'id' => $statute->id,
            'version_label' => '2025-10-18',
            'status' => 'published',
        ]);

        $this->assertInstanceOf(PublishStatus::class, $statute->status);
        $this->assertSame(PublishStatus::PUBLISHED, $statute->status);
        $this->assertSame('2025-10-18', $statute->approved_at->format('Y-m-d'));
        $this->assertSame('2025-10-18', $statute->effective_at->format('Y-m-d'));
        $this->assertNotNull($statute->published_at);
    }

    public function test_cannot_save_statute_with_invalid_akn_xml(): void
    {
        $this->expectException(AknValidationException::class);

        Statute::create([
            'title' => 'Estatuto Inválido',
            'version_label' => 'invalid-v1',
            'approved_at' => '2025-10-18',
            'approved_place' => 'Anápolis, GO',
            'approval_body' => 'Assembleia Geral',
            'effective_at' => '2025-10-18',
            'status' => PublishStatus::DRAFT,
            'akn_xml' => '<invalid>XML</invalid>',
        ]);
    }

    public function test_publishing_statute_archives_previously_published_version(): void
    {
        $v1 = Statute::create([
            'title' => 'Estatuto da CBN-GO',
            'version_label' => 'v1',
            'approved_at' => '2024-01-01',
            'approved_place' => 'Goiânia, GO',
            'approval_body' => 'AG',
            'effective_at' => '2024-01-01',
            'status' => PublishStatus::PUBLISHED,
            'akn_xml' => $this->validXml,
        ]);

        $this->assertSame(PublishStatus::PUBLISHED, $v1->fresh()->status);

        // Publica versão v2
        $v2 = Statute::create([
            'title' => 'Estatuto da CBN-GO',
            'version_label' => 'v2',
            'approved_at' => '2025-10-18',
            'approved_place' => 'Anápolis, GO',
            'approval_body' => 'AG',
            'effective_at' => '2025-10-18',
            'status' => PublishStatus::PUBLISHED,
            'akn_xml' => $this->validXml,
        ]);

        $this->assertSame(PublishStatus::ARCHIVED, $v1->fresh()->status);
        $this->assertSame(PublishStatus::PUBLISHED, $v2->fresh()->status);

        // Apenas 1 versão com status published
        $this->assertSame(1, Statute::published()->count());
    }

    public function test_statute_publish_method_sets_publisher_and_status(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SUPER_ADMIN]);

        $statute = Statute::create([
            'title' => 'Estatuto Rascunho',
            'version_label' => 'draft-1',
            'approved_at' => '2025-10-18',
            'approved_place' => 'Anápolis, GO',
            'approval_body' => 'AG',
            'effective_at' => '2025-10-18',
            'status' => PublishStatus::DRAFT,
            'akn_xml' => $this->validXml,
        ]);

        $this->assertSame(PublishStatus::DRAFT, $statute->status);
        $this->assertNull($statute->published_at);

        $statute->publish($admin);

        $fresh = $statute->fresh();
        $this->assertSame(PublishStatus::PUBLISHED, $fresh->status);
        $this->assertSame($admin->id, $fresh->published_by);
        $this->assertNotNull($fresh->published_at);
    }

    public function test_statute_structure_accessor_returns_parsed_dto(): void
    {
        $statute = Statute::create([
            'title' => 'Estatuto da CBN-GO',
            'version_label' => '2025-10-18',
            'approved_at' => '2025-10-18',
            'approved_place' => 'Anápolis, GO',
            'approval_body' => 'AG',
            'effective_at' => '2025-10-18',
            'status' => PublishStatus::PUBLISHED,
            'akn_xml' => $this->validXml,
        ]);

        $structure = $statute->structure;
        $this->assertInstanceOf(StatuteStructure::class, $structure);
        $this->assertCount(6, $structure->chapters);
    }
}
