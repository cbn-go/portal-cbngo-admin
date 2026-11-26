<?php

namespace App\Models;

use App\Enums\JubanReportInquiryStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $report_id
 * @property int|null $transaction_id
 * @property int $asked_by
 * @property string $question
 * @property Carbon $asked_at
 * @property JubanReportInquiryStatus $status
 * @property int|null $answered_by
 * @property string|null $answer
 * @property Carbon|null $answered_at
 * @property string|null $attachment_path
 * @property int|null $closed_without_answer_by
 * @property Carbon|null $closed_without_answer_at
 * @property string|null $closed_without_answer_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read JubanReport $report
 * @property-read JubanTransaction|null $transaction
 * @property-read User $askedBy
 * @property-read User|null $answeredBy
 * @property-read User|null $closedWithoutAnswerBy
 */
class JubanReportInquiry extends Model
{
    use HasFactory;

    protected $fillable = [
        'report_id',
        'transaction_id',
        'asked_by',
        'question',
        'asked_at',
        'status',
        'answered_by',
        'answer',
        'answered_at',
        'attachment_path',
        'closed_without_answer_by',
        'closed_without_answer_at',
        'closed_without_answer_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'asked_at' => 'datetime',
            'answered_at' => 'datetime',
            'closed_without_answer_at' => 'datetime',
            'status' => JubanReportInquiryStatus::class,
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
     * @return BelongsTo<JubanTransaction, $this>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(JubanTransaction::class, 'transaction_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function askedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asked_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function answeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'answered_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function closedWithoutAnswerBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_without_answer_by');
    }

    public function isOpen(): bool
    {
        return $this->status === JubanReportInquiryStatus::ABERTO;
    }

    public function isAnswered(): bool
    {
        return $this->status === JubanReportInquiryStatus::RESPONDIDO;
    }

    public function isClosedWithoutAnswer(): bool
    {
        return $this->status === JubanReportInquiryStatus::ENCERRADO_SEM_RESPOSTA;
    }
}
