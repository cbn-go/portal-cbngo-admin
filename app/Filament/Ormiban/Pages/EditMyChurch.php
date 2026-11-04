<?php

namespace App\Filament\Ormiban\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\ChurchResource;
use App\Models\Church;
use App\Models\Municipality;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;

/**
 * @property Form $form
 */
class EditMyChurch extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $slug = 'minha-igreja';

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationLabel = 'Minha Igreja';

    protected static ?string $title = 'Dados da Minha Igreja';

    protected static string $view = 'filament.ormiban.pages.edit-my-church';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public ?Church $church = null;

    public function getSubheading(): ?string
    {
        return $this->church?->name;
    }

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user !== null && ($user->role === UserRole::MINISTER || $user->role === UserRole::SUPER_ADMIN);
    }

    public function mount(): void
    {
        $this->church = $this->getChurch();

        if ($this->church !== null) {
            $this->form->fill($this->church->toArray());
        } else {
            $this->form->fill();
        }
    }

    public function getChurch(): ?Church
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user?->minister?->church ?? $user?->church;
    }

    public function form(Form $form): Form
    {
        if ($this->church === null) {
            return $form
                ->schema([
                    Forms\Components\Section::make('Vínculo Eclesiástico')
                        ->schema([
                            Forms\Components\Placeholder::make('no_church_alert')
                                ->label('')
                                ->content(new HtmlString(
                                    '<div class="p-4 rounded-lg bg-amber-50 border border-amber-200 text-amber-900 text-sm">
                                        <strong>Nenhuma igreja vinculada</strong> à sua ficha ministerial no momento.
                                        Se você é pastor titular de uma igreja filiada, entre em contato com a secretaria da CBN-GO para realizar a associação oficial do cadastro.
                                    </div>'
                                )),
                        ]),
                ])
                ->statePath('data');
        }

        return $form
            ->schema([
                Forms\Components\Section::make('Identificação da Congregação')
                    ->description('Informações públicas e oficiais da sua igreja.')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nome da Igreja')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('cnpj')
                            ->label('CNPJ')
                            ->maxLength(18)
                            ->mask('99.999.999/9999-99')
                            ->placeholder('00.000.000/0000-00'),

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
                            ->helperText('Formatos: JPG, PNG, WEBP (máx. 2MB). Proporção 1:1 quadrada.'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Localização & Endereço')
                    ->schema([
                        Forms\Components\Select::make('municipality_id')
                            ->label('Cidade / Município (Goiás - IBGE)')
                            ->options(fn () => Municipality::query()->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (?int $state, Set $set): void {
                                if ($state && ($municipality = Municipality::find($state))) {
                                    $set('city', $municipality->name);
                                }
                            }),

                        Forms\Components\Hidden::make('city'),

                        Forms\Components\TextInput::make('state')
                            ->label('UF')
                            ->required()
                            ->maxLength(2)
                            ->default('GO'),

                        Forms\Components\TextInput::make('zip_code')
                            ->label('CEP')
                            ->maxLength(10)
                            ->mask('99999-999')
                            ->placeholder('74000-000'),

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
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Contatos & Comunicação')
                    ->schema([
                        Forms\Components\TextInput::make('phone')
                            ->label('Telefone Fixo')
                            ->tel()
                            ->maxLength(50),

                        Forms\Components\TextInput::make('cellphone')
                            ->label('Celular / WhatsApp')
                            ->tel()
                            ->maxLength(50),

                        Forms\Components\TextInput::make('email')
                            ->label('E-mail da Igreja')
                            ->email()
                            ->maxLength(255),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Presença Digital & Redes Sociais')
                    ->schema([
                        Forms\Components\TextInput::make('social_links.instagram')
                            ->label('Instagram')
                            ->maxLength(100)
                            ->placeholder('@sua_igreja'),

                        Forms\Components\TextInput::make('social_links.facebook')
                            ->label('Facebook')
                            ->maxLength(255)
                            ->url(),

                        Forms\Components\TextInput::make('social_links.youtube')
                            ->label('YouTube')
                            ->maxLength(255)
                            ->url(),

                        Forms\Components\TextInput::make('social_links.website')
                            ->label('Site Oficial')
                            ->maxLength(255)
                            ->url(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Horários de Culto')
                    ->description('Programação de cultos e reuniões semanais da congregação.')
                    ->schema([
                        Forms\Components\Repeater::make('service_times')
                            ->label('Cultos')
                            ->schema([
                                Forms\Components\Select::make('day')
                                    ->label('Dia')
                                    ->options(array_combine(ChurchResource::SERVICE_DAYS, ChurchResource::SERVICE_DAYS))
                                    ->required()
                                    ->native(false),

                                Forms\Components\TextInput::make('time')
                                    ->label('Horário')
                                    ->required()
                                    ->mask('99h99')
                                    ->placeholder('19h30'),

                                Forms\Components\TextInput::make('label')
                                    ->label('Nome do Culto / Reunião')
                                    ->placeholder('Ex: Culto da Família, Culto de Oração'),
                            ])
                            ->columns(3)
                            ->defaultItems(0)
                            ->addActionLabel('Adicionar Horário de Culto'),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $church = $this->getChurch();

        if ($church === null) {
            return;
        }

        Gate::authorize('update', $church);

        $state = $this->form->getState();
        $church->update($state);

        Notification::make()
            ->title('Dados da igreja atualizados com sucesso!')
            ->success()
            ->send();
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        if ($this->church === null) {
            return [];
        }

        return [
            Action::make('save')
                ->label('Salvar Alterações da Igreja')
                ->submit('save')
                ->extraAttributes([
                    'class' => 'min-h-[44px] text-base font-semibold text-white bg-[#E81828] hover:bg-[#c51422] rounded-lg shadow-sm',
                ]),
        ];
    }
}
