<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum JubanTransactionType: string implements HasColor, HasLabel
{
    case RECEITA = 'receita';
    case DESPESA = 'despesa';

    public function getLabel(): string
    {
        return match ($this) {
            self::RECEITA => 'Receita',
            self::DESPESA => 'Despesa',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::RECEITA => 'success',
            self::DESPESA => 'danger',
        };
    }

    public function isReceita(): bool
    {
        return $this === self::RECEITA;
    }

    public function isDespesa(): bool
    {
        return $this === self::DESPESA;
    }
}
