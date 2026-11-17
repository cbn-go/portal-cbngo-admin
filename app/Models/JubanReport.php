<?php

namespace App\Models;

use App\Enums\JubanReportStatus;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Database\Factories\JubanReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $year
 * @property int $quarter
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
 */
class JubanReport extends Model
{
    /** @use HasFactory<JubanReportFactory> */
    use HasFactory;

    protected $fillable = [
        'year',
        'quarter',
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
     * Verifica se uma data de lançamento contábil recai dentro de um trimestre com relatório fechado/ativo.
     */
    public static function isDateLocked(string|CarbonInterface|null $date): bool
    {
        if (! $date) {
            return false;
        }

        $targetDate = Carbon::parse($date)->toDateString();

        return static::where('status', '!=', JubanReportStatus::REABERTO)
            ->whereDate('period_from', '<=', $targetDate)
            ->whereDate('period_until', '>=', $targetDate)
            ->exists();
    }
}
