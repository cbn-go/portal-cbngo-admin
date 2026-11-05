<?php

namespace App\Filament\Ormiban\Widgets;

use App\DTOs\Ormiban\MinisterDemographicsData;

class AgeChartWidget extends MinisterBandChartWidget
{
    protected static ?string $heading = 'Idade';

    protected static ?string $description = 'Anos completos a partir da data de nascimento. Quem não tem data fica de fora.';

    protected function series(MinisterDemographicsData $data): array
    {
        return $data->ageBands;
    }
}
