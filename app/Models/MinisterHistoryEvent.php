<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $minister_id
 * @property Carbon $occurred_on
 * @property string $code
 * @property string $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Minister $minister
 */
class MinisterHistoryEvent extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'minister_id',
        'occurred_on',
        'code',
        'description',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Minister, $this>
     */
    public function minister(): BelongsTo
    {
        return $this->belongsTo(Minister::class);
    }
}
