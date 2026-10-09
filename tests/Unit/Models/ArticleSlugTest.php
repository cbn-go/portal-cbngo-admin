<?php

namespace Tests\Unit\Models;

use App\Enums\PublishStatus;
use App\Enums\UserRole;
use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ArticleSlugTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    protected function setUp(): void
    {
        parent::setUp();
        $this->author = User::factory()->create([
            'role' => UserRole::AUTHOR,
            'is_active' => true,
        ]);
    }

    public function test_it_generates_unique_slug_from_title(): void
    {
        $slug = Article::generateUniqueSlug('A Graça Manifestada em Cristo');

        $this->assertSame('a-graca-manifestada-em-cristo', $slug);
    }

    public function test_it_increments_slug_when_duplicate_exists(): void
    {
        Article::factory()->create([
            'user_id' => $this->author->id,
            'title' => 'O Poder da Oração',
            'slug' => 'o-poder-da-oracao',
        ]);

        $secondSlug = Article::generateUniqueSlug('O Poder da Oração');
        $this->assertSame('o-poder-da-oracao-1', $secondSlug);

        Article::factory()->create([
            'user_id' => $this->author->id,
            'title' => 'O Poder da Oração',
            'slug' => $secondSlug,
        ]);

        $thirdSlug = Article::generateUniqueSlug('O Poder da Oração');
        $this->assertSame('o-poder-da-oracao-2', $thirdSlug);
    }

    public function test_it_ignores_own_id_when_generating_unique_slug(): void
    {
        $article = Article::factory()->create([
            'user_id' => $this->author->id,
            'title' => 'Fundamentos da Fé',
            'slug' => 'fundamentos-da-fe',
        ]);

        $slug = Article::generateUniqueSlug('Fundamentos da Fé', $article->id);
        $this->assertSame('fundamentos-da-fe', $slug);
    }

    public function test_it_falls_back_to_default_slug_if_title_has_no_slug_characters(): void
    {
        $slug = Article::generateUniqueSlug('??? !!!');
        $this->assertSame('artigo', $slug);
    }

    public function test_creating_article_without_slug_auto_generates_unique_slug(): void
    {
        $this->actingAs($this->author);

        $article = Article::create([
            'title' => 'Vivendo pela Palavra',
            'content' => '<p>Conteúdo de estudo bíblico.</p>',
            'status' => PublishStatus::DRAFT,
        ]);

        $this->assertSame('vivendo-pela-palavra', $article->slug);
    }

    public function test_published_status_sets_published_at_if_null(): void
    {
        $this->actingAs($this->author);
        Carbon::setTestNow('2026-10-09 10:00:00');

        $article = Article::create([
            'title' => 'Artigo Publicado Agora',
            'slug' => 'artigo-publicado-agora',
            'content' => '<p>Conteúdo pronto para publicação.</p>',
            'status' => PublishStatus::PUBLISHED,
            'published_at' => null,
        ]);

        $this->assertNotNull($article->published_at);
        $this->assertSame('2026-10-09 10:00:00', $article->published_at->format('Y-m-d H:i:s'));

        Carbon::setTestNow();
    }

    public function test_published_status_preserves_explicit_published_at(): void
    {
        $this->actingAs($this->author);
        $customDate = Carbon::parse('2026-10-01 12:00:00');

        $article = Article::create([
            'title' => 'Artigo com Data Customizada',
            'slug' => 'artigo-com-data-customizada',
            'content' => '<p>Conteúdo com data retroativa.</p>',
            'status' => PublishStatus::PUBLISHED,
            'published_at' => $customDate,
        ]);

        $this->assertSame('2026-10-01 12:00:00', $article->published_at->format('Y-m-d H:i:s'));
    }

    public function test_draft_status_does_not_auto_set_published_at(): void
    {
        $this->actingAs($this->author);

        $article = Article::create([
            'title' => 'Rascunho de Domingo',
            'slug' => 'rascunho-de-domingo',
            'content' => '<p>Ainda rascunho.</p>',
            'status' => PublishStatus::DRAFT,
            'published_at' => null,
        ]);

        $this->assertNull($article->published_at);
    }

    public function test_saving_article_sanitizes_html_content_automatically(): void
    {
        $this->actingAs($this->author);

        $article = Article::create([
            'title' => 'Artigo com Script Malicioso',
            'slug' => 'artigo-com-script-malicioso',
            'content' => '<p>Estudo bíblico seguro</p><script>alert("hack")</script>',
            'status' => PublishStatus::DRAFT,
        ]);

        $this->assertStringContainsString('Estudo bíblico seguro', $article->fresh()->content);
        $this->assertStringNotContainsString('<script>', $article->fresh()->content);
        $this->assertStringNotContainsString('alert("hack")', $article->fresh()->content);
    }
}
