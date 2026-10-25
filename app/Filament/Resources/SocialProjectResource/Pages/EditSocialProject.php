<?php

namespace App\Filament\Resources\SocialProjectResource\Pages;

use App\Enums\PublishStatus;
use App\Filament\Resources\SocialProjectResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSocialProject extends EditRecord
{
    protected static string $resource = SocialProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SocialProjectResource::approveAction(Actions\Action::class)
                ->after(fn () => $this->refreshFormData(['status', 'published_at'])),
            SocialProjectResource::returnAction(Actions\Action::class)
                ->after(fn () => $this->refreshFormData(['status'])),
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * O representante não escolhe "Publicado": um projeto publicado aparece
     * como "Enviar para revisão", que é o que acontece ao salvar a edição.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        if (! SocialProjectResource::isAdmin() && ! array_key_exists((string) $data['status'], SocialProjectResource::statusOptions())) {
            $data['status'] = PublishStatus::PENDING_REVIEW->value;
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
