<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $opinion_id
 * @property int $cancelled_by
 * @property string $pdf_path
 * @property string $pdf_sha256
 * @property string|null $reason
 * @property Carbon $cancelled_at
 * @property-read JubanReportOpinion $opinion
 * @property-read User $cancelledBy
 */
class JubanReportOpinionHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'opinion_id',
        'cancelled_by',
        'pdf_path',
        'pdf_sha256',
        'reason',
        'cancelled_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<JubanReportOpinion, $this>
     */
    public function opinion(): BelongsTo
    {
        return $this->belongsTo(JubanReportOpinion::class, 'opinion_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}
