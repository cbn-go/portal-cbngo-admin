<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Parte da imagem do banner que deve permanecer visível quando o portal
 * corta a imagem em telas estreitas (aplicada como object-position).
 */
enum BannerFocalPoint: string implements HasLabel
{
    case CENTER = 'center';
    case LEFT = 'left';
    case RIGHT = 'right';

    public function getLabel(): string
    {
        return match ($this) {
            self::CENTER => 'Centro',
            self::LEFT => 'Esquerda',
            self::RIGHT => 'Direita',
        };
    }

    /**
     * Valor CSS de object-position correspondente.
     */
    public function objectPosition(): string
    {
        return match ($this) {
            self::CENTER => 'center center',
            self::LEFT => 'left center',
            self::RIGHT => 'right center',
        };
    }
}
