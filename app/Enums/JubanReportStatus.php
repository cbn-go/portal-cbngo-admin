<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum JubanReportStatus: string implements HasColor, HasIcon, HasLabel
{
    case AGUARDANDO_TESOURARIA = 'aguardando_tesouraria';
    case AGUARDANDO_PRESIDENCIA = 'aguardando_presidencia';
    case ASSINADO = 'assinado';
    case REABERTO = 'reaberto';

    public function getLabel(): string
    {
        return match ($this) {
            self::AGUARDANDO_TESOURARIA => 'Aguardando Tesoureiro',
            self::AGUARDANDO_PRESIDENCIA => 'Aguardando Presidente',
            self::ASSINADO => 'Assinado',
            self::REABERTO => 'Reaberto',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::AGUARDANDO_TESOURARIA => 'warning',
            self::AGUARDANDO_PRESIDENCIA => 'info',
            self::ASSINADO => 'success',
            self::REABERTO => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::AGUARDANDO_TESOURARIA => 'heroicon-m-clock',
            self::AGUARDANDO_PRESIDENCIA => 'heroicon-m-pencil-square',
            self::ASSINADO => 'heroicon-m-check-badge',
            self::REABERTO => 'heroicon-m-arrow-path',
        };
    }

    public function isLocked(): bool
    {
        return $this !== self::REABERTO;
    }
}
