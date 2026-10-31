<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Faixas do índice de prioridade municipal (0 a 100) do Painel de Estratégia.
 */
enum PriorityBand: string implements HasColor, HasLabel
{
    case HIGH = 'alta';
    case MEDIUM = 'media';
    case LOW = 'baixa';

    public function getLabel(): string
    {
        return match ($this) {
            self::HIGH => 'Alta (70 ou mais)',
            self::MEDIUM => 'Média (40 a 69)',
            self::LOW => 'Baixa (menos de 40)',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::HIGH => 'danger',
            self::MEDIUM => 'warning',
            self::LOW => 'gray',
        };
    }

    public static function forScore(?float $score): ?self
    {
        return match (true) {
            $score === null => null,
            $score >= 70 => self::HIGH,
            $score >= 40 => self::MEDIUM,
            default => self::LOW,
        };
    }
}
