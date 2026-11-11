<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ChurchType: string implements HasColor, HasLabel
{
    case CHURCH = 'igreja';
    case MISSION = 'missao';

    public function getLabel(): string
    {
        return match ($this) {
            self::CHURCH => 'Igreja',
            self::MISSION => 'Missão',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::CHURCH => 'success',
            self::MISSION => 'warning',
        };
    }

    public function isMission(): bool
    {
        return $this === self::MISSION;
    }

    public function isChurch(): bool
    {
        return $this === self::CHURCH;
    }
}
