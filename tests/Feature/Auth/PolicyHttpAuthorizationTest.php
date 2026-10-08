<?php

namespace Tests\Feature\Auth;

use App\Enums\PublishStatus;
use App\Enums\UserRole;
use App\Models\Article;
use App\Models\Church;
use App\Models\News;
use App\Models\Notice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PolicyHttpAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->group(function (): void {
            Route::put('/test-api/articles/{id}', function (int $id) {
                $article = Article::withoutGlobalScopes()->findOrFail($id);
                Gate::authorize('update', $article);

                return response()->json(['status' => 'authorized']);
            });

            Route::post('/test-api/news', function () {
                Gate::authorize('create', News::class);

                return response()->json(['status' => 'authorized']);
            });

            Route::put('/test-api/news/{id}', function (int $id) {
                $news = News::withoutGlobalScopes()->findOrFail($id);
                Gate::authorize('update', $news);

                return response()->json(['status' => 'authorized']);
            });

            Route::put('/test-api/notices/{id}', function (int $id) {
                $notice = Notice::withoutGlobalScopes()->findOrFail($id);
                Gate::authorize('update', $notice);

                return response()->json(['status' => 'authorized']);
            });
        });
    }

    public function test_author_cannot_update_another_authors_article_via_http(): void
    {
        $author1 = User::factory()->create(['role' => UserRole::AUTHOR, 'is_active' => true]);
        $author2 = User::factory()->create(['role' => UserRole::AUTHOR, 'is_active' => true]);
        $article = Article::factory()->create(['user_id' => $author1->id]);

        $response = $this->actingAs($author2)->putJson("/test-api/articles/{$article->id}");

        $response->assertForbidden();
    }

    public function test_author_can_update_own_article_via_http(): void
    {
        $author = User::factory()->create(['role' => UserRole::AUTHOR, 'is_active' => true]);
        $article = Article::factory()->create(['user_id' => $author->id]);

        $response = $this->actingAs($author)->putJson("/test-api/articles/{$article->id}");

        $response->assertOk();
        $response->assertJson(['status' => 'authorized']);
    }

    public function test_church_representative_cannot_update_another_church_news_via_http(): void
    {
        $church1 = Church::factory()->create();
        $church2 = Church::factory()->create();

        $rep1 = User::factory()->create([
            'role' => UserRole::CHURCH_REPRESENTATIVE,
            'church_id' => $church1->id,
            'is_active' => true,
        ]);
        $rep2 = User::factory()->create([
            'role' => UserRole::CHURCH_REPRESENTATIVE,
            'church_id' => $church2->id,
            'is_active' => true,
        ]);

        $news1 = News::factory()->create(['church_id' => $church1->id, 'user_id' => $rep1->id]);

        $response = $this->actingAs($rep2)->putJson("/test-api/news/{$news1->id}");

        $response->assertForbidden();
    }

    public function test_church_representative_can_update_own_church_news_via_http(): void
    {
        $church = Church::factory()->create();
        $rep = User::factory()->create([
            'role' => UserRole::CHURCH_REPRESENTATIVE,
            'church_id' => $church->id,
            'is_active' => true,
        ]);
        $news = News::factory()->create(['church_id' => $church->id, 'user_id' => $rep->id]);

        $response = $this->actingAs($rep)->putJson("/test-api/news/{$news->id}");

        $response->assertOk();
        $response->assertJson(['status' => 'authorized']);
    }

    public function test_church_representative_without_church_cannot_create_news_via_http(): void
    {
        $unlinkedRep = User::factory()->create([
            'role' => UserRole::CHURCH_REPRESENTATIVE,
            'church_id' => null,
            'is_active' => true,
        ]);

        $response = $this->actingAs($unlinkedRep)->postJson('/test-api/news');

        $response->assertForbidden();
    }

    public function test_author_and_church_representative_cannot_update_notices_via_http(): void
    {
        $author = User::factory()->create(['role' => UserRole::AUTHOR, 'is_active' => true]);
        $churchRep = User::factory()->create(['role' => UserRole::CHURCH_REPRESENTATIVE, 'is_active' => true]);
        $notice = Notice::factory()->create();

        $authorResponse = $this->actingAs($author)->putJson("/test-api/notices/{$notice->id}");
        $authorResponse->assertForbidden();

        $repResponse = $this->actingAs($churchRep)->putJson("/test-api/notices/{$notice->id}");
        $repResponse->assertForbidden();
    }

    public function test_inactive_users_are_forbidden_from_http_actions(): void
    {
        $inactiveAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN, 'is_active' => false]);
        $inactiveAuthor = User::factory()->create(['role' => UserRole::AUTHOR, 'is_active' => false]);
        $article = Article::factory()->create(['user_id' => $inactiveAuthor->id]);

        $responseAdmin = $this->actingAs($inactiveAdmin)->putJson("/test-api/articles/{$article->id}");
        $responseAdmin->assertForbidden();

        $responseAuthor = $this->actingAs($inactiveAuthor)->putJson("/test-api/articles/{$article->id}");
        $responseAuthor->assertForbidden();
    }

    public function test_super_admin_can_update_any_article_and_news_via_http(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN, 'is_active' => true]);
        $author = User::factory()->create(['role' => UserRole::AUTHOR, 'is_active' => true]);
        $church = Church::factory()->create();

        $article = Article::factory()->create(['user_id' => $author->id]);
        $news = News::factory()->create(['church_id' => $church->id]);

        $resArticle = $this->actingAs($superAdmin)->putJson("/test-api/articles/{$article->id}");
        $resArticle->assertOk();

        $resNews = $this->actingAs($superAdmin)->putJson("/test-api/news/{$news->id}");
        $resNews->assertOk();
    }

    public function test_global_scope_automatically_filters_articles_for_authenticated_author(): void
    {
        $author1 = User::factory()->create(['role' => UserRole::AUTHOR, 'is_active' => true]);
        $author2 = User::factory()->create(['role' => UserRole::AUTHOR, 'is_active' => true]);

        Article::factory()->count(2)->create(['user_id' => $author1->id]);
        Article::factory()->count(3)->create(['user_id' => $author2->id]);

        $this->actingAs($author1);
        $this->assertCount(2, Article::all());

        $this->actingAs($author2);
        $this->assertCount(3, Article::all());
    }

    public function test_global_scope_automatically_filters_news_for_authenticated_church_rep(): void
    {
        $church1 = Church::factory()->create();
        $church2 = Church::factory()->create();

        $rep1 = User::factory()->create([
            'role' => UserRole::CHURCH_REPRESENTATIVE,
            'church_id' => $church1->id,
            'is_active' => true,
        ]);
        $rep2 = User::factory()->create([
            'role' => UserRole::CHURCH_REPRESENTATIVE,
            'church_id' => $church2->id,
            'is_active' => true,
        ]);

        News::factory()->count(2)->create(['church_id' => $church1->id]);
        News::factory()->count(4)->create(['church_id' => $church2->id]);

        $this->actingAs($rep1);
        $this->assertCount(2, News::all());

        $this->actingAs($rep2);
        $this->assertCount(4, News::all());
    }

    public function test_global_scope_restricts_notices_for_non_directors(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN, 'is_active' => true]);
        $author = User::factory()->create(['role' => UserRole::AUTHOR, 'is_active' => true]);

        Notice::factory()->count(3)->create();

        $this->actingAs($superAdmin);
        $this->assertCount(3, Notice::all());

        $this->actingAs($author);
        $this->assertCount(0, Notice::all());
    }

    public function test_anti_idor_protects_article_creation_against_forged_author(): void
    {
        $legitAuthor = User::factory()->create(['role' => UserRole::AUTHOR, 'is_active' => true]);
        $victimAuthor = User::factory()->create(['role' => UserRole::AUTHOR, 'is_active' => true]);

        $this->actingAs($legitAuthor);

        $article = Article::create([
            'title' => 'Artigo Seguro',
            'slug' => 'artigo-seguro',
            'excerpt' => 'Resumo do artigo',
            'content' => 'Conteúdo seguro',
            'user_id' => $victimAuthor->id,
            'status' => PublishStatus::DRAFT,
        ]);

        $this->assertSame($legitAuthor->id, $article->user_id);
    }

    public function test_anti_idor_protects_article_update_against_reassignment(): void
    {
        $legitAuthor = User::factory()->create(['role' => UserRole::AUTHOR, 'is_active' => true]);
        $otherAuthor = User::factory()->create(['role' => UserRole::AUTHOR, 'is_active' => true]);

        $article = Article::factory()->create(['user_id' => $legitAuthor->id]);

        $this->actingAs($legitAuthor);

        $article->update(['user_id' => $otherAuthor->id]);
        $article->refresh();

        $this->assertSame($legitAuthor->id, $article->user_id);
    }

    public function test_anti_idor_protects_news_creation_against_forged_church_and_author(): void
    {
        $church1 = Church::factory()->create();
        $church2 = Church::factory()->create();

        $rep = User::factory()->create([
            'role' => UserRole::CHURCH_REPRESENTATIVE,
            'church_id' => $church1->id,
            'is_active' => true,
        ]);
        $otherUser = User::factory()->create();

        $this->actingAs($rep);

        $news = News::create([
            'title' => 'Notícia Segura',
            'slug' => 'noticia-segura',
            'excerpt' => 'Resumo',
            'content' => 'Conteúdo',
            'church_id' => $church2->id,
            'user_id' => $otherUser->id,
            'status' => PublishStatus::DRAFT,
        ]);

        $this->assertSame($church1->id, $news->church_id);
        $this->assertSame($rep->id, $news->user_id);
    }

    public function test_anti_idor_protects_news_update_against_church_reassignment(): void
    {
        $church1 = Church::factory()->create();
        $church2 = Church::factory()->create();

        $rep = User::factory()->create([
            'role' => UserRole::CHURCH_REPRESENTATIVE,
            'church_id' => $church1->id,
            'is_active' => true,
        ]);

        $news = News::factory()->create(['church_id' => $church1->id, 'user_id' => $rep->id]);

        $this->actingAs($rep);

        $news->update(['church_id' => $church2->id]);
        $news->refresh();

        $this->assertSame($church1->id, $news->church_id);
    }
}
