<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum PublishStatus: string implements HasColor, HasIcon, HasLabel
{
    case DRAFT = 'draft';
    case PENDING_REVIEW = 'pending_review';
    case PUBLISHED = 'published';
    case ARCHIVED = 'archived';

    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFT => 'Rascunho',
            self::PENDING_REVIEW => 'Pendente de Revisão',
            self::PUBLISHED => 'Publicado',
            self::ARCHIVED => 'Arquivado',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::PENDING_REVIEW => 'warning',
            self::PUBLISHED => 'success',
            self::ARCHIVED => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::DRAFT => 'heroicon-m-pencil-square',
            self::PENDING_REVIEW => 'heroicon-m-clock',
            self::PUBLISHED => 'heroicon-m-check-circle',
            self::ARCHIVED => 'heroicon-m-archive-box',
        };
    }
}
