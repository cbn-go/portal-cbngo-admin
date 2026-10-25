<?php

namespace App\Models;

use App\Enums\BannerFocalPoint;
use App\Enums\NoticeValidityStatus;
use App\Models\Concerns\HasImageVariants;
use Database\Factories\BannerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Banner do hero da página inicial do portal público.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $title
 * @property string|null $subtitle
 * @property string $image
 * @property BannerFocalPoint $focal_point
 * @property string $alt_text
 * @property string|null $link_url
 * @property string|null $link_label
 * @property bool $opens_in_new_tab
 * @property int $position
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property bool $is_active
 * @property-read NoticeValidityStatus $validity_status
 * @property-read string $display_link_label
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static> visible()
 * @method static Builder<static> scheduled()
 * @method static Builder<static> expired()
 * @method static Builder<static> inactive()
 */
class Banner extends Model
{
    /** @use HasFactory<BannerFactory> */
    use HasFactory;

    use HasImageVariants;

    /**
     * Texto do botão quando o banner tem link e link_label está vazio.
     */
    public const DEFAULT_LINK_LABEL = 'Saiba mais';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'title',
        'subtitle',
        'image',
        'focal_point',
        'alt_text',
        'link_url',
        'link_label',
        'opens_in_new_tab',
        'position',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'focal_point' => BannerFocalPoint::class,
            'opens_in_new_tab' => 'boolean',
            'position' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Banner $banner): void {
            /** @var User|null $user */
            $user = Auth::user();

            if ($user !== null && $banner->user_id === null) {
                $banner->user_id = $user->id;
            }

            if (blank($banner->position)) {
                $banner->position = (int) static::query()->max('position') + 1;
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
     * Banners exibidos agora no hero, na ordem definida no painel.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeVisible(Builder $query): Builder
    {
        $now = now();

        return $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
            ->orderBy('position');
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
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', now());
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('is_active', false);
    }

    public function getValidityStatusAttribute(): NoticeValidityStatus
    {
        if (! $this->is_active) {
            return NoticeValidityStatus::INACTIVE;
        }

        if ($this->starts_at !== null && $this->starts_at->isFuture()) {
            return NoticeValidityStatus::SCHEDULED;
        }

        if ($this->ends_at !== null && $this->ends_at->isPast()) {
            return NoticeValidityStatus::EXPIRED;
        }

        return NoticeValidityStatus::ACTIVE;
    }

    public function getDisplayLinkLabelAttribute(): string
    {
        return filled($this->link_label) ? (string) $this->link_label : self::DEFAULT_LINK_LABEL;
    }

    /**
     * @return list<string>
     */
    public function imageAttributes(): array
    {
        return ['image'];
    }
}
