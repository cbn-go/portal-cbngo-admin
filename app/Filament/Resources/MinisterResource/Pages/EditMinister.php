<?php

namespace App\Filament\Resources\MinisterResource\Pages;

use App\Filament\Resources\MinisterResource;
use App\Models\Minister;
use App\Support\Cpf;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMinister extends EditRecord
{
    protected static string $resource = MinisterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            ...MinisterResource::getAccessActions(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        if (isset($data['cpf']) && is_string($data['cpf'])) {
            $data['cpf'] = Cpf::format($data['cpf']) ?? $data['cpf'];
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['cpf']) && is_string($data['cpf'])) {
            $data['cpf'] = Cpf::normalize($data['cpf']);
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $minister = $this->getRecord();

        if ($minister instanceof Minister) {
            $minister->syncAccessUser();
        }
    }
}
