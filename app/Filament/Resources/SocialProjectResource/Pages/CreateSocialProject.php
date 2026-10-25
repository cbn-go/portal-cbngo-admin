<?php

namespace App\Filament\Resources\SocialProjectResource\Pages;

use App\Filament\Resources\SocialProjectResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSocialProject extends CreateRecord
{
    protected static string $resource = SocialProjectResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
