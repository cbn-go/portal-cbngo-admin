<?php

namespace App\Models;

use App\Enums\NoticePriority;
use App\Enums\NoticeValidityStatus;
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
 * @property-read NoticeValidityStatus $validity_status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static> currentlyActive()
 * @method static Builder<static> scheduled()
 * @method static Builder<static> expired()
 * @method static Builder<static> inactive()
 * @method static Builder<static> forUser(\App\Models\User $user)
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

    public function getValidityStatusAttribute(): NoticeValidityStatus
    {
        if (! $this->is_active) {
            return NoticeValidityStatus::INACTIVE;
        }

        $now = now();

        if ($this->starts_at !== null && $this->starts_at->isFuture()) {
            return NoticeValidityStatus::SCHEDULED;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return NoticeValidityStatus::EXPIRED;
        }

        return NoticeValidityStatus::ACTIVE;
    }

    public function isCurrentlyActive(): bool
    {
        return $this->validity_status === NoticeValidityStatus::ACTIVE;
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeCurrentlyActive(Builder $query): Builder
    {
        $now = now();

        return $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', $now));
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeScheduled(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->whereNotNull('starts_at')
            ->where('starts_at', '>', now());
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now());
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('is_active', false);
    }
}
