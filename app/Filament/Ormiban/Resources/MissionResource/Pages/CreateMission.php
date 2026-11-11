<?php

namespace App\Filament\Ormiban\Resources\MissionResource\Pages;

use App\Enums\ChurchType;
use App\Filament\Ormiban\Resources\MissionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMission extends CreateRecord
{
    protected static string $resource = MissionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['type'] = ChurchType::MISSION->value;
        $data['parent_church_id'] = auth()->user()?->contentChurchId();
        $data['cnpj'] = null;

        return $data;
    }
}
