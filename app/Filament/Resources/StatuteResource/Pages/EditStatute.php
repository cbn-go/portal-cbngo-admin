<?php

namespace App\Filament\Resources\StatuteResource\Pages;

use App\Filament\Resources\StatuteResource;
use App\Models\Statute;
use App\Services\AknStatuteSerializer;
use App\Services\AknValidator;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditStatute extends EditRecord
{
    protected static string $resource = StatuteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn (Statute $record): bool => ! $record->isPublished() && (auth()->user()?->can('delete', $record) ?? false)),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        if (! empty($data['akn_xml'])) {
            $serializer = app(AknStatuteSerializer::class);
            $parsedForm = $serializer->toFormData($data['akn_xml']);
            $data['chapters'] = $parsedForm['chapters'] ?? [];
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = auth()->id();

        // Se houver estrutura de capítulos alterada no formulário, sincroniza com o XML
        if (isset($data['chapters']) && is_array($data['chapters'])) {
            $serializer = app(AknStatuteSerializer::class);
            $xml = $serializer->toXml($data, [
                'title' => $data['title'] ?? 'Estatuto',
                'version_label' => $data['version_label'] ?? '',
                'approved_at' => is_string($data['approved_at']) ? $data['approved_at'] : now()->toDateString(),
                'approved_place' => $data['approved_place'] ?? 'Anápolis, GO',
            ]);

            $validator = app(AknValidator::class);
            if (! $validator->isValid($xml)) {
                $errors = implode('; ', array_slice($validator->getErrors($xml), 0, 5));
                throw ValidationException::withMessages([
                    'chapters' => 'A estrutura gerou inconsistências de validação: '.$errors,
                ]);
            }

            $data['akn_xml'] = $xml;
        }

        unset($data['chapters']);

        return $data;
    }
}
