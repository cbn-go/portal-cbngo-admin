<?php

namespace App\Models;

use App\Enums\JubanPaymentMethod;
use App\Enums\JubanTransactionType;
use Database\Factories\JubanTransactionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

/**
 * @property int $id
 * @property JubanTransactionType $type
 * @property float|string $amount
 * @property Carbon $occurred_on
 * @property int $category_id
 * @property string $description
 * @property JubanPaymentMethod|null $payment_method
 * @property string|null $document_ref
 * @property string|null $notes
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read JubanCategory $category
 * @property-read User|null $creator
 * @property-read User|null $updater
 */
class JubanTransaction extends Model
{
    /** @use HasFactory<JubanTransactionFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'type',
        'amount',
        'occurred_on',
        'category_id',
        'description',
        'payment_method',
        'document_ref',
        'notes',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => JubanTransactionType::class,
            'amount' => 'decimal:2',
            'occurred_on' => 'date',
            'payment_method' => JubanPaymentMethod::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (JubanTransaction $transaction): void {
            if ((float) $transaction->amount <= 0.0) {
                throw new InvalidArgumentException('O valor da transação deve ser estritamente maior que zero.');
            }

            if (Auth::check()) {
                if (blank($transaction->created_by)) {
                    $transaction->created_by = (int) Auth::id();
                }
                $transaction->updated_by = (int) Auth::id();
            }
        });
    }

    /**
     * @return BelongsTo<JubanCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(JubanCategory::class, 'category_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * @param  Builder<JubanTransaction>  $query
     * @return Builder<JubanTransaction>
     */
    public function scopeReceitas(Builder $query): Builder
    {
        return $query->where('type', JubanTransactionType::RECEITA);
    }

    /**
     * @param  Builder<JubanTransaction>  $query
     * @return Builder<JubanTransaction>
     */
    public function scopeDespesas(Builder $query): Builder
    {
        return $query->where('type', JubanTransactionType::DESPESA);
    }

    /**
     * @param  Builder<JubanTransaction>  $query
     * @return Builder<JubanTransaction>
     */
    public function scopePeriod(Builder $query, Carbon|string $start, Carbon|string $end): Builder
    {
        return $query->whereBetween('occurred_on', [$start, $end]);
    }

    /**
     * @param  Builder<JubanTransaction>  $query
     * @return Builder<JubanTransaction>
     */
    public function scopeForMonth(Builder $query, int $year, int $month): Builder
    {
        return $query->whereYear('occurred_on', $year)
            ->whereMonth('occurred_on', $month);
    }

    public function isReceita(): bool
    {
        return $this->type === JubanTransactionType::RECEITA;
    }

    public function isDespesa(): bool
    {
        return $this->type === JubanTransactionType::DESPESA;
    }

    /**
     * Retorna o valor contábil com sinal (+ para receita, - para despesa).
     */
    public function signedAmount(): float
    {
        $numericAmount = (float) $this->amount;

        return $this->isReceita() ? $numericAmount : -$numericAmount;
    }
}
