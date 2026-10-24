<?php

namespace App\Models\Concerns;

use App\Jobs\GenerateImageVariants;
use App\Support\MediaVariants;
use Illuminate\Database\Eloquent\Model;

/**
 * Mantém as versões WebP das imagens do model no disco de mídia: gera as
 * versões das imagens novas e apaga original + versões das imagens
 * substituídas, removidas ou do registro excluído.
 *
 * O model informa os atributos de imagem em imageAttributes(); atributos
 * com lista de caminhos (ex.: galeria) são tratados item a item.
 *
 * @mixin Model
 */
trait HasImageVariants
{
    /**
     * @return list<string>
     */
    abstract public function imageAttributes(): array;

    public static function bootHasImageVariants(): void
    {
        static::saved(function (self $model): void {
            $current = $model->currentImagePaths();
            $previous = $model->imagePaths(fn (string $attribute): mixed => $model->getOriginal($attribute));

            $added = array_values(array_diff($current, $previous));
            $removed = array_values(array_diff($previous, $current));

            if ($added !== []) {
                GenerateImageVariants::dispatchAfterResponse($added);
            }

            foreach ($removed as $path) {
                MediaVariants::deleteWithVariants($path);
            }
        });

        static::deleted(function (self $model): void {
            foreach ($model->currentImagePaths() as $path) {
                MediaVariants::deleteWithVariants($path);
            }
        });
    }

    /**
     * Caminhos de imagem preenchidos, a partir dos valores devolvidos por $value.
     *
     * @param  callable(string): mixed  $value
     * @return list<string>
     */
    public function imagePaths(callable $value): array
    {
        $paths = [];

        foreach ($this->imageAttributes() as $attribute) {
            foreach ((array) $value($attribute) as $path) {
                if (is_string($path) && $path !== '') {
                    $paths[] = $path;
                }
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * @return list<string>
     */
    public function currentImagePaths(): array
    {
        return $this->imagePaths(fn (string $attribute): mixed => $this->getAttribute($attribute));
    }
}
