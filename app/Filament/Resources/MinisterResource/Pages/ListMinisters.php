<?php

namespace App\Filament\Resources\MinisterResource\Pages;

use App\Filament\Resources\MinisterResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMinisters extends ListRecords
{
    protected static string $resource = MinisterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
