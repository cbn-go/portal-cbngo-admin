<?php

namespace App\Filament\Resources\StatuteResource\Pages;

use App\Enums\PublishStatus;
use App\Filament\Resources\StatuteResource;
use App\Models\Statute;
use App\Services\AknStatuteParser;
use App\Services\AknValidator;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ListStatutes extends ListRecords
{
    protected static string $resource = StatuteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),

            Actions\Action::make('import_xml')
                ->label('Importar XML Akoma Ntoso')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->visible(fn (): bool => auth()->user()?->role->isSuperAdmin() ?? false)
                ->modalHeading('Importar Estatuto em XML Akoma Ntoso 3.0')
                ->modalDescription('Faça upload de um arquivo XML ou cole o código XML Akoma Ntoso 3.0. O documento será validado contra os schemas oficiais e cadastrado como rascunho.')
                ->form([
                    Forms\Components\TextInput::make('version_label')
                        ->label('Identificador da Versão')
                        ->placeholder('Ex: 2026-v1-importado')
                        ->required()
                        ->maxLength(50),

                    Forms\Components\FileUpload::make('xml_file')
                        ->label('Arquivo XML (.xml ou .akn.xml)')
                        ->acceptedFileTypes(['application/xml', 'text/xml', 'text/plain'])
                        ->maxSize(10240)
                        ->disk('local')
                        ->directory('temp-statute-imports'),

                    Forms\Components\Textarea::make('xml_content')
                        ->label('Ou cole o conteúdo XML diretamente')
                        ->rows(10)
                        ->helperText('Se informado, o texto colado prevalece sobre o arquivo.'),
                ])
                ->action(function (array $data): void {
                    $xml = '';

                    if (! empty($data['xml_content'])) {
                        $xml = trim($data['xml_content']);
                    } elseif (! empty($data['xml_file'])) {
                        $disk = Storage::disk('local');
                        if ($disk->exists($data['xml_file'])) {
                            $xml = (string) $disk->get($data['xml_file']);
                            $disk->delete($data['xml_file']);
                        }
                    }

                    if (empty($xml)) {
                        throw ValidationException::withMessages([
                            'xml_content' => 'É necessário fornecer um arquivo XML ou colar o conteúdo no campo de texto.',
                        ]);
                    }

                    $validator = app(AknValidator::class);
                    if (! $validator->isValid($xml)) {
                        $errors = implode('; ', array_slice($validator->getErrors($xml), 0, 5));
                        throw ValidationException::withMessages([
                            'xml_content' => 'O XML fornecido não é compatível com o schema Akoma Ntoso 3.0: '.$errors,
                        ]);
                    }

                    $parser = app(AknStatuteParser::class);
                    $structure = $parser->parse($xml);

                    $statute = Statute::create([
                        'title' => $structure->title ?: 'Estatuto da Convenção Batista Nacional do Estado de Goiás',
                        'version_label' => $data['version_label'],
                        'approved_at' => now(),
                        'approved_place' => 'Anápolis, GO',
                        'approval_body' => 'Assembleia Geral Ordinária',
                        'effective_at' => now(),
                        'status' => PublishStatus::DRAFT,
                        'akn_xml' => $xml,
                        'created_by' => auth()->id(),
                    ]);

                    Notification::make()
                        ->title('Estatuto importado com sucesso')
                        ->body("Versão '{$statute->version_label}' importada como rascunho com {$structure->articleCount()} artigos.")
                        ->success()
                        ->send();

                    $this->redirect(StatuteResource::getUrl('edit', ['record' => $statute]));
                }),
        ];
    }
}
