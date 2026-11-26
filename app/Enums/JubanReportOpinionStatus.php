<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum JubanReportOpinionStatus: string implements HasColor, HasLabel
{
    case DRAFT = 'draft';
    case ISSUED = 'issued';

    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFT => 'Rascunho',
            self::ISSUED => 'Emitido',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::ISSUED => 'success',
        };
    }
}
