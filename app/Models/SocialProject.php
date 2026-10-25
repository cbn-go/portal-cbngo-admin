<?php

namespace App\Models;

use App\Enums\ProjectCategory;
use App\Enums\PublishStatus;
use App\Enums\UserRole;
use App\Models\Concerns\HasImageVariants;
use App\Models\Scopes\SocialProjectScope;
use App\Services\HtmlSanitizerService;
use Database\Factories\SocialProjectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Projeto social de uma igreja, exibido em /projetos-sociais no portal público.
 *
 * O representante da igreja cadastra e envia para revisão; a administração
 * (super admin e editor da convenção) aprova a publicação.
 *
 * @property int $id
 * @property int $church_id
 * @property int|null $user_id
 * @property string $title
 * @property string $slug
 * @property ProjectCategory $category
 * @property string $summary
 * @property string $description
 * @property string|null $cover_image
 * @property list<string>|null $gallery
 * @property string|null $target_audience
 * @property int|null $beneficiaries_count
 * @property string $city
 * @property Carbon $started_at
 * @property Carbon|null $ended_at
 * @property string|null $contact_name
 * @property string|null $contact_phone
 * @property string|null $contact_email
 * @property string|null $how_to_help
 * @property PublishStatus $status
 * @property Carbon|null $published_at
 * @property bool $is_featured
 * @property string|null $review_notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static> published()
 * @method static Builder<static> pendingReview()
 * @method static Builder<static> ongoing()
 * @method static Builder<static> ended()
 * @method static Builder<static> forUser(User $user)
 */
class SocialProject extends Model
{
    /** @use HasFactory<SocialProjectFactory> */
    use HasFactory;

    use HasImageVariants;

    /**
     * Status que o representante da igreja pode escolher.
     *
     * @var list<PublishStatus>
     */
    public const REPRESENTATIVE_STATUSES = [
        PublishStatus::DRAFT,
        PublishStatus::PENDING_REVIEW,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'church_id',
        'user_id',
        'title',
        'slug',
        'category',
        'summary',
        'description',
        'cover_image',
        'gallery',
        'target_audience',
        'beneficiaries_count',
        'city',
        'started_at',
        'ended_at',
        'contact_name',
        'contact_phone',
        'contact_email',
        'how_to_help',
        'status',
        'published_at',
        'is_featured',
        'review_notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'church_id' => 'integer',
            'user_id' => 'integer',
            'category' => ProjectCategory::class,
            'status' => PublishStatus::class,
            'gallery' => 'array',
            'beneficiaries_count' => 'integer',
            'started_at' => 'date',
            'ended_at' => 'date',
            'published_at' => 'datetime',
            'is_featured' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new SocialProjectScope);

        static::creating(function (SocialProject $project): void {
            /** @var User|null $user */
            $user = Auth::user();

            if ($user !== null && $user->role === UserRole::CHURCH_REPRESENTATIVE) {
                if ($user->church_id !== null) {
                    $project->church_id = $user->church_id;
                }

                $project->user_id = $user->id;
                $project->is_featured = false;
                $project->review_notes = null;
                $project->published_at = null;

                if (! in_array($project->status, self::REPRESENTATIVE_STATUSES, true)) {
                    $project->status = PublishStatus::PENDING_REVIEW;
                }
            }

            if ($user !== null && blank($project->user_id)) {
                $project->user_id = $user->id;
            }

            if (blank($project->slug) && filled($project->title)) {
                $project->slug = static::generateUniqueSlug($project->title);
            }

            if (blank($project->city) && filled($project->church_id)) {
                $project->city = (string) Church::query()->whereKey($project->church_id)->value('city');
            }
        });

        static::updating(function (SocialProject $project): void {
            /** @var User|null $user */
            $user = Auth::user();

            if ($user === null || $user->role !== UserRole::CHURCH_REPRESENTATIVE) {
                return;
            }

            foreach (['church_id', 'user_id', 'is_featured', 'review_notes', 'published_at'] as $attribute) {
                if ($project->isDirty($attribute)) {
                    $project->setAttribute($attribute, $project->getOriginal($attribute));
                }
            }

            // Qualquer alteração do representante em um projeto publicado (ou a
            // tentativa de publicar) volta para a revisão da administração.
            if (! in_array($project->status, self::REPRESENTATIVE_STATUSES, true)) {
                $project->status = PublishStatus::PENDING_REVIEW;
                $project->published_at = null;
            }
        });

        static::saving(function (SocialProject $project): void {
            if ($project->isDirty('description') && filled($project->description)) {
                $project->description = app(HtmlSanitizerService::class)->sanitize($project->description);
            }

            if ($project->status === PublishStatus::PUBLISHED && $project->published_at === null) {
                $project->published_at = now();
            }
        });
    }

    /**
     * Gera um slug único a partir do título.
     */
    public static function generateUniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $slug = preg_match('/[a-zA-Z0-9]/u', $title) === 1 ? Str::slug($title) : '';

        if (blank($slug)) {
            $slug = 'projeto';
        }

        $originalSlug = $slug;
        $count = 1;

        while (static::query()->withoutGlobalScope(SocialProjectScope::class)
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn (Builder $q): Builder => $q->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = "{$originalSlug}-{$count}";
            $count++;
        }

        return $slug;
    }

    /**
     * Aprova e publica o projeto.
     */
    public function approve(): void
    {
        $this->status = PublishStatus::PUBLISHED;
        $this->published_at ??= now();
        $this->review_notes = null;
        $this->save();
    }

    /**
     * Devolve o projeto ao representante como rascunho, com o motivo.
     */
    public function returnForAdjustments(string $notes): void
    {
        $this->status = PublishStatus::DRAFT;
        $this->review_notes = $notes;
        $this->save();
    }

    public function isOngoing(): bool
    {
        return $this->ended_at === null || $this->ended_at->greaterThanOrEqualTo(today());
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
     * Projetos visíveis no portal público.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', PublishStatus::PUBLISHED)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopePendingReview(Builder $query): Builder
    {
        return $query->where('status', PublishStatus::PENDING_REVIEW);
    }

    /**
     * Em andamento: sem data de término ou com término a partir de hoje.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeOngoing(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('ended_at')->orWhereDate('ended_at', '>=', today()));
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeEnded(Builder $query): Builder
    {
        return $query->whereNotNull('ended_at')->whereDate('ended_at', '<', today());
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        $query->withoutGlobalScope(SocialProjectScope::class);

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

    /**
     * @return list<string>
     */
    public function imageAttributes(): array
    {
        return ['cover_image', 'gallery'];
    }
}
