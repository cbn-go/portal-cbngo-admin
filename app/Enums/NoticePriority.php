<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum NoticePriority: string implements HasColor, HasIcon, HasLabel
{
    case NORMAL = 'normal';
    case HIGH = 'high';
    case URGENT = 'urgent';

    public function getLabel(): string
    {
        return match ($this) {
            self::NORMAL => '1: Normal',
            self::HIGH => '2: Alta',
            self::URGENT => '3: Urgente',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::NORMAL => 'gray',
            self::HIGH => 'warning',
            self::URGENT => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::NORMAL => 'heroicon-m-information-circle',
            self::HIGH => 'heroicon-m-exclamation-circle',
            self::URGENT => 'heroicon-m-exclamation-triangle',
        };
    }
}
