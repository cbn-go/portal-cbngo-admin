<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\MinisterFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $registration_number
 * @property string $function
 * @property string $name
 * @property string $status
 * @property string $sectional
 * @property string $church_name
 * @property string|null $address
 * @property string|null $zip_code
 * @property string|null $phone
 * @property string|null $email
 * @property string $cpf
 * @property Carbon|null $birth_date
 * @property string|null $nationality
 * @property string|null $place_of_birth
 * @property string|null $education_level
 * @property string|null $marital_status
 * @property string|null $gender
 * @property string|null $profession
 * @property string|null $identity_document
 * @property string|null $identity_issuer
 * @property string|null $passport
 * @property Carbon|null $identity_issued_at
 * @property array<int, array<string, mixed>>|null $other_phones
 * @property array<int, array<string, mixed>>|null $occupational_roles
 * @property Carbon|null $registered_at
 * @property Carbon|null $source_updated_at
 * @property int|null $church_id
 * @property int|null $user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Church|null $church
 * @property-read User|null $user
 */
class Minister extends Model
{
    /** @use HasFactory<MinisterFactory> */
    use HasFactory;

    public const STATUS_ACTIVE = 'ATIVO(A)';

    public const STATUS_INACTIVE = 'INATIVO(A)';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'registration_number',
        'function',
        'name',
        'status',
        'sectional',
        'church_name',
        'address',
        'zip_code',
        'phone',
        'email',
        'cpf',
        'birth_date',
        'nationality',
        'place_of_birth',
        'education_level',
        'marital_status',
        'gender',
        'profession',
        'identity_document',
        'identity_issuer',
        'passport',
        'identity_issued_at',
        'other_phones',
        'occupational_roles',
        'registered_at',
        'source_updated_at',
        'church_id',
        'user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'identity_issued_at' => 'date',
            'other_phones' => 'array',
            'occupational_roles' => 'array',
            'registered_at' => 'datetime',
            'source_updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Church, $this>
     */
    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<MinisterFamilyMember, $this>
     */
    public function familyMembers(): HasMany
    {
        return $this->hasMany(MinisterFamilyMember::class);
    }

    /**
     * @return HasMany<MinisterHistoryEvent, $this>
     */
    public function historyEvents(): HasMany
    {
        return $this->hasMany(MinisterHistoryEvent::class);
    }

    /**
     * Evento de diplomação do ministro (código '1').
     */
    public function diplomaEvent(): ?MinisterHistoryEvent
    {
        return $this->historyEvents->firstWhere('code', '1')
            ?? $this->historyEvents()->where('code', '1')->first();
    }

    /**
     * Evento de ordenação do ministro (código '2').
     */
    public function ordinationEvent(): ?MinisterHistoryEvent
    {
        return $this->historyEvents->firstWhere('code', '2')
            ?? $this->historyEvents()->where('code', '2')->first();
    }

    /**
     * Anos de ministério (tempo desde a diplomação).
     */
    public function yearsOfMinistry(): ?int
    {
        $diploma = $this->diplomaEvent();

        if ($diploma === null || $diploma->occurred_on === null) {
            return null;
        }

        return (int) $diploma->occurred_on->diffInYears(now());
    }

    /**
     * Anos desde a ordenação pastoral/ministerial.
     */
    public function yearsSinceOrdination(): ?int
    {
        $ordenacao = $this->ordinationEvent();

        if ($ordenacao === null || $ordenacao->occurred_on === null) {
            return null;
        }

        return (int) $ordenacao->occurred_on->diffInYears(now());
    }

    public function isActiveOnRoll(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function activate(): void
    {
        $this->update(['status' => self::STATUS_ACTIVE]);
        $this->user?->update(['is_active' => true]);
    }

    public function deactivate(): void
    {
        $this->update(['status' => self::STATUS_INACTIVE]);
        $this->user?->update(['is_active' => false]);
    }

    /**
     * Invalida a senha atual e exige definição no próximo login por CPF.
     */
    public function forcePasswordReset(): void
    {
        $this->user?->update([
            'password' => null,
            'must_set_password' => true,
        ]);
    }

    /**
     * Cria ou atualiza o usuário de acesso vinculado ao ministro.
     */
    public function syncAccessUser(): User
    {
        $payload = [
            'name' => $this->name,
            'email' => $this->email,
            'cpf' => $this->cpf,
            'role' => UserRole::MINISTER,
            'is_active' => $this->isActiveOnRoll(),
        ];

        if ($this->user !== null) {
            $this->user->update($payload);

            return $this->user;
        }

        $user = User::query()->create([
            ...$payload,
            'password' => null,
            'must_set_password' => true,
        ]);

        $this->update(['user_id' => $user->id]);
        $this->setRelation('user', $user);

        return $user;
    }
}
