<?php

namespace App\Filament\Ormiban\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\ChurchResource;
use App\Models\Church;
use App\Models\Minister;
use App\Models\Municipality;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
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

            return;
        }

        if ($this->ministerCanClaimChurch()) {
            $this->form->fill([
                'mode' => 'vincular',
                'state' => 'GO',
                'pastor_name' => auth()->user()?->minister?->name,
            ]);

            return;
        }

        $this->form->fill();
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
                ->schema($this->unlinkedChurchSchema())
                ->statePath('data');
        }

        return $form
            ->schema($this->churchDetailSchema(claiming: false))
            ->statePath('data');
    }

    public function save(): void
    {
        if ($this->church === null) {
            if (! $this->ministerCanClaimChurch()) {
                return;
            }

            if (($this->data['mode'] ?? null) === 'cadastrar') {
                $this->createChurch();
            } else {
                $this->linkChurch();
            }

            return;
        }

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

    public function linkChurch(): void
    {
        if ($this->blockedFromClaim()) {
            return;
        }

        $state = $this->form->getState();
        $church = Church::query()
            ->where('is_active', true)
            ->find($state['existing_church_id'] ?? null);

        if (! $church instanceof Church) {
            Notification::make()
                ->title('Selecione uma igreja ativa.')
                ->danger()
                ->send();

            return;
        }

        $this->attachMinisterToChurch($church);

        Notification::make()
            ->title('Igreja vinculada com sucesso!')
            ->success()
            ->send();

        $this->redirect('/ormiban/minha-igreja');
    }

    public function createChurch(): void
    {
        if ($this->blockedFromClaim()) {
            return;
        }

        $state = $this->form->getState();

        if (($state['mode'] ?? null) !== 'cadastrar') {
            return;
        }

        /** @var User $user */
        $user = auth()->user();
        $minister = $user->minister;

        if (! $minister instanceof Minister) {
            abort(403);
        }

        $payload = array_intersect_key($state, array_flip([
            'name',
            'cnpj',
            'pastor_name',
            'municipality_id',
            'city',
            'state',
            'neighborhood',
            'zip_code',
            'address',
            'number',
            'complement',
            'phone',
            'cellphone',
            'email',
            'social_links',
            'service_times',
            'logo',
        ]));

        $payload['pastor_name'] = filled($payload['pastor_name'] ?? null)
            ? $payload['pastor_name']
            : $minister->name;
        $payload['state'] = filled($payload['state'] ?? null) ? $payload['state'] : 'GO';
        $payload['is_active'] = true;

        $church = DB::transaction(function () use ($payload, $minister, $user): Church {
            $church = Church::query()->create($payload);

            $minister->update([
                'church_id' => $church->id,
                'church_name' => $church->name,
            ]);
            $user->update(['church_id' => $church->id]);

            return $church;
        });

        $this->church = $church;

        Notification::make()
            ->title('Igreja cadastrada e vinculada com sucesso!')
            ->success()
            ->send();

        $this->redirect('/ormiban/minha-igreja');
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        if ($this->church === null) {
            if (! $this->ministerCanClaimChurch()) {
                return [];
            }

            return [
                Action::make('linkChurch')
                    ->label('Vincular igreja')
                    ->visible(fn (): bool => ($this->data['mode'] ?? 'vincular') !== 'cadastrar')
                    ->action(function (): void {
                        $this->linkChurch();
                    })
                    ->extraAttributes($this->primaryButtonAttributes()),
                Action::make('createChurch')
                    ->label('Cadastrar igreja')
                    ->visible(fn (): bool => ($this->data['mode'] ?? null) === 'cadastrar')
                    ->action(function (): void {
                        $this->createChurch();
                    })
                    ->extraAttributes($this->primaryButtonAttributes()),
            ];
        }

        return [
            Action::make('save')
                ->label('Salvar Alterações da Igreja')
                ->submit('save')
                ->extraAttributes($this->primaryButtonAttributes()),
        ];
    }

    /**
     * @return list<Forms\Components\Component>
     */
    private function unlinkedChurchSchema(): array
    {
        if (! $this->ministerCanClaimChurch()) {
            return [
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
            ];
        }

        return [
            Forms\Components\Section::make('Vínculo Eclesiástico')
                ->description('Escolha uma igreja já cadastrada ou informe os dados de uma nova.')
                ->schema([
                    Forms\Components\Radio::make('mode')
                        ->label('Como deseja vincular sua igreja?')
                        ->options([
                            'vincular' => 'Vincular a uma igreja já cadastrada',
                            'cadastrar' => 'Cadastrar uma nova igreja',
                        ])
                        ->default('vincular')
                        ->required()
                        ->live(),
                    Forms\Components\Select::make('existing_church_id')
                        ->label('Igreja')
                        ->options(fn (): array => $this->activeChurchOptions())
                        ->searchable()
                        ->preload()
                        ->required(fn (Get $get): bool => $get('mode') !== 'cadastrar')
                        ->visible(fn (Get $get): bool => $get('mode') !== 'cadastrar'),
                ]),
            Forms\Components\Group::make()
                ->schema($this->churchDetailSchema(claiming: true))
                ->visible(fn (Get $get): bool => $get('mode') === 'cadastrar'),
        ];
    }

    /**
     * @return array<int|string, string>
     */
    private function activeChurchOptions(): array
    {
        return Church::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Church $church): array => [
                $church->id => $church->name.' — '.$church->city,
            ])
            ->all();
    }

    /**
     * @return list<Forms\Components\Component>
     */
    private function churchDetailSchema(bool $claiming): array
    {
        return [
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
                        ->required($claiming)
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
        ];
    }

    private function ministerCanClaimChurch(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user instanceof User
            && $user->role === UserRole::MINISTER
            && $user->minister !== null
            && $this->getChurch() === null;
    }

    private function blockedFromClaim(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user instanceof User || $user->role !== UserRole::MINISTER || $user->minister === null) {
            abort(403);
        }

        if ($this->getChurch() !== null) {
            Notification::make()
                ->title('Você já possui uma igreja vinculada.')
                ->danger()
                ->send();

            return true;
        }

        return false;
    }

    private function attachMinisterToChurch(Church $church): void
    {
        /** @var User $user */
        $user = auth()->user();
        $minister = $user->minister;

        if (! $minister instanceof Minister) {
            abort(403);
        }

        DB::transaction(function () use ($church, $minister, $user): void {
            $minister->update([
                'church_id' => $church->id,
                'church_name' => $church->name,
            ]);
            $user->update(['church_id' => $church->id]);
        });

        $this->church = $church;
    }

    /**
     * @return array<string, string>
     */
    private function primaryButtonAttributes(): array
    {
        return [
            'class' => 'min-h-[44px] text-base font-semibold text-white bg-[#E81828] hover:bg-[#c51422] rounded-lg shadow-sm',
        ];
    }
}
