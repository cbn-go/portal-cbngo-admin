<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $opinion_id
 * @property int $user_id
 * @property string $comment
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read JubanReportOpinion $opinion
 * @property-read User $user
 */
class JubanReportOpinionComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'opinion_id',
        'user_id',
        'comment',
    ];

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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
