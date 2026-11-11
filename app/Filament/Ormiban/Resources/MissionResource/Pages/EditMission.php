<?php

namespace App\Filament\Ormiban\Resources\MissionResource\Pages;

use App\Enums\ChurchType;
use App\Filament\Ormiban\Resources\MissionResource;
use App\Models\Church;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMission extends EditRecord
{
    protected static string $resource = MissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->hidden(fn (Church $record): bool => ! auth()->user()?->can('delete', $record)),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['type'] = ChurchType::MISSION->value;
        $data['cnpj'] = null;

        return $data;
    }
}
