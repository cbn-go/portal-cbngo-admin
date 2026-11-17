<?php

namespace App\Filament\Juban\Resources\JubanCategoryResource\Pages;

use App\Filament\Juban\Resources\JubanCategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateJubanCategory extends CreateRecord
{
    protected static string $resource = JubanCategoryResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
