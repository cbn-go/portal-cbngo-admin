<?php

namespace App\Models;

use App\Enums\JubanReportOpinionMemberRole;
use App\Enums\JubanReportOpinionResult;
use App\Enums\JubanReportOpinionStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $report_id
 * @property JubanReportOpinionResult $result
 * @property JubanReportOpinionStatus $status
 * @property Carbon $meeting_date
 * @property string $meeting_place
 * @property string $city
 * @property Carbon $issued_on
 * @property string|null $reservations
 * @property int $drafted_by
 * @property Carbon|null $issued_at
 * @property string|null $pdf_path
 * @property string|null $pdf_sha256
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read JubanReport $report
 * @property-read User $draftedBy
 * @property-read Collection<int, JubanReportOpinionMember> $members
 * @property-read JubanReportOpinionMember|null $relatorMember
 * @property-read Collection<int, JubanReportOpinionMember> $vogalMembers
 * @property-read Collection<int, JubanReportOpinionHistory> $histories
 * @property-read Collection<int, JubanReportOpinionComment> $comments
 */
class JubanReportOpinion extends Model
{
    use HasFactory;

    protected $fillable = [
        'report_id',
        'result',
        'status',
        'meeting_date',
        'meeting_place',
        'city',
        'issued_on',
        'reservations',
        'drafted_by',
        'issued_at',
        'pdf_path',
        'pdf_sha256',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'result' => JubanReportOpinionResult::class,
            'status' => JubanReportOpinionStatus::class,
            'meeting_date' => 'date',
            'issued_on' => 'date',
            'issued_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<JubanReport, $this>
     */
    public function report(): BelongsTo
    {
        return $this->belongsTo(JubanReport::class, 'report_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function draftedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'drafted_by');
    }

    /**
     * @return HasMany<JubanReportOpinionMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(JubanReportOpinionMember::class, 'opinion_id')->orderBy('position');
    }

    /**
     * @return HasOne<JubanReportOpinionMember, $this>
     */
    public function relatorMember(): HasOne
    {
        return $this->hasOne(JubanReportOpinionMember::class, 'opinion_id')
            ->where('role', JubanReportOpinionMemberRole::RELATOR);
    }

    /**
     * @return HasMany<JubanReportOpinionMember, $this>
     */
    public function vogalMembers(): HasMany
    {
        return $this->hasMany(JubanReportOpinionMember::class, 'opinion_id')
            ->where('role', JubanReportOpinionMemberRole::VOGAL)
            ->orderBy('position');
    }

    /**
     * @return HasMany<JubanReportOpinionHistory, $this>
     */
    public function histories(): HasMany
    {
        return $this->hasMany(JubanReportOpinionHistory::class, 'opinion_id')->latest();
    }

    /**
     * @return HasMany<JubanReportOpinionComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(JubanReportOpinionComment::class, 'opinion_id')->latest();
    }

    public function isDraft(): bool
    {
        return $this->status === JubanReportOpinionStatus::DRAFT;
    }

    public function isIssued(): bool
    {
        return $this->status === JubanReportOpinionStatus::ISSUED;
    }

    public function canBeEditedBy(User $user): bool
    {
        if (! $this->isDraft()) {
            return false;
        }

        $relator = $this->relatorMember;
        if (! $relator) {
            return $this->drafted_by === $user->id;
        }

        return $relator->member->user_id === $user->id;
    }

    public function canBeIssuedBy(User $user): bool
    {
        if (! $this->isDraft()) {
            return false;
        }

        $relator = $this->relatorMember;
        if (! $relator) {
            return false;
        }

        return $relator->member->user_id === $user->id;
    }

    public function canBeCancelledBy(User $user): bool
    {
        if (! $this->isIssued()) {
            return false;
        }

        if ($this->hasSignatures()) {
            return false;
        }

        $relator = $this->relatorMember;
        if (! $relator) {
            return false;
        }

        return $relator->member->user_id === $user->id;
    }

    public function hasSignatures(): bool
    {
        // Se houver assinaturas em juban_report_signatures (ou coluna opinion_id)
        return false;
    }
}
