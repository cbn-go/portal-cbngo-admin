<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum JubanReportStatus: string implements HasColor, HasIcon, HasLabel
{
    case AGUARDANDO_TESOURARIA = 'aguardando_tesouraria';
    case AGUARDANDO_PRESIDENCIA = 'aguardando_presidencia';
    case AGUARDANDO_CONSELHO_FISCAL = 'aguardando_conselho_fiscal';
    case EM_REVISAO = 'em_revisao';
    case APROVADO = 'aprovado';
    case APROVADO_COM_RESSALVAS = 'aprovado_com_ressalvas';
    case REPROVADO = 'reprovado';
    case ASSINADO = 'assinado';
    case REABERTO = 'reaberto';

    public function getLabel(): string
    {
        return match ($this) {
            self::AGUARDANDO_TESOURARIA => 'Aguardando Tesoureiro',
            self::AGUARDANDO_PRESIDENCIA => 'Aguardando Presidente',
            self::AGUARDANDO_CONSELHO_FISCAL => 'Aguardando Conselho Fiscal',
            self::EM_REVISAO => 'Em Revisão pelo Conselho',
            self::APROVADO => 'Aprovado pelo Conselho Fiscal',
            self::APROVADO_COM_RESSALVAS => 'Aprovado com Ressalvas',
            self::REPROVADO => 'Reprovado pelo Conselho Fiscal',
            self::ASSINADO => 'Assinado',
            self::REABERTO => 'Reaberto',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::AGUARDANDO_TESOURARIA => 'warning',
            self::AGUARDANDO_PRESIDENCIA => 'info',
            self::AGUARDANDO_CONSELHO_FISCAL => 'primary',
            self::EM_REVISAO => 'warning',
            self::APROVADO => 'success',
            self::APROVADO_COM_RESSALVAS => 'warning',
            self::REPROVADO => 'danger',
            self::ASSINADO => 'success',
            self::REABERTO => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::AGUARDANDO_TESOURARIA => 'heroicon-m-clock',
            self::AGUARDANDO_PRESIDENCIA => 'heroicon-m-pencil-square',
            self::AGUARDANDO_CONSELHO_FISCAL => 'heroicon-m-scale',
            self::EM_REVISAO => 'heroicon-m-magnifying-glass',
            self::APROVADO => 'heroicon-m-check-badge',
            self::APROVADO_COM_RESSALVAS => 'heroicon-m-exclamation-triangle',
            self::REPROVADO => 'heroicon-m-x-circle',
            self::ASSINADO => 'heroicon-m-check-badge',
            self::REABERTO => 'heroicon-m-arrow-path',
        };
    }

    public function isLocked(): bool
    {
        return $this !== self::REABERTO;
    }

    public function isVisibleToFiscalCouncil(): bool
    {
        return in_array($this, [
            self::AGUARDANDO_CONSELHO_FISCAL,
            self::EM_REVISAO,
            self::APROVADO,
            self::APROVADO_COM_RESSALVAS,
            self::REPROVADO,
            self::ASSINADO,
        ], true);
    }
}
