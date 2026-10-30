<?php

namespace App\Models;

use App\DTOs\Statute\StatuteStructure;
use App\Enums\PublishStatus;
use App\Services\AknStatuteParser;
use App\Services\AknValidator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property string $version_label
 * @property Carbon $approved_at
 * @property string $approved_place
 * @property string $approval_body
 * @property string|null $minutes_reference
 * @property string|null $registry_info
 * @property Carbon $effective_at
 * @property PublishStatus $status
 * @property string $akn_xml
 * @property string|null $original_pdf
 * @property int|null $published_by
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read StatuteStructure $structure
 */
class Statute extends Model
{
    use HasFactory;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'version_label',
        'approved_at',
        'approved_place',
        'approval_body',
        'minutes_reference',
        'registry_info',
        'effective_at',
        'status',
        'akn_xml',
        'original_pdf',
        'published_by',
        'published_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'approved_at' => 'date',
            'effective_at' => 'date',
            'published_at' => 'datetime',
            'status' => PublishStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Statute $statute): void {
            if ($statute->isDirty('akn_xml')) {
                app(AknValidator::class)->validate($statute->akn_xml);
            }

            if ($statute->status === PublishStatus::PUBLISHED) {
                if (! $statute->published_at) {
                    $statute->published_at = now();
                }

                // Garante que só uma versão pode estar publicada
                static::query()
                    ->when($statute->exists, fn (Builder $q) => $q->where('id', '!=', $statute->id))
                    ->where('status', PublishStatus::PUBLISHED)
                    ->update(['status' => PublishStatus::ARCHIVED]);
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    /**
     * @param  Builder<Statute>  $query
     * @return Builder<Statute>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', PublishStatus::PUBLISHED);
    }

    public function isPublished(): bool
    {
        return $this->status === PublishStatus::PUBLISHED;
    }

    public function publish(?User $user = null): void
    {
        $this->status = PublishStatus::PUBLISHED;
        if ($user) {
            $this->published_by = $user->id;
        }
        if (! $this->published_at) {
            $this->published_at = now();
        }

        $this->save();
    }

    public function archive(): void
    {
        $this->status = PublishStatus::ARCHIVED;
        $this->save();
    }

    public function getStructureAttribute(): StatuteStructure
    {
        return app(AknStatuteParser::class)->parse($this->akn_xml);
    }
}
