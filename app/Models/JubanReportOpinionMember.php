<?php

namespace App\Models;

use App\Enums\JubanReportOpinionMemberRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $opinion_id
 * @property int $member_id
 * @property JubanReportOpinionMemberRole $role
 * @property int $position
 * @property-read JubanReportOpinion $opinion
 * @property-read FiscalCouncilMember $member
 */
class JubanReportOpinionMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'opinion_id',
        'member_id',
        'role',
        'position',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => JubanReportOpinionMemberRole::class,
            'position' => 'integer',
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
     * @return BelongsTo<FiscalCouncilMember, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(FiscalCouncilMember::class, 'member_id');
    }
}
