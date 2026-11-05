<?php

namespace App\Filament\Ormiban\Widgets;

use App\DTOs\Ormiban\MinisterDemographicsData;

class MinistryAgeChartWidget extends MinisterBandChartWidget
{
    protected static ?string $heading = 'Idade de ministério';

    protected static ?string $description = 'Anos desde a ordenação mais antiga. Quem não tem o evento fica em Sem ordenação.';

    protected function series(MinisterDemographicsData $data): array
    {
        return $data->ministryBands;
    }
}
