<?php

namespace App\Models;

use App\Enums\FiscalCouncilSeat;
use Carbon\CarbonInterface;
use Database\Factories\FiscalCouncilMemberFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * @property int $id
 * @property int $user_id
 * @property FiscalCouncilSeat $seat
 * @property string|null $title
 * @property string|null $qualification
 * @property Carbon $term_starts_on
 * @property Carbon $term_ends_on
 * @property bool $active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
class FiscalCouncilMember extends Model
{
    /** @use HasFactory<FiscalCouncilMemberFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'seat',
        'title',
        'qualification',
        'term_starts_on',
        'term_ends_on',
        'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'seat' => FiscalCouncilSeat::class,
            'term_starts_on' => 'date',
            'term_ends_on' => 'date',
            'active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (FiscalCouncilMember $member): void {
            if ($member->active) {
                $seatValue = $member->seat->value;
                $seatLabel = $member->seat->getLabel();

                $conflict = static::query()
                    ->where('seat', $seatValue)
                    ->where('active', true)
                    ->when($member->exists, fn (Builder $q) => $q->where('id', '!=', $member->id))
                    ->exists();

                if ($conflict) {
                    throw new InvalidArgumentException("Já existe um membro ativo ocupando o assento {$seatLabel}.");
                }
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isTermActive(?CarbonInterface $date = null): bool
    {
        if (! $this->active) {
            return false;
        }

        $checkDate = $date ?? now();

        return $checkDate->between($this->term_starts_on->startOfDay(), $this->term_ends_on->endOfDay());
    }

    public function canSign(?CarbonInterface $date = null): bool
    {
        return $this->isTermActive($date) && (bool) $this->user->is_active;
    }

    /**
     * @param  Builder<FiscalCouncilMember>  $query
     * @return Builder<FiscalCouncilMember>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /**
     * @param  Builder<FiscalCouncilMember>  $query
     * @return Builder<FiscalCouncilMember>
     */
    public function scopeCurrentTerm(Builder $query, ?CarbonInterface $date = null): Builder
    {
        $target = ($date ?? now())->toDateString();

        return $query->where('active', true)
            ->whereDate('term_starts_on', '<=', $target)
            ->whereDate('term_ends_on', '>=', $target);
    }
}
