<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum JubanSigningRole: string implements HasColor, HasIcon, HasLabel
{
    case TESOURARIA = 'tesouraria';
    case PRESIDENCIA = 'presidencia';
    case CONSELHO_RELATOR = 'conselho_relator';
    case CONSELHO_VOGAL_1 = 'conselho_vogal_1';
    case CONSELHO_VOGAL_2 = 'conselho_vogal_2';

    public function getLabel(): string
    {
        return match ($this) {
            self::TESOURARIA => 'Tesouraria',
            self::PRESIDENCIA => 'Presidência',
            self::CONSELHO_RELATOR => 'Conselho Fiscal - Relator',
            self::CONSELHO_VOGAL_1 => 'Conselho Fiscal - 1º Vogal',
            self::CONSELHO_VOGAL_2 => 'Conselho Fiscal - 2º Vogal',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::TESOURARIA => 'warning',
            self::PRESIDENCIA => 'info',
            self::CONSELHO_RELATOR => 'success',
            self::CONSELHO_VOGAL_1 => 'primary',
            self::CONSELHO_VOGAL_2 => 'primary',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::TESOURARIA => 'heroicon-m-banknotes',
            self::PRESIDENCIA => 'heroicon-m-user-circle',
            self::CONSELHO_RELATOR => 'heroicon-m-pencil-square',
            self::CONSELHO_VOGAL_1 => 'heroicon-m-check-badge',
            self::CONSELHO_VOGAL_2 => 'heroicon-m-check-badge',
        };
    }

    public function isFiscalCouncil(): bool
    {
        return in_array($this, [
            self::CONSELHO_RELATOR,
            self::CONSELHO_VOGAL_1,
            self::CONSELHO_VOGAL_2,
        ], true);
    }
}
