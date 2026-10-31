<?php

namespace App\Models;

use App\Filament\Pages\Dashboard;
use App\Models\Concerns\HasImageVariants;
use Database\Factories\ChurchFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $cnpj
 * @property string|null $registration_number
 * @property string|null $pastor_name
 * @property int|null $municipality_id
 * @property string $city
 * @property string $state
 * @property string|null $neighborhood
 * @property string|null $zip_code
 * @property string|null $address
 * @property string|null $number
 * @property string|null $complement
 * @property string|null $latitude
 * @property string|null $longitude
 * @property string|null $phone
 * @property string|null $cellphone
 * @property string|null $email
 * @property array<string, mixed>|null $social_links
 * @property list<array{day: string, time: string, label: string|null}>|null $service_times
 * @property string|null $logo
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Church extends Model
{
    /** @use HasFactory<ChurchFactory> */
    use HasFactory;

    use HasImageVariants;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'cnpj',
        'registration_number',
        'pastor_name',
        'municipality_id',
        'city',
        'state',
        'neighborhood',
        'zip_code',
        'address',
        'number',
        'complement',
        'latitude',
        'longitude',
        'phone',
        'cellphone',
        'email',
        'social_links',
        'service_times',
        'logo',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'social_links' => 'array',
            'service_times' => 'array',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Church $church): void {
            if (blank($church->slug) && filled($church->name)) {
                $church->slug = static::generateUniqueSlug($church->name);
            }
        });

        static::saving(function (Church $church): void {
            if (filled($church->municipality_id)) {
                if ($church->isDirty('municipality_id') || blank($church->city)) {
                    $municipality = $church->municipality ?? Municipality::find($church->municipality_id);
                    if ($municipality) {
                        $church->city = $municipality->name;
                    }
                }
            } elseif (filled($church->city) && blank($church->municipality_id)) {
                $church->municipality_id = Municipality::findByNormalizedName($church->city)?->id;
            }
        });

        static::saved(function (): void {
            Cache::forget(Dashboard::STATS_CACHE_KEY);
            Cache::forget('cbngo:dashboard:stats:overview');
        });

        static::deleted(function (): void {
            Cache::forget(Dashboard::STATS_CACHE_KEY);
            Cache::forget('cbngo:dashboard:stats:overview');
        });
    }

    /**
     * Gera um slug único para a página pública da igreja a partir do nome.
     */
    public static function generateUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $slug = Str::slug($name);

        if (blank($slug)) {
            $slug = 'igreja';
        }

        $originalSlug = $slug;
        $count = 1;

        while (static::query()
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn (Builder $q): Builder => $q->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = "{$originalSlug}-{$count}";
            $count++;
        }

        return $slug;
    }

    /**
     * Vincula as igrejas aos municípios de Goiás pelo nome normalizado da cidade.
     *
     * Com `$onlyUnlinked` (padrão), só preenche igrejas ainda sem município:
     * é o que o deploy faz para igrejas cadastradas antes da base territorial.
     *
     * @return array{linked: int, unmatched: list<Church>}
     */
    public static function linkToMunicipalities(bool $onlyUnlinked = true): array
    {
        $linked = 0;
        $unmatched = [];

        $churches = static::query()
            ->when($onlyUnlinked, fn (Builder $query): Builder => $query->whereNull('municipality_id'))
            ->orderBy('id')
            ->get();

        foreach ($churches as $church) {
            $municipality = Municipality::findByNormalizedName($church->city);

            if ($municipality === null) {
                $unmatched[] = $church;

                continue;
            }

            $church->updateQuietly([
                'municipality_id' => $municipality->id,
                'city' => $municipality->name,
            ]);
            $linked++;
        }

        return ['linked' => $linked, 'unmatched' => $unmatched];
    }

    /**
     * @return BelongsTo<Municipality, $this>
     */
    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
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

    /**
     * @return HasMany<SocialProject, $this>
     */
    public function socialProjects(): HasMany
    {
        return $this->hasMany(SocialProject::class);
    }

    /**
     * Atributos com imagens no disco de mídia que ganham versões WebP.
     *
     * @return list<string>
     */
    public function imageAttributes(): array
    {
        return ['logo'];
    }
}
