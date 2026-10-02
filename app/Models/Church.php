<?php

namespace App\Models;

use Database\Factories\ChurchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string|null $cnpj
 * @property string|null $registration_number
 * @property string|null $pastor_name
 * @property string $city
 * @property string $state
 * @property string|null $neighborhood
 * @property string|null $zip_code
 * @property string|null $address
 * @property string|null $number
 * @property string|null $complement
 * @property string|null $phone
 * @property string|null $cellphone
 * @property string|null $email
 * @property array<string, mixed>|null $social_links
 * @property string|null $logo
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Church extends Model
{
    /** @use HasFactory<ChurchFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'cnpj',
        'registration_number',
        'pastor_name',
        'city',
        'state',
        'neighborhood',
        'zip_code',
        'address',
        'number',
        'complement',
        'phone',
        'cellphone',
        'email',
        'social_links',
        'logo',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'social_links' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<News, $this>
     */
    public function news(): HasMany
    {
        return $this->hasMany(News::class);
    }
}
