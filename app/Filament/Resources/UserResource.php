<?php

namespace App\Filament\Resources;

use App\Enums\UserRole;
use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use App\Policies\UserPolicy;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Password;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Administração';

    protected static ?string $navigationLabel = 'Usuários';

    protected static ?string $modelLabel = 'Usuário';

    protected static ?string $pluralModelLabel = 'Usuários';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Dados da Conta de Acesso')
                    ->description('Credenciais de acesso e identificação individual do usuário.')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nome Completo')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('email')
                            ->label('E-mail')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(User::class, 'email', ignoreRecord: true),

                        Forms\Components\TextInput::make('password')
                            ->label('Senha de Acesso')
                            ->password()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->minLength(8)
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? $state : null)
                            ->helperText('Mínimo de 8 caracteres. Na edição, deixe em branco para manter a senha atual.'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Perfil & Vinculação Institucional')
                    ->description('Definição de permissões administrativas e relacionamento com igrejas filiadas.')
                    ->schema([
                        Forms\Components\Select::make('role')
                            ->label('Nível de Acesso (Perfil)')
                            ->options(function (): array {
                                /** @var User|null $currentUser */
                                $currentUser = auth()->user();

                                if ($currentUser?->role === UserRole::SUPER_ADMIN) {
                                    return collect(UserRole::cases())
                                        ->mapWithKeys(fn (UserRole $role): array => [$role->value => $role->getLabel()])
                                        ->all();
                                }

                                return collect(UserRole::cases())
                                    ->filter(fn (UserRole $role): bool => $role !== UserRole::SUPER_ADMIN)
                                    ->mapWithKeys(fn (UserRole $role): array => [$role->value => $role->getLabel()])
                                    ->all();
                            })
                            ->rules([
                                fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
                                    /** @var User|null $currentUser */
                                    $currentUser = auth()->user();

                                    if ($currentUser === null) {
                                        $fail('Usuário não autenticado.');

                                        return;
                                    }

                                    $role = UserRole::tryFrom((string) $value);
                                    if ($role === null) {
                                        $fail('Perfil selecionado é inválido.');

                                        return;
                                    }

                                    if (! app(UserPolicy::class)->assignRole($currentUser, $role)) {
                                        $fail('Você não tem permissão para atribuir o perfil de Super Administrador.');
                                    }
                                },
                            ])
                            ->required()
                            ->live()
                            ->native(false),

                        Forms\Components\Select::make('church_id')
                            ->label('Congregação Vinculada')
                            ->relationship('church', 'name')
                            ->searchable()
                            ->preload()
                            ->required(fn (Forms\Get $get): bool => $get('role') === UserRole::CHURCH_REPRESENTATIVE->value || $get('role') === UserRole::CHURCH_REPRESENTATIVE)
                            ->helperText('Obrigatório para representantes locais de congregações.'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Conta Ativa')
                            ->default(true)
                            ->disabled(fn (?User $record): bool => $record !== null && $record->id === auth()->id())
                            ->helperText('Usuários inativos não conseguem autenticar nem acessar painéis.'),
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

                Tables\Columns\TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('role')
                    ->label('Perfil')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('church.name')
                    ->label('Congregação')
                    ->placeholder('CBN Goiás (Estadual)')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Ativo')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->label('Perfil')
                    ->options(UserRole::class),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status Ativo'),

                Tables\Filters\SelectFilter::make('church_id')
                    ->label('Congregação')
                    ->relationship('church', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\Action::make('resetPassword')
                    ->label('Redefinir Senha')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->authorize(fn (User $record): bool => auth()->user()?->can('update', $record) ?? false)
                    ->visible(fn (User $record): bool => auth()->user()?->can('update', $record) ?? false)
                    ->form([
                        Forms\Components\TextInput::make('new_password')
                            ->label('Nova Senha Provisória')
                            ->password()
                            ->required()
                            ->minLength(8)
                            ->helperText('Mínimo de 8 caracteres.'),
                    ])
                    ->action(function (User $record, array $data): void {
                        $record->update([
                            'password' => $data['new_password'],
                        ]);

                        Notification::make()
                            ->title('Senha redefinida com sucesso')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('sendResetLink')
                    ->label('Enviar Link')
                    ->icon('heroicon-o-envelope')
                    ->color('info')
                    ->authorize(fn (User $record): bool => (auth()->user()?->can('update', $record) ?? false) && $record->is_active)
                    ->visible(fn (User $record): bool => (auth()->user()?->can('update', $record) ?? false) && $record->is_active)
                    ->requiresConfirmation()
                    ->modalHeading('Enviar e-mail de recuperação')
                    ->modalDescription('Um e-mail para recuperação de senha será enviado com o token oficial.')
                    ->action(function (User $record): void {
                        if (! $record->is_active) {
                            Notification::make()
                                ->title('Não é possível enviar link para conta inativa')
                                ->danger()
                                ->send();

                            return;
                        }

                        $record->sendPasswordResetNotification(Password::createToken($record));

                        Notification::make()
                            ->title('Link de recuperação enviado com sucesso')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->hidden(fn (User $record): bool => $record->id === auth()->id()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * @return Builder<User>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<User> $query */
        $query = parent::getEloquentQuery()->with('church');

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
