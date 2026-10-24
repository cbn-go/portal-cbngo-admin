<?php

namespace App\Models;

use App\Enums\DocumentCategory;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string|null $description
 * @property DocumentCategory $category
 * @property string|null $file
 * @property string|null $external_url
 * @property string $file_format
 * @property int $position
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static> active()
 */
class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory;

    /**
     * Formato exibido pelo portal quando o documento é um link externo.
     */
    public const EXTERNAL_LINK_FORMAT = 'Página';

    /**
     * Extensões aceitas no upload e o formato exibido para cada uma.
     *
     * @var array<string, string>
     */
    public const FILE_FORMATS = [
        'pdf' => 'PDF',
        'doc' => 'DOC',
        'docx' => 'DOCX',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'slug',
        'description',
        'category',
        'file',
        'external_url',
        'position',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => DocumentCategory::class,
            'position' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Document $document): void {
            if (blank($document->position)) {
                $document->position = (int) static::query()->max('position') + 1;
            }
        });

        static::saving(function (Document $document): void {
            $document->file_format = self::detectFileFormat($document->file, $document->external_url);
        });
    }

    /**
     * Deduz o formato exibido no portal: a extensão do arquivo enviado tem
     * prioridade; sem arquivo, um link para PDF/DOC/DOCX mantém o formato do
     * arquivo e qualquer outro link é uma página.
     */
    public static function detectFileFormat(?string $file, ?string $externalUrl): string
    {
        if (filled($file)) {
            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

            return self::FILE_FORMATS[$extension] ?? strtoupper(substr($extension, 0, 10));
        }

        if (filled($externalUrl)) {
            $path = (string) parse_url($externalUrl, PHP_URL_PATH);
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

            return self::FILE_FORMATS[$extension] ?? self::EXTERNAL_LINK_FORMAT;
        }

        return self::FILE_FORMATS['pdf'];
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
