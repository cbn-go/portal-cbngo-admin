<?php

namespace Tests\Feature\Database;

use App\Enums\UserRole;
use App\Models\Article;
use App\Models\AuthorProfile;
use App\Models\Church;
use App\Models\News;
use App\Models\Notice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferentialIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_eloquent_relationships_resolve(): void
    {
        $church = Church::factory()->create();
        $user = User::factory()->create([
            'church_id' => $church->id,
            'role' => UserRole::AUTHOR,
        ]);
        $profile = AuthorProfile::factory()->create(['user_id' => $user->id]);
        $article = Article::factory()->create(['user_id' => $user->id]);
        $news = News::factory()->create([
            'church_id' => $church->id,
            'user_id' => $user->id,
        ]);
        $notice = Notice::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($user->church?->is($church));
        $this->assertTrue($church->users->contains($user));
        $this->assertTrue($user->authorProfile?->is($profile));
        $this->assertTrue($profile->user?->is($user));
        $this->assertTrue($user->articles->contains($article));
        $this->assertTrue($article->author?->is($user));
        $this->assertTrue($user->news->contains($news));
        $this->assertTrue($news->author?->is($user));
        $this->assertTrue($news->church?->is($church));
        $this->assertTrue($church->news->contains($news));
        $this->assertTrue($user->notices->contains($notice));
        $this->assertTrue($notice->author?->is($user));
    }

    public function test_deleting_a_church_nulls_users_and_cascades_news(): void
    {
        $church = Church::factory()->create();
        $user = User::factory()->create(['church_id' => $church->id]);
        $news = News::factory()->create([
            'church_id' => $church->id,
            'user_id' => $user->id,
        ]);

        $church->delete();

        $this->assertDatabaseMissing('news', ['id' => $news->id]);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'church_id' => null,
        ]);
    }

    public function test_deleting_a_user_cascades_owned_records_and_nulls_notices(): void
    {
        $user = User::factory()->create();
        $profile = AuthorProfile::factory()->create(['user_id' => $user->id]);
        $article = Article::factory()->create(['user_id' => $user->id]);
        $news = News::factory()->create(['user_id' => $user->id]);
        $notice = Notice::factory()->create(['user_id' => $user->id]);

        $user->delete();

        $this->assertDatabaseMissing('author_profiles', ['id' => $profile->id]);
        $this->assertDatabaseMissing('articles', ['id' => $article->id]);
        $this->assertDatabaseMissing('news', ['id' => $news->id]);
        $this->assertDatabaseHas('notices', [
            'id' => $notice->id,
            'user_id' => null,
        ]);
    }
}
