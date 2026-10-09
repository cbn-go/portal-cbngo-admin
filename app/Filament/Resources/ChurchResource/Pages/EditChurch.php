<?php

namespace App\Filament\Resources\ChurchResource\Pages;

use App\Filament\Resources\ChurchResource;
use App\Models\Church;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditChurch extends EditRecord
{
    protected static string $resource = ChurchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->hidden(fn (Church $record): bool => ! auth()->user()?->can('delete', $record)),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
