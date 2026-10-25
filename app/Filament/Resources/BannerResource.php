<?php

namespace App\Filament\Resources;

use App\Enums\BannerFocalPoint;
use App\Filament\Resources\BannerResource\Pages;
use App\Models\Banner;
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
                Forms\Components\Section::make('Conteúdo')
                    ->description('Título e subtítulo são escritos pelo portal público sobre a imagem do hero.')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('Título')
                            ->required()
                            ->maxLength(120),

                        Forms\Components\TextInput::make('subtitle')
                            ->label('Subtítulo')
                            ->maxLength(200),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Imagem')
                    ->schema([
                        Forms\Components\FileUpload::make('image')
                            ->label('Imagem do banner')
                            ->image()
                            ->required()
                            ->disk(MediaVariants::diskName())
                            ->directory('banners')
                            ->visibility('public')
                            ->maxSize(5120)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->rules(['dimensions:min_width=1280,min_height=533'])
                            ->columnSpanFull()
                            ->helperText('Recomendado: 1920×800 px (mínimo 1280×533), JPG, PNG ou WEBP de até 5MB. Não use artes com texto embutido: título, subtítulo e botão são escritos pelo site e se adaptam a cada tela.'),

                        Forms\Components\Select::make('focal_point')
                            ->label('Ponto focal')
                            ->options(BannerFocalPoint::class)
                            ->default(BannerFocalPoint::CENTER)
                            ->required()
                            ->native(false)
                            ->helperText('Parte da imagem que continua visível quando ela é cortada em telas estreitas.'),

                        Forms\Components\TextInput::make('alt_text')
                            ->label('Texto alternativo')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Descreva a imagem para leitores de tela.'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Link (opcional)')
                    ->description('Sem link, o banner não exibe botão.')
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

                        Forms\Components\TextInput::make('link_label')
                            ->label('Texto do botão')
                            ->maxLength(60)
                            ->placeholder(Banner::DEFAULT_LINK_LABEL)
                            ->visible(fn (Get $get): bool => filled($get('link_url'))),

                        Forms\Components\Toggle::make('opens_in_new_tab')
                            ->label('Abrir em nova aba')
                            ->default(false)
                            ->inline(false)
                            ->visible(fn (Get $get): bool => filled($get('link_url'))),
                    ])
                    ->columns(3),

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
                    ->height(48)
                    ->width(115),

                Tables\Columns\TextColumn::make('title')
                    ->label('Título')
                    ->searchable()
                    ->limit(40)
                    ->description(fn (Banner $record): ?string => $record->subtitle),

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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBanners::route('/'),
            'create' => Pages\CreateBanner::route('/create'),
            'edit' => Pages\EditBanner::route('/{record}/edit'),
        ];
    }
}
