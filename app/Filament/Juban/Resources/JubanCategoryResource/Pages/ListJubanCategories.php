<?php

namespace App\Filament\Juban\Resources\JubanCategoryResource\Pages;

use App\Filament\Juban\Resources\JubanCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListJubanCategories extends ListRecords
{
    protected static string $resource = JubanCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
