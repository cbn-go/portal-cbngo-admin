<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum JubanCategoryKind: string implements HasColor, HasLabel
{
    case RECEITA = 'receita';
    case DESPESA = 'despesa';
    case AMBOS = 'ambos';

    public function getLabel(): string
    {
        return match ($this) {
            self::RECEITA => 'Receita',
            self::DESPESA => 'Despesa',
            self::AMBOS => 'Receita e Despesa',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::RECEITA => 'success',
            self::DESPESA => 'danger',
            self::AMBOS => 'info',
        };
    }

    public function allows(JubanTransactionType $type): bool
    {
        return $this === self::AMBOS || $this->value === $type->value;
    }
}
