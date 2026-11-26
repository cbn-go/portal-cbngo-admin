<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum JubanReportInquiryStatus: string implements HasColor, HasIcon, HasLabel
{
    case ABERTO = 'aberto';
    case RESPONDIDO = 'respondido';
    case ENCERRADO_SEM_RESPOSTA = 'encerrado_sem_resposta';

    public function getLabel(): string
    {
        return match ($this) {
            self::ABERTO => 'Pendente',
            self::RESPONDIDO => 'Respondido',
            self::ENCERRADO_SEM_RESPOSTA => 'Encerrado sem Resposta',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::ABERTO => 'warning',
            self::RESPONDIDO => 'success',
            self::ENCERRADO_SEM_RESPOSTA => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::ABERTO => 'heroicon-m-clock',
            self::RESPONDIDO => 'heroicon-m-check-circle',
            self::ENCERRADO_SEM_RESPOSTA => 'heroicon-m-x-circle',
        };
    }
}
