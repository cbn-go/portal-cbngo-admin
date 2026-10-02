<?php

namespace App\Models;

use Database\Factories\AuthorProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $pastoral_title
 * @property string|null $bio
 * @property string|null $avatar
 * @property array<string, mixed>|null $social_links
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AuthorProfile extends Model
{
    /** @use HasFactory<AuthorProfileFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
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

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
