<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Throwable;

/**
 * Versões WebP otimizadas das imagens enviadas pelo painel. Para
 * "news/featured/abc.jpg" grava "news/featured/abc-thumb.webp",
 * "abc-card.webp", "abc-cover.webp" e "abc-full.webp" no disco de mídia, ao lado do original.
 */
class MediaVariants
{
    public static function diskName(): string
    {
        return (string) config('media.disk', 'media');
    }

    public static function disk(): Filesystem
    {
        return Storage::disk(self::diskName());
    }

    /**
     * @return array<string, int> nome da versão => largura máxima em pixels
     */
    public static function sizes(): array
    {
        /** @var array<string, int> $sizes */
        $sizes = config('media.variants', []);

        return $sizes;
    }

    /**
     * @return array<string, int>
     */
    public static function sizesFor(string $path): array
    {
        if (str_starts_with($path, 'banners/')) {
            /** @var array<string, int> $sizes */
            $sizes = config('media.banner_variants', [
                'thumb' => 160,
                'card' => 800,
                'cover' => 1080,
            ]);

            return $sizes;
        }

        return self::sizes();
    }

    /**
     * Caminho de uma versão: "dir/nome.jpg" + "card" => "dir/nome-card.webp".
     */
    public static function pathFor(string $path, string $variant): string
    {
        $info = pathinfo($path);
        $directory = ($info['dirname'] ?? '.') === '.' ? '' : $info['dirname'].'/';

        return $directory.$info['filename'].'-'.$variant.'.webp';
    }

    /**
     * @return array<string, string> nome da versão => caminho
     */
    public static function pathsFor(string $path): array
    {
        $paths = [];

        foreach (array_keys(self::sizesFor($path)) as $variant) {
            $paths[$variant] = self::pathFor($path, $variant);
        }

        return $paths;
    }

    /**
     * Gera as versões que faltam (ou todas, com $force). Retorna quantas foram gravadas.
     */
    public static function generate(string $path, bool $force = false): int
    {
        $disk = self::disk();

        if (! $disk->exists($path)) {
            return 0;
        }

        $pending = array_filter(
            self::pathsFor($path),
            fn (string $variantPath): bool => $force || ! $disk->exists($variantPath),
        );

        if ($pending === []) {
            return 0;
        }

        self::raiseMemoryLimit();

        try {
            $image = (new ImageManager(new Driver))->read((string) $disk->get($path));
            $quality = (int) config('media.webp_quality', 85);
            $sizes = self::sizesFor($path);

            if ($ratio = self::aspectRatioFor($path)) {
                [$width, $height] = self::cropBox($image->width(), $image->height(), $ratio);
                $image->cover($width, $height);
            }

            // Da maior para a menor: a imagem é aberta uma vez só e reduzida
            // a cada passo, o que mantém o consumo de memória baixo.
            arsort($sizes);

            foreach ($sizes as $variant => $width) {
                $image->scaleDown(width: $width);

                if (isset($pending[$variant])) {
                    $disk->put($pending[$variant], $image->toWebp(quality: $quality)->toString(), 'public');
                }
            }
        } catch (Throwable $exception) {
            Log::warning('Falha ao gerar versões WebP da imagem.', [
                'path' => $path,
                'error' => $exception->getMessage(),
            ]);

            return 0;
        }

        return count($pending);
    }

    /**
     * @return array{int, int}|null [largura, altura] da proporção, ou null para manter a do original
     */
    public static function aspectRatioFor(string $path): ?array
    {
        /** @var array<string, array{int, int}> $ratios */
        $ratios = config('media.aspect_ratios', []);

        foreach ($ratios as $prefix => $ratio) {
            if (str_starts_with($path, $prefix)) {
                return $ratio;
            }
        }

        return null;
    }

    /**
     * Maior área com a proporção pedida que cabe na imagem, sem ampliar.
     *
     * @param  array{int, int}  $ratio
     * @return array{int, int}
     */
    public static function cropBox(int $width, int $height, array $ratio): array
    {
        [$ratioWidth, $ratioHeight] = $ratio;

        if ($width * $ratioHeight > $height * $ratioWidth) {
            return [(int) round($height * $ratioWidth / $ratioHeight), $height];
        }

        return [$width, (int) round($width * $ratioHeight / $ratioWidth)];
    }

    private static function raiseMemoryLimit(): void
    {
        $target = (string) config('media.memory_limit', '512M');
        $current = (string) ini_get('memory_limit');

        if ($current !== '-1' && self::bytes($current) < self::bytes($target)) {
            @ini_set('memory_limit', $target);
        }
    }

    private static function bytes(string $value): int
    {
        $number = (int) $value;

        return match (strtoupper(substr(trim($value), -1))) {
            'G' => $number * 1024 ** 3,
            'M' => $number * 1024 ** 2,
            'K' => $number * 1024,
            default => $number,
        };
    }

    /**
     * Remove o original e todas as suas versões.
     */
    public static function deleteWithVariants(string $path): void
    {
        self::disk()->delete([$path, ...array_values(self::pathsFor($path))]);
    }
}
