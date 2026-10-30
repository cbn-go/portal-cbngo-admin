<?php

namespace App\Filament\Resources;

use App\Enums\PublishStatus;
use App\Filament\Resources\StatuteResource\Pages;
use App\Models\Statute;
use App\Services\StatuteDiffService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Response;

class StatuteResource extends Resource
{
    protected static ?string $model = Statute::class;

    protected static ?string $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationGroup = 'Secretaria';

    protected static ?string $navigationLabel = 'Estatuto';

    protected static ?string $modelLabel = 'Estatuto';

    protected static ?string $pluralModelLabel = 'Estatutos';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'version_label';

    public static function getGloballySearchableAttributes(): array
    {
        return ['version_label', 'title', 'approved_place', 'approval_body'];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('StatuteTabs')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Metadados')
                            ->icon('heroicon-o-identification')
                            ->schema([
                                Forms\Components\Grid::make(2)->schema([
                                    Forms\Components\TextInput::make('title')
                                        ->label('Título do Estatuto')
                                        ->required()
                                        ->maxLength(255)
                                        ->default('Estatuto da Convenção Batista Nacional do Estado de Goiás')
                                        ->columnSpan(2),

                                    Forms\Components\TextInput::make('version_label')
                                        ->label('Identificador da Versão')
                                        ->required()
                                        ->unique(ignoreRecord: true)
                                        ->placeholder('Ex: 2025-10-18 ou 2026-v1')
                                        ->maxLength(50),

                                    Forms\Components\DatePicker::make('approved_at')
                                        ->label('Data de Aprovação')
                                        ->required()
                                        ->native(false)
                                        ->displayFormat('d/m/Y')
                                        ->default(now()),

                                    Forms\Components\TextInput::make('approval_body')
                                        ->label('Órgão Aprovador')
                                        ->required()
                                        ->default('Assembleia Geral Ordinária'),

                                    Forms\Components\TextInput::make('approved_place')
                                        ->label('Local de Aprovação')
                                        ->required()
                                        ->default('Anápolis, GO'),

                                    Forms\Components\DatePicker::make('effective_at')
                                        ->label('Início de Vigência')
                                        ->native(false)
                                        ->displayFormat('d/m/Y')
                                        ->helperText('Deixe vazio para vigência a partir da data de aprovação.'),

                                    Forms\Components\TextInput::make('minutes_reference')
                                        ->label('Referência da Ata')
                                        ->placeholder('Ex: Ata da 42ª Assembleia Geral, Livro 04, fls. 12-18')
                                        ->columnSpan(2),

                                    Forms\Components\TextInput::make('registry_info')
                                        ->label('Dados do Registro Cartorário')
                                        ->placeholder('Ex: Registro nº 12345 no 1º Registro de PJ de Goiânia')
                                        ->columnSpan(2),

                                    Forms\Components\Textarea::make('change_summary')
                                        ->label('Resumo da Reforma / Justificativa')
                                        ->placeholder('Descreva sucintamente as principais alterações introduzidas nesta versão.')
                                        ->rows(3)
                                        ->columnSpan(2),
                                ]),
                            ]),

                        Forms\Components\Tabs\Tab::make('Estrutura de Artigos')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Forms\Components\Placeholder::make('structured_intro')
                                    ->label('Editor Estruturado do Estatuto')
                                    ->content('Organize os capítulos, seções e artigos abaixo. O documento será versionado e formatado automaticamente.'),

                                Forms\Components\Repeater::make('chapters')
                                    ->label('Capítulos do Estatuto')
                                    ->collapsible()
                                    ->cloneable()
                                    ->itemLabel(fn (array $state): string => ($state['num'] ?? '').(! empty($state['heading']) ? ' - '.$state['heading'] : ''))
                                    ->schema([
                                        Forms\Components\Hidden::make('eId'),
                                        Forms\Components\Grid::make(2)->schema([
                                            Forms\Components\TextInput::make('num')
                                                ->label('Número do Capítulo')
                                                ->placeholder('Ex: CAPÍTULO I')
                                                ->required(),

                                            Forms\Components\TextInput::make('heading')
                                                ->label('Título do Capítulo')
                                                ->placeholder('Ex: Do Nome, da Constituição e da Sede'),
                                        ]),

                                        // Artigos diretos do Capítulo
                                        Forms\Components\Repeater::make('articles')
                                            ->label('Artigos do Capítulo')
                                            ->collapsible()
                                            ->cloneable()
                                            ->itemLabel(fn (array $state): string => ($state['num'] ?? 'Novo Artigo'))
                                            ->schema(self::getArticleFormSchema()),

                                        // Seções do Capítulo
                                        Forms\Components\Repeater::make('sections')
                                            ->label('Seções do Capítulo (opcional)')
                                            ->collapsible()
                                            ->schema([
                                                Forms\Components\Hidden::make('eId'),
                                                Forms\Components\Grid::make(2)->schema([
                                                    Forms\Components\TextInput::make('num')
                                                        ->label('Número da Seção')
                                                        ->placeholder('Ex: SEÇÃO I')
                                                        ->required(),

                                                    Forms\Components\TextInput::make('heading')
                                                        ->label('Título da Seção')
                                                        ->placeholder('Ex: Da Diretoria'),
                                                ]),

                                                Forms\Components\Repeater::make('articles')
                                                    ->label('Artigos da Seção')
                                                    ->collapsible()
                                                    ->cloneable()
                                                    ->itemLabel(fn (array $state): string => ($state['num'] ?? 'Novo Artigo'))
                                                    ->schema(self::getArticleFormSchema()),
                                            ]),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    private static function getArticleFormSchema(): array
    {
        return [
            Forms\Components\Hidden::make('eId'),
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('num')
                    ->label('Número do Artigo')
                    ->placeholder('Ex: Art. 1º')
                    ->required(),

                Forms\Components\TextInput::make('heading')
                    ->label('Subtítulo do Artigo (opcional)')
                    ->placeholder('Ex: Da Competência'),
            ]),

            Forms\Components\Textarea::make('intro')
                ->label('Texto do Artigo (Caput)')
                ->rows(3)
                ->required(),

            Forms\Components\Repeater::make('points')
                ->label('Incisos do Caput (I, II...)')
                ->collapsible()
                ->schema([
                    Forms\Components\Hidden::make('eId'),
                    Forms\Components\TextInput::make('num')
                        ->label('Número do Inciso')
                        ->placeholder('Ex: I -'),

                    Forms\Components\Textarea::make('content')
                        ->label('Texto do Inciso')
                        ->rows(2),

                    Forms\Components\Repeater::make('points')
                        ->label('Alíneas do Inciso (a, b...)')
                        ->collapsible()
                        ->schema([
                            Forms\Components\Hidden::make('eId'),
                            Forms\Components\TextInput::make('num')
                                ->label('Alínea')
                                ->placeholder('Ex: a)'),

                            Forms\Components\Textarea::make('content')
                                ->label('Texto da Alínea')
                                ->rows(2),
                        ]),
                ]),

            Forms\Components\Repeater::make('paragraphs')
                ->label('Parágrafos (§ 1º, Parágrafo único...)')
                ->collapsible()
                ->schema([
                    Forms\Components\Hidden::make('eId'),
                    Forms\Components\TextInput::make('num')
                        ->label('Identificação do Parágrafo')
                        ->placeholder('Ex: § 1º - ou Parágrafo único -'),

                    Forms\Components\Textarea::make('content')
                        ->label('Texto do Parágrafo')
                        ->rows(2),

                    Forms\Components\Repeater::make('points')
                        ->label('Incisos do Parágrafo')
                        ->collapsible()
                        ->schema([
                            Forms\Components\Hidden::make('eId'),
                            Forms\Components\TextInput::make('num')
                                ->label('Número do Inciso')
                                ->placeholder('Ex: I -'),

                            Forms\Components\Textarea::make('content')
                                ->label('Texto do Inciso')
                                ->rows(2),
                        ]),
                ]),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('version_label')
                    ->label('Versão')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('title')
                    ->label('Título')
                    ->limit(35)
                    ->searchable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge(),

                Tables\Columns\TextColumn::make('approved_at')
                    ->label('Aprovado em')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('effective_at')
                    ->label('Vigência')
                    ->date('d/m/Y')
                    ->placeholder('Imediata')
                    ->sortable(),

                Tables\Columns\TextColumn::make('approval_body')
                    ->label('Órgão Aprovador')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Criado por')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updater.name')
                    ->label('Atualizado por')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('approved_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(PublishStatus::class),
            ])
            ->actions([
                Tables\Actions\Action::make('preview')
                    ->label('Visualizar')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading(fn (Statute $record) => "Visualização do Estatuto - Versão {$record->version_label}")
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar')
                    ->modalContent(fn (Statute $record): View => view('filament.resources.statute-resource.modals.preview', [
                        'record' => $record,
                    ])),

                Tables\Actions\Action::make('compare')
                    ->label('Comparar / Diff')
                    ->icon('heroicon-o-arrows-right-left')
                    ->color('info')
                    ->modalHeading(fn (Statute $record) => "Comparação de Versões - {$record->version_label}")
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar')
                    ->modalContent(function (Statute $record): View {
                        $published = Statute::published()->where('id', '!=', $record->id)->first();
                        $baseStatute = $published ?? Statute::where('id', '!=', $record->id)->latest('approved_at')->first();

                        $diffService = app(StatuteDiffService::class);
                        $oldXml = $baseStatute?->akn_xml ?? $record->akn_xml;
                        $diff = $diffService->compare($oldXml, $record->akn_xml);

                        return view('filament.resources.statute-resource.modals.diff', [
                            'diff' => $diff,
                            'oldVersionLabel' => $baseStatute ? "Versão {$baseStatute->version_label} (".($baseStatute->isPublished() ? 'Publicada' : 'Anterior').')' : 'Versão Base',
                            'newVersionLabel' => "Versão {$record->version_label}",
                        ]);
                    }),

                Tables\Actions\Action::make('fork')
                    ->label('Duplicar Rascunho')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('gray')
                    ->visible(fn (Statute $record): bool => auth()->user()?->can('fork', $record) ?? false)
                    ->form([
                        Forms\Components\TextInput::make('new_version_label')
                            ->label('Identificador da Nova Versão')
                            ->default(fn (Statute $record) => $record->version_label.'-draft')
                            ->required()
                            ->maxLength(50),
                    ])
                    ->action(function (Statute $record, array $data): void {
                        $newDraft = $record->forkAsDraft($data['new_version_label'], auth()->user());

                        Notification::make()
                            ->title('Rascunho criado com sucesso')
                            ->body("Novo rascunho '{$newDraft->version_label}' criado a partir da versão '{$record->version_label}'.")
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('publish')
                    ->label('Publicar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Statute $record): bool => $record->status !== PublishStatus::PUBLISHED && (auth()->user()?->can('publish', $record) ?? false))
                    ->requiresConfirmation()
                    ->modalHeading('Confirmar Publicação')
                    ->modalDescription('Tem certeza que deseja publicar esta versão do Estatuto? A versão atualmente publicada será automaticamente arquivada.')
                    ->action(function (Statute $record): void {
                        $record->publish(auth()->user());

                        Notification::make()
                            ->title('Estatuto publicado')
                            ->body("A versão '{$record->version_label}' agora é a versão oficial publicada.")
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('download_xml')
                    ->label('Baixar XML')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(function (Statute $record) {
                        $filename = "estatuto-cbngo-{$record->version_label}.akn.xml";

                        return Response::streamDownload(function () use ($record) {
                            echo $record->akn_xml;
                        }, $filename, [
                            'Content-Type' => 'application/xml',
                        ]);
                    }),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->action(function ($records): void {
                            foreach ($records as $record) {
                                if (! $record->isPublished()) {
                                    $record->delete();
                                }
                            }
                        }),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStatutes::route('/'),
            'create' => Pages\CreateStatute::route('/create'),
            'edit' => Pages\EditStatute::route('/{record}/edit'),
        ];
    }
}
