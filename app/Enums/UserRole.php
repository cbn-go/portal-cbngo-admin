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
    case MINISTER = 'minister';
    case JUBAN_TREASURER = 'juban_treasurer';
    case JUBAN_PRESIDENT = 'juban_president';

    public function getLabel(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Super Administrador',
            self::CONVENTION_EDITOR => 'Editor da Convenção',
            self::AUTHOR => 'Autor',
            self::CHURCH_REPRESENTATIVE => 'Representante de Igreja',
            self::MINISTER => 'Ministro Ormiban',
            self::JUBAN_TREASURER => 'Tesouraria JUBAN',
            self::JUBAN_PRESIDENT => 'Presidência JUBAN',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::SUPER_ADMIN => 'danger',
            self::CONVENTION_EDITOR => 'warning',
            self::AUTHOR => 'info',
            self::CHURCH_REPRESENTATIVE => 'success',
            self::MINISTER => 'primary',
            self::JUBAN_TREASURER => 'info',
            self::JUBAN_PRESIDENT => 'primary',
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

    public function isMinister(): bool
    {
        return $this === self::MINISTER;
    }

    public function isJubanTreasurer(): bool
    {
        return $this === self::JUBAN_TREASURER;
    }

    public function isJubanPresident(): bool
    {
        return $this === self::JUBAN_PRESIDENT;
    }

    public function canManageJubanTreasury(): bool
    {
        return in_array($this, [self::SUPER_ADMIN, self::CONVENTION_EDITOR, self::JUBAN_TREASURER], true);
    }

    public function canViewJubanTreasury(): bool
    {
        return in_array($this, [self::SUPER_ADMIN, self::CONVENTION_EDITOR, self::JUBAN_TREASURER, self::JUBAN_PRESIDENT], true);
    }

    public function hasAdminPanelAccess(): bool
    {
        return in_array($this, [self::SUPER_ADMIN, self::CONVENTION_EDITOR], true);
    }

    public function hasPortalPanelAccess(): bool
    {
        return in_array($this, [self::SUPER_ADMIN, self::AUTHOR, self::CHURCH_REPRESENTATIVE], true);
    }

    public function hasOrmibanPanelAccess(): bool
    {
        return in_array($this, [self::SUPER_ADMIN, self::MINISTER], true);
    }

    public function hasJubanPanelAccess(): bool
    {
        return $this->canViewJubanTreasury();
    }

    public function canAccessStrategy(): bool
    {
        return in_array($this, [self::SUPER_ADMIN, self::CONVENTION_EDITOR, self::MINISTER], true);
    }
}
