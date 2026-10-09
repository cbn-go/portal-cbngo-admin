<?php

namespace Tests\Unit\Models;

use App\Enums\PublishStatus;
use App\Enums\UserRole;
use App\Models\Church;
use App\Models\News;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class NewsModelTest extends TestCase
{
    use RefreshDatabase;

    private Church $church;

    private User $churchRep;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->church = Church::factory()->create();

        $this->churchRep = User::factory()->create([
            'role' => UserRole::CHURCH_REPRESENTATIVE,
            'church_id' => $this->church->id,
            'is_active' => true,
        ]);

        $this->superAdmin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'is_active' => true,
        ]);
    }

    public function test_it_generates_unique_slug_from_title(): void
    {
        $slug = News::generateUniqueSlug('Congresso de Homens da CBN Goiás');

        $this->assertSame('congresso-de-homens-da-cbn-goias', $slug);
    }

    public function test_it_increments_slug_when_duplicate_exists(): void
    {
        News::factory()->create([
            'church_id' => $this->church->id,
            'user_id' => $this->churchRep->id,
            'title' => 'Conferência Missionária Estadual',
            'slug' => 'conferencia-missionaria-estadual',
        ]);

        $secondSlug = News::generateUniqueSlug('Conferência Missionária Estadual');
        $this->assertSame('conferencia-missionaria-estadual-1', $secondSlug);

        News::factory()->create([
            'church_id' => $this->church->id,
            'user_id' => $this->churchRep->id,
            'title' => 'Conferência Missionária Estadual',
            'slug' => $secondSlug,
        ]);

        $thirdSlug = News::generateUniqueSlug('Conferência Missionária Estadual');
        $this->assertSame('conferencia-missionaria-estadual-2', $thirdSlug);
    }

    public function test_it_ignores_own_id_when_generating_unique_slug(): void
    {
        $news = News::factory()->create([
            'church_id' => $this->church->id,
            'user_id' => $this->churchRep->id,
            'title' => 'Retiro de Jovens Renovados',
            'slug' => 'retiro-de-jovens-renovados',
        ]);

        $slug = News::generateUniqueSlug('Retiro de Jovens Renovados', $news->id);
        $this->assertSame('retiro-de-jovens-renovados', $slug);
    }

    public function test_it_falls_back_to_default_slug_if_title_has_no_slug_characters(): void
    {
        $slug = News::generateUniqueSlug('### @@@ !!!');
        $this->assertSame('noticia', $slug);
    }

    public function test_creating_news_without_slug_auto_generates_unique_slug(): void
    {
        $this->actingAs($this->churchRep);

        $news = News::create([
            'title' => 'Vigília de Avivamento e Louvor',
            'content' => '<p>Noite de profunda adoração e busca ao Espírito Santo.</p>',
            'status' => PublishStatus::DRAFT,
        ]);

        $this->assertSame('vigilia-de-avivamento-e-louvor', $news->slug);
    }

    public function test_published_status_sets_published_at_if_null(): void
    {
        $this->actingAs($this->churchRep);
        Carbon::setTestNow('2026-10-09 11:30:00');

        $news = News::create([
            'title' => 'Notícia Publicada Imediata',
            'slug' => 'noticia-publicada-imediata',
            'content' => '<p>Conteúdo de divulgação da programação.</p>',
            'status' => PublishStatus::PUBLISHED,
            'published_at' => null,
        ]);

        $this->assertNotNull($news->published_at);
        $this->assertSame('2026-10-09 11:30:00', $news->published_at->format('Y-m-d H:i:s'));

        Carbon::setTestNow();
    }

    public function test_published_status_preserves_explicit_published_at(): void
    {
        $this->actingAs($this->churchRep);
        $customDate = Carbon::parse('2026-10-01 08:00:00');

        $news = News::create([
            'title' => 'Notícia com Data Retroativa',
            'slug' => 'noticia-com-data-retroativa',
            'content' => '<p>Publicação com data já definida.</p>',
            'status' => PublishStatus::PUBLISHED,
            'published_at' => $customDate,
        ]);

        $this->assertSame('2026-10-01 08:00:00', $news->published_at->format('Y-m-d H:i:s'));
    }

    public function test_draft_status_does_not_auto_set_published_at(): void
    {
        $this->actingAs($this->churchRep);

        $news = News::create([
            'title' => 'Rascunho de Notícia Local',
            'slug' => 'rascunho-de-noticia-local',
            'content' => '<p>Ainda aguardando revisão da equipe.</p>',
            'status' => PublishStatus::DRAFT,
            'published_at' => null,
        ]);

        $this->assertNull($news->published_at);
    }

    public function test_saving_news_sanitizes_html_content_automatically(): void
    {
        $this->actingAs($this->churchRep);

        $news = News::create([
            'title' => 'Notícia com Script e Evento Malicioso',
            'slug' => 'noticia-com-script-e-evento-malicioso',
            'content' => '<p>Texto da igreja seguro</p><script>alert("xss")</script><img src="x" onerror="alert(1)">',
            'status' => PublishStatus::DRAFT,
        ]);

        $freshContent = $news->fresh()->content;
        $this->assertStringContainsString('Texto da igreja seguro', $freshContent);
        $this->assertStringNotContainsString('<script>', $freshContent);
        $this->assertStringNotContainsString('alert("xss")', $freshContent);
        $this->assertStringNotContainsString('onerror', $freshContent);
    }

    public function test_church_representative_cannot_set_is_official_on_create(): void
    {
        $this->actingAs($this->churchRep);

        $news = News::create([
            'title' => 'Tentativa de Criar Notícia Oficial',
            'slug' => 'tentativa-criar-noticia-oficial',
            'content' => '<p>Conteúdo comum.</p>',
            'status' => PublishStatus::DRAFT,
            'is_official' => true,
        ]);

        $this->assertFalse($news->is_official);
        $this->assertFalse((bool) $news->fresh()->is_official);
    }

    public function test_church_representative_cannot_mutate_is_official_on_update(): void
    {
        $this->actingAs($this->superAdmin);
        $news = News::create([
            'church_id' => $this->church->id,
            'user_id' => $this->churchRep->id,
            'title' => 'Notícia da Igreja Comum',
            'slug' => 'noticia-da-igreja-comum',
            'content' => '<p>Conteúdo comum.</p>',
            'status' => PublishStatus::DRAFT,
            'is_official' => false,
        ]);

        $this->actingAs($this->churchRep);
        $news->update(['is_official' => true]);

        $this->assertFalse((bool) $news->fresh()->is_official);
    }

    public function test_admin_can_set_and_mutate_is_official(): void
    {
        $this->actingAs($this->superAdmin);

        $news = News::create([
            'church_id' => $this->church->id,
            'user_id' => $this->superAdmin->id,
            'title' => 'Comunicado Oficial Estadual',
            'slug' => 'comunicado-oficial-estadual',
            'content' => '<p>Conteúdo oficial da Convenção Batista Nacional de Goiás.</p>',
            'status' => PublishStatus::PUBLISHED,
            'is_official' => true,
        ]);

        $this->assertTrue((bool) $news->fresh()->is_official);

        $news->update(['is_official' => false]);
        $this->assertFalse((bool) $news->fresh()->is_official);
    }
}
