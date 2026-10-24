<?php

namespace App\Models;

use App\Models\Concerns\HasImageVariants;
use Database\Factories\AuthorProfileFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $user_id
 * @property string $slug
 * @property string|null $pastoral_title
 * @property string|null $bio
 * @property string|null $avatar
 * @property array<string, mixed>|null $social_links
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string|null $display_name
 */
class AuthorProfile extends Model
{
    /** @use HasFactory<AuthorProfileFactory> */
    use HasFactory;

    use HasImageVariants;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'slug',
        'pastoral_title',
        'bio',
        'avatar',
        'social_links',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'social_links' => 'array',
        ];
    }

    protected static function booted(): void
    {
        // O slug é gerado uma única vez para manter estável a URL pública /autores/{slug}.
        static::creating(function (AuthorProfile $profile): void {
            if (blank($profile->slug)) {
                $profile->slug = static::generateUniqueSlug((string) $profile->user?->name);
            }
        });
    }

    /**
     * Gera um slug único para a página pública do autor a partir do nome.
     */
    public static function generateUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $slug = Str::slug($name);

        if (blank($slug)) {
            $slug = 'autor';
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
     * Nome de exibição no portal público, vindo de `users.name`.
     *
     * @return Attribute<string|null, never>
     */
    protected function displayName(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->user?->name);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Atributos com imagens no disco de mídia que ganham versões WebP.
     *
     * @return list<string>
     */
    public function imageAttributes(): array
    {
        return ['avatar'];
    }
}
