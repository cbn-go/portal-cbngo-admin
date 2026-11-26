<?php

namespace App\Filament\Resources\FiscalCouncilMemberResource\Pages;

use App\Filament\Resources\FiscalCouncilMemberResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFiscalCouncilMember extends EditRecord
{
    protected static string $resource = FiscalCouncilMemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
