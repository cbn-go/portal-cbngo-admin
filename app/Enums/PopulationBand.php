<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Faixas de população usadas no filtro de municípios do Painel de Estratégia.
 */
enum PopulationBand: string implements HasLabel
{
    case UP_TO_10K = 'ate_10k';
    case FROM_10K_TO_20K = '10k_20k';
    case FROM_20K_TO_50K = '20k_50k';
    case FROM_50K_TO_100K = '50k_100k';
    case OVER_100K = 'acima_100k';

    public function getLabel(): string
    {
        return match ($this) {
            self::UP_TO_10K => 'Até 10 mil',
            self::FROM_10K_TO_20K => '10 a 20 mil',
            self::FROM_20K_TO_50K => '20 a 50 mil',
            self::FROM_50K_TO_100K => '50 a 100 mil',
            self::OVER_100K => 'Mais de 100 mil',
        };
    }

    public function contains(?int $population): bool
    {
        if ($population === null) {
            return false;
        }

        return match ($this) {
            self::UP_TO_10K => $population <= 10_000,
            self::FROM_10K_TO_20K => $population > 10_000 && $population <= 20_000,
            self::FROM_20K_TO_50K => $population > 20_000 && $population <= 50_000,
            self::FROM_50K_TO_100K => $population > 50_000 && $population <= 100_000,
            self::OVER_100K => $population > 100_000,
        };
    }
}
