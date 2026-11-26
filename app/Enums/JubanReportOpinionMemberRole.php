<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum JubanReportOpinionMemberRole: string implements HasLabel
{
    case RELATOR = 'relator';
    case VOGAL = 'vogal';

    public function getLabel(): string
    {
        return match ($this) {
            self::RELATOR => 'Relator',
            self::VOGAL => 'Vogal',
        };
    }
}
