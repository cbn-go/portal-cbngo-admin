<?php

namespace App\Filament\Ormiban\Resources;

use App\Enums\ChurchType;
use App\Filament\Ormiban\Resources\MissionResource\Pages;
use App\Filament\Resources\ChurchResource;
use App\Models\Church;
use App\Models\Minister;
use App\Models\Municipality;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class MissionResource extends Resource
{
    protected static ?string $model = Church::class;

    protected static ?string $navigationIcon = 'heroicon-o-flag';

    protected static ?string $navigationLabel = 'Missões da Igreja';

    protected static ?string $modelLabel = 'Missão';

    protected static ?string $pluralModelLabel = 'Missões';

    protected static ?string $slug = 'missoes';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->role->isMinister() && ($user->contentChurchId() !== null || $user->minister?->id !== null);
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();
        $churchId = $user?->contentChurchId();
        $ministerId = $user?->minister?->id;

        return parent::getEloquentQuery()
            ->where('type', ChurchType::MISSION)
            ->where(function (Builder $query) use ($churchId, $ministerId): void {
                if ($churchId !== null) {
                    $query->where('parent_church_id', $churchId)
                        ->orWhere('id', $churchId);
                }

                if ($ministerId !== null) {
                    $query->orWhere('leader_minister_id', $ministerId);
                }

                if ($churchId === null && $ministerId === null) {
                    $query->whereRaw('1 = 0');
                }
            });
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Identificação da Missão')
                    ->description('Dados oficiais da congregação missionária vinculada à igreja-mãe.')
                    ->schema([
                        Forms\Components\Placeholder::make('parent_church_info')
                            ->label('Igreja-Mãe')
                            ->content(function (?Church $record): string {
                                $mother = $record?->parentChurch ?? auth()->user()?->contentChurch();

                                if (! $mother) {
                                    return 'Igreja vinculada ao pastor';
                                }

                                $cnpjInfo = $mother->cnpj ? " · CNPJ: {$mother->cnpj}" : '';

                                return "{$mother->name}{$cnpjInfo}";
                            }),

                        Forms\Components\TextInput::make('name')
                            ->label('Nome da Missão')
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
                            ->helperText('Identificador único usado na página pública da missão no portal.'),

                        Forms\Components\Select::make('leader_minister_id')
                            ->label('Ministro Líder / Responsável')
                            ->relationship('leaderMinister', 'name')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (?int $state, Set $set): void {
                                if ($state && ($minister = Minister::find($state))) {
                                    $set('pastor_name', $minister->name);
                                }
                            })
                            ->helperText('Ministro filiado à Ormiban responsável pela liderança da missão.'),

                        Forms\Components\TextInput::make('pastor_name')
                            ->label('Pastor / Líder Local')
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
                    ->description('Localização geográfica onde a missão se reúne.')
                    ->schema([
                        Forms\Components\TextInput::make('zip_code')
                            ->label('CEP')
                            ->maxLength(10)
                            ->mask('99999-999')
                            ->placeholder('74000-000'),

                        Forms\Components\Select::make('municipality_id')
                            ->label('Cidade / Município (Goiás - IBGE)')
                            ->relationship('municipality', 'name')
                            ->searchable()
                            ->preload()
                            ->required(fn (Forms\Get $get): bool => blank($get('city')))
                            ->live()
                            ->afterStateHydrated(function (Forms\Components\Select $component, ?int $state, ?Church $record, Set $set): void {
                                if (! $state && $record && filled($record->city)) {
                                    $m = Municipality::findByNormalizedName($record->city);
                                    if ($m) {
                                        $component->state($m->id);
                                        $set('city', $m->name);
                                    }
                                }
                            })
                            ->afterStateUpdated(function (?int $state, Set $set): void {
                                if ($state && ($municipality = Municipality::find($state))) {
                                    $set('city', $municipality->name);
                                }
                            }),

                        Forms\Components\TextInput::make('city')
                            ->label('Nome do Município')
                            ->required()
                            ->maxLength(100),

                        Forms\Components\TextInput::make('state')
                            ->label('Estado')
                            ->default('GO')
                            ->disabled()
                            ->dehydrated(),

                        Forms\Components\TextInput::make('neighborhood')
                            ->label('Bairro / Setor')
                            ->maxLength(100),

                        Forms\Components\TextInput::make('address')
                            ->label('Logradouro (Rua, Avenida, Praça)')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('number')
                            ->label('Número')
                            ->maxLength(20),

                        Forms\Components\TextInput::make('complement')
                            ->label('Complemento')
                            ->maxLength(100),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Horários de Culto & Reuniões')
                    ->description('Programação semanal dos cultos da missão.')
                    ->schema([
                        Forms\Components\Repeater::make('service_times')
                            ->label('Cultos Semanais')
                            ->schema([
                                Forms\Components\Select::make('day')
                                    ->label('Dia da Semana')
                                    ->options(array_combine(ChurchResource::SERVICE_DAYS, ChurchResource::SERVICE_DAYS))
                                    ->required(),

                                Forms\Components\TextInput::make('time')
                                    ->label('Horário')
                                    ->placeholder('19h30')
                                    ->required(),

                                Forms\Components\TextInput::make('label')
                                    ->label('Descrição do Culto')
                                    ->placeholder('Culto da Família / Oração')
                                    ->maxLength(100),
                            ])
                            ->columns(3)
                            ->defaultItems(0)
                            ->reorderable()
                            ->addActionLabel('Adicionar Culto'),
                    ]),

                Forms\Components\Section::make('Contatos & Redes Sociais')
                    ->schema([
                        Forms\Components\TextInput::make('cellphone')
                            ->label('Celular / WhatsApp')
                            ->tel()
                            ->maxLength(20)
                            ->mask('(99) 99999-9999'),

                        Forms\Components\TextInput::make('phone')
                            ->label('Telefone Fixo')
                            ->tel()
                            ->maxLength(20)
                            ->mask('(99) 9999-9999'),

                        Forms\Components\TextInput::make('email')
                            ->label('E-mail de Contato')
                            ->email()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\KeyValue::make('social_links')
                            ->label('Redes Sociais & Links')
                            ->keyLabel('Canal / Rede')
                            ->valueLabel('Link / URL')
                            ->reorderable()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Situação Cadastral')
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label('Missão Ativa')
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
                    ->label('Missão')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('parentChurch.name')
                    ->label('Igreja-Mãe')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('city')
                    ->label('Cidade')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('pastor_name')
                    ->label('Ministro Líder')
                    ->placeholder('Não informado')
                    ->searchable(),

                Tables\Columns\TextColumn::make('cellphone')
                    ->label('Contato')
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Ativa')
                    ->boolean(),
            ])
            ->defaultSort('name', 'asc')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->hidden(fn (Church $record): bool => ! auth()->user()?->can('delete', $record)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMissions::route('/'),
            'create' => Pages\CreateMission::route('/create'),
            'edit' => Pages\EditMission::route('/{record}/edit'),
        ];
    }
}
