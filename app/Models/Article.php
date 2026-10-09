<?php

namespace App\Models;

use App\Enums\PublishStatus;
use App\Enums\UserRole;
use App\Models\Scopes\ArticleScope;
use App\Services\HtmlSanitizerService;
use Database\Factories\ArticleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $user_id
 * @property string $title
 * @property string $slug
 * @property string|null $excerpt
 * @property string $content
 * @property string|null $cover_image
 * @property PublishStatus $status
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Article extends Model
{
    /** @use HasFactory<ArticleFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'title',
        'slug',
        'excerpt',
        'content',
        'cover_image',
        'status',
        'published_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'status' => PublishStatus::class,
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new ArticleScope);

        static::creating(function (Article $article): void {
            /** @var User|null $user */
            $user = Auth::user();

            if ($user !== null && $user->role === UserRole::AUTHOR) {
                $article->user_id = $user->id;
            }

            if (blank($article->slug) && filled($article->title)) {
                $article->slug = static::generateUniqueSlug($article->title);
            }
        });

        static::updating(function (Article $article): void {
            /** @var User|null $user */
            $user = Auth::user();

            if ($user !== null && $user->role === UserRole::AUTHOR && $article->isDirty('user_id')) {
                $article->user_id = (int) $article->getOriginal('user_id');
            }
        });

        static::saving(function (Article $article): void {
            if ($article->isDirty('content') && filled($article->content)) {
                $article->content = app(HtmlSanitizerService::class)->sanitize($article->content);
            }

            if ($article->status === PublishStatus::PUBLISHED && $article->published_at === null) {
                $article->published_at = now();
            }
        });
    }

    /**
     * Gera um slug único amigável a partir do título fornecido.
     */
    public static function generateUniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $slug = Str::slug($title);

        if (blank($slug)) {
            $slug = 'artigo';
        }

        $originalSlug = $slug;
        $count = 1;

        while (static::query()->withoutGlobalScope(ArticleScope::class)
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn (Builder $q): Builder => $q->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = "{$originalSlug}-{$count}";
            $count++;
        }

        return $slug;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        $query->withoutGlobalScope(ArticleScope::class);

        if (! $user->is_active) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->role->hasAdminPanelAccess()) {
            return $query;
        }

        if ($user->role === UserRole::AUTHOR) {
            return $query->where('user_id', $user->id);
        }

        return $query->whereRaw('1 = 0');
    }
}
