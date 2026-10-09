<?php

namespace Tests\Feature\Filament;

use App\Enums\PublishStatus;
use App\Enums\UserRole;
use App\Filament\Resources\NewsResource;
use App\Filament\Resources\NewsResource\Pages\CreateNews;
use App\Filament\Resources\NewsResource\Pages\EditNews;
use App\Filament\Resources\NewsResource\Pages\ListNews;
use App\Models\Church;
use App\Models\News;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class NewsResourceTest extends TestCase
{
    use RefreshDatabase;

    private Church $firstChurch;

    private Church $secondChurch;

    private User $superAdmin;

    private User $conventionEditor;

    private User $churchRep;

    private User $secondChurchRep;

    private User $author;

    protected function setUp(): void
    {
        parent::setUp();

        $this->firstChurch = Church::factory()->create(['name' => 'Primeira Igreja Batista Nacional de Goiânia']);
        $this->secondChurch = Church::factory()->create(['name' => 'Segunda Igreja Batista Nacional de Anápolis']);

        $this->superAdmin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'is_active' => true,
        ]);

        $this->conventionEditor = User::factory()->create([
            'role' => UserRole::CONVENTION_EDITOR,
            'is_active' => true,
        ]);

        $this->churchRep = User::factory()->create([
            'role' => UserRole::CHURCH_REPRESENTATIVE,
            'church_id' => $this->firstChurch->id,
            'is_active' => true,
        ]);

        $this->secondChurchRep = User::factory()->create([
            'role' => UserRole::CHURCH_REPRESENTATIVE,
            'church_id' => $this->secondChurch->id,
            'is_active' => true,
        ]);

        $this->author = User::factory()->create([
            'role' => UserRole::AUTHOR,
            'is_active' => true,
        ]);
    }

    public function test_church_representative_can_access_news_pages_on_portal_panel(): void
    {
        $news = News::factory()->create([
            'church_id' => $this->firstChurch->id,
            'user_id' => $this->churchRep->id,
        ]);

        $this->actingAs($this->churchRep)
            ->get('/portal/news')
            ->assertSuccessful();

        $this->actingAs($this->churchRep)
            ->get('/portal/news/create')
            ->assertSuccessful();

        $this->actingAs($this->churchRep)
            ->get("/portal/news/{$news->id}/edit")
            ->assertSuccessful();
    }

    public function test_church_representative_cannot_access_another_church_news_on_portal_panel(): void
    {
        $foreignNews = News::factory()->create([
            'church_id' => $this->secondChurch->id,
            'user_id' => $this->secondChurchRep->id,
        ]);

        $this->actingAs($this->churchRep)
            ->get("/portal/news/{$foreignNews->id}/edit")
            ->assertNotFound();
    }

    public function test_church_representative_without_church_cannot_access_news_pages(): void
    {
        $unlinkedRep = User::factory()->create([
            'role' => UserRole::CHURCH_REPRESENTATIVE,
            'church_id' => null,
            'is_active' => true,
        ]);

        $this->actingAs($unlinkedRep)
            ->get('/portal/news')
            ->assertForbidden();

        $this->actingAs($unlinkedRep)
            ->get('/portal/news/create')
            ->assertForbidden();
    }

    public function test_super_admin_and_convention_editor_can_access_news_pages_on_admin_panel(): void
    {
        $news = News::factory()->create([
            'church_id' => $this->firstChurch->id,
            'user_id' => $this->churchRep->id,
        ]);

        $this->actingAs($this->superAdmin)
            ->get('/admin/news')
            ->assertSuccessful();

        $this->actingAs($this->superAdmin)
            ->get('/admin/news/create')
            ->assertSuccessful();

        $this->actingAs($this->superAdmin)
            ->get("/admin/news/{$news->id}/edit")
            ->assertSuccessful();

        $this->actingAs($this->conventionEditor)
            ->get('/admin/news')
            ->assertSuccessful();

        $this->actingAs($this->conventionEditor)
            ->get('/admin/news/create')
            ->assertSuccessful();

        $this->actingAs($this->conventionEditor)
            ->get("/admin/news/{$news->id}/edit")
            ->assertSuccessful();
    }

    public function test_author_and_inactive_users_cannot_access_news_resource(): void
    {
        $news = News::factory()->create([
            'church_id' => $this->firstChurch->id,
            'user_id' => $this->churchRep->id,
        ]);

        $inactiveRep = User::factory()->create([
            'role' => UserRole::CHURCH_REPRESENTATIVE,
            'church_id' => $this->firstChurch->id,
            'is_active' => false,
        ]);

        $this->actingAs($this->author)
            ->get('/portal/news')
            ->assertForbidden();

        $this->actingAs($this->author)
            ->get('/admin/news')
            ->assertForbidden();

        $this->actingAs($inactiveRep)
            ->get('/portal/news')
            ->assertForbidden();

        $this->actingAs($inactiveRep)
            ->get("/portal/news/{$news->id}/edit")
            ->assertForbidden();
    }

    public function test_church_representative_sees_only_own_church_news_in_portal_table(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('portal'));

        $ownNews = News::factory()->create([
            'church_id' => $this->firstChurch->id,
            'user_id' => $this->churchRep->id,
            'title' => 'Notícia da Primeira Igreja',
        ]);

        $foreignNews = News::factory()->create([
            'church_id' => $this->secondChurch->id,
            'user_id' => $this->secondChurchRep->id,
            'title' => 'Notícia da Segunda Igreja',
        ]);

        $this->actingAs($this->churchRep);

        Livewire::test(ListNews::class)
            ->assertCanSeeTableRecords([$ownNews])
            ->assertCanNotSeeTableRecords([$foreignNews]);
    }

    public function test_super_admin_sees_all_news_in_admin_table(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $firstNews = News::factory()->create([
            'church_id' => $this->firstChurch->id,
            'user_id' => $this->churchRep->id,
            'title' => 'Notícia de Goiânia',
        ]);

        $secondNews = News::factory()->create([
            'church_id' => $this->secondChurch->id,
            'user_id' => $this->secondChurchRep->id,
            'title' => 'Notícia de Anápolis',
        ]);

        $this->actingAs($this->superAdmin);

        Livewire::test(ListNews::class)
            ->assertCanSeeTableRecords([$firstNews, $secondNews]);
    }

    public function test_can_render_news_list_table_columns(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $news = News::factory()->create([
            'church_id' => $this->firstChurch->id,
            'user_id' => $this->churchRep->id,
            'title' => 'Seminário de Vida Cristã',
            'status' => PublishStatus::PUBLISHED,
            'is_official' => true,
            'event_date' => '2026-11-15',
        ]);

        $this->actingAs($this->superAdmin);

        Livewire::test(ListNews::class)
            ->assertCanRenderTableColumn('title')
            ->assertCanRenderTableColumn('status')
            ->assertCanRenderTableColumn('is_official')
            ->assertCanRenderTableColumn('event_date');
    }

    public function test_church_representative_can_create_news_with_auto_church_and_author_assignment(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('portal'));
        $this->actingAs($this->churchRep);

        Livewire::test(CreateNews::class)
            ->fillForm([
                'title' => 'Conferência de Missões Urbanas',
                'slug' => 'conferencia-de-missoes-urbanas',
                'excerpt' => 'Grande conferência para mobilização evangelística na capital.',
                'content' => '<p>Programação completa do evento com preletores convidados.</p>',
                'event_date' => '2026-11-20',
                'status' => PublishStatus::DRAFT->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('news', [
            'church_id' => $this->firstChurch->id,
            'user_id' => $this->churchRep->id,
            'title' => 'Conferência de Missões Urbanas',
            'slug' => 'conferencia-de-missoes-urbanas',
            'excerpt' => 'Grande conferência para mobilização evangelística na capital.',
            'is_official' => 0,
        ]);

        $createdNews = News::where('slug', 'conferencia-de-missoes-urbanas')->first();
        $this->assertNotNull($createdNews);
        $this->assertSame('2026-11-20', $createdNews->event_date?->toDateString());
    }

    public function test_church_representative_creates_news_with_featured_image_and_gallery_uploads(): void
    {
        Storage::fake('public');
        Filament::setCurrentPanel(Filament::getPanel('portal'));
        $this->actingAs($this->churchRep);

        $featured = UploadedFile::fake()->image('destaque.jpg', 1200, 675);
        $galleryPhoto1 = UploadedFile::fake()->image('foto1.jpg', 800, 600);
        $galleryPhoto2 = UploadedFile::fake()->image('foto2.jpg', 800, 600);

        Livewire::test(CreateNews::class)
            ->fillForm([
                'title' => 'Congresso com Galeria de Fotos',
                'slug' => 'congresso-com-galeria-de-fotos',
                'content' => '<p>Registros fotográficos da nossa conferência.</p>',
                'status' => PublishStatus::DRAFT->value,
                'featured_image' => $featured,
                'gallery' => [$galleryPhoto1, $galleryPhoto2],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $news = News::where('slug', 'congresso-com-galeria-de-fotos')->first();
        $this->assertNotNull($news);
        $this->assertNotNull($news->featured_image);
        Storage::disk('public')->assertExists($news->featured_image);

        $this->assertIsArray($news->gallery);
        $this->assertCount(2, $news->gallery);
        foreach ($news->gallery as $galleryItem) {
            Storage::disk('public')->assertExists($galleryItem);
        }
    }

    public function test_cannot_upload_invalid_file_type_as_featured_image_or_gallery(): void
    {
        Storage::fake('public');
        Filament::setCurrentPanel(Filament::getPanel('portal'));
        $this->actingAs($this->churchRep);

        $invalidFeatured = UploadedFile::fake()->create('destaque.pdf', 100, 'application/pdf');
        $invalidGallery = UploadedFile::fake()->create('foto.exe', 100, 'application/x-msdownload');

        Livewire::test(CreateNews::class)
            ->fillForm([
                'title' => 'Notícia com Upload Inválido',
                'slug' => 'noticia-com-upload-invalido',
                'content' => '<p>Tentativa de mídia inválida.</p>',
                'status' => PublishStatus::DRAFT->value,
                'featured_image' => $invalidFeatured,
                'gallery' => [$invalidGallery],
            ])
            ->call('create')
            ->assertHasFormErrors(['featured_image', 'gallery']);

        $this->assertDatabaseMissing('news', [
            'slug' => 'noticia-com-upload-invalido',
        ]);
    }

    public function test_church_representative_cannot_see_or_modify_is_official_field(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('portal'));
        $this->actingAs($this->churchRep);

        Livewire::test(CreateNews::class)
            ->assertFormFieldIsHidden('is_official')
            ->assertFormFieldIsHidden('church_id');
    }

    public function test_super_admin_can_see_and_configure_official_cbn_go_news(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superAdmin);

        Livewire::test(CreateNews::class)
            ->assertFormFieldIsVisible('is_official')
            ->assertFormFieldIsVisible('church_id')
            ->fillForm([
                'title' => 'Assembleia Geral Ordinária Estadual 2026',
                'slug' => 'assembleia-geral-ordinaria-estadual-2026',
                'content' => '<p>Convocatória oficial para todos os pastores de Goiás.</p>',
                'is_official' => true,
                'church_id' => null,
                'status' => PublishStatus::PUBLISHED->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('news', [
            'title' => 'Assembleia Geral Ordinária Estadual 2026',
            'is_official' => 1,
            'church_id' => null,
        ]);
    }

    public function test_cannot_create_news_with_missing_required_fields(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('portal'));
        $this->actingAs($this->churchRep);

        Livewire::test(CreateNews::class)
            ->fillForm([
                'title' => null,
                'slug' => null,
                'content' => null,
            ])
            ->call('create')
            ->assertHasFormErrors([
                'title' => 'required',
                'slug' => 'required',
                'content' => 'required',
            ]);
    }

    public function test_cannot_create_news_with_duplicate_slug(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('portal'));
        $this->actingAs($this->churchRep);

        News::factory()->create([
            'church_id' => $this->firstChurch->id,
            'user_id' => $this->churchRep->id,
            'slug' => 'slug-de-noticia-duplicado',
        ]);

        Livewire::test(CreateNews::class)
            ->fillForm([
                'title' => 'Outra Notícia com Mesmo Slug',
                'slug' => 'slug-de-noticia-duplicado',
                'content' => '<p>Conteúdo de teste.</p>',
                'status' => PublishStatus::DRAFT->value,
            ])
            ->call('create')
            ->assertHasFormErrors(['slug' => 'unique']);
    }

    public function test_church_representative_can_update_own_news(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('portal'));
        $this->actingAs($this->churchRep);

        $news = News::factory()->create([
            'church_id' => $this->firstChurch->id,
            'user_id' => $this->churchRep->id,
            'title' => 'Título Antes da Atualização',
            'content' => '<p>Conteúdo inicial da congregação.</p>',
        ]);

        Livewire::test(EditNews::class, ['record' => $news->getKey()])
            ->fillForm([
                'title' => 'Título Atualizado pela Igreja',
                'content' => '<p>Conteúdo enriquecido com bênçãos.</p>',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('news', [
            'id' => $news->id,
            'church_id' => $this->firstChurch->id,
            'title' => 'Título Atualizado pela Igreja',
        ]);
    }

    public function test_church_representative_can_delete_own_news(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('portal'));
        $this->actingAs($this->churchRep);

        $news = News::factory()->create([
            'church_id' => $this->firstChurch->id,
            'user_id' => $this->churchRep->id,
        ]);

        Livewire::test(EditNews::class, ['record' => $news->getKey()])
            ->callAction(DeleteAction::class);

        $this->assertModelMissing($news);
    }

    public function test_news_resource_eager_loads_church_and_author(): void
    {
        $query = NewsResource::getEloquentQuery();

        $this->assertTrue(
            array_key_exists('church', $query->getEagerLoads()),
            'O relacionamento church deve ser eager-loaded para prevenir N+1.'
        );

        $this->assertTrue(
            array_key_exists('author', $query->getEagerLoads()),
            'O relacionamento author deve ser eager-loaded para prevenir N+1.'
        );
    }
}
