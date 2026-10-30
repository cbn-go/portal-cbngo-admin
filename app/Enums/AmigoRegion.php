<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Regiões das Associações Missionárias Goianas (AMIGO), homologadas pela
 * Assembleia Geral (art. 10 do Estatuto). A ordem dos casos é a ordem de
 * exibição no portal.
 */
enum AmigoRegion: string implements HasLabel
{
    case LESTE = 'leste';
    case OESTE = 'oeste';
    case NORTE = 'norte';
    case SUDOESTE = 'sudoeste';

    public function getLabel(): string
    {
        return 'AMIGO '.ucfirst($this->value);
    }

    /**
     * Reconhece a região em textos antigos ("Região Sudoeste (Rio Verde e Jataí)").
     * "Sudoeste" é testado antes de "Oeste", que está contido nele.
     */
    public static function fromText(?string $text): ?self
    {
        $normalized = mb_strtolower((string) $text);

        foreach ([self::SUDOESTE, self::LESTE, self::OESTE, self::NORTE] as $region) {
            if (preg_match('/\b'.$region->value.'\b/u', $normalized) === 1) {
                return $region;
            }
        }

        return null;
    }
}
