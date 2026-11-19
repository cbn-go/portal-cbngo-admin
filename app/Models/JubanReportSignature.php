<?php

namespace App\Models;

use App\Enums\JubanSigningRole;
use Carbon\Carbon;
use Database\Factories\JubanReportSignatureFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $report_id
 * @property int $user_id
 * @property JubanSigningRole $role
 * @property int $passkey_id
 * @property string $nonce
 * @property string $challenge
 * @property string $client_data_json
 * @property string $authenticator_data
 * @property string $signature
 * @property string $pdf_sha256
 * @property Carbon $signed_at
 * @property string|null $ip
 * @property string|null $user_agent
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read JubanReport $report
 * @property-read User $user
 * @property-read Passkey $passkey
 */
class JubanReportSignature extends Model
{
    /** @use HasFactory<JubanReportSignatureFactory> */
    use HasFactory;

    protected $fillable = [
        'report_id',
        'user_id',
        'role',
        'passkey_id',
        'nonce',
        'challenge',
        'client_data_json',
        'authenticator_data',
        'signature',
        'pdf_sha256',
        'signed_at',
        'ip',
        'user_agent',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => JubanSigningRole::class,
            'signed_at' => 'datetime',
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<Passkey, $this>
     */
    public function passkey(): BelongsTo
    {
        return $this->belongsTo(Passkey::class, 'passkey_id');
    }
}
