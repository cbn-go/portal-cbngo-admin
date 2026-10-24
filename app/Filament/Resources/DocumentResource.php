<?php

namespace App\Filament\Resources;

use App\Enums\DocumentCategory;
use App\Filament\Resources\DocumentResource\Pages;
use App\Models\Document;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentResource extends Resource
{
    protected static ?string $model = Document::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Institucional';

    protected static ?string $navigationLabel = 'Documentos';

    protected static ?string $modelLabel = 'Documento';

    protected static ?string $pluralModelLabel = 'Documentos';

    protected static ?int $navigationSort = 2;

    /**
     * Tipos MIME aceitos no upload: PDF, DOC e DOCX.
     *
     * @var list<string>
     */
    public const ACCEPTED_MIME_TYPES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Identificação')
                    ->description('Documentos oficiais listados para consulta e download no portal público.')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('Título')
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
                            ->unique(Document::class, 'slug', ignoreRecord: true)
                            ->alphaDash(),

                        Forms\Components\Select::make('category')
                            ->label('Categoria')
                            ->options(DocumentCategory::class)
                            ->default(DocumentCategory::OUTROS)
                            ->required()
                            ->native(false),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Exibir no portal')
                            ->default(true)
                            ->inline(false),

                        Forms\Components\Textarea::make('description')
                            ->label('Descrição')
                            ->rows(3)
                            ->maxLength(1000)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Arquivo ou Link')
                    ->description('Envie o arquivo (PDF, DOC ou DOCX) ou informe o link de uma página externa. O formato exibido no portal é detectado automaticamente.')
                    ->schema([
                        Forms\Components\FileUpload::make('file')
                            ->label('Arquivo')
                            ->disk(config('media.disk'))
                            ->directory('documents/files')
                            ->visibility('public')
                            ->maxSize(10240)
                            ->acceptedFileTypes(self::ACCEPTED_MIME_TYPES)
                            ->openable()
                            ->downloadable()
                            ->live()
                            ->required(fn (Get $get): bool => blank($get('external_url')))
                            ->helperText('PDF, DOC ou DOCX (máx. 10MB).'),

                        Forms\Components\TextInput::make('external_url')
                            ->label('Link externo')
                            ->url()
                            ->maxLength(255)
                            ->placeholder('https://cbngo.com.br/pacto-renovado/')
                            ->live(onBlur: true)
                            ->required(fn (Get $get): bool => blank($get('file')))
                            ->rules([
                                fn (Get $get): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                                    if (filled($value) && filled($get('file'))) {
                                        $fail('Informe o arquivo ou o link externo, não os dois.');
                                    }
                                },
                            ])
                            ->helperText('Use apenas quando o documento for uma página e não um arquivo.'),

                        Forms\Components\Placeholder::make('file_format')
                            ->label('Formato detectado')
                            ->content(fn (?Document $record): string => $record->file_format ?? '—')
                            ->visibleOn('edit'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Título')
                    ->searchable()
                    ->limit(50)
                    ->tooltip(fn (Document $record): string => $record->title),

                Tables\Columns\TextColumn::make('category')
                    ->label('Categoria')
                    ->badge(),

                Tables\Columns\TextColumn::make('file_format')
                    ->label('Formato')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Ativo'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Atualizado em')
                    ->dateTime('d/m/Y H:i')
                    ->toggleable(),
            ])
            ->reorderable('position')
            ->defaultSort('position')
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->label('Categoria')
                    ->options(DocumentCategory::class),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Ativo'),
            ])
            ->actions([
                Tables\Actions\Action::make('open')
                    ->label('Abrir')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (Document $record): ?string => self::publicUrl($record), shouldOpenInNewTab: true),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function publicUrl(Document $document): ?string
    {
        if (filled($document->file)) {
            return Storage::disk(config('media.disk'))->url($document->file);
        }

        return $document->external_url;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDocuments::route('/'),
            'create' => Pages\CreateDocument::route('/create'),
            'edit' => Pages\EditDocument::route('/{record}/edit'),
        ];
    }
}
