<?php

namespace App\Filament\Resources\SocialProjectResource\Pages;

use App\Filament\Resources\SocialProjectResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSocialProjects extends ListRecords
{
    protected static string $resource = SocialProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
