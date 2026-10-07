<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\Article;
use App\Models\Church;
use App\Models\News;
use App\Models\Notice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class TenantScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_article_for_user_scope_isolates_author_records(): void
    {
        $author1 = User::factory()->create(['role' => UserRole::AUTHOR, 'is_active' => true]);
        $author2 = User::factory()->create(['role' => UserRole::AUTHOR, 'is_active' => true]);
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN, 'is_active' => true]);
        $churchRep = User::factory()->create(['role' => UserRole::CHURCH_REPRESENTATIVE, 'is_active' => true]);

        Article::factory()->count(2)->create(['user_id' => $author1->id]);
        Article::factory()->count(3)->create(['user_id' => $author2->id]);

        $this->assertSame(2, Article::forUser($author1)->count());
        $this->assertSame(3, Article::forUser($author2)->count());
        $this->assertSame(5, Article::forUser($superAdmin)->count());
        $this->assertSame(0, Article::forUser($churchRep)->count());
    }

    public function test_news_for_user_scope_isolates_church_records(): void
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
        $unlinkedRep = User::factory()->create([
            'role' => UserRole::CHURCH_REPRESENTATIVE,
            'church_id' => null,
            'is_active' => true,
        ]);
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN, 'is_active' => true]);
        $author = User::factory()->create(['role' => UserRole::AUTHOR, 'is_active' => true]);

        News::factory()->count(2)->create(['church_id' => $church1->id, 'user_id' => $rep1->id]);
        News::factory()->count(3)->create(['church_id' => $church2->id, 'user_id' => $rep2->id]);

        $this->assertSame(2, News::forUser($rep1)->count());
        $this->assertSame(3, News::forUser($rep2)->count());
        $this->assertSame(5, News::forUser($superAdmin)->count());
        $this->assertSame(0, News::forUser($unlinkedRep)->count());
        $this->assertSame(0, News::forUser($author)->count());
    }

    public function test_notice_for_user_scope_restricts_to_directors_only(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN, 'is_active' => true]);
        $editor = User::factory()->create(['role' => UserRole::CONVENTION_EDITOR, 'is_active' => true]);
        $author = User::factory()->create(['role' => UserRole::AUTHOR, 'is_active' => true]);
        $churchRep = User::factory()->create(['role' => UserRole::CHURCH_REPRESENTATIVE, 'is_active' => true]);

        Notice::factory()->count(4)->create(['user_id' => $superAdmin->id]);

        $this->assertSame(4, Notice::forUser($superAdmin)->count());
        $this->assertSame(4, Notice::forUser($editor)->count());
        $this->assertSame(0, Notice::forUser($author)->count());
        $this->assertSame(0, Notice::forUser($churchRep)->count());
    }

    public function test_inactive_users_always_get_empty_scopes(): void
    {
        $inactiveAuthor = User::factory()->create(['role' => UserRole::AUTHOR, 'is_active' => false]);
        $inactiveRep = User::factory()->create(['role' => UserRole::CHURCH_REPRESENTATIVE, 'is_active' => false]);
        $inactiveAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN, 'is_active' => false]);

        Article::factory()->create(['user_id' => $inactiveAuthor->id]);
        News::factory()->create(['user_id' => $inactiveRep->id]);
        Notice::factory()->create(['user_id' => $inactiveAdmin->id]);

        $this->assertSame(0, Article::forUser($inactiveAuthor)->count());
        $this->assertSame(0, News::forUser($inactiveRep)->count());
        $this->assertSame(0, Notice::forUser($inactiveAdmin)->count());
    }

    public function test_gate_authorization_enforces_anti_idor_rules(): void
    {
        $church1 = Church::factory()->create();
        $church2 = Church::factory()->create();

        $rep1 = User::factory()->create([
            'role' => UserRole::CHURCH_REPRESENTATIVE,
            'church_id' => $church1->id,
            'is_active' => true,
        ]);
        $author1 = User::factory()->create(['role' => UserRole::AUTHOR, 'is_active' => true]);
        $author2 = User::factory()->create(['role' => UserRole::AUTHOR, 'is_active' => true]);

        $article1 = Article::factory()->create(['user_id' => $author1->id]);
        $news1 = News::factory()->create(['church_id' => $church1->id, 'user_id' => $rep1->id]);
        $news2 = News::factory()->create(['church_id' => $church2->id]);
        $notice = Notice::factory()->create();

        // Anti-IDOR Artigos
        $this->assertTrue(Gate::forUser($author1)->allows('update', $article1));
        $this->assertTrue(Gate::forUser($author2)->denies('update', $article1));

        // Anti-IDOR Notícias
        $this->assertTrue(Gate::forUser($rep1)->allows('update', $news1));
        $this->assertTrue(Gate::forUser($rep1)->denies('update', $news2));

        // Bloqueio cruzado de Avisos
        $this->assertTrue(Gate::forUser($author1)->denies('update', $notice));
        $this->assertTrue(Gate::forUser($rep1)->denies('update', $notice));
    }
}
