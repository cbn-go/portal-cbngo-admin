<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Título eclesiástico exibido antes do nome. O valor gravado é a própria
 * abreviação, para o portal público exibir sem tradução. Sem título = leigo.
 */
enum ClergyTitle: string implements HasLabel
{
    case PASTOR = 'Pr.';
    case PASTORA = 'Pra.';
    case EVANGELISTA = 'Ev.';
    case MISSIONARIO = 'Miss.';
    case DIACONO = 'Diác.';
    case PRESBITERO = 'Presb.';

    public function getLabel(): string
    {
        return match ($this) {
            self::PASTOR => 'Pr. (Pastor)',
            self::PASTORA => 'Pra. (Pastora)',
            self::EVANGELISTA => 'Ev. (Evangelista)',
            self::MISSIONARIO => 'Miss. (Missionário)',
            self::DIACONO => 'Diác. (Diácono)',
            self::PRESBITERO => 'Presb. (Presbítero)',
        };
    }

    /**
     * Separa o título do início de um nome ("Pr. André Luiz" → [PASTOR, "André Luiz"]).
     *
     * @return array{0: self|null, 1: string}
     */
    public static function splitName(string $name): array
    {
        $name = trim($name);
        $aliases = ['Diac.' => self::DIACONO];

        foreach ([...array_column(self::cases(), null, 'value'), ...$aliases] as $abbreviation => $title) {
            if (preg_match('/^'.preg_quote($abbreviation, '/').'\s+(.+)$/iu', $name, $matches) === 1) {
                return [$title, trim($matches[1])];
            }
        }

        return [null, $name];
    }
}
