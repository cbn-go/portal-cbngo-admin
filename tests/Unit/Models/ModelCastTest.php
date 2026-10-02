<?php

namespace Tests\Unit\Models;

use App\Enums\NoticePriority;
use App\Enums\PublishStatus;
use App\Enums\UserRole;
use App\Models\Article;
use App\Models\AuthorProfile;
use App\Models\Church;
use App\Models\News;
use App\Models\Notice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ModelCastTest extends TestCase
{
    use RefreshDatabase;

    public function test_models_cast_enums_dates_booleans_and_json(): void
    {
        $church = Church::factory()->create([
            'social_links' => ['site' => 'https://cbngo.org.br'],
            'is_active' => false,
        ]);
        $church->refresh();

        $this->assertSame(['site' => 'https://cbngo.org.br'], $church->social_links);
        $this->assertFalse($church->is_active);

        $user = User::factory()->create([
            'role' => UserRole::CONVENTION_EDITOR,
            'is_active' => true,
        ]);
        $user->refresh();

        $this->assertSame(UserRole::CONVENTION_EDITOR, $user->role);
        $this->assertTrue($user->is_active);

        $profile = AuthorProfile::factory()->create([
            'user_id' => $user->id,
            'social_links' => ['instagram' => 'https://instagram.com/cbngo'],
        ]);
        $profile->refresh();

        $this->assertSame(['instagram' => 'https://instagram.com/cbngo'], $profile->social_links);

        $notice = Notice::factory()->create([
            'priority' => NoticePriority::URGENT,
            'starts_at' => '2026-10-01 10:00:00',
            'expires_at' => '2026-10-31 23:59:00',
            'is_active' => true,
        ]);
        $notice->refresh();

        $this->assertSame(NoticePriority::URGENT, $notice->priority);
        $this->assertInstanceOf(Carbon::class, $notice->starts_at);
        $this->assertInstanceOf(Carbon::class, $notice->expires_at);
        $this->assertTrue($notice->is_active);

        $article = Article::factory()->create([
            'status' => PublishStatus::PUBLISHED,
            'published_at' => '2026-10-01 12:00:00',
        ]);
        $article->refresh();

        $this->assertSame(PublishStatus::PUBLISHED, $article->status);
        $this->assertInstanceOf(Carbon::class, $article->published_at);

        $news = News::factory()->create([
            'status' => PublishStatus::PENDING_REVIEW,
            'gallery' => ['a.jpg', 'b.jpg'],
            'event_date' => '2026-10-15',
            'is_official' => true,
            'published_at' => '2026-10-01 08:00:00',
        ]);
        $news->refresh();

        $this->assertSame(PublishStatus::PENDING_REVIEW, $news->status);
        $this->assertSame(['a.jpg', 'b.jpg'], $news->gallery);
        $this->assertInstanceOf(Carbon::class, $news->event_date);
        $this->assertSame('2026-10-15', $news->event_date->toDateString());
        $this->assertTrue($news->is_official);
        $this->assertInstanceOf(Carbon::class, $news->published_at);
    }
}
