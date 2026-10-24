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
 * "abc-card.webp" e "abc-cover.webp" no disco de mídia, ao lado do original.
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

        foreach (array_keys(self::sizes()) as $variant) {
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

        try {
            $manager = new ImageManager(new Driver);
            $contents = (string) $disk->get($path);
            $quality = (int) config('media.webp_quality', 80);
            $sizes = self::sizes();

            foreach ($pending as $variant => $variantPath) {
                $encoded = $manager->read($contents)
                    ->scaleDown(width: $sizes[$variant])
                    ->toWebp(quality: $quality);

                $disk->put($variantPath, $encoded->toString(), 'public');
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
     * Remove o original e todas as suas versões.
     */
    public static function deleteWithVariants(string $path): void
    {
        self::disk()->delete([$path, ...array_values(self::pathsFor($path))]);
    }
}
