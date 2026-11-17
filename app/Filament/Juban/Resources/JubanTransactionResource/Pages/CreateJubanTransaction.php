<?php

namespace App\Filament\Juban\Resources\JubanTransactionResource\Pages;

use App\Filament\Juban\Resources\JubanTransactionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateJubanTransaction extends CreateRecord
{
    protected static string $resource = JubanTransactionResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
