<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ChurchResource\Pages;
use App\Models\Church;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ChurchResource extends Resource
{
    protected static ?string $model = Church::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = 'Administração';

    protected static ?string $navigationLabel = 'Igrejas';

    protected static ?string $modelLabel = 'Igreja';

    protected static ?string $pluralModelLabel = 'Igrejas';

    protected static ?int $navigationSort = 1;

    /**
     * Dias da semana no formato exibido pelo portal público.
     *
     * @var list<string>
     */
    public const SERVICE_DAYS = [
        'Domingo',
        'Segunda-feira',
        'Terça-feira',
        'Quarta-feira',
        'Quinta-feira',
        'Sexta-feira',
        'Sábado',
    ];

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Identificação da Congregação')
                    ->description('Dados oficiais e institucionais da igreja local filiada à CBN Goiás.')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Razão Social / Nome Fantasia')
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
                            ->unique(Church::class, 'slug', ignoreRecord: true)
                            ->alphaDash()
                            ->helperText('Identificador único usado na página pública da igreja no portal.'),

                        Forms\Components\TextInput::make('cnpj')
                            ->label('CNPJ')
                            ->maxLength(18)
                            ->unique(Church::class, 'cnpj', ignoreRecord: true)
                            ->mask('99.999.999/9999-99')
                            ->placeholder('00.000.000/0000-00'),

                        Forms\Components\TextInput::make('registration_number')
                            ->label('Registro Estatutário / ROL')
                            ->maxLength(50)
                            ->placeholder('Ex: ROL-2026-GO'),

                        Forms\Components\TextInput::make('pastor_name')
                            ->label('Pastor Titular / Presidente')
                            ->maxLength(255),

                        Forms\Components\FileUpload::make('logo')
                            ->label('Logotipo Oficial')
                            ->image()
                            ->disk(config('media.disk'))
                            ->directory('churches/logos')
                            ->visibility('public')
                            ->maxSize(2048)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->imageCropAspectRatio('1:1')
                            ->columnSpanFull()
                            ->helperText('Formatos: JPG, PNG, WEBP (máx. 2MB). Proporção 1:1 quadrada.'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Localização & Endereço')
                    ->description('Localização geográfica do templo para diretório e contatos dos membros.')
                    ->schema([
                        Forms\Components\TextInput::make('zip_code')
                            ->label('CEP')
                            ->maxLength(10)
                            ->mask('99999-999')
                            ->placeholder('74000-000'),

                        Forms\Components\TextInput::make('city')
                            ->label('Cidade')
                            ->required()
                            ->maxLength(100)
                            ->default('Goiânia'),

                        Forms\Components\TextInput::make('state')
                            ->label('Estado (UF)')
                            ->required()
                            ->maxLength(2)
                            ->default('GO'),

                        Forms\Components\TextInput::make('neighborhood')
                            ->label('Bairro')
                            ->maxLength(100),

                        Forms\Components\TextInput::make('address')
                            ->label('Logradouro / Endereço')
                            ->maxLength(255)
                            ->columnSpan(2),

                        Forms\Components\TextInput::make('number')
                            ->label('Número')
                            ->maxLength(20),

                        Forms\Components\TextInput::make('complement')
                            ->label('Complemento')
                            ->maxLength(100),

                        Forms\Components\TextInput::make('latitude')
                            ->label('Latitude')
                            ->numeric()
                            ->minValue(-90)
                            ->maxValue(90)
                            ->placeholder('-16.6868912')
                            ->helperText('No Google Maps, clique com o botão direito no templo e copie as coordenadas.'),

                        Forms\Components\TextInput::make('longitude')
                            ->label('Longitude')
                            ->numeric()
                            ->minValue(-180)
                            ->maxValue(180)
                            ->placeholder('-49.2647943'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Horários de Culto')
                    ->description('Programação semanal exibida na página pública da igreja.')
                    ->schema([
                        Forms\Components\Repeater::make('service_times')
                            ->label('Horários')
                            ->schema([
                                Forms\Components\Select::make('day')
                                    ->label('Dia')
                                    ->options(array_combine(self::SERVICE_DAYS, self::SERVICE_DAYS))
                                    ->required()
                                    ->native(false),

                                Forms\Components\TextInput::make('time')
                                    ->label('Horário')
                                    ->required()
                                    ->mask('99h99')
                                    ->regex('/^([01]\d|2[0-3])h[0-5]\d$/')
                                    ->placeholder('19h00'),

                                Forms\Components\TextInput::make('label')
                                    ->label('Descrição')
                                    ->maxLength(100)
                                    ->placeholder('Culto de Celebração'),
                            ])
                            ->columns(3)
                            ->reorderable()
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => implode(' · ', array_filter([
                                trim(($state['day'] ?? '').' '.($state['time'] ?? '')),
                                $state['label'] ?? null,
                            ])) ?: null)
                            ->addActionLabel('Adicionar horário')
                            ->defaultItems(0),
                    ]),

                Forms\Components\Section::make('Contato & Redes Sociais')
                    ->description('Canais de comunicação direta e presença digital da congregação.')
                    ->schema([
                        Forms\Components\TextInput::make('phone')
                            ->label('Telefone Fixo')
                            ->tel()
                            ->maxLength(20)
                            ->mask('(99) 9999-9999'),

                        Forms\Components\TextInput::make('cellphone')
                            ->label('Celular / WhatsApp')
                            ->tel()
                            ->maxLength(20)
                            ->mask('(99) 99999-9999'),

                        Forms\Components\TextInput::make('email')
                            ->label('E-mail Institucional')
                            ->email()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\KeyValue::make('social_links')
                            ->label('Redes Sociais & Links Oficiais')
                            ->keyLabel('Canal / Rede')
                            ->valueLabel('Link / URL')
                            ->reorderable()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Situação Cadastral')
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label('Igreja Ativa na Convenção')
                            ->default(true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('logo')
                    ->label('Logo')
                    ->disk(config('media.disk'))
                    ->circular()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Igreja')
                    ->searchable()
                    ->sortable()
                    ->limit(35)
                    ->tooltip(fn (Church $record): string => $record->name),

                Tables\Columns\TextColumn::make('city')
                    ->label('Cidade')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('pastor_name')
                    ->label('Pastor Titular')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Não informado'),

                Tables\Columns\TextColumn::make('cellphone')
                    ->label('Contato')
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Ativa')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Cadastrada em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name', 'asc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status Ativo'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->hidden(fn (Church $record): bool => ! auth()->user()?->can('delete', $record)),
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
            'index' => Pages\ListChurches::route('/'),
            'create' => Pages\CreateChurch::route('/create'),
            'edit' => Pages\EditChurch::route('/{record}/edit'),
        ];
    }
}
