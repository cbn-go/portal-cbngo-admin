<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Categorias dos documentos institucionais. O valor persistido é o
 * identificador em minúsculas que o portal público já utiliza.
 */
enum DocumentCategory: string implements HasColor, HasIcon, HasLabel
{
    case ESTATUTO = 'estatuto';
    case REGIMENTO = 'regimento';
    case PACTO = 'pacto';
    case ATAS = 'atas';
    case OUTROS = 'outros';

    public function getLabel(): string
    {
        return match ($this) {
            self::ESTATUTO => 'Estatuto',
            self::REGIMENTO => 'Regimento Interno',
            self::PACTO => 'Pacto',
            self::ATAS => 'Atas',
            self::OUTROS => 'Outros',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::ESTATUTO => 'danger',
            self::REGIMENTO => 'warning',
            self::PACTO => 'success',
            self::ATAS => 'info',
            self::OUTROS => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::ESTATUTO => 'heroicon-m-scale',
            self::REGIMENTO => 'heroicon-m-book-open',
            self::PACTO => 'heroicon-m-hand-raised',
            self::ATAS => 'heroicon-m-clipboard-document-list',
            self::OUTROS => 'heroicon-m-document',
        };
    }
}
