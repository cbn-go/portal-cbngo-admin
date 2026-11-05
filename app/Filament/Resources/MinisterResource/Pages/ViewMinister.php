<?php

namespace App\Filament\Resources\MinisterResource\Pages;

use App\Filament\Resources\MinisterResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewMinister extends ViewRecord
{
    protected static string $resource = MinisterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            ...MinisterResource::getAccessActions(),
        ];
    }
}
