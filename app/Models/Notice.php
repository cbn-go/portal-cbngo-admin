<?php

namespace App\Models;

use App\Enums\NoticePriority;
use App\Models\Scopes\NoticeScope;
use Database\Factories\NoticeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $title
 * @property string $slug
 * @property string $content
 * @property string|null $action_url
 * @property string|null $action_label
 * @property NoticePriority $priority
 * @property Carbon|null $starts_at
 * @property Carbon|null $expires_at
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Notice extends Model
{
    /** @use HasFactory<NoticeFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'title',
        'slug',
        'content',
        'action_url',
        'action_label',
        'priority',
        'starts_at',
        'expires_at',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'priority' => NoticePriority::class,
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new NoticeScope);

        static::creating(function (Notice $notice): void {
            /** @var User|null $user */
            $user = Auth::user();

            if ($user !== null && $notice->user_id === null) {
                $notice->user_id = $user->id;
            }
        });
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
        $query->withoutGlobalScope(NoticeScope::class);

        if (! $user->is_active) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->role->hasAdminPanelAccess()) {
            return $query;
        }

        return $query->whereRaw('1 = 0');
    }
}
