<?php

namespace App\Filament\Resources;

use App\Enums\PublishStatus;
use App\Filament\Resources\NewsResource\Pages;
use App\Models\News;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class NewsResource extends Resource
{
    protected static ?string $model = News::class;

    protected static ?string $navigationIcon = 'heroicon-o-newspaper';

    protected static ?string $navigationGroup = 'Comunicação';

    protected static ?string $navigationLabel = 'Notícias';

    protected static ?string $modelLabel = 'Notícia';

    protected static ?string $pluralModelLabel = 'Notícias';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Conteúdo da Notícia')
                    ->description('Defina o título, identificador de URL e redação completa do comunicado ou cobertura do evento.')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('Título da Notícia')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (string $operation, ?string $state, Set $set): void {
                                if ($operation === 'create' && filled($state)) {
                                    $set('slug', Str::slug($state));
                                }
                            }),

                        Forms\Components\TextInput::make('slug')
                            ->label('Slug / Identificador URL')
                            ->required()
                            ->maxLength(255)
                            ->unique(News::class, 'slug', ignoreRecord: true)
                            ->alphaDash()
                            ->helperText('Identificador único amigável utilizado nas rotas públicas do portal.'),

                        Forms\Components\Textarea::make('excerpt')
                            ->label('Resumo / Linha Fina')
                            ->maxLength(500)
                            ->rows(3)
                            ->columnSpanFull()
                            ->helperText('Breve introdução exibida nos cards de destaque e listagens do portal.'),

                        Forms\Components\RichEditor::make('content')
                            ->label('Texto da Notícia')
                            ->required()
                            ->columnSpanFull()
                            ->toolbarButtons([
                                'attachFiles',
                                'blockquote',
                                'bold',
                                'bulletList',
                                'codeBlock',
                                'h2',
                                'h3',
                                'italic',
                                'link',
                                'orderedList',
                                'redo',
                                'strike',
                                'underline',
                                'undo',
                            ])
                            ->fileAttachmentsDisk('public')
                            ->fileAttachmentsDirectory('news/attachments')
                            ->fileAttachmentsVisibility('public'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Mídia & Galeria Visual')
                    ->description('Adicione a imagem principal de destaque e fotos do evento ou celebração.')
                    ->schema([
                        Forms\Components\FileUpload::make('featured_image')
                            ->label('Imagem de Destaque')
                            ->image()
                            ->disk('public')
                            ->directory('news/featured')
                            ->visibility('public')
                            ->maxSize(5120)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->imageCropAspectRatio('16:9')
                            ->imageResizeMode('cover')
                            ->imageResizeTargetWidth('1200')
                            ->imageResizeTargetHeight('675')
                            ->columnSpanFull()
                            ->helperText('Formatos: JPG, PNG, WEBP (máx. 5MB). Proporção recomendada 16:9 (1200x675).'),

                        Forms\Components\FileUpload::make('gallery')
                            ->label('Galeria de Fotos do Evento')
                            ->image()
                            ->multiple()
                            ->reorderable()
                            ->disk('public')
                            ->directory('news/galleries')
                            ->visibility('public')
                            ->maxSize(5120)
                            ->maxFiles(10)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->columnSpanFull()
                            ->helperText('Upload de até 10 fotos da galeria (JPG, PNG, WEBP até 5MB cada).'),
                    ]),

                Forms\Components\Section::make('Classificação & Publicação')
                    ->description('Controle a vinculação institucional, datas e status de divulgação.')
                    ->schema([
                        Forms\Components\Select::make('church_id')
                            ->label('Igreja Local')
                            ->relationship('church', 'name')
                            ->searchable()
                            ->preload()
                            ->visible(fn (): bool => auth()->user()?->role->hasAdminPanelAccess() ?? false)
                            ->helperText('Deixe vazio para publicações institucionais gerais da Convenção Estadual.'),

                        Forms\Components\DatePicker::make('event_date')
                            ->label('Data do Evento')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->helperText('Informe caso a notícia se refira à realização de um evento ou conferência.'),

                        Forms\Components\Toggle::make('is_official')
                            ->label('Notícia Oficial CBN-GO')
                            ->helperText('Marque se esta notícia possui abrangência estadual oficial da Convenção.')
                            ->visible(fn (): bool => auth()->user()?->role->hasAdminPanelAccess() ?? false)
                            ->default(false),

                        Forms\Components\Select::make('status')
                            ->label('Status de Publicação')
                            ->options(PublishStatus::class)
                            ->default(PublishStatus::DRAFT)
                            ->required()
                            ->native(false),

                        Forms\Components\DateTimePicker::make('published_at')
                            ->label('Data de Publicação')
                            ->native(false)
                            ->displayFormat('d/m/Y H:i')
                            ->seconds(false)
                            ->helperText('Deixe vazio para preenchimento automático no momento da publicação.'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('featured_image')
                    ->label('Capa')
                    ->disk('public')
                    ->circular()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('title')
                    ->label('Título')
                    ->searchable()
                    ->sortable()
                    ->limit(40)
                    ->tooltip(fn (News $record): string => $record->title),

                Tables\Columns\TextColumn::make('church.name')
                    ->label('Igreja')
                    ->searchable()
                    ->sortable()
                    ->placeholder('CBN-GO (Estadual)')
                    ->visible(fn (): bool => auth()->user()?->role->hasAdminPanelAccess() ?? false),

                Tables\Columns\IconColumn::make('is_official')
                    ->label('Oficial')
                    ->boolean()
                    ->sortable()
                    ->visible(fn (): bool => auth()->user()?->role->hasAdminPanelAccess() ?? false),

                Tables\Columns\TextColumn::make('event_date')
                    ->label('Data do Evento')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('published_at')
                    ->label('Publicado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(PublishStatus::class),

                Tables\Filters\TernaryFilter::make('is_official')
                    ->label('Oficial CBN-GO')
                    ->visible(fn (): bool => auth()->user()?->role->hasAdminPanelAccess() ?? false),

                Tables\Filters\SelectFilter::make('church_id')
                    ->label('Igreja')
                    ->relationship('church', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(fn (): bool => auth()->user()?->role->hasAdminPanelAccess() ?? false),
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

    /**
     * @return Builder<News>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<News> $query */
        $query = parent::getEloquentQuery()->with(['church', 'author']);

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNews::route('/'),
            'create' => Pages\CreateNews::route('/create'),
            'edit' => Pages\EditNews::route('/{record}/edit'),
        ];
    }
}
