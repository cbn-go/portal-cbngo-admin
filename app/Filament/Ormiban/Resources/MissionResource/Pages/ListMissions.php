<?php

namespace App\Filament\Ormiban\Resources\MissionResource\Pages;

use App\Filament\Ormiban\Resources\MissionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMissions extends ListRecords
{
    protected static string $resource = MissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nova Missão')
                ->visible(fn (): bool => auth()->user()?->contentChurchId() !== null),
        ];
    }
}
