<?php

namespace App\Filament\Resources\StatuteResource\Pages;

use App\Enums\PublishStatus;
use App\Filament\Resources\StatuteResource;
use App\Services\AknStatuteSerializer;
use App\Services\AknValidator;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateStatute extends CreateRecord
{
    protected static string $resource = StatuteResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] = PublishStatus::DRAFT;
        $data['created_by'] = auth()->id();

        // Se o XML não foi informado manualmente na aba Avançada, compila a partir dos capítulos estruturados
        if (empty($data['akn_xml'])) {
            $serializer = app(AknStatuteSerializer::class);
            $xml = $serializer->toXml($data, [
                'title' => $data['title'] ?? 'Estatuto',
                'version_label' => $data['version_label'] ?? '',
                'approved_at' => $data['approved_at'] ?? now()->toDateString(),
                'approved_place' => $data['approved_place'] ?? 'Anápolis, GO',
            ]);

            $validator = app(AknValidator::class);
            if (! $validator->isValid($xml)) {
                $errors = implode('; ', array_slice($validator->getErrors($xml), 0, 5));
                throw ValidationException::withMessages([
                    'chapters' => 'A estrutura gerou um XML inválido perante o schema Akoma Ntoso: '.$errors,
                ]);
            }

            $data['akn_xml'] = $xml;
        }

        unset($data['chapters']);

        return $data;
    }
}
