<?php

namespace Tests\Feature\Filament;

use App\Enums\DocumentCategory;
use App\Enums\UserRole;
use App\Filament\Resources\DocumentResource\Pages\CreateDocument;
use App\Filament\Resources\DocumentResource\Pages\EditDocument;
use App\Filament\Resources\DocumentResource\Pages\ListDocuments;
use App\Models\Document;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class DocumentResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'is_active' => true,
        ]);
    }

    public function test_super_admin_can_access_document_pages(): void
    {
        $document = Document::factory()->create();

        $this->actingAs($this->superAdmin)->get('/admin/documents')->assertSuccessful();
        $this->actingAs($this->superAdmin)->get('/admin/documents/create')->assertSuccessful();
        $this->actingAs($this->superAdmin)->get("/admin/documents/{$document->id}/edit")->assertSuccessful();
    }

    public function test_other_roles_and_inactive_users_cannot_access_documents(): void
    {
        $document = Document::factory()->create();

        $users = [
            User::factory()->create(['role' => UserRole::CONVENTION_EDITOR, 'is_active' => true]),
            User::factory()->create(['role' => UserRole::AUTHOR, 'is_active' => true]),
            User::factory()->create(['role' => UserRole::CHURCH_REPRESENTATIVE, 'is_active' => true]),
            User::factory()->create(['role' => UserRole::SUPER_ADMIN, 'is_active' => false]),
        ];

        foreach ($users as $user) {
            $this->actingAs($user)->get('/admin/documents')->assertForbidden();
            $this->actingAs($user)->get("/admin/documents/{$document->id}/edit")->assertForbidden();
        }
    }

    public function test_super_admin_can_upload_pdf_document(): void
    {
        Storage::fake('public');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superAdmin);

        Livewire::test(CreateDocument::class)
            ->fillForm([
                'title' => 'Estatuto da CBN-GO',
                'slug' => 'estatuto-da-cbn-go',
                'category' => DocumentCategory::ESTATUTO->value,
                'description' => 'Estatuto social vigente.',
                'file' => UploadedFile::fake()->create('estatuto.pdf', 300, 'application/pdf'),
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $document = Document::query()->where('slug', 'estatuto-da-cbn-go')->firstOrFail();

        $this->assertSame(DocumentCategory::ESTATUTO, $document->category);
        $this->assertSame('PDF', $document->file_format);
        $this->assertNull($document->external_url);
        $this->assertNotNull($document->file);
        $this->assertStringStartsWith('documents/files/', $document->file);
        Storage::disk('public')->assertExists($document->file);
    }

    public function test_super_admin_can_upload_docx_document(): void
    {
        Storage::fake('public');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superAdmin);

        Livewire::test(CreateDocument::class)
            ->fillForm([
                'title' => 'Ata da Assembleia 2026',
                'slug' => 'ata-da-assembleia-2026',
                'category' => DocumentCategory::ATAS->value,
                'file' => UploadedFile::fake()->create(
                    'ata.docx',
                    120,
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                ),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('DOCX', Document::query()->where('slug', 'ata-da-assembleia-2026')->value('file_format'));
    }

    public function test_super_admin_can_register_external_link_document(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superAdmin);

        Livewire::test(CreateDocument::class)
            ->fillForm([
                'title' => 'Pacto Renovado',
                'slug' => 'pacto-renovado',
                'category' => DocumentCategory::PACTO->value,
                'external_url' => 'https://cbngo.com.br/pacto-renovado/',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $document = Document::query()->where('slug', 'pacto-renovado')->firstOrFail();

        $this->assertNull($document->file);
        $this->assertSame('Página', $document->file_format);
    }

    public function test_document_requires_file_or_external_link(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superAdmin);

        Livewire::test(CreateDocument::class)
            ->fillForm([
                'title' => 'Documento sem arquivo',
                'slug' => 'documento-sem-arquivo',
                'category' => DocumentCategory::OUTROS->value,
            ])
            ->call('create')
            ->assertHasFormErrors(['file' => 'required', 'external_url' => 'required']);
    }

    public function test_document_cannot_have_file_and_external_link(): void
    {
        Storage::fake('public');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superAdmin);

        Livewire::test(CreateDocument::class)
            ->fillForm([
                'title' => 'Documento duplicado',
                'slug' => 'documento-duplicado',
                'category' => DocumentCategory::OUTROS->value,
                'file' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
                'external_url' => 'https://cbngo.com.br/doc.pdf',
            ])
            ->call('create')
            ->assertHasFormErrors(['external_url']);

        $this->assertDatabaseMissing('documents', ['slug' => 'documento-duplicado']);
    }

    public function test_cannot_upload_invalid_file_type(): void
    {
        Storage::fake('public');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superAdmin);

        Livewire::test(CreateDocument::class)
            ->fillForm([
                'title' => 'Imagem não é documento',
                'slug' => 'imagem-nao-e-documento',
                'category' => DocumentCategory::OUTROS->value,
                'file' => UploadedFile::fake()->image('foto.png'),
            ])
            ->call('create')
            ->assertHasFormErrors(['file']);

        $this->assertDatabaseMissing('documents', ['slug' => 'imagem-nao-e-documento']);
    }

    public function test_slug_must_be_unique(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superAdmin);

        Document::factory()->create(['slug' => 'regimento-interno']);

        Livewire::test(CreateDocument::class)
            ->fillForm([
                'title' => 'Regimento Interno',
                'slug' => 'regimento-interno',
                'category' => DocumentCategory::REGIMENTO->value,
                'external_url' => 'https://cbngo.com.br/regimento.pdf',
            ])
            ->call('create')
            ->assertHasFormErrors(['slug' => 'unique']);
    }

    public function test_switching_from_file_to_link_updates_format(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superAdmin);

        $document = Document::factory()->create();
        $this->assertSame('PDF', $document->file_format);

        Livewire::test(EditDocument::class, ['record' => $document->getKey()])
            ->fillForm(['file' => null, 'external_url' => 'https://cbngo.com.br/documento'])
            ->call('save')
            ->assertHasNoFormErrors();

        $document->refresh();
        $this->assertNull($document->file);
        $this->assertSame('Página', $document->file_format);
    }

    public function test_table_is_sorted_by_position_and_filterable_by_category(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superAdmin);

        $estatuto = Document::factory()->create(['category' => DocumentCategory::ESTATUTO]);
        $ata = Document::factory()->create(['category' => DocumentCategory::ATAS]);

        $this->assertSame(1, $estatuto->position);
        $this->assertSame(2, $ata->position);

        Livewire::test(ListDocuments::class)
            ->assertCanSeeTableRecords([$estatuto, $ata], inOrder: true)
            ->filterTable('category', DocumentCategory::ATAS->value)
            ->assertCanSeeTableRecords([$ata])
            ->assertCanNotSeeTableRecords([$estatuto]);

        Livewire::test(ListDocuments::class)
            ->call('reorderTable', [(string) $ata->id, (string) $estatuto->id]);

        $this->assertSame(1, $ata->refresh()->position);
        $this->assertSame(2, $estatuto->refresh()->position);
    }
}
