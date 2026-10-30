<?php

namespace App\Support;

/**
 * Comparação da proporção de uma imagem com uma proporção de referência.
 */
class AspectRatio
{
    public const WIDESCREEN = 16 / 9;

    public const SQUARE = 1.0;

    /**
     * Diferença relativa entre a proporção da imagem e a de referência
     * (0.05 = 5%). Retorna INF para dimensões inválidas.
     */
    public static function deviation(int $width, int $height, float $target = self::WIDESCREEN): float
    {
        if ($width <= 0 || $height <= 0) {
            return INF;
        }

        return abs(($width / $height) - $target) / $target;
    }

    public static function isWithin(int $width, int $height, float $tolerance = 0.05, float $target = self::WIDESCREEN): bool
    {
        return self::deviation($width, $height, $target) <= $tolerance;
    }

    public static function isSquare(int $width, int $height, float $tolerance = 0.05): bool
    {
        return self::isWithin($width, $height, $tolerance, self::SQUARE);
    }

    /**
     * Proporção legível, como "1,79:1".
     */
    public static function format(int $width, int $height): string
    {
        return $height > 0 ? number_format($width / $height, 2, ',', '').':1' : '—';
    }
}
