<?php

namespace App\Filament\Ormiban\Widgets;

use App\DTOs\Ormiban\MinisterDemographicsData;

class OrdinationsByYearChartWidget extends MinisterBandChartWidget
{
    protected static ?string $heading = 'Ordenações por ano';

    protected static ?string $description = 'Quantidade de ordenações (código 2) em cada ano. Anos sem evento ficam em zero.';

    protected function datasetLabel(): string
    {
        return 'Ordenações';
    }

    protected function series(MinisterDemographicsData $data): array
    {
        return $data->ordinationsByYear;
    }
}
