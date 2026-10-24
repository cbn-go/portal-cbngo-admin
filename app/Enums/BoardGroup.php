<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Órgãos da convenção exibidos em /diretoria no portal público. A ordem dos
 * casos é a ordem institucional de exibição das seções.
 */
enum BoardGroup: string implements HasColor, HasLabel
{
    case PRESIDENCIA = 'presidencia';
    case VICE_PRESIDENCIA = 'vice_presidencia';
    case SECRETARIA = 'secretaria';
    case TESOURARIA = 'tesouraria';
    case CONSELHO_FISCAL = 'conselho_fiscal';
    case COORDENACAO_REGIONAL = 'coordenacao_regional';

    public function getLabel(): string
    {
        return match ($this) {
            self::PRESIDENCIA => 'Presidência',
            self::VICE_PRESIDENCIA => 'Vice-presidências',
            self::SECRETARIA => 'Secretaria',
            self::TESOURARIA => 'Tesouraria',
            self::CONSELHO_FISCAL => 'Conselho Fiscal',
            self::COORDENACAO_REGIONAL => 'Coordenadores de Regiões',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::PRESIDENCIA => 'danger',
            self::VICE_PRESIDENCIA => 'warning',
            self::SECRETARIA => 'info',
            self::TESOURARIA => 'success',
            self::CONSELHO_FISCAL => 'gray',
            self::COORDENACAO_REGIONAL => 'primary',
        };
    }
}
