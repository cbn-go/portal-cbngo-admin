<?php

namespace App\Filament\Resources;

use App\Enums\PublishStatus;
use App\Filament\Resources\ArticleResource\Pages;
use App\Models\Article;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ArticleResource extends Resource
{
    protected static ?string $model = Article::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Conteúdo';

    protected static ?string $navigationLabel = 'Artigos';

    protected static ?string $modelLabel = 'Artigo';

    protected static ?string $pluralModelLabel = 'Artigos';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Conteúdo do Artigo')
                    ->description('Defina o título, o slug de URL e a redação completa do artigo pastoral ou teológico.')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('Título do Artigo')
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
                            ->unique(Article::class, 'slug', ignoreRecord: true)
                            ->alphaDash()
                            ->helperText('Identificador único amigável utilizado nas rotas públicas do portal.'),

                        Forms\Components\Textarea::make('excerpt')
                            ->label('Resumo / Linha Fina')
                            ->maxLength(500)
                            ->rows(3)
                            ->columnSpanFull()
                            ->helperText('Breve introdução exibida nos cards de destaque e listagens do portal.'),

                        Forms\Components\RichEditor::make('content')
                            ->label('Texto do Artigo')
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
                            ->fileAttachmentsDirectory('articles/attachments')
                            ->fileAttachmentsVisibility('public'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Publicação & Autoria')
                    ->description('Controle a atribuição de autoria, imagem de capa e status de divulgação.')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('Autor do Artigo')
                            ->relationship('author', 'name')
                            ->searchable()
                            ->preload()
                            ->visible(fn (): bool => auth()->user()?->role->hasAdminPanelAccess() ?? false)
                            ->default(fn () => auth()->id())
                            ->required(fn (): bool => auth()->user()?->role->hasAdminPanelAccess() ?? false),

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

                        Forms\Components\FileUpload::make('cover_image')
                            ->label('Imagem de Capa')
                            ->image()
                            ->disk('public')
                            ->directory('articles/covers')
                            ->visibility('public')
                            ->maxSize(5120)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->imageCropAspectRatio('16:9')
                            ->imageResizeMode('cover')
                            ->imageResizeTargetWidth('1200')
                            ->imageResizeTargetHeight('675')
                            ->columnSpanFull()
                            ->helperText('Formatos: JPG, PNG, WEBP (máx. 5MB). Proporção recomendada 16:9 (1200x675).'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('cover_image')
                    ->label('Capa')
                    ->disk('public')
                    ->circular()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('title')
                    ->label('Título')
                    ->searchable()
                    ->sortable()
                    ->limit(45)
                    ->tooltip(fn (Article $record): string => $record->title),

                Tables\Columns\TextColumn::make('author.name')
                    ->label('Autor')
                    ->searchable()
                    ->sortable()
                    ->visible(fn (): bool => auth()->user()?->role->hasAdminPanelAccess() ?? false),

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

                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Autor')
                    ->relationship('author', 'name')
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
     * @return Builder<Article>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Article> $query */
        $query = parent::getEloquentQuery()->with('author');

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListArticles::route('/'),
            'create' => Pages\CreateArticle::route('/create'),
            'edit' => Pages\EditArticle::route('/{record}/edit'),
        ];
    }
}
