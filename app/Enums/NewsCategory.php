<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Categorias de notícias. O valor persistido é o próprio rótulo exibido
 * pelo portal público, que filtra e lista por este campo sem mapeamento.
 */
enum NewsCategory: string implements HasColor, HasIcon, HasLabel
{
    case GERAL = 'Geral';
    case COMUNICADO_OFICIAL = 'Comunicado Oficial';
    case MISSOES = 'Missões';
    case EDUCACAO_TEOLOGICA = 'Educação Teológica';
    case EVENTOS = 'Eventos';
    case ACAO_SOCIAL = 'Ação Social';

    public function getLabel(): string
    {
        return $this->value;
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::GERAL => 'gray',
            self::COMUNICADO_OFICIAL => 'danger',
            self::MISSOES => 'success',
            self::EDUCACAO_TEOLOGICA => 'info',
            self::EVENTOS => 'warning',
            self::ACAO_SOCIAL => 'primary',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::GERAL => 'heroicon-m-newspaper',
            self::COMUNICADO_OFICIAL => 'heroicon-m-megaphone',
            self::MISSOES => 'heroicon-m-globe-americas',
            self::EDUCACAO_TEOLOGICA => 'heroicon-m-academic-cap',
            self::EVENTOS => 'heroicon-m-calendar-days',
            self::ACAO_SOCIAL => 'heroicon-m-heart',
        };
    }
}
