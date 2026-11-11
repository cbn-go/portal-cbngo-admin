<?php

namespace App\Models;

use App\Enums\JubanCategoryKind;
use App\Enums\JubanTransactionType;
use Database\Factories\JubanCategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $code
 * @property JubanCategoryKind $kind
 * @property string|null $description
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, JubanTransaction> $transactions
 */
class JubanCategory extends Model
{
    /** @use HasFactory<JubanCategoryFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'code',
        'kind',
        'description',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => JubanCategoryKind::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<JubanTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(JubanTransaction::class, 'category_id');
    }

    /**
     * @param  Builder<JubanCategory>  $query
     * @return Builder<JubanCategory>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<JubanCategory>  $query
     * @return Builder<JubanCategory>
     */
    public function scopeForType(Builder $query, JubanTransactionType $type): Builder
    {
        return $query->whereIn('kind', [$type->value, JubanCategoryKind::AMBOS->value]);
    }

    public function canAcceptType(JubanTransactionType $type): bool
    {
        return $this->kind->allows($type);
    }
}
