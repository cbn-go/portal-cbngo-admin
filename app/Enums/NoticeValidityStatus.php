<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum NoticeValidityStatus: string implements HasColor, HasIcon, HasLabel
{
    case ACTIVE = 'active';
    case SCHEDULED = 'scheduled';
    case EXPIRED = 'expired';
    case INACTIVE = 'inactive';

    public function getLabel(): string
    {
        return match ($this) {
            self::ACTIVE => 'Vigente',
            self::SCHEDULED => 'Agendado',
            self::EXPIRED => 'Expirado',
            self::INACTIVE => 'Inativo',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::ACTIVE => 'success',
            self::SCHEDULED => 'info',
            self::EXPIRED => 'danger',
            self::INACTIVE => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::ACTIVE => 'heroicon-m-check-circle',
            self::SCHEDULED => 'heroicon-m-clock',
            self::EXPIRED => 'heroicon-m-x-circle',
            self::INACTIVE => 'heroicon-m-no-symbol',
        };
    }
}
