<?php

namespace App\Filament\Juban\Resources;

use App\Enums\JubanCategoryKind;
use App\Filament\Juban\Resources\JubanCategoryResource\Pages;
use App\Models\JubanCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class JubanCategoryResource extends Resource
{
    protected static ?string $model = JubanCategory::class;

    protected static ?string $slug = 'categorias';

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'Tesouraria';

    protected static ?string $navigationLabel = 'Categorias';

    protected static ?string $modelLabel = 'Categoria';

    protected static ?string $pluralModelLabel = 'Categorias';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Categoria')
                    ->description('Plano de contas da tesouraria. Categorias inativas não aparecem em novos lançamentos, mas o histórico é preservado.')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nome')
                            ->required()
                            ->maxLength(100)
                            ->placeholder('Ex.: Eventos e congressos')
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (string $operation, ?string $state, Set $set): void {
                                if ($operation === 'create' && filled($state)) {
                                    $set('code', Str::slug($state));
                                }
                            }),

                        Forms\Components\TextInput::make('code')
                            ->label('Código')
                            ->required()
                            ->maxLength(50)
                            ->alphaDash()
                            ->unique(JubanCategory::class, 'code', ignoreRecord: true)
                            ->helperText('Identificador único, gerado a partir do nome.'),

                        Forms\Components\Select::make('kind')
                            ->label('Natureza')
                            ->options(JubanCategoryKind::class)
                            ->required()
                            ->native(false)
                            ->helperText('Define em quais lançamentos a categoria pode ser usada.'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Ativa')
                            ->default(true)
                            ->inline(false),

                        Forms\Components\Textarea::make('description')
                            ->label('Descrição')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('code')
                    ->label('Código')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('kind')
                    ->label('Natureza')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('transactions_count')
                    ->label('Lançamentos')
                    ->counts('transactions')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Ativa')
                    ->sortable()
                    ->disabled(fn (JubanCategory $record): bool => ! static::canEdit($record)),
            ])
            ->defaultSort('name')
            ->filters([
                Tables\Filters\SelectFilter::make('kind')
                    ->label('Natureza')
                    ->options(JubanCategoryKind::class),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Ativa'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    /**
     * @return Builder<JubanCategory>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<JubanCategory> $query */
        $query = parent::getEloquentQuery();

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListJubanCategories::route('/'),
            'create' => Pages\CreateJubanCategory::route('/create'),
            'edit' => Pages\EditJubanCategory::route('/{record}/edit'),
        ];
    }
}
