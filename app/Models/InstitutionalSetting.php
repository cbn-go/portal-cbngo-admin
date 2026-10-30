<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $email
 * @property string|null $phone
 * @property string|null $whatsapp
 * @property string|null $office_hours
 * @property string|null $street
 * @property string|null $number
 * @property string|null $complement
 * @property string|null $district
 * @property string $city
 * @property string $state
 * @property string|null $postal_code
 * @property string|null $maps_url
 * @property string|null $instagram_url
 * @property string|null $facebook_url
 * @property string|null $youtube_url
 * @property string|null $tiktok_url
 * @property string|null $spotify_url
 * @property string|null $linkedin_url
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $updater
 * @property-read string $full_address
 */
class InstitutionalSetting extends Model
{
    use HasFactory;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'email',
        'phone',
        'whatsapp',
        'office_hours',
        'street',
        'number',
        'complement',
        'district',
        'city',
        'state',
        'postal_code',
        'maps_url',
        'instagram_url',
        'facebook_url',
        'youtube_url',
        'tiktok_url',
        'spotify_url',
        'linkedin_url',
        'updated_by',
    ];

    /**
     * Singleton getter para as configurações institucionais.
     */
    public static function current(): self
    {
        /** @var self $setting */
        $setting = static::query()->firstOrCreate(
            ['id' => 1],
            [
                'email' => 'contato@cbngo.org.br',
                'phone' => '(62) 3223-0000',
                'whatsapp' => '(62) 98100-0000',
                'office_hours' => 'Segunda a Sexta, das 08h às 17h',
                'street' => 'Rua 04',
                'number' => '515',
                'complement' => '12º andar, sala 1.207, Edifício Parthenon Center',
                'district' => 'Setor Central',
                'city' => 'Goiânia',
                'state' => 'GO',
                'postal_code' => '74020-045',
                'maps_url' => 'https://maps.google.com/?q=Rua+04,+515+-+Setor+Central,+Goiânia+-+GO,+74020-045',
                'instagram_url' => 'https://www.instagram.com/cbngoias/',
                'facebook_url' => 'https://www.facebook.com/cbngoias/',
                'youtube_url' => 'https://www.youtube.com/@cbngoias',
            ]
        );

        return $setting;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Retorna o endereço concatenado formatado.
     */
    public function getFullAddressAttribute(): string
    {
        $parts = [];

        if ($this->street) {
            $streetPart = $this->street;
            if ($this->number) {
                $streetPart .= ', '.$this->number;
            }
            if ($this->complement) {
                $streetPart .= ' - '.$this->complement;
            }
            $parts[] = $streetPart;
        }

        if ($this->district) {
            $parts[] = $this->district;
        }

        if ($this->city) {
            $cityState = $this->city;
            if ($this->state) {
                $cityState .= ' - '.$this->state;
            }
            $parts[] = $cityState;
        }

        if ($this->postal_code) {
            $parts[] = 'CEP '.$this->postal_code;
        }

        return implode(', ', $parts);
    }
}
