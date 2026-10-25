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

    protected static ?string $navigationGroup = 'Comunicação';

    protected static ?string $navigationLabel = 'Banners da Home';

    protected static ?string $modelLabel = 'Banner';

    protected static ?string $pluralModelLabel = 'Banners da Home';

    protected static ?int $navigationSort = 2;

    protected static bool $hasTitleCaseModelLabel = false;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Arte do banner')
                    ->description('O banner é a arte completa, já com textos e logos. O site exibe a imagem inteira, sem cortes e sem escrever nada por cima.')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('Nome do banner (uso interno)')
                            ->required()
                            ->maxLength(120)
                            ->helperText('Aparece só na listagem do painel e nos controles do carrossel para leitores de tela. Não é exibido sobre a imagem.'),

                        Forms\Components\FileUpload::make('image')
                            ->label('Arte')
                            ->image()
                            ->required()
                            ->disk(MediaVariants::diskName())
                            ->directory('banners')
                            ->visibility('public')
                            ->maxSize(5120)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->rules(['dimensions:min_width='.Banner::MIN_WIDTH.',min_height='.Banner::MIN_HEIGHT])
                            ->imageEditor()
                            ->imageEditorAspectRatios(['16:9', null])
                            ->live()
                            ->helperText('Envie a arte final do banner, já com textos e logos, em 16:9 ('.Banner::RECOMMENDED_WIDTH.'×'.Banner::RECOMMENDED_HEIGHT.'). A imagem é exibida inteira no site. Mínimo '.Banner::MIN_WIDTH.'×'.Banner::MIN_HEIGHT.', JPG, PNG ou WEBP até 5MB. Se a arte fugir do padrão, use o editor (ícone de lápis) para recortar em 16:9.'),

                        Forms\Components\Placeholder::make('aspect_ratio_warning')
                            ->hiddenLabel()
                            ->content(fn (Get $get): ?string => self::aspectRatioWarning($get('image')))
                            ->visible(fn (Get $get): bool => self::aspectRatioWarning($get('image')) !== null),

                        Forms\Components\Textarea::make('alt_text')
                            ->label('Texto alternativo (transcrição da arte)')
                            ->required()
                            ->rows(3)
                            ->maxLength(500)
                            ->helperText('Transcreva as informações da arte (nome do evento, datas, local e chamada), porque o texto da imagem não é lido por leitores de tela nem por buscadores.'),
                    ]),

                Forms\Components\Section::make('Link (opcional)')
                    ->description('Com link, a arte inteira fica clicável no site. Sem link, a imagem não é clicável.')
                    ->schema([
                        Forms\Components\TextInput::make('link_url')
                            ->label('Link')
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
                            ->visible(fn (Get $get): bool => filled($get('link_url'))),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Exibição')
                    ->schema([
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
                            ->helperText('Deixe vazio para não expirar. O banner some sozinho depois desta data.'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Ativo')
                            ->default(true)
                            ->inline(false),
                    ])
                    ->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image')
                    ->label('Imagem')
                    ->disk(MediaVariants::diskName())
                    ->height(54)
                    ->width(96),

                Tables\Columns\TextColumn::make('title')
                    ->label('Nome')
                    ->searchable()
                    ->limit(40)
                    ->tooltip(fn (Banner $record): string => $record->alt_text),

                Tables\Columns\TextColumn::make('validity_status')
                    ->label('Status')
                    ->badge(),

                Tables\Columns\TextColumn::make('link_url')
                    ->label('Link')
                    ->limit(30)
                    ->icon(fn (Banner $record): ?string => $record->opens_in_new_tab ? 'heroicon-m-arrow-top-right-on-square' : null)
                    ->placeholder('Sem link')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('starts_at')
                    ->label('Início')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Imediato')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('ends_at')
                    ->label('Fim')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Sem prazo')
                    ->toggleable(),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Ativo'),
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
     * Aviso (sem bloquear o salvamento) quando a arte foge de 16:9 em mais de 5%.
     */
    public static function aspectRatioWarning(mixed $state): ?string
    {
        $dimensions = self::imageDimensions($state);

        if ($dimensions === null) {
            return null;
        }

        [$width, $height] = $dimensions;

        if (AspectRatio::isWithin($width, $height)) {
            return null;
        }

        return sprintf(
            'Atenção: a arte tem %d×%d (proporção %s). O padrão é 16:9 (%d×%d), e fora dele o site exibe faixas nas laterais ou em cima e embaixo. Use o editor para recortar em 16:9 ou envie uma nova arte.',
            $width,
            $height,
            AspectRatio::format($width, $height),
            Banner::RECOMMENDED_WIDTH,
            Banner::RECOMMENDED_HEIGHT,
        );
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
