<?php

namespace App\Filament\Resources\MinisterResource\Pages;

use App\Filament\Resources\MinisterResource;
use App\Models\Minister;
use App\Support\Cpf;
use Filament\Resources\Pages\CreateRecord;

class CreateMinister extends CreateRecord
{
    protected static string $resource = MinisterResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (isset($data['cpf']) && is_string($data['cpf'])) {
            $data['cpf'] = Cpf::normalize($data['cpf']);
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $minister = $this->getRecord();

        if ($minister instanceof Minister) {
            $minister->syncAccessUser();
        }
    }
}
