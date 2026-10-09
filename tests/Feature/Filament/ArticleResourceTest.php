<?php

namespace Tests\Feature\Filament;

use App\Enums\PublishStatus;
use App\Enums\UserRole;
use App\Filament\Resources\ArticleResource;
use App\Filament\Resources\ArticleResource\Pages\CreateArticle;
use App\Filament\Resources\ArticleResource\Pages\EditArticle;
use App\Filament\Resources\ArticleResource\Pages\ListArticles;
use App\Models\Article;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ArticleResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $conventionEditor;

    private User $author;

    private User $secondAuthor;

    private User $churchRep;

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

        $this->author = User::factory()->create([
            'role' => UserRole::AUTHOR,
            'is_active' => true,
        ]);

        $this->secondAuthor = User::factory()->create([
            'role' => UserRole::AUTHOR,
            'is_active' => true,
        ]);

        $this->churchRep = User::factory()->create([
            'role' => UserRole::CHURCH_REPRESENTATIVE,
            'is_active' => true,
        ]);
    }

    public function test_author_can_access_article_pages_on_portal_panel(): void
    {
        $article = Article::factory()->create([
            'user_id' => $this->author->id,
        ]);

        $this->actingAs($this->author)
            ->get('/portal/articles')
            ->assertSuccessful();

        $this->actingAs($this->author)
            ->get('/portal/articles/create')
            ->assertSuccessful();

        $this->actingAs($this->author)
            ->get("/portal/articles/{$article->id}/edit")
            ->assertSuccessful();
    }

    public function test_author_cannot_access_another_authors_article_on_portal_panel(): void
    {
        $foreignArticle = Article::factory()->create([
            'user_id' => $this->secondAuthor->id,
        ]);

        $this->actingAs($this->author)
            ->get("/portal/articles/{$foreignArticle->id}/edit")
            ->assertNotFound();
    }

    public function test_super_admin_and_convention_editor_can_access_article_pages_on_admin_panel(): void
    {
        $article = Article::factory()->create([
            'user_id' => $this->author->id,
        ]);

        $this->actingAs($this->superAdmin)
            ->get('/admin/articles')
            ->assertSuccessful();

        $this->actingAs($this->superAdmin)
            ->get('/admin/articles/create')
            ->assertSuccessful();

        $this->actingAs($this->superAdmin)
            ->get("/admin/articles/{$article->id}/edit")
            ->assertSuccessful();

        $this->actingAs($this->conventionEditor)
            ->get('/admin/articles')
            ->assertSuccessful();

        $this->actingAs($this->conventionEditor)
            ->get('/admin/articles/create')
            ->assertSuccessful();

        $this->actingAs($this->conventionEditor)
            ->get("/admin/articles/{$article->id}/edit")
            ->assertSuccessful();
    }

    public function test_church_representative_and_inactive_users_cannot_access_article_resource(): void
    {
        $article = Article::factory()->create(['user_id' => $this->author->id]);

        $inactiveAuthor = User::factory()->create([
            'role' => UserRole::AUTHOR,
            'is_active' => false,
        ]);

        $this->actingAs($this->churchRep)
            ->get('/portal/articles')
            ->assertForbidden();

        $this->actingAs($this->churchRep)
            ->get('/admin/articles')
            ->assertForbidden();

        $this->actingAs($inactiveAuthor)
            ->get('/portal/articles')
            ->assertForbidden();

        $this->actingAs($inactiveAuthor)
            ->get("/portal/articles/{$article->id}/edit")
            ->assertForbidden();
    }

    public function test_author_sees_only_own_articles_in_portal_table(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('portal'));

        $ownArticle = Article::factory()->create([
            'user_id' => $this->author->id,
            'title' => 'Meu Artigo Pastoral',
        ]);

        $foreignArticle = Article::factory()->create([
            'user_id' => $this->secondAuthor->id,
            'title' => 'Artigo de Outro Autor',
        ]);

        $this->actingAs($this->author);

        Livewire::test(ListArticles::class)
            ->assertCanSeeTableRecords([$ownArticle])
            ->assertCanNotSeeTableRecords([$foreignArticle]);
    }

    public function test_admin_sees_all_articles_in_admin_table(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $firstArticle = Article::factory()->create([
            'user_id' => $this->author->id,
            'title' => 'Artigo do Primeiro Autor',
        ]);

        $secondArticle = Article::factory()->create([
            'user_id' => $this->secondAuthor->id,
            'title' => 'Artigo do Segundo Autor',
        ]);

        $this->actingAs($this->superAdmin);

        Livewire::test(ListArticles::class)
            ->assertCanSeeTableRecords([$firstArticle, $secondArticle]);
    }

    public function test_can_render_article_list_table_columns(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $article = Article::factory()->create([
            'user_id' => $this->author->id,
            'title' => 'Artigo Teológico de Exemplo',
            'status' => PublishStatus::PUBLISHED,
        ]);

        $this->actingAs($this->superAdmin);

        Livewire::test(ListArticles::class)
            ->assertCanRenderTableColumn('title')
            ->assertCanRenderTableColumn('status')
            ->assertCanRenderTableColumn('published_at');
    }

    public function test_author_can_create_article_with_auto_author_assignment(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('portal'));
        $this->actingAs($this->author);

        Livewire::test(CreateArticle::class)
            ->fillForm([
                'title' => 'Crescimento Espiritual e Oração',
                'slug' => 'crescimento-espiritual-e-oracao',
                'excerpt' => 'Breve resumo sobre maturidade cristã.',
                'content' => '<p>Conteúdo bíblico aprofundado com citações.</p>',
                'status' => PublishStatus::DRAFT->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('articles', [
            'user_id' => $this->author->id,
            'title' => 'Crescimento Espiritual e Oração',
            'slug' => 'crescimento-espiritual-e-oracao',
            'excerpt' => 'Breve resumo sobre maturidade cristã.',
        ]);
    }

    public function test_cannot_create_article_with_missing_required_fields(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('portal'));
        $this->actingAs($this->author);

        Livewire::test(CreateArticle::class)
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

    public function test_cannot_create_article_with_duplicate_slug(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('portal'));
        $this->actingAs($this->author);

        Article::factory()->create([
            'user_id' => $this->author->id,
            'slug' => 'slug-ja-existente',
        ]);

        Livewire::test(CreateArticle::class)
            ->fillForm([
                'title' => 'Novo Artigo',
                'slug' => 'slug-ja-existente',
                'content' => '<p>Texto</p>',
                'status' => PublishStatus::DRAFT->value,
            ])
            ->call('create')
            ->assertHasFormErrors(['slug' => 'unique']);
    }

    public function test_author_can_update_own_article(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('portal'));
        $this->actingAs($this->author);

        $article = Article::factory()->create([
            'user_id' => $this->author->id,
            'title' => 'Título Inicial',
            'content' => '<p>Conteúdo original.</p>',
        ]);

        Livewire::test(EditArticle::class, ['record' => $article->getKey()])
            ->fillForm([
                'title' => 'Título Atualizado com Sucesso',
                'content' => '<p>Conteúdo revisado com bênçãos.</p>',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('articles', [
            'id' => $article->id,
            'user_id' => $this->author->id,
            'title' => 'Título Atualizado com Sucesso',
        ]);
    }

    public function test_author_can_delete_own_article(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('portal'));
        $this->actingAs($this->author);

        $article = Article::factory()->create([
            'user_id' => $this->author->id,
        ]);

        Livewire::test(EditArticle::class, ['record' => $article->getKey()])
            ->callAction(DeleteAction::class);

        $this->assertModelMissing($article);
    }

    public function test_article_resource_eager_loads_author(): void
    {
        $query = ArticleResource::getEloquentQuery();

        $this->assertTrue(
            array_key_exists('author', $query->getEagerLoads()),
            'O relacionamento author deve ser eager-loaded para prevenir queries N+1.'
        );
    }

    public function test_author_can_upload_cover_image_with_proper_storage(): void
    {
        Storage::fake('public');
        Filament::setCurrentPanel(Filament::getPanel('portal'));
        $this->actingAs($this->author);

        $file = function_exists('imagecreatetruecolor')
            ? UploadedFile::fake()->image('capa-artigo.jpg', 1200, 675)
            : UploadedFile::fake()->createWithContent(
                'capa-artigo.jpg',
                (string) base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=')
            );

        Livewire::test(CreateArticle::class)
            ->fillForm([
                'title' => 'Artigo com Imagem de Capa',
                'slug' => 'artigo-com-imagem-de-capa',
                'content' => '<p>Conteúdo de estudo teológico com capa.</p>',
                'status' => PublishStatus::DRAFT->value,
                'cover_image' => $file,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $article = Article::where('slug', 'artigo-com-imagem-de-capa')->first();
        $this->assertNotNull($article);
        $this->assertNotNull($article->cover_image);
        Storage::disk('public')->assertExists($article->cover_image);
    }

    public function test_cannot_upload_invalid_file_type_as_cover_image(): void
    {
        Storage::fake('public');
        Filament::setCurrentPanel(Filament::getPanel('portal'));
        $this->actingAs($this->author);

        $invalidFile = UploadedFile::fake()->create('payload.pdf', 100, 'application/pdf');

        Livewire::test(CreateArticle::class)
            ->fillForm([
                'title' => 'Artigo com Upload Inválido',
                'slug' => 'artigo-com-upload-invalido',
                'content' => '<p>Tentativa de capa em PDF.</p>',
                'status' => PublishStatus::DRAFT->value,
                'cover_image' => $invalidFile,
            ])
            ->call('create')
            ->assertHasFormErrors(['cover_image']);

        $this->assertDatabaseMissing('articles', [
            'slug' => 'artigo-com-upload-invalido',
        ]);
    }
}
