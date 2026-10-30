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
    case CONPLEX = 'conplex';
    case CONSELHO_FISCAL = 'conselho_fiscal';
    case COORDENACAO_REGIONAL = 'coordenacao_regional';

    public function getLabel(): string
    {
        return match ($this) {
            self::PRESIDENCIA => 'Presidência',
            self::VICE_PRESIDENCIA => 'Vice-presidência',
            self::SECRETARIA => 'Secretaria',
            self::TESOURARIA => 'Tesouraria',
            self::CONPLEX => 'Conselho de Planejamento e Execução (CONPLEX)',
            self::CONSELHO_FISCAL => 'Conselho Fiscal',
            self::COORDENACAO_REGIONAL => 'Diretorias Regionais (AMIGO)',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::PRESIDENCIA => 'danger',
            self::VICE_PRESIDENCIA => 'warning',
            self::SECRETARIA => 'info',
            self::TESOURARIA => 'success',
            self::CONPLEX, self::CONSELHO_FISCAL => 'gray',
            self::COORDENACAO_REGIONAL => 'primary',
        };
    }

    /**
     * Cargos sugeridos no formulário. O campo continua livre para cargos novos.
     *
     * @return list<string>
     */
    public function roleSuggestions(): array
    {
        return match ($this) {
            self::PRESIDENCIA => ['Presidente'],
            self::VICE_PRESIDENCIA => ['Vice-Presidente', '1º Vice-Presidente', '2º Vice-Presidente'],
            self::SECRETARIA => ['1º Secretário de Atas', '2º Secretário de Atas'],
            self::TESOURARIA => ['1º Tesoureiro', '2º Tesoureiro'],
            self::CONPLEX => ['Vogal'],
            self::CONSELHO_FISCAL => ['Relator', '1º Vogal', '2º Vogal', '1º Suplente', '2º Suplente', '3º Suplente'],
            self::COORDENACAO_REGIONAL => ['Coordenador', 'Vice-Coordenador', '1º Secretário', '2º Secretário'],
        };
    }
}
