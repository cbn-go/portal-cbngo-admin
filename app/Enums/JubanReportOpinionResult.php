<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum JubanReportOpinionResult: string implements HasColor, HasLabel
{
    case APROVADO = 'aprovado';
    case APROVADO_COM_RESSALVAS = 'aprovado_com_ressalvas';
    case REPROVADO = 'reprovado';

    public function getLabel(): string
    {
        return match ($this) {
            self::APROVADO => 'Favorável (Aprovado)',
            self::APROVADO_COM_RESSALVAS => 'Favorável com Ressalvas',
            self::REPROVADO => 'Desfavorável (Reprovado)',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::APROVADO => 'success',
            self::APROVADO_COM_RESSALVAS => 'warning',
            self::REPROVADO => 'danger',
        };
    }

    public function getConclusionText(): string
    {
        return match ($this) {
            self::APROVADO => 'a documentação se encontra em perfeita harmonia com os lançamentos contábeis',
            self::APROVADO_COM_RESSALVAS => 'a documentação se encontra em harmonia com os lançamentos contábeis, com as ressalvas abaixo',
            self::REPROVADO => 'a documentação não se encontra em harmonia com os lançamentos contábeis, pelos motivos abaixo',
        };
    }

    public function getRecommendationText(): string
    {
        return match ($this) {
            self::APROVADO => 'favorável que o balancete seja aprovado pelo CONPLEX desta',
            self::APROVADO_COM_RESSALVAS => 'favorável, com ressalvas, que o balancete seja aprovado pelo CONPLEX desta',
            self::REPROVADO => 'desfavorável à aprovação do balancete pelo CONPLEX desta',
        };
    }

    public function requiresReservations(): bool
    {
        return $this !== self::APROVADO;
    }
}
