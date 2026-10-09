<?php

namespace App\Models;

use App\Enums\PublishStatus;
use App\Enums\UserRole;
use App\Models\Scopes\NewsScope;
use App\Services\HtmlSanitizerService;
use Database\Factories\NewsFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int|null $church_id
 * @property int $user_id
 * @property string $title
 * @property string $slug
 * @property string|null $excerpt
 * @property string $content
 * @property string|null $featured_image
 * @property list<mixed>|null $gallery
 * @property Carbon|null $event_date
 * @property bool $is_official
 * @property PublishStatus $status
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class News extends Model
{
    /** @use HasFactory<NewsFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'church_id',
        'user_id',
        'title',
        'slug',
        'excerpt',
        'content',
        'featured_image',
        'gallery',
        'event_date',
        'is_official',
        'status',
        'published_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'church_id' => 'integer',
            'user_id' => 'integer',
            'status' => PublishStatus::class,
            'gallery' => 'array',
            'event_date' => 'date',
            'is_official' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new NewsScope);

        static::creating(function (News $news): void {
            /** @var User|null $user */
            $user = Auth::user();

            if ($user !== null) {
                if ($user->role === UserRole::CHURCH_REPRESENTATIVE) {
                    if ($user->church_id !== null) {
                        $news->church_id = $user->church_id;
                    }
                    $news->user_id = $user->id;
                    $news->is_official = false;
                } elseif (! $user->role->hasAdminPanelAccess()) {
                    $news->is_official = false;
                }

                if (blank($news->user_id)) {
                    $news->user_id = $user->id;
                }
            }

            if (blank($news->slug) && filled($news->title)) {
                $news->slug = static::generateUniqueSlug($news->title);
            }
        });

        static::updating(function (News $news): void {
            /** @var User|null $user */
            $user = Auth::user();

            if ($user !== null && $user->role === UserRole::CHURCH_REPRESENTATIVE) {
                if ($news->isDirty('church_id')) {
                    $news->church_id = $news->getOriginal('church_id');
                }

                if ($news->isDirty('user_id')) {
                    $news->user_id = (int) $news->getOriginal('user_id');
                }

                if ($news->isDirty('is_official')) {
                    $news->is_official = (bool) $news->getOriginal('is_official');
                }
            } elseif ($user !== null && ! $user->role->hasAdminPanelAccess()) {
                if ($news->isDirty('is_official')) {
                    $news->is_official = (bool) $news->getOriginal('is_official');
                }
            }
        });

        static::saving(function (News $news): void {
            if ($news->isDirty('content') && filled($news->content)) {
                $news->content = app(HtmlSanitizerService::class)->sanitize($news->content);
            }

            if ($news->status === PublishStatus::PUBLISHED && $news->published_at === null) {
                $news->published_at = now();
            }
        });
    }

    /**
     * Gera um slug único amigável a partir do título fornecido.
     */
    public static function generateUniqueSlug(string $title, ?int $ignoreId = null): string
    {
        if (preg_match('/[a-zA-Z0-9]/u', $title) !== 1) {
            $slug = 'noticia';
        } else {
            $slug = Str::slug($title);
        }

        if (blank($slug)) {
            $slug = 'noticia';
        }

        $originalSlug = $slug;
        $count = 1;

        while (static::query()->withoutGlobalScope(NewsScope::class)
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn (Builder $q): Builder => $q->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = "{$originalSlug}-{$count}";
            $count++;
        }

        return $slug;
    }

    /**
     * @return BelongsTo<Church, $this>
     */
    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
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
        $query->withoutGlobalScope(NewsScope::class);

        if (! $user->is_active) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->role->hasAdminPanelAccess()) {
            return $query;
        }

        if ($user->role === UserRole::CHURCH_REPRESENTATIVE && $user->church_id !== null) {
            return $query->where('church_id', $user->church_id);
        }

        return $query->whereRaw('1 = 0');
    }
}
