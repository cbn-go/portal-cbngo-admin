<?php

namespace App\Filament\Resources\StatuteResource\Pages;

use App\Filament\Resources\StatuteResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStatutes extends ListRecords
{
    protected static string $resource = StatuteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
