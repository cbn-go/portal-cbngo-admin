<?php

namespace App\Filament\Ormiban\Pages;

use App\Enums\UserRole;
use App\Models\Minister;
use App\Models\User;
use App\Support\Cpf;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Auth\EditProfile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class EditMinisterProfile extends EditProfile
{
    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    protected static ?string $navigationLabel = 'Meu Perfil';

    protected static ?string $title = 'Meu Perfil Ministerial';

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user !== null && ($user->role === UserRole::MINISTER || $user->role === UserRole::SUPER_ADMIN);
    }

    /**
     * Tempo de ministério contado desde a ordenação (também usado na tela inicial).
     */
    public static function ministryTimeLabel(Minister $minister): string
    {
        $years = $minister->yearsOfMinistry();

        return $years !== null ? "{$years} anos (desde a ordenação)" : 'Sem registro de ordenação';
    }

    /**
     * Igreja vinculada à ficha ou, na falta dela, a informada no rol.
     */
    public static function churchLabel(Minister $minister): string
    {
        return $minister->church?->name ?? $minister->church_name ?? 'Não informada';
    }

    public function form(Form $form): Form
    {
        /** @var User $user */
        $user = $this->getUser();
        $minister = $user->minister;

        $schema = [];

        if ($minister === null) {
            $schema[] = Forms\Components\Section::make('Aviso Importante')
                ->schema([
                    Forms\Components\Placeholder::make('no_minister_info')
                        ->label('')
                        ->content(new HtmlString(view('filament.ormiban.partials.no-minister-notice')->render())),
                ]);
        } else {
            $schema[] = Forms\Components\Section::make('Identificação Oficial no Rol (Somente Leitura)')
                ->description('Dados oficiais registrados na convenção. Para alterações, procure a secretaria.')
                ->schema([
                    Forms\Components\TextInput::make('registration_number')
                        ->label('Matrícula Oficial')
                        ->disabled()
                        ->dehydrated(false),

                    Forms\Components\TextInput::make('status')
                        ->label('Situação Cadastral')
                        ->disabled()
                        ->dehydrated(false),

                    Forms\Components\TextInput::make('cpf')
                        ->label('CPF')
                        ->disabled()
                        ->dehydrated(false),

                    Forms\Components\TextInput::make('function')
                        ->label('Função / Título')
                        ->disabled()
                        ->dehydrated(false),

                    Forms\Components\TextInput::make('church_name')
                        ->label('Igreja Informada')
                        ->disabled()
                        ->dehydrated(false)
                        ->columnSpan(2),

                    Forms\Components\Placeholder::make('ministry_time')
                        ->label('Tempo de Ministério')
                        ->content(fn (): string => self::ministryTimeLabel($minister)),
                ])
                ->columns(2);

            $schema[] = Forms\Components\Section::make('Histórico Ministerial & Dependentes (Somente Leitura)')
                ->schema([
                    Forms\Components\Placeholder::make('history_events')
                        ->label('Histórico de Eventos Oficiais')
                        ->content(function () use ($minister): HtmlString {
                            $events = $minister->historyEvents()->orderBy('occurred_on', 'asc')->get();
                            if ($events->isEmpty()) {
                                return new HtmlString('<span class="text-xs text-gray-500">Nenhum evento registrado.</span>');
                            }

                            $items = $events->map(fn ($e) => sprintf(
                                '<li><strong>%s</strong> — %s (cód. %s)</li>',
                                $e->occurred_on->format('d/m/Y'),
                                e($e->description),
                                e($e->code)
                            ))->implode('');

                            return new HtmlString("<ul class='text-sm space-y-1 list-disc list-inside text-gray-700'>{$items}</ul>");
                        }),

                    Forms\Components\Placeholder::make('family_members')
                        ->label('Dependentes e Familiares')
                        ->content(function () use ($minister): HtmlString {
                            $family = $minister->familyMembers()->get();
                            if ($family->isEmpty()) {
                                return new HtmlString('<span class="text-xs text-gray-500">Nenhum dependente registrado.</span>');
                            }

                            $items = $family->map(fn ($f) => sprintf(
                                '<li><strong>%s:</strong> %s %s</li>',
                                e($f->relationship),
                                e($f->name),
                                $f->birth_date ? '('.$f->birth_date->format('d/m/Y').')' : ''
                            ))->implode('');

                            return new HtmlString("<ul class='text-sm space-y-1 list-disc list-inside text-gray-700'>{$items}</ul>");
                        }),
                ])
                ->columns(2)
                ->collapsed();
        }

        $schema[] = Forms\Components\Section::make('Dados de Contato & Endereço (Editáveis)')
            ->description('Mantenha suas informações de contato atualizadas junto à Ormiban GO.')
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nome Completo')
                    ->required()
                    ->maxLength(255)
                    ->columnSpan(2),

                Forms\Components\TextInput::make('email')
                    ->label('E-mail de Contato')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(User::class, 'email', ignorable: $user),

                Forms\Components\TextInput::make('phone')
                    ->label('Telefone / WhatsApp')
                    ->tel()
                    ->maxLength(50)
                    ->placeholder('(62) 99999-9999'),

                Forms\Components\TextInput::make('address')
                    ->label('Endereço Residencial')
                    ->maxLength(255)
                    ->placeholder('Rua, Número, Bairro')
                    ->columnSpan(2),

                Forms\Components\TextInput::make('zip_code')
                    ->label('CEP')
                    ->maxLength(20)
                    ->mask('99999-999')
                    ->placeholder('74000-000'),
            ])
            ->columns(2);

        $schema[] = Forms\Components\Section::make('Alteração de Senha (Opcional)')
            ->description('Preencha apenas se desejar redefinir sua senha de acesso.')
            ->schema([
                Forms\Components\TextInput::make('password')
                    ->label('Nova Senha')
                    ->password()
                    ->revealable()
                    ->nullable()
                    ->minLength(8)
                    ->same('password_confirmation')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? $state : null),

                Forms\Components\TextInput::make('password_confirmation')
                    ->label('Confirmar Nova Senha')
                    ->password()
                    ->revealable()
                    ->nullable()
                    ->dehydrated(false),
            ])
            ->columns(2)
            ->collapsed();

        return $form->schema($schema);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var User $user */
        $user = $this->getUser();
        $minister = $user->minister;

        if ($minister !== null) {
            $data['phone'] = $minister->phone;
            $data['address'] = $minister->address;
            $data['zip_code'] = $minister->zip_code;
            $data['registration_number'] = $minister->registration_number;
            $data['status'] = $minister->status;
            $data['function'] = $minister->function;
            $data['cpf'] = Cpf::format($minister->cpf);
            $data['church_name'] = self::churchLabel($minister);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $record */
        $userData = [
            'name' => $data['name'] ?? $record->name,
            'email' => $data['email'] ?? $record->email,
        ];

        if (filled($data['password'] ?? null)) {
            $userData['password'] = $data['password'];
        }

        $record->update($userData);

        if ($record->minister !== null) {
            $record->minister->update([
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'zip_code' => $data['zip_code'] ?? null,
            ]);
        }

        return $record;
    }
}
