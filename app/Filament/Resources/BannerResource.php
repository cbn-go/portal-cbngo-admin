<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BannerResource\Pages;
use App\Models\Banner;
use App\Support\AspectRatio;
use App\Support\LinkTarget;
use App\Support\MediaVariants;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class BannerResource extends Resource
{
    protected static ?string $model = Banner::class;

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationGroup = 'Portal';

    protected static ?string $navigationLabel = 'Avisos da Home';

    protected static ?string $modelLabel = 'Aviso da Home';

    protected static ?string $pluralModelLabel = 'Avisos da Home';

    protected static ?int $navigationSort = 1;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getGloballySearchableAttributes(): array
    {
        return ['title', 'subtitle', 'tag', 'alt_text'];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Foto do Aviso')
                    ->description('Foto quadrada em estilo post de rede social. O texto é exibido ao lado da imagem no site.')
                    ->schema([
                        Forms\Components\FileUpload::make('image')
                            ->label('Foto (proporção 1:1)')
                            ->image()
                            ->required()
                            ->disk(MediaVariants::diskName())
                            ->directory('banners')
                            ->visibility('public')
                            ->maxSize(5120)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->rules([
                                'dimensions:min_width='.Banner::MIN_WIDTH.',min_height='.Banner::MIN_HEIGHT,
                                fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
                                    $dimensions = BannerResource::imageDimensions($value);
                                    if ($dimensions !== null) {
                                        [$width, $height] = $dimensions;
                                        if (! AspectRatio::isSquare($width, $height)) {
                                            $fail('A imagem deve ser quadrada (proporção 1:1, recomendado '.Banner::RECOMMENDED_WIDTH.'×'.Banner::RECOMMENDED_HEIGHT.', mínimo '.Banner::MIN_WIDTH.'×'.Banner::MIN_HEIGHT.'). Use o editor para recortar em 1:1 antes de salvar.');
                                        }
                                    }
                                },
                            ])
                            ->imageEditor()
                            ->imageCropAspectRatio('1:1')
                            ->imageEditorAspectRatios(['1:1', null])
                            ->live()
                            ->helperText('Foto sem texto, quadrada ('.Banner::RECOMMENDED_WIDTH.'×'.Banner::RECOMMENDED_HEIGHT.', mínimo '.Banner::MIN_WIDTH.'×'.Banner::MIN_HEIGHT.'). O título, o subtítulo e o botão são exibidos ao lado da imagem no site. Use o editor (ícone de lápis) para recortar em 1:1.'),

                        Forms\Components\Textarea::make('alt_text')
                            ->label('Texto alternativo (acessibilidade)')
                            ->required()
                            ->rows(2)
                            ->maxLength(500)
                            ->helperText('Descreva a imagem para leitores de tela e buscadores (ex.: pastores reunidos no auditório, jovens em culto ao ar livre).'),
                    ]),

                Forms\Components\Section::make('Texto em Destaque')
                    ->description('Textos principais exibidos ao lado da foto no carrossel da home.')
                    ->schema([
                        Forms\Components\TextInput::make('tag')
                            ->label('Selo de categoria')
                            ->maxLength(30)
                            ->datalist(['Assembleia', 'Juventude', 'Ação social', 'SETEBAN-GO', 'Mulheres', 'Missões', 'Família'])
                            ->placeholder('Ex.: Assembleia, Juventude, Ação social')
                            ->live(onBlur: true)
                            ->helperText('Selo exibido no topo da foto e acima do título (opcional, até 30 caracteres).'),

                        Forms\Components\TextInput::make('title')
                            ->label('Título do aviso')
                            ->required()
                            ->maxLength(70)
                            ->live(onBlur: true)
                            ->placeholder('Ex.: Assembleia Geral Ordinária 2026')
                            ->helperText('Título principal em destaque ao lado da foto (até 70 caracteres).'),

                        Forms\Components\Textarea::make('subtitle')
                            ->label('Subtítulo')
                            ->maxLength(160)
                            ->rows(2)
                            ->live(onBlur: true)
                            ->placeholder('Ex.: Dias 14 e 15 de março, em Anápolis. Inscreva os mensageiros da sua igreja até 28 de fevereiro.')
                            ->helperText('Texto explicativo complementar exibido abaixo do título (até 160 caracteres).')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Ação (Botão)')
                    ->description('Botão de ação do carrossel direcionando para link interno ou externo.')
                    ->schema([
                        Forms\Components\TextInput::make('link_label')
                            ->label('Texto do botão')
                            ->default(Banner::DEFAULT_LINK_LABEL)
                            ->placeholder('Saiba mais')
                            ->maxLength(60)
                            ->live(onBlur: true)
                            ->helperText('Rótulo da ação. Se vazio, exibe "Saiba mais".'),

                        Forms\Components\TextInput::make('link_url')
                            ->label('Link de destino')
                            ->maxLength(255)
                            ->placeholder('/eventos/assembleia ou https://...')
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state, Set $set): void {
                                $set('opens_in_new_tab', LinkTarget::isExternal($state));
                            })
                            ->rules([
                                fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
                                    if (filled($value) && ! LinkTarget::isValid((string) $value)) {
                                        $fail('Informe um caminho do site (começando com /) ou uma URL completa com http:// ou https://.');
                                    }
                                },
                            ])
                            ->helperText('Caminho do site (ex.: /eventos) ou URL completa. Links para fora de cbngo.org.br abrem em nova aba por padrão.'),

                        Forms\Components\Toggle::make('opens_in_new_tab')
                            ->label('Abrir em nova aba')
                            ->default(false)
                            ->inline(false)
                            ->live()
                            ->visible(fn (Get $get): bool => filled($get('link_url'))),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Exibição & Vigência')
                    ->schema([
                        Forms\Components\TextInput::make('meta')
                            ->label('Linha de apoio (data e local)')
                            ->maxLength(60)
                            ->placeholder('Ex.: 14–15 mar · Anápolis ou Até 30 de junho')
                            ->live(onBlur: true)
                            ->helperText('Informação curta de data, período ou local exibida ao lado do botão.'),

                        Forms\Components\DateTimePicker::make('starts_at')
                            ->label('Início da exibição')
                            ->native(false)
                            ->displayFormat('d/m/Y H:i')
                            ->seconds(false)
                            ->helperText('Deixe vazio para exibir imediatamente.'),

                        Forms\Components\DateTimePicker::make('ends_at')
                            ->label('Fim da exibição')
                            ->native(false)
                            ->displayFormat('d/m/Y H:i')
                            ->seconds(false)
                            ->rules([
                                fn (Get $get): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                                    $startsAt = $get('starts_at');
                                    if (filled($startsAt) && filled($value) && Carbon::parse($value)->lt(Carbon::parse($startsAt))) {
                                        $fail('O fim da exibição deve ser igual ou posterior ao início.');
                                    }
                                },
                            ])
                            ->helperText('Deixe vazio para não expirar. O aviso sai do carrossel após esta data.'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Ativo')
                            ->default(true)
                            ->inline(false),
                    ])
                    ->columns(4),

                Forms\Components\Section::make('Pré-visualização do aviso na Home')
                    ->description('Simulação de como o aviso será apresentado no carrossel da nova home do site.')
                    ->schema([
                        Forms\Components\ViewField::make('preview')
                            ->view('filament.resources.banner-resource.preview')
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image')
                    ->label('Foto')
                    ->disk(MediaVariants::diskName())
                    ->square()
                    ->height(56)
                    ->width(56),

                Tables\Columns\TextColumn::make('tag')
                    ->label('Selo')
                    ->badge()
                    ->placeholder('Sem selo')
                    ->searchable(),

                Tables\Columns\TextColumn::make('title')
                    ->label('Título do aviso')
                    ->searchable()
                    ->limit(40)
                    ->tooltip(fn (Banner $record): string => $record->title),

                Tables\Columns\TextColumn::make('validity_status')
                    ->label('Vigência')
                    ->badge(),

                Tables\Columns\TextColumn::make('position')
                    ->label('Ordem')
                    ->sortable(),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Ativo')
                    ->disabled(fn (Banner $record): bool => ! $record->is_square)
                    ->tooltip(fn (Banner $record): ?string => ! $record->is_square ? 'Aviso com foto 16:9 antiga. Edite o aviso e envie uma foto 1:1 para ativar.' : null),
            ])
            ->reorderable('position')
            ->defaultSort('position')
            ->filters([
                Tables\Filters\SelectFilter::make('validity')
                    ->label('Status')
                    ->options([
                        'active' => 'Vigentes',
                        'scheduled' => 'Agendados',
                        'expired' => 'Expirados',
                        'inactive' => 'Inativos',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        /** @var Builder<Banner> $bannerQuery */
                        $bannerQuery = $query;

                        return match ($data['value'] ?? null) {
                            'active' => $bannerQuery->visible(),
                            'scheduled' => $bannerQuery->scheduled(),
                            'expired' => $bannerQuery->expired(),
                            'inactive' => $bannerQuery->inactive(),
                            default => $query,
                        };
                    }),
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
     * Largura e altura da imagem do campo de upload: arquivo recém-enviado
     * (temporário) ou caminho já salvo no disco de mídia.
     *
     * @return array{0: int, 1: int}|null
     */
    public static function imageDimensions(mixed $state): ?array
    {
        $file = is_array($state) ? (array_values($state)[0] ?? null) : $state;

        $contents = match (true) {
            $file instanceof TemporaryUploadedFile => $file->get(),
            is_string($file) && $file !== '' && MediaVariants::disk()->exists($file) => MediaVariants::disk()->get($file),
            default => null,
        };

        if (! is_string($contents) || $contents === '') {
            return null;
        }

        $info = @getimagesizefromstring($contents);

        return $info === false ? null : [(int) $info[0], (int) $info[1]];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBanners::route('/'),
            'create' => Pages\CreateBanner::route('/create'),
            'edit' => Pages\EditBanner::route('/{record}/edit'),
        ];
    }
}
