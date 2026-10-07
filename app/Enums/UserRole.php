<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasColor, HasLabel
{
    case SUPER_ADMIN = 'super_admin';
    case CONVENTION_EDITOR = 'convention_editor';
    case AUTHOR = 'author';
    case CHURCH_REPRESENTATIVE = 'church_representative';

    public function getLabel(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Super Administrador',
            self::CONVENTION_EDITOR => 'Editor da Convenção',
            self::AUTHOR => 'Autor',
            self::CHURCH_REPRESENTATIVE => 'Representante de Igreja',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::SUPER_ADMIN => 'danger',
            self::CONVENTION_EDITOR => 'warning',
            self::AUTHOR => 'info',
            self::CHURCH_REPRESENTATIVE => 'success',
        };
    }

    public function isSuperAdmin(): bool
    {
        return $this === self::SUPER_ADMIN;
    }

    public function isConventionEditor(): bool
    {
        return $this === self::CONVENTION_EDITOR;
    }

    public function isAuthor(): bool
    {
        return $this === self::AUTHOR;
    }

    public function isChurchRepresentative(): bool
    {
        return $this === self::CHURCH_REPRESENTATIVE;
    }

    public function hasAdminPanelAccess(): bool
    {
        return in_array($this, [self::SUPER_ADMIN, self::CONVENTION_EDITOR], true);
    }

    public function hasPortalPanelAccess(): bool
    {
        return in_array($this, [self::SUPER_ADMIN, self::AUTHOR, self::CHURCH_REPRESENTATIVE], true);
    }
}
