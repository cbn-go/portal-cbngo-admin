<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Categorias dos projetos sociais, seguindo as dimensões da missão integral
 * do Pacto de Lausanne. O valor persistido é o rótulo exibido pelo portal.
 */
enum ProjectCategory: string implements HasColor, HasIcon, HasLabel
{
    case SOCIAL = 'Social';
    case CULTURAL = 'Cultural';
    case EDUCACAO = 'Educação';
    case LIDERANCA = 'Liderança';

    public function getLabel(): string
    {
        return $this->value;
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::SOCIAL => 'danger',
            self::CULTURAL => 'warning',
            self::EDUCACAO => 'info',
            self::LIDERANCA => 'success',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::SOCIAL => 'heroicon-m-heart',
            self::CULTURAL => 'heroicon-m-musical-note',
            self::EDUCACAO => 'heroicon-m-academic-cap',
            self::LIDERANCA => 'heroicon-m-user-group',
        };
    }

    /**
     * Descrição curta da categoria, para o painel e o portal público.
     */
    public function description(): string
    {
        return match ($this) {
            self::SOCIAL => 'Ações de cuidado, justiça e dignidade junto a pessoas em vulnerabilidade.',
            self::CULTURAL => 'Expressões culturais e artísticas como ponte para o Evangelho na comunidade.',
            self::EDUCACAO => 'Reforço escolar, cursos, alfabetização e formação para a vida.',
            self::LIDERANCA => 'Formação e capacitação de líderes para a igreja e a sociedade.',
        };
    }

    /**
     * Artigo do Pacto de Lausanne que fundamenta a categoria.
     */
    public function lausanneReference(): string
    {
        return match ($this) {
            self::SOCIAL => 'Pacto de Lausanne, art. 5: Responsabilidade social cristã',
            self::CULTURAL => 'Pacto de Lausanne, art. 10: Evangelização e cultura',
            self::EDUCACAO, self::LIDERANCA => 'Pacto de Lausanne, art. 11: Educação e liderança',
        };
    }
}
