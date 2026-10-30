<?php

namespace Tests\Feature\Filament;

use App\Enums\PublishStatus;
use App\Enums\UserRole;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\ArticleResource;
use App\Filament\Resources\BannerResource;
use App\Filament\Resources\BoardMemberResource;
use App\Filament\Resources\ChurchResource;
use App\Filament\Resources\DocumentResource;
use App\Filament\Resources\NewsResource;
use App\Filament\Resources\NoticeResource;
use App\Filament\Resources\SocialProjectResource;
use App\Filament\Resources\UserResource;
use App\Filament\Widgets\PendingReviewWidget;
use App\Filament\Widgets\PortalSummaryWidget;
use App\Filament\Widgets\StatsOverviewWidget;
use App\Models\Article;
use App\Models\Banner;
use App\Models\Church;
use App\Models\News;
use App\Models\Notice;
use App\Models\SocialProject;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardOverviewTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $conventionEditor;

    private User $churchRep;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create([
            'name' => 'Pastor Silva',
            'role' => UserRole::SUPER_ADMIN,
            'is_active' => true,
        ]);

        $this->conventionEditor = User::factory()->create([
            'name' => 'Editor Santos',
            'role' => UserRole::CONVENTION_EDITOR,
            'is_active' => true,
        ]);

        $this->churchRep = User::factory()->create([
            'role' => UserRole::CHURCH_REPRESENTATIVE,
            'is_active' => true,
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_admin_dashboard_can_be_rendered_for_authorized_users(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-30 09:15:00', 'America/Sao_Paulo'));

        $this->actingAs($this->superAdmin)
            ->get('/admin')
            ->assertSuccessful()
            ->assertSee('Bom dia, Pastor')
            ->assertSee('Sexta-feira, 30 de outubro de 2026')
            ->assertSee('Nova notícia')
            ->assertSee('Registrar contribuição')
            ->assertSee('Igrejas ativas')
            ->assertSee('Pastores no rol')
            ->assertSee('Contribuições em')
            ->assertSee('Aguardando revisão')
            ->assertSee('Contribuições das igrejas')
            ->assertSee('Igrejas sem contribuição');

        $this->actingAs($this->conventionEditor)
            ->get('/admin')
            ->assertSuccessful()
            ->assertSee('Bom dia, Editor');

        Carbon::setTestNow(null);
    }

    public function test_admin_dashboard_greeting_adapts_to_time_of_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-30 15:30:00', 'America/Sao_Paulo'));

        $this->actingAs($this->superAdmin)
            ->get('/admin')
            ->assertSuccessful()
            ->assertSee('Boa tarde, Pastor');

        Carbon::setTestNow(Carbon::parse('2026-10-30 20:00:00', 'America/Sao_Paulo'));

        $this->actingAs($this->superAdmin)
            ->get('/admin')
            ->assertSuccessful()
            ->assertSee('Boa noite, Pastor');

        Carbon::setTestNow(null);
    }

    public function test_resources_are_assigned_to_canonical_navigation_groups(): void
    {
        $this->assertSame('Secretaria', ChurchResource::getNavigationGroup());
        $this->assertSame('Secretaria', BoardMemberResource::getNavigationGroup());
        $this->assertSame('Secretaria', DocumentResource::getNavigationGroup());

        $this->assertSame('Portal', BannerResource::getNavigationGroup());
        $this->assertSame('Portal', NoticeResource::getNavigationGroup());
        $this->assertSame('Portal', NewsResource::getNavigationGroup());
        $this->assertSame('Portal', ArticleResource::getNavigationGroup());
        $this->assertSame('Portal', SocialProjectResource::getNavigationGroup());

        $this->assertSame('Sistema', UserResource::getNavigationGroup());
    }

    public function test_navigation_badges_count_pending_review_items(): void
    {
        News::factory()->count(3)->create(['status' => PublishStatus::PENDING_REVIEW]);
        News::factory()->count(2)->create(['status' => PublishStatus::PUBLISHED]);

        Article::factory()->count(2)->create(['status' => PublishStatus::PENDING_REVIEW]);
        Article::factory()->count(4)->create(['status' => PublishStatus::PUBLISHED]);

        SocialProject::factory()->count(1)->create(['status' => PublishStatus::PENDING_REVIEW]);
        SocialProject::factory()->count(3)->create(['status' => PublishStatus::PUBLISHED]);

        $this->assertSame('3', (string) NewsResource::getNavigationBadge());
        $this->assertSame('2', (string) ArticleResource::getNavigationBadge());
        $this->assertSame('1', (string) SocialProjectResource::getNavigationBadge());

        $this->assertSame('warning', NewsResource::getNavigationBadgeColor());
        $this->assertSame('warning', ArticleResource::getNavigationBadgeColor());
        $this->assertSame('warning', SocialProjectResource::getNavigationBadgeColor());
    }

    public function test_navigation_badges_are_null_when_no_pending_items(): void
    {
        News::query()->delete();
        Article::query()->delete();
        SocialProject::query()->delete();

        $this->assertNull(NewsResource::getNavigationBadge());
        $this->assertNull(ArticleResource::getNavigationBadge());
        $this->assertNull(SocialProjectResource::getNavigationBadge());
    }

    public function test_stats_overview_widget_calculates_metrics_correctly(): void
    {
        // 3 igrejas ativas em 2 cidades diferentes, e 1 inativa
        $church1 = Church::factory()->create(['city' => 'Goiânia', 'is_active' => true]);
        $church2 = Church::factory()->create(['city' => 'Goiânia', 'is_active' => true]);
        $church3 = Church::factory()->create(['city' => 'Anápolis', 'is_active' => true]);
        Church::factory()->create(['city' => 'Rio Verde', 'is_active' => false]);

        // Publicações pendentes
        News::factory()->create([
            'church_id' => $church1->id,
            'status' => PublishStatus::PENDING_REVIEW,
        ]);
        Article::factory()->create(['status' => PublishStatus::PENDING_REVIEW]);
        SocialProject::factory()->create([
            'church_id' => $church2->id,
            'status' => PublishStatus::PENDING_REVIEW,
        ]);

        // Publicações no ar
        News::factory()->create([
            'church_id' => $church3->id,
            'status' => PublishStatus::PUBLISHED,
        ]);
        Article::factory()->create(['status' => PublishStatus::PUBLISHED]);

        $this->actingAs($this->superAdmin);

        Livewire::test(StatsOverviewWidget::class)
            ->assertSuccessful()
            ->assertSee('3')
            ->assertSee('2 cidades no estado de Goiás')
            ->assertSee('Publicações Pendentes')
            ->assertSee('No Ar no Portal');
    }

    public function test_active_churches_kpi_displays_zero_when_no_churches(): void
    {
        Church::query()->delete();

        $this->actingAs($this->superAdmin)
            ->get('/admin')
            ->assertSuccessful()
            ->assertSee('Igrejas ativas')
            ->assertSee('nenhuma igreja ativa cadastrada');
    }

    public function test_active_churches_kpi_counts_real_active_churches_and_distinct_cities(): void
    {
        Church::query()->delete();

        // 3 igrejas ativas em 2 cidades diferentes e 1 inativa
        Church::factory()->create(['city' => 'Goiânia', 'is_active' => true]);
        Church::factory()->create(['city' => 'Goiânia', 'is_active' => true]);
        Church::factory()->create(['city' => 'Anápolis', 'is_active' => true]);
        Church::factory()->create(['city' => 'Rio Verde', 'is_active' => false]);

        $this->actingAs($this->superAdmin)
            ->get('/admin')
            ->assertSuccessful()
            ->assertSee('Igrejas ativas')
            ->assertSee('em 2 cidades de Goiás')
            ->assertSee('3 de 3 igrejas já contribuíram');
    }

    public function test_active_churches_cache_is_invalidated_when_church_is_saved_or_deleted(): void
    {
        Church::query()->delete();

        $church = Church::factory()->create(['city' => 'Goiânia', 'is_active' => true]);

        // Carrega o dashboard e aquece o cache
        $this->actingAs($this->superAdmin)
            ->get('/admin')
            ->assertSuccessful()
            ->assertSee('em 1 cidade de Goiás');

        $this->assertTrue(Cache::has(Dashboard::STATS_CACHE_KEY));

        // Criar uma nova igreja deve invalidar o cache
        $newChurch = Church::factory()->create(['city' => 'Anápolis', 'is_active' => true]);

        $this->assertFalse(Cache::has(Dashboard::STATS_CACHE_KEY));

        // Próximo carregamento reflete o novo valor
        $this->actingAs($this->superAdmin)
            ->get('/admin')
            ->assertSuccessful()
            ->assertSee('em 2 cidades de Goiás');

        // Desativar ou deletar a igreja também invalida o cache
        $newChurch->delete();

        $this->assertFalse(Cache::has(Dashboard::STATS_CACHE_KEY));
    }

    public function test_pending_review_widget_shows_empty_state_when_no_pendencies(): void
    {
        News::query()->delete();
        Article::query()->delete();
        SocialProject::query()->delete();

        $this->actingAs($this->superAdmin);

        Livewire::test(PendingReviewWidget::class)
            ->assertSuccessful()
            ->assertSee('Tudo em dia!')
            ->assertSee('Nenhuma publicação aguardando revisão no momento.');
    }

    public function test_pending_review_widget_lists_pending_items_with_links(): void
    {
        $news = News::factory()->create([
            'title' => 'Assembleia Geral 2026',
            'status' => PublishStatus::PENDING_REVIEW,
            'created_at' => now()->subHours(2),
        ]);

        $article = Article::factory()->create([
            'title' => 'A Importância da Família Cristã',
            'status' => PublishStatus::PENDING_REVIEW,
            'created_at' => now()->subDay(),
        ]);

        $this->actingAs($this->superAdmin);

        Livewire::test(PendingReviewWidget::class)
            ->assertSuccessful()
            ->assertSee('Assembleia Geral 2026')
            ->assertSee('A Importância da Família Cristã')
            ->assertSee('Notícia')
            ->assertSee('Artigo');
    }

    public function test_portal_summary_widget_displays_active_content_and_next_scheduled_banner(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-30 10:00:00', 'America/Sao_Paulo'));

        // Banner ativo no hero
        Banner::factory()->create([
            'title' => 'Banner Principal do Congresso',
            'is_active' => true,
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->addDays(5),
        ]);

        // Banner agendado
        Banner::factory()->create([
            'title' => 'Banner de Natal Agendado',
            'is_active' => true,
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(20),
        ]);

        // Aviso ativo
        Notice::factory()->create([
            'title' => 'Aviso do Encontro de Pastores',
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addDays(4),
        ]);

        $this->actingAs($this->superAdmin);

        Livewire::test(PortalSummaryWidget::class)
            ->assertSuccessful()
            ->assertSee('Banners no ar')
            ->assertSee('Avisos ativos')
            ->assertSee('Banner de Natal Agendado');

        Carbon::setTestNow(null);
    }

    public function test_global_search_is_configured_on_resources(): void
    {
        $this->assertNotNull(ChurchResource::getGloballySearchableAttributes());
        $this->assertNotNull(NewsResource::getGloballySearchableAttributes());
        $this->assertNotNull(ArticleResource::getGloballySearchableAttributes());
        $this->assertNotNull(NoticeResource::getGloballySearchableAttributes());
        $this->assertNotNull(SocialProjectResource::getGloballySearchableAttributes());
        $this->assertNotNull(BoardMemberResource::getGloballySearchableAttributes());
        $this->assertNotNull(DocumentResource::getGloballySearchableAttributes());
    }

    public function test_topbar_renders_view_site_link(): void
    {
        $this->actingAs($this->superAdmin)
            ->get('/admin')
            ->assertSuccessful()
            ->assertSee('Ver site')
            ->assertSee(config('portal.site_url'));
    }

    public function test_unauthorized_users_cannot_access_admin_dashboard(): void
    {
        $this->actingAs($this->churchRep)
            ->get('/admin')
            ->assertForbidden();

        auth()->logout();

        $this->get('/admin')
            ->assertRedirect('/admin/login');
    }
}
