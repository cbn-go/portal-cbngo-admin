<?php

namespace App\Filament\FiscalCouncil\Resources\JubanReportResource\Pages;

use App\Filament\FiscalCouncil\Resources\JubanReportResource;
use Filament\Resources\Pages\ListRecords;

class ListJubanReports extends ListRecords
{
    protected static string $resource = JubanReportResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
