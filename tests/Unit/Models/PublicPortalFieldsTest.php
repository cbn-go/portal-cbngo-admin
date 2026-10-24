<?php

namespace Tests\Unit\Models;

use App\Enums\ArticleCategory;
use App\Enums\NewsCategory;
use App\Models\Article;
use App\Models\AuthorProfile;
use App\Models\Church;
use App\Models\News;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPortalFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_article_casts_category_and_computes_reading_time_on_create(): void
    {
        $article = Article::factory()->create([
            'category' => ArticleCategory::LIDERANCA,
            'content' => '<p>'.str_repeat('palavra ', 401).'</p>',
        ]);

        $article->refresh();

        $this->assertSame(ArticleCategory::LIDERANCA, $article->category);
        $this->assertSame(3, $article->reading_time);
    }

    public function test_article_reading_time_is_recomputed_only_when_content_changes(): void
    {
        $article = Article::factory()->create(['content' => '<p>Texto curto.</p>']);
        $this->assertSame(1, $article->refresh()->reading_time);

        $article->update(['title' => 'Outro título']);
        $this->assertSame(1, $article->refresh()->reading_time);

        $article->update(['content' => '<p>'.str_repeat('palavra ', 600).'</p>']);
        $this->assertSame(3, $article->refresh()->reading_time);
    }

    public function test_article_reading_time_cannot_be_mass_assigned(): void
    {
        $article = Article::factory()->create([
            'content' => '<p>Texto curto.</p>',
            'reading_time' => 99,
        ]);

        $this->assertSame(1, $article->refresh()->reading_time);
    }

    public function test_news_casts_category(): void
    {
        $news = News::factory()->create(['category' => NewsCategory::COMUNICADO_OFICIAL]);

        $this->assertSame(NewsCategory::COMUNICADO_OFICIAL, $news->refresh()->category);
        $this->assertDatabaseHas('news', ['id' => $news->id, 'category' => 'Comunicado Oficial']);
    }

    public function test_church_casts_coordinates_and_service_times(): void
    {
        $church = Church::factory()->create([
            'latitude' => '-16.6868912',
            'longitude' => '-49.2647943',
            'service_times' => [['day' => 'Domingo', 'time' => '19h00', 'label' => 'Culto']],
        ]);

        $church->refresh();

        $this->assertSame('-16.6868912', $church->latitude);
        $this->assertSame('-49.2647943', $church->longitude);
        $this->assertSame([['day' => 'Domingo', 'time' => '19h00', 'label' => 'Culto']], $church->service_times);
    }

    public function test_church_slug_is_generated_unique_and_kept_on_rename(): void
    {
        $first = Church::factory()->create(['name' => 'Igreja Batista Nacional Central', 'slug' => null]);
        $second = Church::factory()->create(['name' => 'Igreja Batista Nacional Central', 'slug' => null]);
        $symbols = Church::factory()->create(['name' => '!!!', 'slug' => null]);

        $this->assertSame('igreja-batista-nacional-central', $first->slug);
        $this->assertSame('igreja-batista-nacional-central-1', $second->slug);
        $this->assertSame('igreja', $symbols->slug);

        $first->update(['name' => 'Igreja Renomeada']);
        $this->assertSame('igreja-batista-nacional-central', $first->refresh()->slug);
    }

    public function test_author_profile_slug_comes_from_user_name_and_is_unique(): void
    {
        $first = AuthorProfile::factory()->create([
            'user_id' => User::factory()->create(['name' => 'João Silva'])->id,
        ]);
        $second = AuthorProfile::factory()->create([
            'user_id' => User::factory()->create(['name' => 'João Silva'])->id,
        ]);

        $this->assertSame('joao-silva', $first->slug);
        $this->assertSame('joao-silva-1', $second->slug);
        $this->assertSame('João Silva', $first->display_name);
    }

    public function test_author_profile_slug_is_kept_when_user_is_renamed(): void
    {
        $user = User::factory()->create(['name' => 'Maria Souza']);
        $profile = AuthorProfile::factory()->create(['user_id' => $user->id]);

        $user->update(['name' => 'Maria Souza Lima']);
        $profile->update(['bio' => 'Nova biografia.']);

        $profile->refresh();
        $this->assertSame('maria-souza', $profile->slug);
        $this->assertSame('Maria Souza Lima', $profile->display_name);
    }
}
