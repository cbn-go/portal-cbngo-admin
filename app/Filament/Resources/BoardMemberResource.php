<?php

namespace App\Filament\Resources;

use App\Enums\BoardGroup;
use App\Filament\Resources\BoardMemberResource\Pages;
use App\Models\BoardMember;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BoardMemberResource extends Resource
{
    protected static ?string $model = BoardMember::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Secretaria';

    protected static ?string $navigationLabel = 'Diretoria';

    protected static ?string $modelLabel = 'Membro da Diretoria';

    protected static ?string $pluralModelLabel = 'Diretoria';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'role', 'church_name'];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Identificação')
                    ->description('Nome, cargo e órgão da convenção exibidos na página /diretoria do portal público.')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Ex: Pr. Roberto Silveira'),

                        Forms\Components\TextInput::make('role')
                            ->label('Cargo')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Ex: Presidente, 1º Secretário, Vogal'),

                        Forms\Components\Select::make('group')
                            ->label('Órgão')
                            ->options(BoardGroup::class)
                            ->required()
                            ->native(false)
                            ->live(),

                        Forms\Components\TextInput::make('region')
                            ->label('Região')
                            ->maxLength(255)
                            ->placeholder('Ex: Região Sudoeste (Rio Verde e Jataí)')
                            ->visible(fn (Get $get): bool => self::isRegionalGroup($get('group')))
                            ->required(fn (Get $get): bool => self::isRegionalGroup($get('group'))),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Gestão')
                    ->schema([
                        Forms\Components\TextInput::make('term_label')
                            ->label('Gestão')
                            ->maxLength(60)
                            ->placeholder('Gestão 2025–2027')
                            ->datalist(fn (): array => array_values(BoardMember::termLabelOptions())),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Exibir no portal')
                            ->helperText('Desative para membros de gestões anteriores.')
                            ->default(true)
                            ->inline(false),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Foto')
                    ->schema([
                        Forms\Components\FileUpload::make('photo')
                            ->label('Foto')
                            ->image()
                            ->disk(config('media.disk'))
                            ->directory('board/photos')
                            ->visibility('public')
                            ->maxSize(2048)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->imageCropAspectRatio('1:1')
                            ->helperText('Formatos: JPG, PNG, WEBP (máx. 2MB). Proporção 1:1 quadrada. Sem foto, o portal exibe as iniciais.'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('photo')
                    ->label('Foto')
                    ->disk(config('media.disk'))
                    ->circular(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable(),

                Tables\Columns\TextColumn::make('role')
                    ->label('Cargo')
                    ->searchable(),

                Tables\Columns\TextColumn::make('group')
                    ->label('Órgão')
                    ->badge(),

                Tables\Columns\TextColumn::make('region')
                    ->label('Região')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('term_label')
                    ->label('Gestão')
                    ->toggleable(),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Ativo'),
            ])
            ->reorderable('position')
            ->defaultSort('position')
            ->filters([
                Tables\Filters\SelectFilter::make('group')
                    ->label('Órgão')
                    ->options(BoardGroup::class),

                Tables\Filters\SelectFilter::make('term_label')
                    ->label('Gestão')
                    ->options(fn (): array => BoardMember::termLabelOptions()),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Ativo'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBoardMembers::route('/'),
            'create' => Pages\CreateBoardMember::route('/create'),
            'edit' => Pages\EditBoardMember::route('/{record}/edit'),
        ];
    }

    private static function isRegionalGroup(mixed $group): bool
    {
        $group = $group instanceof BoardGroup ? $group : BoardGroup::tryFrom((string) $group);

        return $group === BoardGroup::COORDENACAO_REGIONAL;
    }
}
