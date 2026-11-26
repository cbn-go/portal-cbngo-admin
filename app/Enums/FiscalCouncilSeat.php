<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum FiscalCouncilSeat: string implements HasColor, HasLabel
{
    case RELATOR = 'relator';
    case VOGAL_1 = 'vogal_1';
    case VOGAL_2 = 'vogal_2';
    case SUPLENTE_1 = 'suplente_1';
    case SUPLENTE_2 = 'suplente_2';
    case SUPLENTE_3 = 'suplente_3';

    public function getLabel(): string
    {
        return match ($this) {
            self::RELATOR => 'Relator',
            self::VOGAL_1 => '1º Vogal',
            self::VOGAL_2 => '2ª Vogal',
            self::SUPLENTE_1 => '1º Suplente',
            self::SUPLENTE_2 => '2º Suplente',
            self::SUPLENTE_3 => '3º Suplente',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::RELATOR => 'primary',
            self::VOGAL_1, self::VOGAL_2 => 'info',
            self::SUPLENTE_1, self::SUPLENTE_2, self::SUPLENTE_3 => 'gray',
        };
    }

    public function isEfetivo(): bool
    {
        return in_array($this, [self::RELATOR, self::VOGAL_1, self::VOGAL_2], true);
    }

    public function isSuplente(): bool
    {
        return in_array($this, [self::SUPLENTE_1, self::SUPLENTE_2, self::SUPLENTE_3], true);
    }
}
