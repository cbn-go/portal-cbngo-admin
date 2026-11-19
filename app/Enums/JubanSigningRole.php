<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum JubanSigningRole: string implements HasColor, HasIcon, HasLabel
{
    case TESOURARIA = 'tesouraria';
    case PRESIDENCIA = 'presidencia';

    public function getLabel(): string
    {
        return match ($this) {
            self::TESOURARIA => 'Tesouraria',
            self::PRESIDENCIA => 'Presidência',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::TESOURARIA => 'warning',
            self::PRESIDENCIA => 'info',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::TESOURARIA => 'heroicon-m-banknotes',
            self::PRESIDENCIA => 'heroicon-m-user-circle',
        };
    }
}
