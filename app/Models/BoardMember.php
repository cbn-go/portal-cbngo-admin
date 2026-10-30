<?php

namespace App\Models;

use App\Enums\AmigoRegion;
use App\Enums\BoardGroup;
use App\Enums\ClergyTitle;
use App\Models\Concerns\HasImageVariants;
use Database\Factories\BoardMemberFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property ClergyTitle|null $title
 * @property string $role
 * @property BoardGroup $group
 * @property AmigoRegion|null $region
 * @property string|null $term_label
 * @property Carbon|null $term_starts_on
 * @property Carbon|null $term_ends_on
 * @property string|null $photo
 * @property int $position
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $display_name
 *
 * @method static Builder<static> active()
 * @method static Builder<static> currentTerm()
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
        'title',
        'role',
        'group',
        'region',
        'term_label',
        'term_starts_on',
        'term_ends_on',
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
            'title' => ClergyTitle::class,
            'group' => BoardGroup::class,
            'region' => AmigoRegion::class,
            'term_starts_on' => 'date',
            'term_ends_on' => 'date',
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

        static::saving(function (BoardMember $member): void {
            // Região só faz sentido nas diretorias regionais.
            if ($member->group !== BoardGroup::COORDENACAO_REGIONAL) {
                $member->region = null;
            }

            $member->term_label = static::termLabelFor($member->term_starts_on, $member->term_ends_on) ?? $member->term_label;
        });
    }

    /**
     * Rótulo do mandato a partir das datas: "Triênio 2026–2029" para mandatos de
     * três anos, "Gestão 2024–2026" nos demais casos.
     */
    public static function termLabelFor(?\DateTimeInterface $startsOn, ?\DateTimeInterface $endsOn): ?string
    {
        if ($startsOn === null || $endsOn === null) {
            return null;
        }

        $start = (int) $startsOn->format('Y');
        $end = (int) $endsOn->format('Y');

        return ($end - $start === 3 ? 'Triênio' : 'Gestão')." {$start}–{$end}";
    }

    /**
     * Nome com o título eclesiástico, como exibido no portal ("Pr. André Luiz").
     */
    public function getDisplayNameAttribute(): string
    {
        return trim(($this->title?->value ?? '').' '.$this->name);
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
     * Mandato em curso hoje: começou e ainda não terminou.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeCurrentTerm(Builder $query): Builder
    {
        $today = today()->toDateString();

        return $query
            ->whereDate('term_starts_on', '<=', $today)
            ->where(fn (Builder $query) => $query->whereNull('term_ends_on')->orWhereDate('term_ends_on', '>=', $today));
    }

    /**
     * Mandato mais recente cadastrado, usado como padrão de novos membros.
     *
     * @return array{term_starts_on: string, term_ends_on: string|null}|null
     */
    public static function latestTerm(): ?array
    {
        $latest = static::query()->whereNotNull('term_starts_on')->orderByDesc('term_starts_on')->first();

        if ($latest === null) {
            return null;
        }

        return [
            'term_starts_on' => $latest->term_starts_on->toDateString(),
            'term_ends_on' => $latest->term_ends_on?->toDateString(),
        ];
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
