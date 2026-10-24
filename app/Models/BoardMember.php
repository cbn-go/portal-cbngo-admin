<?php

namespace App\Models;

use App\Enums\BoardGroup;
use App\Models\Concerns\HasImageVariants;
use Database\Factories\BoardMemberFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $role
 * @property BoardGroup $group
 * @property string|null $region
 * @property string|null $term_label
 * @property string|null $photo
 * @property int $position
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static> active()
 */
class BoardMember extends Model
{
    /** @use HasFactory<BoardMemberFactory> */
    use HasFactory;

    use HasImageVariants;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'role',
        'group',
        'region',
        'term_label',
        'photo',
        'position',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'group' => BoardGroup::class,
            'position' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Ordem única para toda a diretoria: o painel reordena arrastando as linhas
        // e o portal agrupa por órgão mantendo essa ordem dentro de cada grupo.
        static::creating(function (BoardMember $member): void {
            if (blank($member->position)) {
                $member->position = (int) static::query()->max('position') + 1;
            }
        });
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Gestões cadastradas, da mais recente para a mais antiga, para o filtro do painel.
     *
     * @return array<string, string>
     */
    public static function termLabelOptions(): array
    {
        return static::query()
            ->whereNotNull('term_label')
            ->distinct()
            ->orderByDesc('term_label')
            ->pluck('term_label', 'term_label')
            ->all();
    }

    /**
     * Atributos com imagens no disco de mídia que ganham versões WebP.
     *
     * @return list<string>
     */
    public function imageAttributes(): array
    {
        return ['photo'];
    }
}
