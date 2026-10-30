<?php

namespace App\Models;

use App\Enums\StrategyIndicator;
use Database\Factories\MunicipalityIndicatorFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $municipality_id
 * @property StrategyIndicator $indicator
 * @property string $period
 * @property float|string $value
 * @property string $source
 * @property Carbon $fetched_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Municipality $municipality
 */
class MunicipalityIndicator extends Model
{
    /** @use HasFactory<MunicipalityIndicatorFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'municipality_id',
        'indicator',
        'period',
        'value',
        'source',
        'fetched_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'indicator' => StrategyIndicator::class,
            'value' => 'decimal:2',
            'fetched_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Municipality, $this>
     */
    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeForIndicator(Builder $query, StrategyIndicator $indicator): Builder
    {
        return $query->where('indicator', $indicator);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeForPeriod(Builder $query, string $period): Builder
    {
        return $query->where('period', $period);
    }
}
