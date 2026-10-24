<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Categorias de artigos. O valor persistido é o próprio rótulo exibido
 * pelo portal público, que filtra e lista por este campo sem mapeamento.
 */
enum ArticleCategory: string implements HasColor, HasIcon, HasLabel
{
    case TEOLOGIA = 'Teologia';
    case MISSOES = 'Missões';
    case LIDERANCA = 'Liderança';
    case FAMILIA = 'Família';
    case DISCIPULADO = 'Discipulado';
    case DEVOCIONAL = 'Devocional';

    public function getLabel(): string
    {
        return $this->value;
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::TEOLOGIA => 'info',
            self::MISSOES => 'success',
            self::LIDERANCA => 'warning',
            self::FAMILIA => 'primary',
            self::DISCIPULADO => 'gray',
            self::DEVOCIONAL => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::TEOLOGIA => 'heroicon-m-book-open',
            self::MISSOES => 'heroicon-m-globe-americas',
            self::LIDERANCA => 'heroicon-m-user-group',
            self::FAMILIA => 'heroicon-m-home',
            self::DISCIPULADO => 'heroicon-m-academic-cap',
            self::DEVOCIONAL => 'heroicon-m-sparkles',
        };
    }
}
