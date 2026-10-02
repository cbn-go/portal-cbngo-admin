<?php

namespace Tests\Feature\Database;

use App\Models\Article;
use App\Models\AuthorProfile;
use App\Models\News;
use App\Models\Notice;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UniqueConstraintsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_email_is_unique(): void
    {
        User::factory()->create(['email' => 'duplicado@cbngo.org.br']);

        $this->expectException(UniqueConstraintViolationException::class);

        User::factory()->create(['email' => 'duplicado@cbngo.org.br']);
    }

    public function test_author_profile_user_id_is_unique(): void
    {
        $user = User::factory()->create();
        AuthorProfile::factory()->create(['user_id' => $user->id]);

        $this->expectException(UniqueConstraintViolationException::class);

        AuthorProfile::factory()->create(['user_id' => $user->id]);
    }

    public function test_notice_slug_is_unique(): void
    {
        Notice::factory()->create(['slug' => 'aviso-unico']);

        $this->expectException(UniqueConstraintViolationException::class);

        Notice::factory()->create(['slug' => 'aviso-unico']);
    }

    public function test_article_slug_is_unique(): void
    {
        Article::factory()->create(['slug' => 'artigo-unico']);

        $this->expectException(UniqueConstraintViolationException::class);

        Article::factory()->create(['slug' => 'artigo-unico']);
    }

    public function test_news_slug_is_unique(): void
    {
        News::factory()->create(['slug' => 'noticia-unica']);

        $this->expectException(UniqueConstraintViolationException::class);

        News::factory()->create(['slug' => 'noticia-unica']);
    }
}
