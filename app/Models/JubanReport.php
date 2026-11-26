<?php

namespace App\Models;

use App\Enums\JubanReportInquiryStatus;
use App\Enums\JubanReportStatus;
use App\Enums\JubanSigningRole;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Database\Factories\JubanReportFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $year
 * @property int $quarter
 * @property int $version
 * @property Carbon $period_from
 * @property Carbon $period_until
 * @property JubanReportStatus $status
 * @property array<string, mixed>|null $snapshot
 * @property string|null $pdf_path
 * @property string|null $pdf_sha256
 * @property int|null $closed_by
 * @property Carbon|null $closed_at
 * @property int|null $reopened_by
 * @property Carbon|null $reopened_at
 * @property string|null $reopen_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $closedBy
 * @property-read User|null $reopenedBy
 * @property-read Collection<int, JubanReportSignature> $signatures
 * @property-read JubanReportSignature|null $treasurySignature
 * @property-read JubanReportSignature|null $presidencySignature
 */
class JubanReport extends Model
{
    /** @use HasFactory<JubanReportFactory> */
    use HasFactory;

    protected $fillable = [
        'year',
        'quarter',
        'version',
        'period_from',
        'period_until',
        'status',
        'snapshot',
        'pdf_path',
        'pdf_sha256',
        'closed_by',
        'closed_at',
        'reopened_by',
        'reopened_at',
        'reopen_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'quarter' => 'integer',
            'version' => 'integer',
            'period_from' => 'date',
            'period_until' => 'date',
            'status' => JubanReportStatus::class,
            'snapshot' => 'array',
            'closed_at' => 'datetime',
            'reopened_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reopenedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by');
    }

    /**
     * @return HasMany<JubanReportSignature, $this>
     */
    public function signatures(): HasMany
    {
        return $this->hasMany(JubanReportSignature::class, 'report_id');
    }

    /**
     * @return HasOne<JubanReportSignature, $this>
     */
    public function treasurySignature(): HasOne
    {
        return $this->hasOne(JubanReportSignature::class, 'report_id')
            ->where('role', JubanSigningRole::TESOURARIA);
    }

    /**
     * @return HasOne<JubanReportSignature, $this>
     */
    public function presidencySignature(): HasOne
    {
        return $this->hasOne(JubanReportSignature::class, 'report_id')
            ->where('role', JubanSigningRole::PRESIDENCIA);
    }

    /**
     * @return HasMany<JubanReportInquiry, $this>
     */
    public function inquiries(): HasMany
    {
        return $this->hasMany(JubanReportInquiry::class, 'report_id');
    }

    /**
     * @return HasMany<JubanReportInquiry, $this>
     */
    public function openInquiries(): HasMany
    {
        return $this->hasMany(JubanReportInquiry::class, 'report_id')
            ->where('status', JubanReportInquiryStatus::ABERTO);
    }

    public function hasOpenInquiries(): bool
    {
        return $this->openInquiries()->exists();
    }

    /**
     * Verifica se uma data de lançamento contábil recai dentro de um trimestre com relatório fechado/ativo.
     */
    public static function isDateLocked(string|CarbonInterface|null $date): bool
    {
        if (! $date) {
            return false;
        }

        $targetDate = Carbon::parse($date)->toDateString();

        $latestReport = static::query()
            ->whereDate('period_from', '<=', $targetDate)
            ->whereDate('period_until', '>=', $targetDate)
            ->orderByDesc('version')
            ->first();

        if (! $latestReport) {
            return false;
        }

        return $latestReport->status->isLocked();
    }
}
