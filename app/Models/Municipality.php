<?php

namespace App\Models;

use Database\Factories\MunicipalityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $ibge_code
 * @property string $name
 * @property string $slug
 * @property string $normalized_name
 * @property string $immediate_region_code
 * @property string $immediate_region_name
 * @property string $intermediate_region_code
 * @property string $intermediate_region_name
 * @property string|null $micro_region_name
 * @property string|null $meso_region_name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Municipality extends Model
{
    /** @use HasFactory<MunicipalityFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'ibge_code',
        'name',
        'slug',
        'normalized_name',
        'immediate_region_code',
        'immediate_region_name',
        'intermediate_region_code',
        'intermediate_region_name',
        'micro_region_name',
        'meso_region_name',
    ];

    protected static function booted(): void
    {
        static::saving(function (Municipality $municipality): void {
            if (blank($municipality->slug) && filled($municipality->name)) {
                $municipality->slug = Str::slug($municipality->name);
            }

            if (blank($municipality->normalized_name) && filled($municipality->name)) {
                $municipality->normalized_name = static::normalizeName($municipality->name);
            }
        });
    }

    /**
     * Normaliza nomes de cidades removendo acentos, pontuação e convertendo para minúsculas.
     * Ex.: "Goianésia" => "goianesia", "São Luís de Montes Belos" => "sao luis de montes belos",
     * "São João d'Aliança" => "sao joao d alianca".
     */
    public static function normalizeName(?string $name): string
    {
        if (blank($name)) {
            return '';
        }

        $normalized = Str::ascii($name);
        $normalized = mb_strtolower($normalized, 'UTF-8');
        $normalized = (string) preg_replace('/[^\w\s]/u', ' ', $normalized);
        $normalized = (string) preg_replace('/\s+/', ' ', $normalized);

        return trim($normalized);
    }

    /**
     * Busca município pelo nome normalizado.
     */
    public static function findByNormalizedName(?string $name): ?self
    {
        $normalized = static::normalizeName($name);
        if (blank($normalized)) {
            return null;
        }

        return static::query()->where('normalized_name', $normalized)->first();
    }

    /**
     * @return HasMany<Church, $this>
     */
    public function churches(): HasMany
    {
        return $this->hasMany(Church::class);
    }

    /**
     * @return HasMany<MunicipalityIndicator, $this>
     */
    public function indicators(): HasMany
    {
        return $this->hasMany(MunicipalityIndicator::class);
    }
}
