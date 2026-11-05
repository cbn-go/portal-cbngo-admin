<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MinisterResource\Pages;
use App\Filament\Resources\MinisterResource\RelationManagers\FamilyMembersRelationManager;
use App\Filament\Resources\MinisterResource\RelationManagers\HistoryEventsRelationManager;
use App\Models\Minister;
use App\Models\User;
use App\Support\Cpf;
use Closure;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MinisterResource extends Resource
{
    protected static ?string $model = Minister::class;

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationGroup = 'Secretaria';

    protected static ?string $navigationLabel = 'Rol Ormiban';

    protected static ?string $modelLabel = 'Ministro';

    protected static ?string $pluralModelLabel = 'Ministros';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'registration_number'];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Dados pessoais')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('registration_number')
                            ->label('Matrícula')
                            ->required()
                            ->maxLength(20)
                            ->unique(Minister::class, 'registration_number', ignoreRecord: true),

                        Forms\Components\TextInput::make('function')
                            ->label('Função')
                            ->required()
                            ->maxLength(50),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options(fn (?Minister $record): array => array_filter([
                                Minister::STATUS_ACTIVE => 'Ativo(a)',
                                Minister::STATUS_INACTIVE => 'Inativo(a)',
                                $record?->status => $record?->status,
                            ]))
                            ->default(Minister::STATUS_ACTIVE)
                            ->required()
                            ->native(false),

                        Forms\Components\TextInput::make('cpf')
                            ->label('CPF')
                            ->required()
                            ->maxLength(14)
                            ->dehydrateStateUsing(fn (?string $state): ?string => Cpf::normalize($state))
                            ->rules([
                                fn (?Minister $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                                    if (! is_string($value) || ! Cpf::isValid($value)) {
                                        $fail('Informe um CPF válido.');

                                        return;
                                    }

                                    $digits = Cpf::normalize($value);
                                    $ministerId = $record?->getKey();

                                    $cpfTaken = Minister::query()
                                        ->where('cpf', $digits)
                                        ->when($ministerId !== null, fn (Builder $query): Builder => $query->whereKeyNot($ministerId))
                                        ->exists();

                                    if ($cpfTaken) {
                                        $fail('Este CPF já está cadastrado no rol.');

                                        return;
                                    }

                                    $linkedUserId = $record?->user_id;

                                    $userTaken = User::query()
                                        ->where('cpf', $digits)
                                        ->when($linkedUserId !== null, fn (Builder $query): Builder => $query->whereKeyNot($linkedUserId))
                                        ->exists();

                                    if ($userTaken) {
                                        $fail('Este CPF já está vinculado a outro usuário.');
                                    }
                                },
                            ]),

                        Forms\Components\TextInput::make('email')
                            ->label('E-mail')
                            ->email()
                            ->maxLength(255)
                            ->nullable()
                            ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? $state : null)
                            ->rule(fn (?Minister $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                                if (! is_string($value) || blank($value)) {
                                    return;
                                }

                                $linkedUserId = $record?->user_id;

                                $taken = User::query()
                                    ->where('email', $value)
                                    ->when($linkedUserId !== null, fn (Builder $query): Builder => $query->whereKeyNot($linkedUserId))
                                    ->exists();

                                if ($taken) {
                                    $fail('Este e-mail já está em uso.');
                                }
                            }),

                        Forms\Components\DatePicker::make('birth_date')
                            ->label('Nascimento')
                            ->native(false)
                            ->displayFormat('d/m/Y'),

                        Forms\Components\TextInput::make('phone')
                            ->label('Telefone')
                            ->maxLength(50),

                        Forms\Components\TextInput::make('gender')
                            ->label('Sexo')
                            ->maxLength(20),

                        Forms\Components\TextInput::make('marital_status')
                            ->label('Estado civil')
                            ->maxLength(50),

                        Forms\Components\TextInput::make('nationality')
                            ->label('Nacionalidade')
                            ->maxLength(100),

                        Forms\Components\TextInput::make('place_of_birth')
                            ->label('Naturalidade')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('education_level')
                            ->label('Escolaridade')
                            ->maxLength(100),

                        Forms\Components\TextInput::make('profession')
                            ->label('Profissão')
                            ->maxLength(150),

                        Forms\Components\TextInput::make('identity_document')
                            ->label('RG')
                            ->maxLength(50),

                        Forms\Components\TextInput::make('identity_issuer')
                            ->label('Órgão emissor')
                            ->maxLength(50),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Igreja')
                    ->schema([
                        Forms\Components\TextInput::make('sectional')
                            ->label('Seccional')
                            ->required()
                            ->default('ORMIBAN GO')
                            ->maxLength(50),

                        Forms\Components\TextInput::make('church_name')
                            ->label('Igreja (nome no rol)')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Select::make('church_id')
                            ->label('Igreja vinculada')
                            ->relationship('church', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable(),

                        Forms\Components\TextInput::make('address')
                            ->label('Endereço')
                            ->maxLength(65535)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('zip_code')
                            ->label('CEP')
                            ->maxLength(20),
                    ])
                    ->columns(2),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Dados pessoais')
                    ->schema([
                        Infolists\Components\TextEntry::make('name')->label('Nome'),
                        Infolists\Components\TextEntry::make('registration_number')->label('Matrícula'),
                        Infolists\Components\TextEntry::make('function')->label('Função'),
                        Infolists\Components\TextEntry::make('status')->label('Status'),
                        Infolists\Components\TextEntry::make('cpf')
                            ->label('CPF')
                            ->formatStateUsing(fn (?string $state): string => Cpf::format($state) ?? '—'),
                        Infolists\Components\TextEntry::make('email')->label('E-mail')->placeholder('—'),
                        Infolists\Components\TextEntry::make('birth_date')->label('Nascimento')->date('d/m/Y')->placeholder('—'),
                        Infolists\Components\TextEntry::make('phone')->label('Telefone')->placeholder('—'),
                        Infolists\Components\TextEntry::make('gender')->label('Sexo')->placeholder('—'),
                        Infolists\Components\TextEntry::make('marital_status')->label('Estado civil')->placeholder('—'),
                        Infolists\Components\TextEntry::make('nationality')->label('Nacionalidade')->placeholder('—'),
                        Infolists\Components\TextEntry::make('place_of_birth')->label('Naturalidade')->placeholder('—'),
                        Infolists\Components\TextEntry::make('education_level')->label('Escolaridade')->placeholder('—'),
                        Infolists\Components\TextEntry::make('profession')->label('Profissão')->placeholder('—'),
                        Infolists\Components\TextEntry::make('identity_document')->label('RG')->placeholder('—'),
                        Infolists\Components\TextEntry::make('identity_issuer')->label('Órgão emissor')->placeholder('—'),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Igreja')
                    ->schema([
                        Infolists\Components\TextEntry::make('sectional')->label('Seccional'),
                        Infolists\Components\TextEntry::make('church_name')->label('Igreja (nome no rol)'),
                        Infolists\Components\TextEntry::make('church.name')->label('Igreja vinculada')->placeholder('—'),
                        Infolists\Components\TextEntry::make('address')->label('Endereço')->placeholder('—'),
                        Infolists\Components\TextEntry::make('zip_code')->label('CEP')->placeholder('—'),
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

                Tables\Columns\TextColumn::make('registration_number')
                    ->label('Matrícula')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('function')
                    ->label('Função')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => $state === Minister::STATUS_ACTIVE ? 'success' : 'gray'),

                Tables\Columns\TextColumn::make('church.name')
                    ->label('Igreja')
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('cpf')
                    ->label('CPF')
                    ->formatStateUsing(fn (?string $state): string => Cpf::mask($state) ?? '—')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        $digits = preg_replace('/\D/', '', $search) ?? '';

                        if ($digits === '') {
                            return $query->whereRaw('0 = 1');
                        }

                        return $query->where('ministers.cpf', 'like', '%'.$digits.'%');
                    }),

                Tables\Columns\TextColumn::make('password_defined')
                    ->label('Senha')
                    ->state(function (Minister $record): string {
                        $user = $record->user;

                        if ($user === null || $user->must_set_password || $user->password === null) {
                            return 'Não definida';
                        }

                        return 'Definida';
                    }),
            ])
            ->defaultSort('name')
            ->filters([
                Tables\Filters\SelectFilter::make('function')
                    ->label('Função')
                    ->options(fn (): array => Minister::query()
                        ->whereNotNull('function')
                        ->distinct()
                        ->orderBy('function')
                        ->pluck('function', 'function')
                        ->all()),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        Minister::STATUS_ACTIVE => 'Ativo(a)',
                        Minister::STATUS_INACTIVE => 'Inativo(a)',
                    ]),

                Tables\Filters\SelectFilter::make('church_id')
                    ->label('Igreja')
                    ->relationship('church', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\TernaryFilter::make('password_defined')
                    ->label('Senha definida')
                    ->placeholder('Todas')
                    ->trueLabel('Com senha')
                    ->falseLabel('Sem senha')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereHas(
                            'user',
                            fn (Builder $user): Builder => $user
                                ->where('must_set_password', false)
                                ->whereNotNull('password'),
                        ),
                        false: fn (Builder $query): Builder => $query->where(
                            fn (Builder $inner): Builder => $inner
                                ->whereDoesntHave('user')
                                ->orWhereHas(
                                    'user',
                                    fn (Builder $user): Builder => $user
                                        ->where('must_set_password', true)
                                        ->orWhereNull('password'),
                                ),
                        ),
                    ),

                Tables\Filters\TernaryFilter::make('has_diploma')
                    ->label('Diplomação')
                    ->placeholder('Todas')
                    ->trueLabel('Com diplomação')
                    ->falseLabel('Sem diplomação')
                    ->queries(
                        true: fn (Builder $query): Builder => self::whereHasHistoryCode($query, '1'),
                        false: fn (Builder $query): Builder => self::whereMissingHistoryCode($query, '1'),
                    ),

                Tables\Filters\TernaryFilter::make('has_ordination')
                    ->label('Ordenação')
                    ->placeholder('Todas')
                    ->trueLabel('Com ordenação')
                    ->falseLabel('Sem ordenação')
                    ->queries(
                        true: fn (Builder $query): Builder => self::whereHasHistoryCode($query, '2'),
                        false: fn (Builder $query): Builder => self::whereMissingHistoryCode($query, '2'),
                    ),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('activate')
                    ->label('Ativar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->authorize(fn (Minister $record): bool => auth()->user()?->can('update', $record) ?? false)
                    ->visible(fn (Minister $record): bool => $record->status !== Minister::STATUS_ACTIVE)
                    ->action(function (Minister $record): void {
                        $record->activate();

                        Notification::make()
                            ->title('Ministro ativado')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('deactivate')
                    ->label('Desativar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->authorize(fn (Minister $record): bool => auth()->user()?->can('update', $record) ?? false)
                    ->visible(fn (Minister $record): bool => $record->status === Minister::STATUS_ACTIVE)
                    ->action(function (Minister $record): void {
                        $record->deactivate();

                        Notification::make()
                            ->title('Ministro desativado')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('forcePasswordReset')
                    ->label('Forçar redefinição de senha')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('A senha atual será invalidada. No próximo acesso o ministro define uma nova senha.')
                    ->authorize(fn (Minister $record): bool => auth()->user()?->can('update', $record) ?? false)
                    ->visible(fn (Minister $record): bool => $record->user_id !== null)
                    ->action(function (Minister $record): void {
                        $record->forcePasswordReset();

                        Notification::make()
                            ->title('Redefinição de senha solicitada')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    /**
     * @return Builder<Minister>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Minister> $query */
        $query = parent::getEloquentQuery()->with(['user', 'church', 'historyEvents']);

        return $query;
    }

    /**
     * Ações de status e senha usadas no cabeçalho das páginas de ver e editar.
     *
     * @return list<Action>
     */
    public static function getAccessActions(): array
    {
        return [
            Action::make('activate')
                ->label('Ativar')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->authorize(fn (Minister $record): bool => auth()->user()?->can('update', $record) ?? false)
                ->visible(fn (Minister $record): bool => $record->status !== Minister::STATUS_ACTIVE)
                ->action(function (Minister $record): void {
                    $record->activate();

                    Notification::make()
                        ->title('Ministro ativado')
                        ->success()
                        ->send();
                }),
            Action::make('deactivate')
                ->label('Desativar')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->authorize(fn (Minister $record): bool => auth()->user()?->can('update', $record) ?? false)
                ->visible(fn (Minister $record): bool => $record->status === Minister::STATUS_ACTIVE)
                ->action(function (Minister $record): void {
                    $record->deactivate();

                    Notification::make()
                        ->title('Ministro desativado')
                        ->success()
                        ->send();
                }),
            Action::make('forcePasswordReset')
                ->label('Forçar redefinição de senha')
                ->icon('heroicon-o-key')
                ->color('warning')
                ->requiresConfirmation()
                ->modalDescription('A senha atual será invalidada. No próximo acesso o ministro define uma nova senha.')
                ->authorize(fn (Minister $record): bool => auth()->user()?->can('update', $record) ?? false)
                ->visible(fn (Minister $record): bool => $record->user_id !== null)
                ->action(function (Minister $record): void {
                    $record->forcePasswordReset();

                    Notification::make()
                        ->title('Redefinição de senha solicitada')
                        ->success()
                        ->send();
                }),
        ];
    }

    public static function getRelations(): array
    {
        return [
            FamilyMembersRelationManager::class,
            HistoryEventsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMinisters::route('/'),
            'create' => Pages\CreateMinister::route('/create'),
            'view' => Pages\ViewMinister::route('/{record}'),
            'edit' => Pages\EditMinister::route('/{record}/edit'),
        ];
    }

    /**
     * @param  Builder<Minister>  $query
     * @return Builder<Minister>
     */
    private static function whereHasHistoryCode(Builder $query, string $code): Builder
    {
        return $query->whereHas(
            'historyEvents',
            fn (Builder $events): Builder => $events->where('code', $code),
        );
    }

    /**
     * @param  Builder<Minister>  $query
     * @return Builder<Minister>
     */
    private static function whereMissingHistoryCode(Builder $query, string $code): Builder
    {
        return $query->whereDoesntHave(
            'historyEvents',
            fn (Builder $events): Builder => $events->where('code', $code),
        );
    }
}
