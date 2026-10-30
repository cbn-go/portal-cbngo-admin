<?php

namespace Tests\Feature\Filament;

use App\Enums\PublishStatus;
use App\Enums\UserRole;
use App\Filament\Resources\StatuteResource\Pages\CreateStatute;
use App\Filament\Resources\StatuteResource\Pages\EditStatute;
use App\Filament\Resources\StatuteResource\Pages\ListStatutes;
use App\Models\Statute;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StatuteResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $conventionEditor;

    private string $canonicalXml;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'is_active' => true,
        ]);

        $this->conventionEditor = User::factory()->create([
            'role' => UserRole::CONVENTION_EDITOR,
            'is_active' => true,
        ]);

        $this->canonicalXml = file_get_contents(database_path('seeders/data/estatuto-2025-10-18.akn.xml'));
    }

    private function createPublishedStatute(): Statute
    {
        return Statute::create([
            'title' => 'Estatuto da Convenção Batista Nacional do Estado de Goiás',
            'version_label' => '2025-10-18',
            'approved_at' => '2025-10-18',
            'approved_place' => 'Anápolis, GO',
            'approval_body' => 'Assembleia Geral Ordinária',
            'effective_at' => '2025-10-18',
            'status' => PublishStatus::PUBLISHED,
            'akn_xml' => $this->canonicalXml,
            'created_by' => $this->superAdmin->id,
            'published_by' => $this->superAdmin->id,
            'published_at' => now(),
        ]);
    }

    public function test_super_admin_and_convention_editor_can_access_statute_pages(): void
    {
        $statute = $this->createPublishedStatute();

        foreach ([$this->superAdmin, $this->conventionEditor] as $user) {
            $this->actingAs($user)->get('/admin/statutes')->assertSuccessful();
            $this->actingAs($user)->get('/admin/statutes/create')->assertSuccessful();
            $this->actingAs($user)->get("/admin/statutes/{$statute->id}/edit")->assertSuccessful();
        }
    }

    public function test_unauthorized_roles_and_inactive_users_cannot_access(): void
    {
        $statute = $this->createPublishedStatute();

        $unauthorized = [
            User::factory()->create(['role' => UserRole::AUTHOR, 'is_active' => true]),
            User::factory()->create(['role' => UserRole::CHURCH_REPRESENTATIVE, 'is_active' => true]),
            User::factory()->create(['role' => UserRole::SUPER_ADMIN, 'is_active' => false]),
        ];

        foreach ($unauthorized as $user) {
            $this->actingAs($user)->get('/admin/statutes')->assertForbidden();
            $this->actingAs($user)->get('/admin/statutes/create')->assertForbidden();
            $this->actingAs($user)->get("/admin/statutes/{$statute->id}/edit")->assertForbidden();
        }
    }

    public function test_statute_table_renders_and_lists_records(): void
    {
        $statute = $this->createPublishedStatute();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superAdmin);

        Livewire::test(ListStatutes::class)
            ->assertCanSeeTableRecords([$statute])
            ->assertCountTableRecords(1)
            ->assertSee($statute->version_label);
    }

    public function test_can_create_statute_via_structured_form(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superAdmin);

        Livewire::test(CreateStatute::class)
            ->fillForm([
                'title' => 'Estatuto Teste CBN-GO',
                'version_label' => '2026-v1-teste',
                'approved_at' => '2026-10-30',
                'approval_body' => 'Assembleia Geral Ordinária',
                'approved_place' => 'Anápolis, GO',
                'effective_at' => '2026-10-30',
                'chapters' => [
                    [
                        'num' => 'CAPÍTULO I',
                        'heading' => 'Da Denominação e Fins',
                        'articles' => [
                            [
                                'num' => 'Art. 1º',
                                'heading' => null,
                                'intro' => 'A Convenção Batista Nacional do Estado de Goiás é uma entidade religiosa.',
                                'paragraphs' => [],
                                'points' => [],
                            ],
                        ],
                        'sections' => [],
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $statute = Statute::where('version_label', '2026-v1-teste')->firstOrFail();
        $this->assertSame(PublishStatus::DRAFT, $statute->status);
        $this->assertNotEmpty($statute->akn_xml);
        $this->assertStringContainsString('Art. 1º', $statute->akn_xml);
        $this->assertSame($this->superAdmin->id, $statute->created_by);
    }

    public function test_can_edit_statute_via_structured_form(): void
    {
        $statute = Statute::create([
            'title' => 'Estatuto CBN-GO',
            'version_label' => '2026-draft-edit',
            'approved_at' => '2026-10-30',
            'approved_place' => 'Anápolis, GO',
            'approval_body' => 'Assembleia Geral Ordinária',
            'effective_at' => '2026-10-30',
            'status' => PublishStatus::DRAFT,
            'akn_xml' => $this->canonicalXml,
            'created_by' => $this->superAdmin->id,
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superAdmin);

        $component = Livewire::test(EditStatute::class, ['record' => $statute->id]);
        $formData = $component->get('data');

        $this->assertNotEmpty($formData['chapters']);
        $firstChapterKey = array_key_first($formData['chapters']);
        $this->assertSame('CAPÍTULO I', $formData['chapters'][$firstChapterKey]['num']);

        // Modifica caput do primeiro artigo
        $firstArticleKey = array_key_first($formData['chapters'][$firstChapterKey]['articles']);
        $formData['chapters'][$firstChapterKey]['articles'][$firstArticleKey]['intro'] = 'Novo caput atualizado pelo teste.';

        $component->fillForm($formData)
            ->call('save')
            ->assertHasNoFormErrors();

        $statute->refresh();
        $this->assertStringContainsString('Novo caput atualizado pelo teste.', $statute->akn_xml);
    }

    public function test_fork_action_creates_draft_with_new_version_label(): void
    {
        $published = $this->createPublishedStatute();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->conventionEditor);

        Livewire::test(ListStatutes::class)
            ->callTableAction('fork', $published, [
                'new_version_label' => '2026-reforma-draft',
            ])
            ->assertHasNoTableActionErrors();

        $draft = Statute::where('version_label', '2026-reforma-draft')->firstOrFail();
        $this->assertSame(PublishStatus::DRAFT, $draft->status);
        $this->assertSame($published->akn_xml, $draft->akn_xml);
        $this->assertSame($this->conventionEditor->id, $draft->created_by);
    }

    public function test_super_admin_can_publish_and_archives_previous_version(): void
    {
        $oldPublished = $this->createPublishedStatute();

        $draft = Statute::create([
            'title' => 'Estatuto CBN-GO Nova Reforma',
            'version_label' => '2026-v2',
            'approved_at' => '2026-10-30',
            'approved_place' => 'Goiânia, GO',
            'approval_body' => 'Assembleia Geral Extraordinária',
            'effective_at' => '2026-10-30',
            'status' => PublishStatus::DRAFT,
            'akn_xml' => $this->canonicalXml,
            'created_by' => $this->superAdmin->id,
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superAdmin);

        Livewire::test(ListStatutes::class)
            ->callTableAction('publish', $draft)
            ->assertHasNoTableActionErrors();

        $draft->refresh();
        $oldPublished->refresh();

        $this->assertSame(PublishStatus::PUBLISHED, $draft->status);
        $this->assertNotNull($draft->published_at);
        $this->assertSame($this->superAdmin->id, $draft->published_by);

        // Versão anterior deve ter sido arquivada
        $this->assertSame(PublishStatus::ARCHIVED, $oldPublished->status);
    }

    public function test_convention_editor_cannot_publish_statute(): void
    {
        $draft = Statute::create([
            'title' => 'Estatuto CBN-GO',
            'version_label' => '2026-draft-forbidden',
            'approved_at' => '2026-10-30',
            'approved_place' => 'Goiânia, GO',
            'approval_body' => 'Assembleia Geral',
            'effective_at' => '2026-10-30',
            'status' => PublishStatus::DRAFT,
            'akn_xml' => $this->canonicalXml,
            'created_by' => $this->conventionEditor->id,
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->conventionEditor);

        Livewire::test(ListStatutes::class)
            ->assertTableActionHidden('publish', $draft);
    }

    public function test_cannot_delete_published_statute(): void
    {
        $published = $this->createPublishedStatute();

        $this->assertFalse($this->superAdmin->can('delete', $published));
    }

    public function test_download_xml_action_returns_stream(): void
    {
        $statute = $this->createPublishedStatute();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superAdmin);

        Livewire::test(ListStatutes::class)
            ->callTableAction('download_xml', $statute)
            ->assertFileDownloaded("estatuto-cbngo-{$statute->version_label}.akn.xml");
    }

    public function test_super_admin_can_import_akn_xml(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superAdmin);

        Livewire::test(ListStatutes::class)
            ->callAction('import_xml', [
                'version_label' => '2026-xml-importado',
                'xml_content' => $this->canonicalXml,
            ])
            ->assertHasNoActionErrors();

        $imported = Statute::where('version_label', '2026-xml-importado')->firstOrFail();
        $this->assertSame(PublishStatus::DRAFT, $imported->status);
        $this->assertSame(trim($this->canonicalXml), trim($imported->akn_xml));
    }
}
