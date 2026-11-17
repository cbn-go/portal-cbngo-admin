<?php

namespace App\Filament\Juban\Resources\JubanTransactionResource\Pages;

use App\Filament\Juban\Resources\JubanTransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditJubanTransaction extends EditRecord
{
    protected static string $resource = JubanTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\RestoreAction::make(),
            Actions\ForceDeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
