<?php

namespace App\Filament\Resources;

use App\Enums\AmigoRegion;
use App\Enums\BoardGroup;
use App\Enums\ClergyTitle;
use App\Filament\Resources\BoardMemberResource\Pages;
use App\Models\BoardMember;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

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
        return ['name', 'role'];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Identificação')
                    ->description('Nome, cargo e órgão da convenção exibidos na página /diretoria do portal público.')
                    ->schema([
                        Forms\Components\Select::make('title')
                            ->label('Título')
                            ->options(ClergyTitle::class)
                            ->placeholder('Sem título (leigo)')
                            ->native(false),

                        Forms\Components\TextInput::make('name')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Ex: André Luiz Rodrigues da Silva')
                            ->helperText('Sem o título, que é escolhido ao lado.'),

                        Forms\Components\Select::make('group')
                            ->label('Órgão')
                            ->options(BoardGroup::class)
                            ->required()
                            ->native(false)
                            ->live(),

                        Forms\Components\TextInput::make('role')
                            ->label('Cargo')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Ex: Presidente, Relator, Coordenador')
                            ->datalist(fn (Get $get): array => self::groupFrom($get('group'))?->roleSuggestions() ?? []),

                        Forms\Components\Select::make('region')
                            ->label('Região')
                            ->options(AmigoRegion::class)
                            ->enum(AmigoRegion::class)
                            ->native(false)
                            ->visible(fn (Get $get): bool => self::isRegionalGroup($get('group')))
                            ->required(fn (Get $get): bool => self::isRegionalGroup($get('group'))),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Mandato')
                    ->schema([
                        Forms\Components\DatePicker::make('term_starts_on')
                            ->label('Início do mandato')
                            ->default(fn (): ?string => BoardMember::latestTerm()['term_starts_on'] ?? null)
                            ->live(),

                        Forms\Components\DatePicker::make('term_ends_on')
                            ->label('Fim do mandato')
                            ->default(fn (): ?string => BoardMember::latestTerm()['term_ends_on'] ?? null)
                            ->afterOrEqual('term_starts_on')
                            ->required(fn (Get $get): bool => filled($get('term_starts_on')))
                            ->live(),

                        Forms\Components\Placeholder::make('term_label_preview')
                            ->label('Rótulo no portal')
                            ->content(fn (Get $get, ?BoardMember $record): string => BoardMember::termLabelFor(
                                filled($get('term_starts_on')) ? Carbon::parse($get('term_starts_on')) : null,
                                filled($get('term_ends_on')) ? Carbon::parse($get('term_ends_on')) : null,
                            ) ?? $record?->term_label ?? '—'),

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
                    ->formatStateUsing(fn (BoardMember $record): string => $record->display_name)
                    ->searchable(),

                Tables\Columns\TextColumn::make('role')
                    ->label('Cargo')
                    ->searchable(),

                Tables\Columns\TextColumn::make('group')
                    ->label('Órgão')
                    ->badge(),

                Tables\Columns\TextColumn::make('region')
                    ->label('Região')
                    ->placeholder('—')
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

                Tables\Filters\SelectFilter::make('region')
                    ->label('Região')
                    ->options(AmigoRegion::class),

                Tables\Filters\SelectFilter::make('term_label')
                    ->label('Gestão')
                    ->options(fn (): array => BoardMember::termLabelOptions()),

                Tables\Filters\Filter::make('current_term')
                    ->label('Gestão vigente')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereIn('id', BoardMember::query()->currentTerm()->select('id'))),

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

    private static function groupFrom(mixed $group): ?BoardGroup
    {
        return $group instanceof BoardGroup ? $group : BoardGroup::tryFrom((string) $group);
    }

    private static function isRegionalGroup(mixed $group): bool
    {
        return self::groupFrom($group) === BoardGroup::COORDENACAO_REGIONAL;
    }
}
